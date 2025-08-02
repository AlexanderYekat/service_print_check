<?php

namespace App\Domain\Service;

use App\Interface\BankTerminalInterface;
use App\Domain\Model\BankTransaction;
use App\Domain\Model\OperationResult;
use App\Infrastructure\Logger\LoggerInterface;
use InvalidArgumentException;

/**
 * UseCase для обработки банковских платежей
 * 
 * Инкапсулирует бизнес-логику операций с банковским терминалом:
 * - валидация входных данных
 * - логирование операций
 * - обработка результатов
 * - создание доменных объектов
 */
class ProcessBankPaymentUseCase
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
     * Выполнить платеж через банковский терминал
     *
     * @param float $amount Сумма платежа
     * @return OperationResult Результат операции
     */
    public function pay(float $amount): OperationResult
    {
        $this->logger->info("Начало обработки платежа на сумму: {$amount}");
        
        try {
            // Валидация входных данных
            $this->validateAmount($amount);
            
            // Выполнение операции через терминал
            $result = $this->bankTerminal->pay($amount);
            
            if ($result->isSuccess()) {
                $this->logger->info("Платеж успешно обработан на сумму: {$amount}");
                return $this->createTransactionResult($result, 'payment', $amount);
            } else {
                $this->logger->error("Ошибка обработки платежа: " . $result->getErrorMessage());
                return $result;
            }
            
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Ошибка валидации платежа: " . $e->getMessage());
            return OperationResult::failure($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Неожиданная ошибка при обработке платежа: " . $e->getMessage());
            return OperationResult::failure("Внутренняя ошибка системы");
        }
    }
    
    /**
     * Выполнить возврат денежных средств
     *
     * @param float $amount Сумма возврата
     * @return OperationResult Результат операции
     */
    public function refund(float $amount): OperationResult
    {
        $this->logger->info("Начало обработки возврата на сумму: {$amount}");
        
        try {
            // Валидация входных данных
            $this->validateAmount($amount);
            
            // Выполнение операции через терминал
            $result = $this->bankTerminal->refund($amount);
            
            if ($result->isSuccess()) {
                $this->logger->info("Возврат успешно обработан на сумму: {$amount}");
                return $this->createTransactionResult($result, 'refund', $amount);
            } else {
                $this->logger->error("Ошибка обработки возврата: " . $result->getErrorMessage());
                return $result;
            }
            
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Ошибка валидации возврата: " . $e->getMessage());
            return OperationResult::failure($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Неожиданная ошибка при обработке возврата: " . $e->getMessage());
            return OperationResult::failure("Внутренняя ошибка системы");
        }
    }
    
    /**
     * Отменить операцию
     *
     * @param float $amount Сумма операции для отмены
     * @return OperationResult Результат операции
     */
    public function cancel(float $amount): OperationResult
    {
        $this->logger->info("Начало отмены операции на сумму: {$amount}");
        
        try {
            // Валидация входных данных
            $this->validateAmount($amount);
            
            // Выполнение операции через терминал
            $result = $this->bankTerminal->cancel($amount);
            
            if ($result->isSuccess()) {
                $this->logger->info("Операция успешно отменена на сумму: {$amount}");
                return $this->createTransactionResult($result, 'cancel', $amount);
            } else {
                $this->logger->error("Ошибка отмены операции: " . $result->getErrorMessage());
                return $result;
            }
            
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Ошибка валидации отмены: " . $e->getMessage());
            return OperationResult::failure($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Неожиданная ошибка при отмене операции: " . $e->getMessage());
            return OperationResult::failure("Внутренняя ошибка системы");
        }
    }
    
    /**
     * Закрыть смену банковского терминала
     *
     * @return OperationResult Результат операции
     */
    public function closeShift(): OperationResult
    {
        $this->logger->info("Начало закрытия смены банковского терминала");
        
        try {
            // Выполнение операции через терминал
            $result = $this->bankTerminal->closeShift();
            
            if ($result->isSuccess()) {
                $this->logger->info("Смена банковского терминала успешно закрыта");
                return $this->createShiftCloseResult($result);
            } else {
                $this->logger->error("Ошибка закрытия смены: " . $result->getErrorMessage());
                return $result;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Неожиданная ошибка при закрытии смены: " . $e->getMessage());
            return OperationResult::failure("Внутренняя ошибка системы");
        }
    }
    
    /**
     * Валидация суммы операции
     *
     * @param float $amount Сумма для валидации
     * @throws InvalidArgumentException При некорректной сумме
     */
    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Сумма должна быть положительной");
        }
        
        if ($amount > 999999.99) {
            throw new InvalidArgumentException("Сумма слишком большая");
        }
        
        // Проверяем количество знаков после запятой (не более 2)
        if (round($amount, 2) !== $amount) {
            throw new InvalidArgumentException("Сумма должна содержать не более 2 знаков после запятой");
        }
    }
    
    /**
     * Создать результат банковской транзакции
     *
     * @param OperationResult $terminalResult Результат от терминала
     * @param string $operationType Тип операции
     * @param float $amount Сумма операции
     * @return OperationResult
     */
    private function createTransactionResult(
        OperationResult $terminalResult, 
        string $operationType, 
        float $amount
    ): OperationResult {
        $slip = $terminalResult->getData()['slip'] ?? null;
        $errorCode = $terminalResult->getData()['error_code'] ?? null;
        $errorMessage = $terminalResult->getData()['error_message'] ?? null;
        
        $transaction = new BankTransaction(
            $operationType,
            $amount,
            $terminalResult->isSuccess(),  // ✅ Используем правильный метод
            $errorCode,
            $errorMessage,
            $slip
        );
        
        return OperationResult::success([
            'transaction' => $transaction,
            'slip' => $slip
        ]);
    }
    
    /**
     * Создать результат закрытия смены
     *
     * @param OperationResult $terminalResult Результат от терминала
     * @return OperationResult
     */
    private function createShiftCloseResult(OperationResult $terminalResult): OperationResult
    {
        return OperationResult::success([
            'operation' => 'shift_close',
            'slip' => $terminalResult->getData(),
            'timestamp' => time()
        ]);
    }
}