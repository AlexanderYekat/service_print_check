<?php

/**
 * Банковская транзакция - результат/факт выполненной операции
 * Создается ТОЛЬКО после получения ответа от банковского терминала
 * Все поля всегда известны - никаких null состояний!
 */
class BankTransaction
{
    private string $type;
    private float $amount;
    private bool $isSuccessful;
    private ?int $errorCode;
    private ?string $errorMessage;
    private ?string $slip;

    public function __construct(
        string $type,
        float $amount,
        bool $isSuccessful,
        ?int $errorCode = null,
        ?string $errorMessage = null,
        ?string $slip = null
    ) {
        $this->validateInputs($type, $amount, $isSuccessful, $errorCode, $errorMessage);
        
        $this->type = $type;
        $this->amount = $amount;
        $this->isSuccessful = $isSuccessful;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->slip = $slip;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSlip(): ?string
    {
        return $this->slip;
    }

    /**
     * Проверить, есть ли ошибка
     */
    public function hasError(): bool
    {
        return !$this->isSuccessful;
    }

    /**
     * Проверить, является ли операция денежной
     */
    public function isMoneyOperation(): bool
    {
        return in_array($this->type, ['payment', 'refund', 'cancellation']);
    }

    /**
     * Сериализация для API-слоя
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'amount' => $this->amount,
            'is_successful' => $this->isSuccessful,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'slip' => $this->slip
        ];
    }

    /**
     * Создать успешную транзакцию
     */
    public static function createSuccessful(string $type, float $amount, ?string $slip = null): self
    {
        return new self($type, $amount, true, null, null, $slip);
    }

    /**
     * Создать неуспешную транзакцию
     */
    public static function createFailed(string $type, float $amount, int $errorCode, string $errorMessage): self
    {
        return new self($type, $amount, false, $errorCode, $errorMessage, null);
    }

    /**
     * Валидация входных данных
     */
    private function validateInputs(string $type, float $amount, bool $isSuccessful, ?int $errorCode, ?string $errorMessage): void
    {
        $allowedTypes = ['payment', 'refund', 'cancellation', 'close_shift'];
        if (!in_array($type, $allowedTypes)) {
            throw new InvalidArgumentException("Недопустимый тип операции: {$type}");
        }

        // Для денежных операций сумма обязательна
        if (in_array($type, ['payment', 'refund', 'cancellation']) && $amount <= 0) {
            throw new InvalidArgumentException("Для операции {$type} сумма должна быть больше нуля");
        }

        // Если операция неуспешна, должен быть код ошибки
        if (!$isSuccessful && $errorCode === null) {
            throw new InvalidArgumentException('Для неуспешной операции обязателен код ошибки');
        }
    }
}
