class BankPaymentController {
    private ProcessBankPaymentUseCase $useCase;
    public function __construct(ProcessBankPaymentUseCase $useCase) {
        $this->useCase = $useCase;
    }
    public function handlePayRequest($request) {
        $amount = $request['amount'];
        $result = $this->useCase->execute($amount);
        // вернуть HTTP-ответ в формате API (success, message, slipLines и т.д.)
    }
}
