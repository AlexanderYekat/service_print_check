<?php
/**
 * Главный тест-раннер для чистой архитектуры
 */

require_once __DIR__ . '/unit/GoBankTerminalAdapterTest.php';
require_once __DIR__ . '/unit/HonestSignQueueTest.php';

class TestRunner
{
    private array $testResults = [];
    
    public function runAllTests(): bool
    {
        echo "🧪 Запуск всех тестов для чистой архитектуры...\n\n";
        
        $success = true;
        
        try {
            // Тесты адаптеров
            $this->runTest('GoBankTerminalAdapter', function() {
                $test = new GoBankTerminalAdapterTest();
                $test->runAllTests();
            });
            
            $this->runTest('HonestSignQueue', function() {
                $test = new HonestSignQueueTest();
                $test->runAllTests();
            });
            
            // Тесты use cases (если существуют)
            if (file_exists(__DIR__ . '/../src/tests/PrintCheckUseCaseTest.php')) {
                require_once __DIR__ . '/../src/tests/PrintCheckUseCaseTest.php';
                $this->runTest('PrintCheckUseCase', function() {
                    $test = new PrintCheckUseCaseTest();
                    $test->runAllTests();
                });
            }
            
        } catch (Exception $e) {
            echo "❌ Ошибка при выполнении тестов: " . $e->getMessage() . "\n";
            $success = false;
        }
        
        $this->printResults();
        return $success;
    }
    
    private function runTest(string $name, callable $testFunction): void
    {
        echo "🔄 Выполнение тестов: {$name}\n";
        
        try {
            $startTime = microtime(true);
            $testFunction();
            $endTime = microtime(true);
            
            $this->testResults[$name] = [
                'status' => 'passed',
                'time' => round(($endTime - $startTime) * 1000, 2) . 'ms'
            ];
            
        } catch (Exception $e) {
            $this->testResults[$name] = [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            echo "❌ Тест {$name} не прошел: " . $e->getMessage() . "\n";
        }
    }
    
    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 Результаты тестов:\n\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $name => $result) {
            $status = $result['status'] === 'passed' ? '✅' : '❌';
            $time = $result['time'] ?? '';
            echo "{$status} {$name} {$time}\n";
            
            if ($result['status'] === 'passed') {
                $passed++;
            } else {
                $failed++;
                if (isset($result['error'])) {
                    echo "   Ошибка: {$result['error']}\n";
                }
            }
        }
        
        echo "\n📈 Итого: {$passed} прошло, {$failed} не прошло\n";
        
        if ($failed === 0) {
            echo "🎉 Все тесты прошли успешно!\n";
            echo "✅ Чистая архитектура работает корректно.\n";
        } else {
            echo "⚠️  Есть проблемы, требующие исправления.\n";
        }
    }
}

// Запуск тестов если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new TestRunner();
    $success = $runner->runAllTests();
    exit($success ? 0 : 1);
}