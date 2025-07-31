<?php
/**
 * Unit тест для модели Check
 * 
 * Тестирует чистую бизнес-логику без внешних зависимостей:
 * - Валидация конструктора
 * - Бизнес-правила расчетов
 * - Геттеры и методы
 * - Обработка ошибок
 */

require_once __DIR__ . '/../../../src/domain/model/Check.php';
require_once __DIR__ . '/../../../src/domain/model/CheckItem.php';
require_once __DIR__ . '/../../../src/domain/model/Payment.php';
require_once __DIR__ . '/../../../src/domain/model/MarkingCode.php';

class CheckModelTest
{
    public function testValidCheckCreation(): bool
    {
        try {
            // Создаем валидные товары
            $item1 = new CheckItem('Хлеб', 25.50, 1, 25.50);
            $item2 = new CheckItem('Молоко', 65.00, 2, 130.00);
            
            // Создаем валидные платежи  
            $payment1 = new Payment('cash', 100.00);
            $payment2 = new Payment('card', 55.50);
            
            $check = new Check(
                [$item1, $item2],
                [$payment1, $payment2],
                'Кассир 1',
                Check::TYPE_SELL,
                'osn'
            );
            
            // Проверяем корректность созданного чека
            if (count($check->getItems()) !== 2) {
                throw new Exception('Количество товаров должно быть 2');
            }
            
            if (count($check->getPayments()) !== 2) {
                throw new Exception('Количество платежей должно быть 2');
            }
            
            if ($check->getCashier() !== 'Кассир 1') {
                throw new Exception('Кассир должен быть "Кассир 1"');
            }
            
            if ($check->getType() !== Check::TYPE_SELL) {
                throw new Exception('Тип чека должен быть sell');
            }
            
            echo "✅ Создание валидного чека: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание валидного чека: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCheckTotalCalculation(): bool
    {
        try {
            // Создаем товары с известными суммами
            $item1 = new CheckItem('Товар 1', 100.00, 2, 200.00); // 200.00
            $item2 = new CheckItem('Товар 2', 50.50, 3, 151.50);  // 151.50
            
            $payment = new Payment('cash', 351.50);
            
            $check = new Check(
                [$item1, $item2],
                [$payment],
                'Кассир 1',
                Check::TYPE_SELL,
                'osn'
            );
            
            $expectedTotal = 351.50;
            $actualTotal = $check->getTotalAmount();
            
            if (abs($actualTotal - $expectedTotal) > 0.01) {
                throw new Exception("Неверная сумма чека. Ожидалось: {$expectedTotal}, получено: {$actualTotal}");
            }
            
            echo "✅ Расчет суммы чека: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Расчет суммы чека: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testInvalidCheckType(): bool
    {
        try {
            $item = new CheckItem('Тест', 100.00, 1, 100.00);
            $payment = new Payment('cash', 100.00);
            
            // Пытаемся создать чек с недопустимым типом
            new Check(
                [$item],
                [$payment],
                'Кассир 1',
                'invalid_type', // Недопустимый тип
                'osn'
            );
            
            // Если дошли сюда - тест провален
            echo "❌ Валидация типа чека: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация типа чека: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация типа чека: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testEmptyItemsValidation(): bool
    {
        try {
            $payment = new Payment('cash', 100.00);
            
            // Пытаемся создать чек без товаров
            new Check(
                [], // Пустой массив товаров
                [$payment],
                'Кассир 1',
                Check::TYPE_SELL,
                'osn'
            );
            
            echo "❌ Валидация пустых товаров: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация пустых товаров: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация пустых товаров: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testEmptyCashierValidation(): bool
    {
        try {
            $item = new CheckItem('Тест', 100.00, 1, 100.00);
            $payment = new Payment('cash', 100.00);
            
            // Пытаемся создать чек с пустым именем кассира
            new Check(
                [$item],
                [$payment],
                '', // Пустое имя кассира
                Check::TYPE_SELL,
                'osn'
            );
            
            echo "❌ Валидация пустого кассира: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация пустого кассира: PASSED\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Валидация пустого кассира: FAILED - Неожиданное исключение: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testHasMarkingCodes(): bool
    {
        try {
            // Товар с маркировкой
            $markingCode = new MarkingCode('01234567890123456789012345678901');
            $item1 = new CheckItem('Товар с маркировкой', 100.00, 1, 100.00, $markingCode);
            
            // Товар без маркировки
            $item2 = new CheckItem('Товар без маркировки', 50.00, 1, 50.00);
            
            $payment = new Payment('cash', 150.00);
            
            $check = new Check(
                [$item1, $item2],
                [$payment],
                'Кассир 1',
                Check::TYPE_SELL,
                'osn'
            );
            
            if (!$check->hasMarkingCodes()) {
                throw new Exception('Чек должен иметь маркировки');
            }
            
            // Проверяем получение маркированных товаров
            $markedItems = $check->getMarkedItems();
            if (count($markedItems) !== 1) {
                throw new Exception('Должен быть 1 маркированный товар');
            }
            
            echo "✅ Проверка маркировок: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Проверка маркировок: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов модели Check...\n\n";
        
        $tests = [
            'testValidCheckCreation',
            'testCheckTotalCalculation',
            'testInvalidCheckType',
            'testEmptyItemsValidation',
            'testEmptyCashierValidation',
            'testHasMarkingCodes'
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
    $test = new CheckModelTest();
    $result = $test->run();
    echo $result ? "✅ CheckModelTest PASSED\n" : "❌ CheckModelTest FAILED\n";
    exit($result ? 0 : 1);
}