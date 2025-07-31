<?php

require_once __DIR__ . '/../src/domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/../src/domain/service/EcrMarkCheckUseCase.php';
require_once __DIR__ . '/../src/infrastructure/honest_sign/HttpPermitMarkCheckGateway.php';
require_once __DIR__ . '/../src/infrastructure/honest_sign/QueueEcrMarkCheckGateway.php';
require_once __DIR__ . '/../src/infrastructure/queue/EcrMarkCheckWorker.php';
require_once __DIR__ . '/../src/domain/model/MarkingCode.php';

/**
 * Пример использования новой архитектуры проверки марки
 */

echo "=== Пример новой архитектуры проверки марки ===\n\n";

// Создаем тестовый код маркировки (согласно ТЗ - только значение)
$markingCode = new MarkingCode('010463003759026521uHpB8gXVVdi\u001d910092\u001d92dGVz/yx9tgLxk3g==');

// Дополнительные параметры передаем через контекст
$context = [
    'inn' => '7736207543',
    'gtin' => '04630037590265'
];

echo "Код маркировки: {$markingCode->value}\n";
echo "ИНН: {$context['inn']}, GTIN: {$context['gtin']}\n\n";

// ===========================================
// 1. РАЗРЕШИТЕЛЬНЫЙ РЕЖИМ (синхронный)
// ===========================================

echo "=== 1. РАЗРЕШИТЕЛЬНЫЙ РЕЖИМ (синхронный) ===\n";

$permitGateway = new HttpPermitMarkCheckGateway(
    'https://api.markirovka.ru',
    'your_api_key'
);

$permitUseCase = new PermitMarkCheckUseCase($permitGateway);

echo "Выполняем синхронную проверку марки в разрешительном режиме...\n";

try {
    $permitResult = $permitUseCase->execute($markingCode, $context);
    
    if ($permitResult->success) {
        echo "✅ Разрешительная проверка успешна!\n";
        echo "Сообщение для оператора: " . $permitResult->getData('user_status')['text'] . "\n";
        echo "UUID: " . $permitResult->getData('machine_data')['uuid'] . "\n";
        echo "Время: " . $permitResult->getData('machine_data')['time'] . "\n";
    } else {
        echo "❌ Разрешительная проверка не пройдена: " . $permitResult->error . "\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ===========================================
// 2. ПРОВЕРКА НА ККТ (асинхронный)
// ===========================================

echo "=== 2. ПРОВЕРКА НА ККТ (асинхронная) ===\n";

$ecrGateway = new QueueEcrMarkCheckGateway();
$ecrUseCase = new EcrMarkCheckUseCase($ecrGateway);

echo "Ставим задачу проверки марки на ККТ в очередь...\n";

try {
    // Постановка в очередь
    $taskId = $ecrUseCase->enqueue($markingCode, $context);
    echo "✅ Задача поставлена в очередь. Task ID: {$taskId}\n";
    
    // Проверяем статус сразу (задача должна быть в pending)
    echo "\nПроверяем статус задачи...\n";
    $statusResult = $ecrUseCase->getResult($taskId);
    
    if ($statusResult->success) {
        $taskStatus = $statusResult->getData('machine_data')['taskStatus'];
        echo "📋 Статус задачи: {$taskStatus}\n";
        echo "Сообщение: " . $statusResult->getData('user_status')['text'] . "\n";
    } else {
        echo "❌ Ошибка получения статуса: " . $statusResult->error . "\n";
    }
    
    echo "\n";
    
    // ===========================================
    // 3. ОБРАБОТКА ОЧЕРЕДИ ВОРКЕРОМ
    // ===========================================
    
    echo "=== 3. ОБРАБОТКА ОЧЕРЕДИ ВОРКЕРОМ ===\n";
    
    $worker = new EcrMarkCheckWorker(
        $ecrGateway->getQueue(),
        'https://api.markirovka.ru',
        'your_api_key'
    );
    
    echo "Запускаем воркер для обработки задач...\n";
    $workerResults = $worker->processQueue();
    
    foreach ($workerResults as $workerResult) {
        echo "📦 Задача {$workerResult['task_id']}: {$workerResult['status']}\n";
        if ($workerResult['status'] === 'failed') {
            echo "   Ошибка: {$workerResult['error']}\n";
        }
    }
    
    echo "\n";
    
    // ===========================================
    // 4. ПОЛУЧЕНИЕ ФИНАЛЬНОГО РЕЗУЛЬТАТА
    // ===========================================
    
    echo "=== 4. ПОЛУЧЕНИЕ ФИНАЛЬНОГО РЕЗУЛЬТАТА ===\n";
    
    echo "Получаем финальный результат проверки ККТ...\n";
    $finalResult = $ecrUseCase->getResult($taskId);
    
    if ($finalResult->success) {
        $taskStatus = $finalResult->getData('machine_data')['taskStatus'];
        echo "✅ Финальный статус: {$taskStatus}\n";
        echo "Сообщение для оператора: " . $finalResult->getData('user_status')['text'] . "\n";
        
        if ($taskStatus === 'completed') {
            $itemInfo = $finalResult->getData('machine_data')['itemInfoCheckResult'];
            echo "📊 Результат проверки ККТ:\n";
            echo "   - ecrStandAloneFlag: " . ($itemInfo['ecrStandAloneFlag'] ? 'true' : 'false') . "\n";
            echo "   - imcCheckFlag: " . ($itemInfo['imcCheckFlag'] ? 'true' : 'false') . "\n";
            echo "   - imcCheckResult: " . ($itemInfo['imcCheckResult'] ? 'true' : 'false') . "\n";
            echo "   - imcEstimatedStatusCorrect: " . ($itemInfo['imcEstimatedStatusCorrect'] ? 'true' : 'false') . "\n";
            echo "   - imcStatusInfo: " . ($itemInfo['imcStatusInfo'] ? 'true' : 'false') . "\n";
        }
    } else {
        echo "❌ Ошибка получения результата: " . $finalResult->error . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== ДЕМОНСТРАЦИЯ ЗАВЕРШЕНА ===\n";
echo "\nКлючевые отличия новой архитектуры:\n";
echo "✅ Четкое разделение на синхронный (permit) и асинхронный (ecr) режимы\n";
echo "✅ Единый формат OperationResult с user_status и machine_data\n";
echo "✅ Никаких printed_lines - только структурированные данные\n";
echo "✅ TaskId для отслеживания асинхронных операций\n";
echo "✅ Независимые интерфейсы и use case для каждого режима\n";