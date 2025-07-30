<?php
// domain/service/ProcessBankPaymentUseCase.php
class ProcessBankPaymentUseCase {
    private BankTerminalInterface $terminal;
    public function __construct(BankTerminalInterface $terminal) {
        $this->terminal = $terminal;
    }
    public function pay(float $amount): BankResult {
        // 1. Валидация суммы
        if ($amount <= 0) {
            return new BankResult(false, 'Сумма должна быть положительной');
        }
        // 2. Операция
        return $this->terminal->pay($amount);
    }
    public function refund(float $amount): BankResult {
        if ($amount <= 0) {
            return new BankResult(false, 'Сумма должна быть положительной');
        }
        return $this->terminal->refund($amount);
    }
    public function closeShift(): BankResult {
        return $this->terminal->closeShift();
    }
}