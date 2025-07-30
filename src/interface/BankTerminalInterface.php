<?php
interface BankTerminalInterface {
    public function pay(float $amount): BankResult;
    public function refund(float $amount): BankResult;
    public function closeShift(): BankResult;
}
