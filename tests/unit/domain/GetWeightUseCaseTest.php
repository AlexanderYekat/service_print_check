<?php
/**
 * Unit-тесты для GetWeightUseCase
 * 
 * Тестирует бизнес-логику получения веса:
 * - Успешное получение веса
 * - Обработка ошибок связи с весами
 * - Валидация результатов
 * - Интеграция с различными типами весов
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domain\Service\GetWeightUseCase;
use App\Domain\Model\OperationResult;
use App\Interface\ScaleInterface;

class GetWeightUseCaseTest
{
    public function testSuccessfulWeightRetrieval(): bool
    {
        try {
            // Создаем мок весов с успешным результатом
            $mockScale = new MockSuccessfulScale(150.75);
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            // Проверяем успешность операции
            if (!$result->isSuccess()) {
                throw new Exception('Результат должен быть успешным');
            }
            
            // Проверяем корректность веса
            $weight = $result->getData('weight');
            if (abs($weight - 150.75) > 0.01) {
                throw new Exception("Ожидался вес 150.75, получен: {$weight}");
            }
            
            // Проверяем сообщение
            $message = $result->getData('message') ?? '';
            if (empty($message)) {
                throw new Exception('Должно быть сообщение о результате');
            }
            
            echo "✅ Успешное получение веса: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Успешное получение веса: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testWeightRetrievalConnectionError(): bool
    {
        try {
            // Создаем мок весов с ошибкой подключения
            $mockScale = new MockFailedScale('Ошибка подключения к весам');
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            // Проверяем, что операция неуспешна
            if ($result->isSuccess()) {
                throw new Exception('Результат должен быть неуспешным при ошибке подключения');
            }
            
            // Проверяем сообщение об ошибке
            $errorMessage = $result->getErrorMessage();
            if (strpos($errorMessage, 'подключения') === false) {
                throw new Exception("Некорректное сообщение об ошибке: {$errorMessage}");
            }
            
            echo "✅ Обработка ошибки подключения: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка ошибки подключения: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testWeightRetrievalReadError(): bool
    {
        try {
            // Создаем мок весов с ошибкой чтения
            $mockScale = new MockFailedScale('Ошибка чтения веса: Весы не стабилизированы');
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            // Проверяем неуспешность
            if ($result->isSuccess()) {
                throw new Exception('Результат должен быть неуспешным при ошибке чтения');
            }
            
            // Проверяем тип ошибки
            $errorMessage = $result->getErrorMessage();
            if (strpos($errorMessage, 'чтения') === false) {
                throw new Exception("Некорректное сообщение об ошибке: {$errorMessage}");
            }
            
            echo "✅ Обработка ошибки чтения: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка ошибки чтения: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testZeroWeight(): bool
    {
        try {
            // Тестируем случай с нулевым весом
            $mockScale = new MockSuccessfulScale(0.0);
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            // Нулевой вес тоже должен быть валидным результатом
            if (!$result->isSuccess()) {
                throw new Exception('Нулевой вес должен быть валидным результатом');
            }
            
            $weight = $result->getData('weight');
            if ($weight !== 0.0) {
                throw new Exception("Ожидался вес 0.0, получен: {$weight}");
            }
            
            echo "✅ Нулевой вес: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Нулевой вес: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testHighPrecisionWeight(): bool
    {
        try {
            // Тестируем высокоточный вес
            $mockScale = new MockSuccessfulScale(1234.567);
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            if (!$result->isSuccess()) {
                throw new Exception('Результат должен быть успешным');
            }
            
            $weight = $result->getData('weight');
            if (abs($weight - 1234.567) > 0.001) {
                throw new Exception("Ожидался вес 1234.567, получен: {$weight}");
            }
            
            echo "✅ Высокоточный вес: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Высокоточный вес: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testNegativeWeightHandling(): bool
    {
        try {
            // Тестируем отрицательный вес (некорректное состояние)
            $mockScale = new MockSuccessfulScale(-5.0);
            $useCase = new GetWeightUseCase($mockScale);
            
            $result = $useCase->execute();
            
            // Use case просто передает результат от адаптера весов
            // Валидация отрицательного веса должна быть в адаптере
            if (!$result->isSuccess()) {
                throw new Exception('Результат должен быть успешным (валидация в адаптере)');
            }
            
            $weight = $result->getData('weight');
            if ($weight !== -5.0) {
                throw new Exception("Ожидался вес -5.0, получен: {$weight}");
            }
            
            echo "✅ Отрицательный вес: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Отрицательный вес: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testMultipleWeightReadings(): bool
    {
        try {
            // Тестируем несколько последовательных чтений
            $weights = [123.4, 123.5, 123.6];
            $mockScale = new MockVariableScale($weights);
            $useCase = new GetWeightUseCase($mockScale);
            
            foreach ($weights as $expectedWeight) {
                $result = $useCase->execute();
                
                if (!$result->isSuccess()) {
                    throw new Exception('Все чтения должны быть успешными');
                }
                
                $actualWeight = $result->getData('weight');
                if (abs($actualWeight - $expectedWeight) > 0.01) {
                    throw new Exception("Ожидался вес {$expectedWeight}, получен: {$actualWeight}");
                }
            }
            
            echo "✅ Множественные чтения: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Множественные чтения: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов GetWeightUseCase...\n\n";
        
        $tests = [
            'testSuccessfulWeightRetrieval',
            'testWeightRetrievalConnectionError', 
            'testWeightRetrievalReadError',
            'testZeroWeight',
            'testHighPrecisionWeight',
            'testNegativeWeightHandling',
            'testMultipleWeightReadings'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n📊 Результат: {$passed}/{$total} тестов пройдено\n";
        
        return $passed === $total;
    }
}

// ========================================
// MOCK КЛАССЫ ДЛЯ ТЕСТИРОВАНИЯ
// ========================================

/**
 * Мок успешных весов с фиксированным весом
 */
class MockSuccessfulScale implements ScaleInterface
{
    private float $weight;
    
    public function __construct(float $weight)
    {
        $this->weight = $weight;
    }
    
    public function getWeight(): OperationResult
    {
        return OperationResult::success([
            'weight' => $this->weight,
            'message' => "Вес получен успешно: {$this->weight} кг"
        ], 'Операция выполнена успешно');
    }
}

/**
 * Мок неисправных весов с ошибкой
 */
class MockFailedScale implements ScaleInterface
{
    private string $errorMessage;
    
    public function __construct(string $errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }
    
    public function getWeight(): OperationResult
    {
        return OperationResult::failure($this->errorMessage);
    }
}

/**
 * Мок весов с переменными показаниями
 */
class MockVariableScale implements ScaleInterface
{
    private array $weights;
    private int $currentIndex = 0;
    
    public function __construct(array $weights)
    {
        $this->weights = $weights;
    }
    
    public function getWeight(): OperationResult
    {
        if ($this->currentIndex >= count($this->weights)) {
            $this->currentIndex = 0; // Циклически повторяем
        }
        
        $weight = $this->weights[$this->currentIndex];
        $this->currentIndex++;
        
        return OperationResult::success([
            'weight' => $weight,
            'message' => "Вес получен: {$weight} кг"
        ], 'Операция выполнена успешно');
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new GetWeightUseCaseTest();
    $result = $test->run();
    echo $result ? "✅ GetWeightUseCaseTest PASSED\n" : "❌ GetWeightUseCaseTest FAILED\n";
    exit($result ? 0 : 1);
}