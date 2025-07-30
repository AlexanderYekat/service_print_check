<?php
// domain/service/SendToHonestSignUseCase.php
class SendToHonestSignUseCase {
    private HonestSignGateway $gateway;
    public function __construct(HonestSignGateway $gateway) {
        $this->gateway = $gateway;
    }
    public function validateMark(MarkingCode $code): HonestSignResult {
        return $this->gateway->validateMark($code);
    }
}
