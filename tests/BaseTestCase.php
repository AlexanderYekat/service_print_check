<?php

/**
 * Базовый класс для всех тестов
 * 
 * Обеспечивает единообразную структуру и функциональность:
 * - Стандартизированные методы тестирования
 * - Единообразный вывод результатов
 * - Подсчет статистики
 */
abstract class BaseTestCase
{
    protected array $results = [];
    protected int $passed = 0;
    protected int $failed = 0;
    
    /**
     * Запуск всех тестов
     */
    public function run(): bool
    {
        $this->printHeader();
        
        $tests = $this->getTests();
        $total = count($tests);
        
        foreach ($tests as $test) {
            $this->runTest($test);
        }
        
        $this->printSummary($total);
        
        return $this->failed === 0;
    }
    
    /**
     * Запуск одного теста
     */
    protected function runTest(string $testName): void
    {
        try {
            $result = $this->$testName();
            
            if ($result) {
                $this->passed++;
                $this->results[$testName] = ['status' => 'PASSED'];
                echo "✅ {$testName}\n";
            } else {
                $this->failed++;
                $this->results[$testName] = ['status' => 'FAILED'];
                echo "❌ {$testName}\n";
            }
            
        } catch (\Exception $e) {
            $this->failed++;
            $this->results[$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
            echo "💥 {$testName} - Ошибка: {$e->getMessage()}\n";
        }
    }
    
    /**
     * Вывод заголовка тестов
     */
    protected function printHeader(): void
    {
        $className = static::class;
        echo "🧪 {$className}\n";
        echo str_repeat("=", 50) . "\n";
    }
    
    /**
     * Вывод сводки результатов
     */
    protected function printSummary(int $total): void
    {
        echo "\n📊 Результаты:\n";
        echo "✅ Пройдено: {$this->passed}\n";
        echo "❌ Провалено: {$this->failed}\n";
        echo "📈 Общий результат: " . round(($this->passed / $total) * 100, 1) . "%\n";
        
        if ($this->failed === 0) {
            echo "\n🎉 Все тесты пройдены успешно!\n";
        } else {
            echo "\n⚠️  Некоторые тесты провалены.\n";
        }
    }
    
    /**
     * Получить список тестов для запуска
     */
    abstract protected function getTests(): array;
    
    /**
     * Утверждение что условие истинно
     */
    protected function assertTrue(bool $condition, string $message = ''): bool
    {
        if (!$condition) {
            throw new \Exception($message ?: 'Условие должно быть истинным');
        }
        return true;
    }
    
    /**
     * Утверждение что условие ложно
     */
    protected function assertFalse(bool $condition, string $message = ''): bool
    {
        if ($condition) {
            throw new \Exception($message ?: 'Условие должно быть ложным');
        }
        return true;
    }
    
    /**
     * Утверждение равенства
     */
    protected function assertEquals($expected, $actual, string $message = ''): bool
    {
        if ($expected !== $actual) {
            $error = $message ?: "Ожидалось: " . var_export($expected, true) . 
                    ", получено: " . var_export($actual, true);
            throw new \Exception($error);
        }
        return true;
    }
    
    /**
     * Утверждение что значение больше
     */
    protected function assertGreaterThan($expected, $actual, string $message = ''): bool
    {
        if ($actual <= $expected) {
            $error = $message ?: "Значение должно быть больше {$expected}, получено: {$actual}";
            throw new \Exception($error);
        }
        return true;
    }
    
    /**
     * Утверждение что значение меньше
     */
    protected function assertLessThan($expected, $actual, string $message = ''): bool
    {
        if ($actual >= $expected) {
            $error = $message ?: "Значение должно быть меньше {$expected}, получено: {$actual}";
            throw new \Exception($error);
        }
        return true;
    }
    
    /**
     * Утверждение что массив содержит ключ
     */
    protected function assertArrayHasKey(string $key, array $array, string $message = ''): bool
    {
        if (!array_key_exists($key, $array)) {
            $error = $message ?: "Массив должен содержать ключ '{$key}'";
            throw new \Exception($error);
        }
        return true;
    }
    
    /**
     * Утверждение что строка содержит подстроку
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): bool
    {
        if (strpos($haystack, $needle) === false) {
            $error = $message ?: "Строка должна содержать '{$needle}'";
            throw new \Exception($error);
        }
        return true;
    }
    
    /**
     * Утверждение что исключение выбрасывается
     */
    protected function assertThrows(callable $callback, string $expectedException = '', string $message = ''): bool
    {
        try {
            $callback();
            throw new \Exception($message ?: 'Ожидалось исключение, но ничего не было выброшено');
        } catch (\Exception $e) {
            if ($expectedException && !($e instanceof $expectedException)) {
                throw new \Exception($message ?: "Ожидалось исключение {$expectedException}, получено: " . get_class($e));
            }
            return true;
        }
    }
} 