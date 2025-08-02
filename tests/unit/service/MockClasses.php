<?php
/**
 * Набор Mock классов для тестирования UseCase'ов
 * 
 * Эти моки позволяют тестировать бизнес-логику без реальных адаптеров
 */

// Используем bootstrap для подключения всех зависимостей
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Infrastructure\Logger\LoggerInterface;

/**
 * Mock принтера для тестирования
 */
class MockPrinter implements PrinterInterface
{
    private bool $shouldSucceed;
    private array $printedChecks = [];
    
    public function __construct(bool $shouldSucceed = true)
    {
        $this->shouldSucceed = $shouldSucceed;
    }
    
    public function printCheck(Check $check, array $markCheckData = []): OperationResult
    {
        $this->printedChecks[] = [
            'check' => $check,
            'markCheckData' => $markCheckData,
            'timestamp' => time()
        ];
        
        if ($this->shouldSucceed) {
            return new OperationResult(true, ['receipt_number' => 'TEST-' . time()]);
        } else {
            return new OperationResult(false, [], 'Printer error: Paper jam');
        }
    }
    
    public function getLastPrintedCheck(): ?array
    {
        return empty($this->printedChecks) ? null : end($this->printedChecks);
    }
    
    public function getPrintedChecksCount(): int
    {
        return count($this->printedChecks);
    }
}

/**
 * Mock банковского терминала для тестирования
 */
class MockBankTerminal implements BankTerminalInterface
{
    private bool $shouldSucceed;
    private array $transactions = [];
    private float $minAmount;
    
    public function __construct(bool $shouldSucceed = true, float $minAmount = 1.0)
    {
        $this->shouldSucceed = $shouldSucceed;
        $this->minAmount = $minAmount;
    }
    
    public function pay(float $amount): OperationResult
    {
        $transaction = [
            'type' => 'payment',
            'amount' => $amount,
            'timestamp' => time()
        ];
        
        $this->transactions[] = $transaction;
        
        if (!$this->shouldSucceed) {
            return new OperationResult(false, [], 'Card declined');
        }
        
        if ($amount < $this->minAmount) {
            return new OperationResult(false, [], 'Amount too small');
        }
        
        return new OperationResult(true, [
            'transaction_id' => 'TX' . time(),
            'card_mask' => '1234****5678',
            'amount' => $amount,
            'status' => 'approved'
        ]);
    }
    
    public function refund(float $amount, string $originalTransactionId): OperationResult
    {
        $transaction = [
            'type' => 'refund',
            'amount' => $amount,
            'original_transaction_id' => $originalTransactionId,
            'timestamp' => time()
        ];
        
        $this->transactions[] = $transaction;
        
        if (!$this->shouldSucceed) {
            return new OperationResult(false, [], 'Refund failed');
        }
        
        return new OperationResult(true, [
            'transaction_id' => 'RF' . time(),
            'card_mask' => '1234****5678',
            'amount' => $amount,
            'status' => 'approved'
        ]);
    }
    
    public function cancel(string $transactionId): OperationResult
    {
        $transaction = [
            'type' => 'cancellation',
            'transaction_id' => $transactionId,
            'timestamp' => time()
        ];
        
        $this->transactions[] = $transaction;
        
        if (!$this->shouldSucceed) {
            return new OperationResult(false, [], 'Cancellation failed');
        }
        
        return new OperationResult(true, [
            'transaction_id' => 'CX' . time(),
            'status' => 'cancelled'
        ]);
    }
    
    public function getTransactions(): array
    {
        return $this->transactions;
    }
    
    public function getTransactionsCount(): int
    {
        return count($this->transactions);
    }
}

/**
 * Mock хранилища настроек для тестирования
 */
class MockSettingsStorage implements SettingsStorageInterface
{
    private array $settings;
    
    public function __construct(array $settings = [])
    {
        $this->settings = array_merge([
            'printer' => [
                'emulation' => true,
                'com_port' => 'COM1'
            ],
            'bank' => [
                'host' => 'localhost',
                'port' => 8080
            ]
        ], $settings);
    }
    
    public function load(): array
    {
        return $this->settings;
    }
    
    public function save(array $settings): bool
    {
        $this->settings = $settings;
        return true;
    }
    
    public function setSetting(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }
    
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}

/**
 * Mock логгера для тестирования
 */
class MockLogger implements LoggerInterface
{
    private array $logs = [];
    
    public function info(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }
    
    public function getLogs(): array
    {
        return $this->logs;
    }
    
    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->logs, fn($log) => $log['level'] === $level);
    }
    
    public function hasErrorLogs(): bool
    {
        return !empty($this->getLogsByLevel('error'));
    }
    
    public function clearLogs(): void
    {
        $this->logs = [];
    }
}