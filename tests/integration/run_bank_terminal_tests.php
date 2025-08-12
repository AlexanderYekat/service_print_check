<?php

/**
 * Главный скрипт для запуска всех тестов банковского терминала
 */

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

// Подключаем тестовые классы
require_once __DIR__ . '/BankTerminalIntegrationTest.php';
require_once __DIR__ . '/BankTerminalGoIntegrationTest.php';

use Tests\Integration\BankTerminalIntegrationTest;
use Tests\Integration\BankTerminalGoIntegrationTest;

/**
 * Главный класс для запуска всех тестов банковского терминала
 */
class BankTerminalTestRunner
{
    private array $testSuites = [];
    private array $results = [];
    
    public function __construct()
    {
        $this->initializeTestSuites();
    }
    
    private function initializeTestSuites(): void
    {
        $this->testSuites = [
            'integration' => [
                'name' => 'Интеграционные тесты',
                'class' => BankTerminalIntegrationTest::class,
                'description' => 'Тестирование полного пути от HTTP-запроса до результата'
            ],
            'go_integration' => [
                'name' => 'Тесты интеграции с Go-бинарём',
                'class' => BankTerminalGoIntegrationTest::class,
                'description' => 'Тестирование взаимодействия с Go-программой'
            ]
        ];
    }
    
    public function runAllTests(): void
    {
        echo "🚀 ЗАПУСК КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ БАНКОВСКОГО ТЕРМИНАЛА\n";
        echo "=" . str_repeat("=", 70) . "\n\n";
        
        $startTime = microtime(true);
        $totalPassed = 0;
        $totalTests = 0;
        
        foreach ($this->testSuites as $key => $suite) {
            echo "📋 {$suite['name']}\n";
            echo "📝 {$suite['description']}\n";
            echo "-" . str_repeat("-", 50) . "\n";
            
            try {
                $testInstance = new $suite['class']();
                $result = $testInstance->run();
                
                $this->results[$key] = [
                    'status' => $result ? 'completed' : 'failed',
                    'name' => $suite['name']
                ];
                
                if ($result) {
                    $totalPassed++;
                }
                $totalTests++;
                
            } catch (\Exception $e) {
                echo "❌ Ошибка при запуске тестового набора '{$suite['name']}': " . $e->getMessage() . "\n";
                $this->results[$key] = [
                    'status' => 'error',
                    'name' => $suite['name'],
                    'error' => $e->getMessage()
                ];
            }
            
            echo "\n";
        }
        
        $endTime = microtime(true);
        $totalExecutionTime = ($endTime - $startTime) * 1000;
        
        $this->printSummary($totalExecutionTime, $totalPassed, $totalTests);
    }
    
    public function runTestSuite(string $suiteKey): void
    {
        if (!isset($this->testSuites[$suiteKey])) {
            echo "❌ Неизвестный тестовый набор: {$suiteKey}\n";
            echo "Доступные наборы: " . implode(', ', array_keys($this->testSuites)) . "\n";
            return;
        }
        
        $suite = $this->testSuites[$suiteKey];
        
        echo "🚀 ЗАПУСК ТЕСТОВОГО НАБОРА: {$suite['name']}\n";
        echo "📝 {$suite['description']}\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        try {
            $testInstance = new $suite['class']();
            $testInstance->run();
            
        } catch (\Exception $e) {
            echo "❌ Ошибка при запуске тестового набора: " . $e->getMessage() . "\n";
        }
    }
    
    private function printSummary(float $executionTime, int $passed, int $total): void
    {
        echo "📊 СВОДКА РЕЗУЛЬТАТОВ ТЕСТИРОВАНИЯ\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        $completedSuites = 0;
        $errorSuites = 0;
        
        foreach ($this->results as $key => $result) {
            $status = $result['status'] === 'completed' ? '✅' : '❌';
            echo "{$status} {$result['name']}\n";
            
            if ($result['status'] === 'completed') {
                $completedSuites++;
            } else {
                $errorSuites++;
                if (isset($result['error'])) {
                    echo "   Ошибка: {$result['error']}\n";
                }
            }
        }
        
        echo "\n📈 СТАТИСТИКА:\n";
        echo "✅ Завершено тестовых наборов: {$completedSuites}\n";
        echo "❌ Ошибок: {$errorSuites}\n";
        echo "⏱️  Общее время выполнения: " . round($executionTime, 2) . " мс\n";
        
        if ($errorSuites === 0) {
            echo "\n🎉 ВСЕ ТЕСТОВЫЕ НАБОРЫ ПРОЙДЕНЫ УСПЕШНО!\n";
        } else {
            echo "\n⚠️  НЕКОТОРЫЕ ТЕСТОВЫЕ НАБОРЫ ЗАВЕРШИЛИСЬ С ОШИБКАМИ\n";
        }
    }
    
    public function printHelp(): void
    {
        echo "ИСПОЛЬЗОВАНИЕ: php run_bank_terminal_tests.php [опции]\n\n";
        echo "ОПЦИИ:\n";
        echo "  --all                    Запустить все тестовые наборы (по умолчанию)\n";
        echo "  --integration            Запустить только интеграционные тесты\n";
        echo "  --go-integration         Запустить только тесты интеграции с Go-бинарём\n";
        echo "  --help                   Показать эту справку\n\n";
        
        echo "ДОСТУПНЫЕ ТЕСТОВЫЕ НАБОРЫ:\n";
        foreach ($this->testSuites as $key => $suite) {
            echo "  {$key} - {$suite['name']}\n";
            echo "      {$suite['description']}\n";
        }
    }
}

// Обработка аргументов командной строки
$options = getopt('', ['all', 'integration', 'go-integration', 'help']);

$runner = new BankTerminalTestRunner();

if (isset($options['help'])) {
    $runner->printHelp();
    exit(0);
}

if (isset($options['integration'])) {
    $runner->runTestSuite('integration');
} elseif (isset($options['go-integration'])) {
    $runner->runTestSuite('go_integration');
} else {
    // По умолчанию запускаем все тесты
    $runner->runAllTests();
} 