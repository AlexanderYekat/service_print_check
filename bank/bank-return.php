<?php
/**
 * Альтернативный возврат денег через PowerShell-скрипт bank-return.ps1 через уже существующее задание планировщика.
 * @param float $amount Сумма возврата
 * @param Logger|null $logger Логгер (опционально)
 * @return array Результат операции
 */
function bank_return_via_ps1($amount, $logger = null) {
    if (!is_numeric($amount) || $amount <= 0) {
        if ($logger) $logger->error("Некорректная сумма для возврата: " . $amount);
        return ['success' => false, 'message' => 'Некорректная сумма для возврата.'];
    }

    $baseDir = __DIR__;
    $tempDir = $baseDir . DIRECTORY_SEPARATOR . 'temp';
    if (!is_dir($tempDir)) {
        if ($logger) $logger->info("Папка временных файлов не найдена, создаю: $tempDir");
        mkdir($tempDir, 0777, true);
    } else {
        if ($logger) $logger->info("Папка временных файлов найдена: $tempDir");
    }
    $amountFile = $tempDir . DIRECTORY_SEPARATOR . 'amount.txt';
    $resultFile = $tempDir . DIRECTORY_SEPARATOR . 'result.json';

    // Удаляем старый результат, если есть
    if (file_exists($resultFile)) {
        if ($logger) $logger->info("Удаляю старый файл результата: $resultFile");
        @unlink($resultFile);
    }

    // Пишем сумму во временный файл
    if ($logger) $logger->info("Пишу сумму для возврата в файл: $amountFile (значение: $amount)");
    file_put_contents($amountFile, $amount);
    if ($logger) $logger->info("Сумма для возврата успешно записана.");

    // Запускаем уже существующее задание
    $taskName = 'BankReturnTask';
    $runCmd = "schtasks /run /tn \"$taskName\"";
    if ($logger) $logger->info("Запускаю задание планировщика: $runCmd");
    $output = shell_exec($runCmd . " 2>&1");
    $output = iconv('CP866', 'UTF-8', $output);
    //$output = iconv('Windows-1251', 'UTF-8', $output);
    if ($logger) $logger->info("Ответ от schtasks: $output");

    // Ждем появления файла результата (до 30 секунд)
    $waitTime = 0;
    $maxWait = 30;
    if ($logger) $logger->info("Ожидание появления файла результата: $resultFile (максимум {$maxWait} секунд)");
    while (!file_exists($resultFile) && $waitTime < $maxWait) {
        sleep(1);
        $waitTime++;
        if ($logger) $logger->info("Ожидание... {$waitTime} сек");
    }
    if (!file_exists($resultFile)) {
        $msg = "Файл результата не появился за {$maxWait} секунд.";
        if ($logger) $logger->error($msg);
        return ['success' => false, 'message' => $msg];
    }
    if ($logger) $logger->info("Файл результата найден: $resultFile");

    $answerjson = file_get_contents($resultFile);
    $answerjson = preg_replace('/^\xEF\xBB\xBF|\x{FEFF}/u', '', $answerjson);
    if ($logger) $logger->info("Содержимое файла результата: $answerjson");
    @unlink($resultFile);
    $result = json_decode($answerjson, true);
    if ($result === null) {
        $msg = "Не удалось разобрать JSON-ответ из скрипта: " . $answerjson;
        if ($logger) $logger->error($msg);
        return ['success' => false, 'message' => $msg];
    }
    if ($logger) $logger->info("Результат возврата через PowerShell: " . json_encode($result, JSON_UNESCAPED_UNICODE));

    // Приведение к единому формату
    $methodWasRunned = true;
    $finalSuccess = isset($result['Success']) ? $result['Success'] : false;
    //$finalMessage = isset($result['Message']) ? $result['Message'] : '';
    $returnResult = '';
    if ($finalSuccess && isset($result['Cheque'])) {
        $returnResult = $result['Cheque'];
    } else if (!$finalSuccess && isset($result['Message'])) {
        $returnResult = $result['Message'];
    }
    $coderesult = isset($result['CodeReturn']) ? $result['CodeReturn'] : 0;
    if ($coderesult > 0) {
        $returnResult = $returnResult . decodeErrorCode($coderesult);
    }

    return [
        'success' => $methodWasRunned,
        'messsage' => "",
        'data' => [
            'response' => $returnResult,
            'success' => $finalSuccess,
            'coderesult' => $coderesult
        ]
    ];
}

function decodeErrorCode(int $resultCode): string {
    switch ($resultCode) {
        case 99:
        case 4120:
            return "нет связи с банковским терминалом";
        case 4100:
        case 4119:
            return "нет связи с банком";
        case 403:
        case 4455:
            return "неверный ПИН-код";
        case 4451:
        case 521:
            return "недостаточно средств";
        case 253:
            return "аппаратный сбой";
        case 2000:
            return "операция отменена пользователем";
        case 2002:
            return "клиент слишком долго вводил ПИК-код";
        case 4134:
            return "на терминале давно не закрывали банковскую смену";
        case 4401:
            return "нужно позвонить в банк";
        case 4404:
        case 4407:
        case 4141:
        case 4143:
            return "получена команда изъять карту";
        case 5109:
            return "карта просрочена";
        default:
            return "";
        }
}