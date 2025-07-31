<?php
/**
 * Test Runner для доменных моделей
 * 
 * Запускает все тесты чистой бизнес-логики без внешних зависимостей
 */

require_once __DIR__ . '/CheckModelTest.php';
require_once __DIR__ . '/PaymentModelTest.php';
require_once __DIR__ . '/BankTransactionModelTest.php';

class DomainTestRunner
{
    private array $testClasses = [
        'CheckModelTest',
        'PaymentModelTest', 
        'BankTransactionModelTest'
    ];
    
    public function runAll(): bool
    {
        echo "🧪 === ТЕСТЫ ДОМЕННЫХ МОДЕЛЕЙ (ЧИСТАЯ БИЗНЕС-ЛОГИКА) ===\n\n";
        
        $totalPassed = 0;
        $totalTests = 0;
        $allPassed = true;
        
        foreach ($this->testClasses as $testClass) {
            echo "🔹 Запуск тестов: {$testClass}\n";
            echo str_repeat("-", 50) . "\n";
            
            $test = new $testClass();
            $result = $test->run();
            
            if ($result) {
                echo "✅ {$testClass}: ВСЕ ТЕСТЫ ПРОЙДЕНЫ\n";
                $totalPassed++;
            } else {
                echo "❌ {$testClass}: ЕСТЬ ОШИБКИ\n";
                $allPassed = false;
            }
            
            $totalTests++;
            echo "\n";
        }
        
        // Итоговый отчет
        echo str_repeat("=", 60) . "\n";
        echo "📊 ИТОГОВЫЙ ОТЧЕТ ДОМЕННЫХ ТЕСТОВ:\n";
        echo "   Модулей протестировано: {$totalPassed}/{$totalTests}\n";
        echo "   Результат: " . ($allPassed ? "✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ" : "❌ ЕСТЬ ОШИБКИ") . "\n";
        echo str_repeat("=", 60) . "\n";
        
        return $allPassed;
    }
    
    public function runSpecific(string $testClass): bool
    {
        if (!in_array($testClass, $this->testClasses)) {
            echo "❌ Тест класс '{$testClass}' не найден\n";
            echo "Доступные классы: " . implode(', ', $this->testClasses) . "\n";
            return false;
        }
        
        echo "🧪 Запуск конкретного теста: {$testClass}\n\n";
        
        $test = new $testClass();
        return $test->run();
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new DomainTestRunner();
    
    // Проверяем аргументы командной строки
    if (isset($argv[1])) {
        $testClass = $argv[1];
        $result = $runner->runSpecific($testClass);
    } else {
        $result = $runner->runAll();
    }
    
    exit($result ? 0 : 1);
}