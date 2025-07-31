<?php

require_once 'improved_bank_transaction_example.php';

/**
 * Улучшенный UseCase для банковских операций
 * Демонстрирует правильное разделение ответственности
 */
class ImprovedProcessBankPaymentUseCase
{
    private BankTerminalInterface $bankTerminal;
    private LoggerInterface $logger;
    
    public function __construct(
        BankTerminalInterface $bankTerminal,
        LoggerInterface $logger
    ) {
        $this->bankTerminal = $bankTerminal;
        $this->logger = $logger;
    }
    
    /**
     * Выполнить платеж
     * Возвращает доменную модель, а не технический OperationResult
     */
    public function pay(float $amount): ImprovedBankTransaction
    {
        $this->logger->info("Начало обработки платежа на сумму: {$amount}");
        
        // 1. Создаем доменную модель в "ожидающем" состоянии
        $transaction = ImprovedBankTransaction::createPayment($amount);
        
        try {
            // 2. Выполняем техническую операцию через инфраструктуру
            $terminalResult = $this->bankTerminal->pay($amount);
            
            // 3. Обновляем доменную модель результатом
            if ($terminalResult->isSuccess()) {
                $slip = $terminalResult->getData()['slip'] ?? null;
                $transaction->setSuccessResult($slip);
                
                $this->logger->info("Платеж успешно обработан: {$amount}");
            } else {
                $errorCode = $terminalResult->getData()['error_code'] ?? 999;
                $errorMessage = $terminalResult->getErrorMessage() ?? 'Неизвестная ошибка';
                $transaction->setFailureResult($errorCode, $errorMessage);
                
                $this->logger->error("Ошибка платежа: {$errorMessage}");
            }
            
        } catch (\Exception $e) {
            // Обрабатываем технические ошибки
            $transaction->setFailureResult(500, "Техническая ошибка: " . $e->getMessage());
            $this->logger->error("Техническая ошибка при платеже: " . $e->getMessage());
        }
        
        // 4. Возвращаем доменную модель (всегда!)
        return $transaction;
    }
    
    /**
     * Возврат денежных средств
     */
    public function refund(float $amount): ImprovedBankTransaction
    {
        $this->logger->info("Начало обработки возврата на сумму: {$amount}");
        
        $transaction = ImprovedBankTransaction::createRefund($amount);
        
        try {
            $terminalResult = $this->bankTerminal->refund($amount);
            
            if ($terminalResult->isSuccess()) {
                $slip = $terminalResult->getData()['slip'] ?? null;
                $transaction->setSuccessResult($slip);
                $this->logger->info("Возврат успешно обработан: {$amount}");
            } else {
                $errorCode = $terminalResult->getData()['error_code'] ?? 999;
                $errorMessage = $terminalResult->getErrorMessage() ?? 'Неизвестная ошибка';
                $transaction->setFailureResult($errorCode, $errorMessage);
                $this->logger->error("Ошибка возврата: {$errorMessage}");
            }
            
        } catch (\Exception $e) {
            $transaction->setFailureResult(500, "Техническая ошибка: " . $e->getMessage());
            $this->logger->error("Техническая ошибка при возврате: " . $e->getMessage());
        }
        
        return $transaction;
    }
    
    /**
     * Закрытие смены
     */
    public function closeShift(): ImprovedBankTransaction
    {
        $this->logger->info("Начало закрытия смены банковского терминала");
        
        $transaction = ImprovedBankTransaction::createShiftClose();
        
        try {
            $terminalResult = $this->bankTerminal->closeShift();
            
            if ($terminalResult->isSuccess()) {
                $slip = $terminalResult->getData()['slip'] ?? null;
                $transaction->setSuccessResult($slip);
                $this->logger->info("Смена успешно закрыта");
            } else {
                $errorCode = $terminalResult->getData()['error_code'] ?? 999;
                $errorMessage = $terminalResult->getErrorMessage() ?? 'Ошибка закрытия смены';
                $transaction->setFailureResult($errorCode, $errorMessage);
                $this->logger->error("Ошибка закрытия смены: {$errorMessage}");
            }
            
        } catch (\Exception $e) {
            $transaction->setFailureResult(500, "Техническая ошибка: " . $e->getMessage());
            $this->logger->error("Техническая ошибка при закрытии смены: " . $e->getMessage());
        }
        
        return $transaction;
    }
}

// ========================================
// ПРИМЕР ИСПОЛЬЗОВАНИЯ В КОНТРОЛЛЕРЕ
// ========================================

/**
 * Контроллер API - только маршрутизация и форматирование ответов
 */
class ImprovedBankPaymentController
{
    private ImprovedProcessBankPaymentUseCase $useCase;
    
    public function __construct(ImprovedProcessBankPaymentUseCase $useCase)
    {
        $this->useCase = $useCase;
    }
    
    /**
     * POST /api/bank/pay
     */
    public function pay(): array
    {
        $amount = floatval($_POST['amount'] ?? 0);
        
        // Базовая валидация запроса
        if ($amount <= 0) {
            return [
                'success' => false,
                'error' => 'Некорректная сумма',
                'code' => 400
            ];
        }
        
        // Вызываем use-case
        $transaction = $this->useCase->pay($amount);
        
        // Форматируем ответ
        return $this->formatResponse($transaction);
    }
    
    /**
     * Форматирование ответа API на основе доменной модели
     */
    private function formatResponse(ImprovedBankTransaction $transaction): array
    {
        $response = [
            'transaction_id' => uniqid(), // В реальности - из базы
            'type' => $transaction->getType(),
            'amount' => $transaction->getAmount(),
            'created_at' => $transaction->getCreatedAt()->format('c'),
            'status' => $transaction->isCompleted() ? 'completed' : 'pending',
        ];
        
        if ($transaction->isCompleted()) {
            $response['success'] = $transaction->isSuccessful();
            $response['completed_at'] = $transaction->getCompletedAt()->format('c');
            $response['duration_ms'] = $transaction->getDuration()->s * 1000;
            
            if ($transaction->hasError()) {
                $response['error_code'] = $transaction->getErrorCode();
                $response['error_message'] = $transaction->getErrorMessage();
            } else {
                $response['slip'] = $transaction->getSlip();
            }
        }
        
        return $response;
    }
}

echo "\n💰 Пример работы улучшенного UseCase:\n\n";

// Имитация интерфейсов
interface BankTerminalInterface {}
interface LoggerInterface {
    public function info(string $message): void;
    public function error(string $message): void;
}

class MockBankTerminal implements BankTerminalInterface {
    public function pay(float $amount): object {
        return (object)[
            'success' => true,
            'data' => ['slip' => "Payment $amount approved"]
        ];
    }
}

class MockLogger implements LoggerInterface {
    public function info(string $message): void { echo "ℹ️  $message\n"; }
    public function error(string $message): void { echo "❌ $message\n"; }
}

// Пример использования
$useCase = new ImprovedProcessBankPaymentUseCase(new MockBankTerminal(), new MockLogger());
$transaction = $useCase->pay(1000.00);

echo "\n📊 Результат операции:\n";
print_r($transaction->toArray());