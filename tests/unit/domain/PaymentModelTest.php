<?php
/**
 * Unit тест для модели Payment
 * 
 * Тестирует чистую бизнес-логику платежей:
 * - Валидация типов платежей
 * - Валидация сумм
 * - Бизнес-правила для каждого типа
 */

require_once __DIR__ . '/../../../src/domain/model/Payment.php';

class PaymentModelTest
{
    public function testValidCashPayment(): bool
    {
        try {
            $payment = new Payment('cash', 100.50);
            
            if ($payment->getType() !== 'cash') {
                throw new Exception('Тип платежа должен быть cash');
            }
            
            if (abs($payment->getAmount() - 100.50) > 0.01) {
                throw new Exception('Сумма платежа должна быть 100.50');
            }
            
            if (!$payment->isCash()) {
                throw new Exception('Платеж должен быть наличным');
            }
            
            echo "✅ Создание наличного платежа: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание наличного платежа: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testValidCardPayment(): bool
    {
        try {
            $payment = new Payment('card', 250.75);
            
            if ($payment->getType() !== 'card') {
                throw new Exception('Тип платежа должен быть card');
            }
            
            if (abs($payment->getAmount() - 250.75) > 0.01) {
                throw new Exception('Сумма платежа должна быть 250.75');
            }
            
            if ($payment->isCash()) {
                throw new Exception('Платеж не должен быть наличным');
            }
            
            echo "✅ Создание картового платежа: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание картового платежа: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testInvalidPaymentType(): bool
    {
        try {
            // Пытаемся создать платеж с недопустимым типом
            new Payment('bitcoin', 100.00);
            
            echo "❌ Валидация типа платежа: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация типа платежа: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация типа платежа: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testNegativeAmount(): bool
    {
        try {
            // Пытаемся создать платеж с отрицательной суммой
            new Payment('cash', -50.00);
            
            echo "❌ Валидация отрицательной суммы: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация отрицательной суммы: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация отрицательной суммы: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testZeroAmount(): bool
    {
        try {
            // Пытаемся создать платеж с нулевой суммой
            new Payment('cash', 0.00);
            
            echo "❌ Валидация нулевой суммы: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация нулевой суммы: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация нулевой суммы: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testEmptyPaymentType(): bool
    {
        try {
            // Пытаемся создать платеж с пустым типом
            new Payment('', 100.00);
            
            echo "❌ Валидация пустого типа: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация пустого типа: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация пустого типа: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testPaymentToArray(): bool
    {
        try {
            $payment = new Payment('cash', 123.45);
            $array = $payment->toArray();
            
            $expected = [
                'type' => 'cash',
                'amount' => 123.45
            ];
            
            if ($array !== $expected) {
                throw new Exception('Метод toArray() возвращает неверную структуру');
            }
            
            echo "✅ Сериализация в массив: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сериализация в массив: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов модели Payment...\n\n";
        
        $tests = [
            'testValidCashPayment',
            'testValidCardPayment',
            'testInvalidPaymentType',
            'testNegativeAmount',
            'testZeroAmount',
            'testEmptyPaymentType',
            'testPaymentToArray'
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

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PaymentModelTest();
    $result = $test->run();
    echo $result ? "✅ PaymentModelTest PASSED\n" : "❌ PaymentModelTest FAILED\n";
    exit($result ? 0 : 1);
}