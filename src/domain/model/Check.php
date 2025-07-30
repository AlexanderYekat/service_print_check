<?php

class Check
{
    public array $tableData;   // [{name, quantity, price}, ...]
    public string $cashier;
    public array $payments;    // [{type, amount}, ...]
    public string $type;       // 'sell'|'return'
    public string $taxationSystem;

    public function __construct(
        array $tableData,
        string $cashier,
        array $payments,
        string $type,
        string $taxationSystem
    ) {
        $this->tableData = $tableData;
        $this->cashier = $cashier;
        $this->payments = $payments;
        $this->type = $type;
        $this->taxationSystem = $taxationSystem;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['tableData'],
            $data['cashier'],
            $data['payments'],
            $data['type'],
            $data['taxationSystem']
        );
    }
}
