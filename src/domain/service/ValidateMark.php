<?php
// domain/service/ValidateMarkUseCase.php
class ValidateMarkUseCase {
    private HonestSignGateway $gateway;
    private HonestSignResult $result;
    public function __construct(HonestSignGateway $gateway, HonestSignResult $result) {
        $this->gateway = $gateway;
        $this->result = $result;
    }
    public function validateMark(MarkingCode $code): HonestSignResult {
        return $this->gateway->validateMark($code);
    }
}
