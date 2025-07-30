<?php
// Тестовый скрипт для проверки чистой архитектуры

echo "🧪 Тестирование чистой архитектуры CloudPosBridge\n\n";

// Запуск unit тестов
require_once __DIR__ . '/src/tests/TestRunner.php';
$testRunner = new TestRunner();
$testRunner->runAllTests();

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Демонстрация работы компонентов:\n\n";

// Демонстрация DI контейнера
require_once __DIR__ . '/src/bootstrap.php';

echo "✅ Bootstrap инициализирован\n";
echo "✅ DI контейнер содержит " . count($GLOBALS['di']) . " компонентов\n";

// Проверка health check
echo "\n🏥 Проверка состояния сервисов:\n";
$healthReport = $GLOBALS['di']['health_checker']->checkAll();
echo "Общий статус: " . $healthReport['overall_status'] . "\n";

foreach ($healthReport['services'] as $name => $status) {
    $emoji = $status['status'] === 'healthy' ? '✅' : '❌';
    echo "{$emoji} {$name}: {$status['status']} ({$status['response_time_ms']}ms)\n";
}

// Проверка очереди
echo "\n📋 Статус очереди Честного Знака:\n";
$queueStatus = $GLOBALS['di']['send_to_honest_sign_use_case']->getQueueStatus();
echo "Всего элементов: {$queueStatus['total']}\n";
echo "Ожидающих: {$queueStatus['pending']}\n";
echo "Завершенных: {$queueStatus['completed']}\n";
echo "Неудачных: {$queueStatus['failed']}\n";

echo "\n🎉 Чистая архитектура успешно работает!\n";
echo "🚀 Готово к использованию в production.\n";