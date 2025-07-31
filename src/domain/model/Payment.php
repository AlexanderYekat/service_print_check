<?php

/**
 * Платеж в чеке - строгая доменная модель
 * Содержит информацию об одном виде оплаты
 */
class Payment
{
    private string $type;
    private float $amount;

    // Допустимые типы платежей
    public const TYPE_CASH = 'cash';
    public const TYPE_CARD = 'card';
    public const TYPE_OTHER = 'other';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_ADVANCE = 'advance';

    private const ALLOWED_TYPES = [
        self::TYPE_CASH,
        self::TYPE_CARD,
        self::TYPE_OTHER,
        self::TYPE_CREDIT,
        self::TYPE_ADVANCE
    ];

    public function __construct(string $type, float $amount)
    {
        $this->validateInputs($type, $amount);
        
        $this->type = $type;
        $this->amount = $amount;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    public function isCard(): bool
    {
        return $this->type === self::TYPE_CARD;
    }

    public function isElectronic(): bool
    {
        return in_array($this->type, [self::TYPE_CARD, self::TYPE_OTHER]);
    }

    /**
     * Сериализация для API-слоя
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'amount' => $this->amount
        ];
    }

    /**
     * Статический конструктор для наличных
     */
    public static function cash(float $amount): self
    {
        return new self(self::TYPE_CASH, $amount);
    }

    /**
     * Статический конструктор для карты
     */
    public static function card(float $amount): self
    {
        return new self(self::TYPE_CARD, $amount);
    }

    /**
     * Получить список допустимых типов платежей
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    /**
     * Валидация входных данных
     */
    private function validateInputs(string $type, float $amount): void
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new InvalidArgumentException(
                sprintf('Недопустимый тип платежа: %s. Разрешены: %s', 
                    $type, 
                    implode(', ', self::ALLOWED_TYPES)
                )
            );
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма платежа должна быть больше нуля');
        }
    }
}