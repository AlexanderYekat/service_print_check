<?php

class BankTransaction
{
    public float $amount;
    public string $type; // 'pay', 'refund', 'cancel', etc.
    public ?int $resultCode;
    public ?string $chequeText;

    public function __construct(
        float $amount,
        string $type,
        ?int $resultCode = null,
        ?string $chequeText = null
    ) {
        $this->amount = $amount;
        $this->type = $type;
        $this->resultCode = $resultCode;
        $this->chequeText = $chequeText;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['amount'],
            $data['type'],
            $data['resultCode'] ?? null,
            $data['chequeText'] ?? null
        );
    }
}
