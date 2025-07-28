<?php
// worker.php
require __DIR__ . '/kktutils.php';
// Например, используем SQLite для очереди:
$db = new PDO('sqlite:' . __DIR__ . '/data/jobs.db');
$db->exec("
  CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    imc TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    result TEXT,
    ecrStandAloneFlag INTEGER,
    imcCheckFlag INTEGER,
    imcCheckResult INTEGER,
    imcEstimatedStatusCorrect INTEGER,
    imcStatusInfo INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
");

$driver = new TFptr10Driver(/* параметры подключения к ККТ */);

while (true) {
    // 1) Берем одну задачу
    $stmt = $db->query("SELECT * FROM jobs WHERE status='pending' ORDER BY id LIMIT 1");
    $job  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        sleep(2);
        continue;
    }

    // 2) Помечаем в processing
    $db->prepare("UPDATE jobs SET status='processing' WHERE id=?")
       ->execute([$job['id']]);

    // 3) Начинаем проверку
    list($ok, $respJson, $err) = $driver->beginMarkingCodeValidation($job['imc']);

    // 4) Периодически опрашиваем статус
    do {
        sleep(1);
        $status = $driver->getMarkingCodeValidationStatus();
    } while (empty($status['ready']));

    // 5) В зависимости от результата — подтверждаем или отклоняем
    if (!empty($status['onlineValidation']['imcCheckResult'])) {
        // 1) Выполняем подтверждение
        list($ok2, $json2, $err2) = $driver->acceptMarkingCode();
        // 2) Парсим ответ
       $resp2 = json_decode($json2, true);
        $info = $resp2['itemInfoCheckResult'] ?? [];
        $final = 'accepted';
    } else {
        list($ok2, $json2, $err2) = $driver->declineMarkingCode();
        // decline, но поле itemInfoCheckResult по документации приходит только на accept
        $info = [];
        $final = 'rejected';
    }

    // 6) Обновляем запись, записывая флаги
    $stmt = $db->prepare("
    UPDATE jobs SET
        status = :status,
        result = :result,
        ecrStandAloneFlag            = :ecrStandAloneFlag,
        imcCheckFlag                 = :imcCheckFlag,
        imcCheckResult               = :imcCheckResult,
        imcEstimatedStatusCorrect    = :imcEstimatedStatusCorrect,
        imcStatusInfo                = :imcStatusInfo
    WHERE id = :id
    ");
    $stmt->execute([
        ':status'                     => 'done',
        ':result'                     => $final,
        ':ecrStandAloneFlag'          => $info['ecrStandAloneFlag']            ?? null,
        ':imcCheckFlag'               => $info['imcCheckFlag']                 ?? null,
        ':imcCheckResult'             => $info['imcCheckResult']               ?? null,
        ':imcEstimatedStatusCorrect'  => $info['imcEstimatedStatusCorrect']    ?? null,
        ':imcStatusInfo'              => $info['imcStatusInfo']                ?? null,
        ':id'                         => $job['id'],
    ]);}
}