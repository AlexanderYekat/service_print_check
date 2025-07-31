<?php
/**
 * Unit тест для модели BankTransaction
 * 
 * Тестирует чистую бизнес-логику банковских транзакций:
 * - Создание различных типов транзакций
 * - Валидация данных
 * - Бизнес-правила банковских операций
 */

require_once __DIR__ . '/../../../src/domain/model/BankTransaction.php';

class BankTransactionModelTest
{
    public function testValidPaymentTransaction(): bool
    {
        try {
            // Создаем успешную платежную транзакцию (как это делает сервис)
            $transaction = new BankTransaction('payment', 250.50, true, null, null, 'SLIP_DATA_HERE');
            
            if ($transaction->getType() !== 'payment') {
                throw new Exception('Тип транзакции должен быть payment');
            }
            
            if (abs($transaction->getAmount() - 250.50) > 0.01) {
                throw new Exception('Сумма транзакции должна быть 250.50');
            }
            
            if (!$transaction->isSuccessful()) {
                throw new Exception('Транзакция должна быть успешной');
            }
            
            if ($transaction->hasError()) {
                throw new Exception('Успешная транзакция не должна иметь ошибки');
            }
            
            if (!$transaction->isMoneyOperation()) {
                throw new Exception('Платеж должен быть денежной операцией');
            }
            
            if ($transaction->getSlip() !== 'SLIP_DATA_HERE') {
                throw new Exception('Слип должен быть установлен');
            }
            
            echo "✅ Создание платежной транзакции: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание платежной транзакции: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testValidRefundTransaction(): bool
    {
        try {
            $transaction = new BankTransaction('refund', 100.00, true);
            
            if ($transaction->getType() !== 'refund') {
                throw new Exception('Тип транзакции должен быть refund');
            }
            
            if (!$transaction->isSuccessful()) {
                throw new Exception('Возврат должен быть успешным');
            }
            
            echo "✅ Создание возвратной транзакции: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание возвратной транзакции: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testDeclinedTransaction(): bool
    {
        try {
            $transaction = new BankTransaction('payment', 500.00, false, 101, 'Карта отклонена банком');
            
            if ($transaction->isSuccessful()) {
                throw new Exception('Отклоненная транзакция не должна быть успешной');
            }
            
            if (!$transaction->hasError()) {
                throw new Exception('Отклоненная транзакция должна иметь ошибку');
            }
            
            if ($transaction->getErrorCode() !== 101) {
                throw new Exception('Код ошибки должен быть 101');
            }
            
            if ($transaction->getErrorMessage() !== 'Карта отклонена банком') {
                throw new Exception('Сообщение об ошибке неверное');
            }
            
            echo "✅ Обработка отклоненной транзакции: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка отклоненной транзакции: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testInvalidTransactionType(): bool
    {
        try {
            // Пытаемся создать транзакцию с недопустимым типом
            new BankTransaction('invalid_type', 100.00);
            
            echo "❌ Валидация типа транзакции: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация типа транзакции: PASSED\n";
            return true;
        }
    }
    
    public function testNegativeAmount(): bool
    {
        try {
            // Пытаемся создать платеж с отрицательной суммой
            new BankTransaction('payment', -100.00, true);
            
            echo "❌ Валидация отрицательной суммы: FAILED - Исключение не было выброшено\n";
            return false;
            
        } catch (InvalidArgumentException $e) {
            echo "✅ Валидация отрицательной суммы: PASSED\n";
            return true;
        }
    }
    
    public function testCloseShiftOperation(): bool
    {
        try {
            // Создаем операцию закрытия смены (без суммы)
            $transaction = new BankTransaction('close_shift', 0, true);
            
            if ($transaction->getType() !== 'close_shift') {
                throw new Exception('Тип операции должен быть close_shift');
            }
            
            if ($transaction->isMoneyOperation()) {
                throw new Exception('Закрытие смены не должно быть денежной операцией');
            }
            
            if (!$transaction->isSuccessful()) {
                throw new Exception('Закрытие смены должно быть успешным');
            }
            
            echo "✅ Закрытие смены: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Закрытие смены: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testTransactionToArray(): bool
    {
        try {
            $transaction = new BankTransaction('payment', 150.75, true, null, null, 'SLIP123');
            
            $array = $transaction->toArray();
            
            // Проверяем основные поля
            if ($array['type'] !== 'payment') {
                throw new Exception('Неверный тип в toArray()');
            }
            
            if (abs($array['amount'] - 150.75) > 0.01) {
                throw new Exception('Неверная сумма в toArray()');
            }
            
            if ($array['is_successful'] !== true) {
                throw new Exception('Неверный статус успеха в toArray()');
            }
            
            if ($array['slip'] !== 'SLIP123') {
                throw new Exception('Неверный слип в toArray()');
            }
            
            echo "✅ Сериализация в массив: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сериализация в массив: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCancellationTransaction(): bool
    {
        try {
            $transaction = new BankTransaction('cancellation', 200.00, true);
            
            if ($transaction->getType() !== 'cancellation') {
                throw new Exception('Тип транзакции должен быть cancellation');
            }
            
            if (!$transaction->isMoneyOperation()) {
                throw new Exception('Отмена должна быть денежной операцией');
            }
            
            echo "✅ Создание транзакции отмены: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Создание транзакции отмены: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов модели BankTransaction...\n\n";
        
        $tests = [
            'testValidPaymentTransaction',
            'testValidRefundTransaction',
            'testDeclinedTransaction',
            'testInvalidTransactionType',
            'testNegativeAmount',
            'testCloseShiftOperation',
            'testTransactionToArray',
            'testCancellationTransaction'
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
    $test = new BankTransactionModelTest();
    $result = $test->run();
    echo $result ? "✅ BankTransactionModelTest PASSED\n" : "❌ BankTransactionModelTest FAILED\n";
    exit($result ? 0 : 1);
}