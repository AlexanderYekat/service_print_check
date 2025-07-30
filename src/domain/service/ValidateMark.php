<?php
// domain/service/ValidateMarkUseCase.php
class ValidateMarkUseCase {
    private ValidateMarkGateway $gateway;
    private HonestSignResult $result;
    public function __construct(ValidateMarkGateway $gateway) {
        $this->gateway = $gateway;
        $this->result = $result;
    }
    public function validateMark(MarkingCode $code): HonestSignResult {
        return $this->gateway->validateMark($code);
    }
}
