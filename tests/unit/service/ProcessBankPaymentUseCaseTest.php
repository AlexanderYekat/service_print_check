<?php
/**
 * Unit тест для ProcessBankPaymentUseCase
 * 
 * Тестирует бизнес-логику банковских операций с mock-интерфейсами:
 * - Успешные операции (pay, refund, cancel)
 * - Обработка ошибок терминала
 * - Валидация входных данных
 * - Логирование операций
 */

require_once __DIR__ . '/MockClasses.php';

// Импортируем класс с namespace
use App\Domain\Service\ProcessBankPaymentUseCase;

class ProcessBankPaymentUseCaseTest
{
    public function testSuccessfulPayment(): bool
    {
        try {
            // Arrange: создаем успешные моки
            $mockTerminal = new MockBankTerminal(true); // shouldSucceed = true
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: выполняем платеж
            $result = $useCase->pay(250.50);
            
            // Assert: проверяем результат
            if (!$result->isSuccess()) {
                throw new Exception('Платеж должен быть успешным');
            }
            
            $data = $result->getData();
            if (!isset($data['transaction_id']) || !isset($data['amount'])) {
                throw new Exception('Результат должен содержать transaction_id и amount');
            }
            
            if (abs($data['amount'] - 250.50) > 0.01) {
                throw new Exception('Сумма в результате должна быть 250.50');
            }
            
            // Проверяем, что операция была записана в терминал
            if ($mockTerminal->getTransactionsCount() !== 1) {
                throw new Exception('Должна быть записана 1 транзакция');
            }
            
            // Проверяем логирование
            $logs = $mockLogger->getLogs();
            if (count($logs) < 2) { // начало + успех
                throw new Exception('Должно быть минимум 2 лог записи');
            }
            
            echo "✅ Успешный платеж: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Успешный платеж: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testFailedPayment(): bool
    {
        try {
            // Arrange: создаем неуспешный мок терминала
            $mockTerminal = new MockBankTerminal(false); // shouldSucceed = false
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: пытаемся выполнить платеж
            $result = $useCase->pay(100.00);
            
            // Assert: проверяем, что операция провалилась
            if ($result->isSuccess()) {
                throw new Exception('Платеж должен быть неуспешным');
            }
            
            if ($result->getErrorMessage() !== 'Card declined') {
                throw new Exception('Сообщение об ошибке должно быть "Card declined"');
            }
            
            // Проверяем логирование ошибки
            if (!$mockLogger->hasErrorLogs()) {
                throw new Exception('Должны быть записи об ошибках в логе');
            }
            
            echo "✅ Неуспешный платеж: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Неуспешный платеж: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testInvalidAmount(): bool
    {
        try {
            // Arrange
            $mockTerminal = new MockBankTerminal(true);
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: пытаемся выполнить платеж с недопустимой суммой
            $result = $useCase->pay(-100.00);
            
            // Assert: проверяем, что валидация сработала
            if ($result->isSuccess()) {
                throw new Exception('Платеж с отрицательной суммой должен быть отклонен');
            }
            
            // Терминал не должен был быть вызван
            if ($mockTerminal->getTransactionsCount() > 0) {
                throw new Exception('Терминал не должен быть вызван при невалидных данных');
            }
            
            echo "✅ Валидация суммы: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Валидация суммы: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testSuccessfulRefund(): bool
    {
        try {
            // Arrange
            $mockTerminal = new MockBankTerminal(true);
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: выполняем возврат
            $result = $useCase->refund(150.00, 'TX123456789');
            
            // Assert
            if (!$result->isSuccess()) {
                throw new Exception('Возврат должен быть успешным');
            }
            
            $data = $result->getData();
            if (!isset($data['transaction_id'])) {
                throw new Exception('Результат должен содержать transaction_id');
            }
            
            // Проверяем, что это именно возврат в терминале
            $transactions = $mockTerminal->getTransactions();
            if (count($transactions) !== 1 || $transactions[0]['type'] !== 'refund') {
                throw new Exception('Должна быть записана 1 транзакция типа refund');
            }
            
            echo "✅ Успешный возврат: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Успешный возврат: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testSuccessfulCancellation(): bool
    {
        try {
            // Arrange
            $mockTerminal = new MockBankTerminal(true);
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: выполняем отмену
            $result = $useCase->cancel('TX123456789');
            
            // Assert
            if (!$result->isSuccess()) {
                throw new Exception('Отмена должна быть успешной');
            }
            
            $data = $result->getData();
            if (!isset($data['transaction_id'])) {
                throw new Exception('Результат должен содержать transaction_id');
            }
            
            // Проверяем, что это именно отмена в терминале
            $transactions = $mockTerminal->getTransactions();
            if (count($transactions) !== 1 || $transactions[0]['type'] !== 'cancellation') {
                throw new Exception('Должна быть записана 1 транзакция типа cancellation');
            }
            
            echo "✅ Успешная отмена: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Успешная отмена: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testMinimumAmountValidation(): bool
    {
        try {
            // Arrange: устанавливаем минимальную сумму 10.00
            $mockTerminal = new MockBankTerminal(true, 10.00);
            $mockLogger = new MockLogger();
            
            $useCase = new ProcessBankPaymentUseCase($mockTerminal, $mockLogger);
            
            // Act: пытаемся заплатить меньше минимума
            $result = $useCase->pay(5.00);
            
            // Assert
            if ($result->isSuccess()) {
                throw new Exception('Платеж ниже минимума должен быть отклонен');
            }
            
            if ($result->getErrorMessage() !== 'Amount too small') {
                throw new Exception('Сообщение об ошибке должно быть "Amount too small"');
            }
            
            echo "✅ Валидация минимальной суммы: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Валидация минимальной суммы: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов ProcessBankPaymentUseCase...\n\n";
        
        $tests = [
            'testSuccessfulPayment',
            'testFailedPayment',
            'testInvalidAmount',
            'testSuccessfulRefund',
            'testSuccessfulCancellation',
            'testMinimumAmountValidation'
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
    $test = new ProcessBankPaymentUseCaseTest();
    $result = $test->run();
    echo $result ? "✅ ProcessBankPaymentUseCaseTest PASSED\n" : "❌ ProcessBankPaymentUseCaseTest FAILED\n";
    exit($result ? 0 : 1);
}