class BankResult {
    public bool $success;
    public ?string $message;
    public ?array $slipLines; // для чека, если есть
    public ?int $resultCode;
    // ...
    public function __construct(bool $success, ?string $message, ?array $slipLines = null, ?int $resultCode = null) { ... }
}