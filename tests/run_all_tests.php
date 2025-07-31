<?php
/**
 * Скрипт для запуска всех тестов проекта
 * 
 * Запускает unit и integration тесты, собирает статистику
 */

require_once __DIR__ . '/../src/bootstrap.php';

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function runAllTests(): void
    {
        echo "🧪 Запуск всех тестов CloudPosBridge\n";
        echo "=====================================\n\n";

        $this->runUnitTests();
        $this->runIntegrationTests();
        $this->printSummary();
    }

    private function runUnitTests(): void
    {
        echo "📋 Unit тесты:\n";
        echo "--------------\n";

        $unitTestsDir = __DIR__ . '/unit';
        $this->runTestsInDirectory($unitTestsDir);
        echo "\n";
    }

    private function runIntegrationTests(): void
    {
        echo "🔗 Integration тесты:\n";
        echo "---------------------\n";

        $integrationTestsDir = __DIR__ . '/integration';
        $this->runTestsInDirectory($integrationTestsDir);
        echo "\n";
    }

    private function runTestsInDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            echo "⚠️  Папка $directory не найдена\n";
            return;
        }

        $files = glob($directory . '/*Test.php');
        
        if (empty($files)) {
            echo "ℹ️  Тесты не найдены в $directory\n";
            return;
        }

        foreach ($files as $file) {
            $this->runTestFile($file);
        }
    }

    private function runTestFile(string $file): void
    {
        $testName = basename($file, '.php');
        
        try {
            ob_start();
            $result = include $file;
            $output = ob_get_clean();

            if ($result === true || $result === 1) {
                echo "✅ $testName - PASSED\n";
                $this->passed++;
            } else {
                echo "❌ $testName - FAILED\n";
                if ($output) {
                    echo "   Вывод: $output\n";
                }
                $this->failed++;
                $this->errors[] = "$testName: $output";
            }
        } catch (Exception $e) {
            echo "💥 $testName - ERROR: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->errors[] = "$testName: " . $e->getMessage();
        }
    }

    private function printSummary(): void
    {
        echo "📊 Итоги тестирования:\n";
        echo "======================\n";
        echo "✅ Пройдено: {$this->passed}\n";
        echo "❌ Провалено: {$this->failed}\n";
        echo "📈 Общий процент: " . ($this->passed + $this->failed > 0 ? 
            round($this->passed / ($this->passed + $this->failed) * 100) : 0) . "%\n";

        if (!empty($this->errors)) {
            echo "\n🔍 Детали ошибок:\n";
            foreach ($this->errors as $error) {
                echo "   • $error\n";
            }
        }

        echo "\n" . ($this->failed === 0 ? "🎉 Все тесты пройдены успешно!" : "🚨 Есть неудачные тесты") . "\n";
        
        // Возвращаем правильный exit код
        exit($this->failed === 0 ? 0 : 1);
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new TestRunner();
    $runner->runAllTests();
}