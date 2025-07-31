<?php

namespace App\Domain\Model;

/**
 * Доменная модель банковской транзакции
 * 
 * Представляет операцию с банковским терминалом:
 * - тип операции (оплата, возврат, отмена)
 * - сумма операции
 * - результат выполнения
 * - дополнительные данные (слип, коды ошибок)
 */
class BankTransaction
{
    private string $type;
    private float $amount;
    private array $resultData;
    private \DateTime $timestamp;

    public function __construct(
        string $type,
        float $amount,
        array $resultData = []
    ) {
        $this->type = $type;
        $this->amount = $amount;
        $this->resultData = $resultData;
        $this->timestamp = new \DateTime();
    }

    /**
     * Получить тип операции
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Получить сумму операции
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Получить данные результата
     */
    public function getResultData(): array
    {
        return $this->resultData;
    }

    /**
     * Получить временную метку операции
     */
    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }

    /**
     * Получить слип операции, если есть
     */
    public function getSlip(): ?array
    {
        return $this->resultData['slip'] ?? null;
    }

    /**
     * Получить код результата операции
     */
    public function getResultCode(): ?int
    {
        return $this->resultData['result_code'] ?? null;
    }

    /**
     * Проверить, является ли операция денежной
     */
    public function isMoneyOperation(): bool
    {
        return in_array($this->type, ['pay', 'refund', 'cancel']);
    }

    /**
     * Создать транзакцию из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'] ?? $data['operation'] ?? 'unknown',
            (float)($data['amount'] ?? 0),
            $data['resultData'] ?? $data['result'] ?? []
        );
    }

    /**
     * Преобразовать транзакцию в массив
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'amount' => $this->amount,
            'result_data' => $this->resultData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Получить описание операции
     */
    public function getDescription(): string
    {
        $descriptions = [
            'pay' => 'Оплата',
            'refund' => 'Возврат',
            'cancel' => 'Отмена',
            'close_shift' => 'Закрытие смены'
        ];

        $baseDescription = $descriptions[$this->type] ?? 'Неизвестная операция';
        
        if ($this->isMoneyOperation()) {
            return $baseDescription . ' на сумму ' . number_format($this->amount, 2, '.', ' ') . ' руб.';
        }
        
        return $baseDescription;
    }
}
