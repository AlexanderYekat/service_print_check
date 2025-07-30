<?php

class BankResult
{
    public bool $success;
    public ?string $message;
    public ?array $slipLines;
    public ?int $resultCode;

    public function __construct(
        bool $success,
        ?string $message = null,
        ?array $slipLines = null,
        ?int $resultCode = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->slipLines = $slipLines;
        $this->resultCode = $resultCode;
    }
}
