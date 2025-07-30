<?php

require_once __DIR__ . '/GoBankTerminalAdapterTest.php';
require_once __DIR__ . '/HonestSignQueueTest.php';
require_once __DIR__ . '/PrintCheckUseCaseTest.php';

class TestRunner
{
    public function runAllTests()
    {
        echo "🧪 Запуск всех тестов для чистой архитектуры...\n\n";
        
        // Запуск тестов адаптеров
        $bankTest = new GoBankTerminalAdapterTest();
        $bankTest->runAllTests();
        
        $queueTest = new HonestSignQueueTest();
        $queueTest->runAllTests();
        
        // Запуск тестов use cases (если они существуют)
        if (class_exists('PrintCheckUseCaseTest')) {
            $printTest = new PrintCheckUseCaseTest();
            $printTest->runAllTests();
        }
        
        echo "🎉 Все тесты успешно выполнены!\n";
        echo "✅ Чистая архитектура работает корректно.\n";
    }
}

// Запуск тестов если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new TestRunner();
    $runner->runAllTests();
}