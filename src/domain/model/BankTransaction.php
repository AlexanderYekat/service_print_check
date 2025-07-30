<?php
class BankTransaction {
    public float $amount;
    public string $type; // 'pay', 'refund', etc
    public \DateTime $date;
    public ?string $resultCode;
    public ?string $chequeText;
    // ...и другие нужные поля
    public function __construct($amount, $type) { ... }
}