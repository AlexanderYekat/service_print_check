class FakeBankTerminal implements BankTerminalInterface {
    public function pay(float $amount): BankResult {
        return new BankResult(true, "Мок-успех", ["Строка чека"], 0);
    }
    public function refund(float $amount): BankResult { ... }
    public function closeShift(): BankResult { ... }
}

// PHPUnit:
public function testSuccessfulPayment() {
    $mock = new FakeBankTerminal();
    $useCase = new ProcessBankPaymentUseCase($mock);
    $result = $useCase->execute(100.0);
    $this->assertTrue($result->success);
    $this->assertEquals("Мок-успех", $result->message);
}
