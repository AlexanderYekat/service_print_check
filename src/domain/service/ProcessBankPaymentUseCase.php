<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../../interface/BankTerminalInterface.php';

// domain/service/ProcessBankPaymentUseCase.php
class ProcessBankPaymentUseCase {
    private BankTerminalInterface $terminal;
    
    public function __construct(BankTerminalInterface $terminal) {
        $this->terminal = $terminal;
    }
    
    public function pay(float $amount): OperationResult {
        // 1. Валидация суммы
        if ($amount <= 0) {
            return OperationResult::failure('Сумма должна быть положительной');
        }
        
        // 2. Операция через банковский терминал
        $bankResult = $this->terminal->pay($amount);
        
        // 3. Преобразование в доменный результат
        return $this->convertBankResultToOperationResult($bankResult, 'Операция оплаты');
    }
    
    public function refund(float $amount): OperationResult {
        if ($amount <= 0) {
            return OperationResult::failure('Сумма должна быть положительной');
        }
        
        $bankResult = $this->terminal->refund($amount);
        return $this->convertBankResultToOperationResult($bankResult, 'Операция возврата');
    }
    
    public function closeShift(): OperationResult {
        $bankResult = $this->terminal->closeShift();
        return $this->convertBankResultToOperationResult($bankResult, 'Закрытие смены');
    }
    
    /**
     * Преобразует результат банковского терминала в доменный OperationResult
     */
    private function convertBankResultToOperationResult($bankResult, string $operationType): OperationResult {
        if ($bankResult->success) {
            return OperationResult::success(
                [
                    'slip' => $bankResult->slipLines ?? [],        // Слип — доменная логика банковской операции
                    'result_code' => $bankResult->resultCode       // Код результата банка
                ],
                $bankResult->message ?? "$operationType успешно завершена"
            );
        } else {
            return OperationResult::failure(
                $bankResult->message ?? "$operationType завершена с ошибкой",
                [
                    'result_code' => $bankResult->resultCode
                ]
            );
        }
    }
}