<?php

/**
 * Элемент чека - строгая доменная модель
 * Содержит информацию об одной позиции в чеке
 */
class CheckItem
{
    private string $name;
    private float $price;
    private int $quantity;
    private float $sum;
    private ?MarkingCode $markingCode;

    public function __construct(
        string $name,
        float $price,
        int $quantity,
        float $sum,
        ?MarkingCode $markingCode = null
    ) {
        $this->validateInputs($name, $price, $quantity, $sum);
        
        $this->name = trim($name);
        $this->price = $price;
        $this->quantity = $quantity;
        $this->sum = $sum;
        $this->markingCode = $markingCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSum(): float
    {
        return $this->sum;
    }

    public function getMarkingCode(): ?MarkingCode
    {
        return $this->markingCode;
    }

    public function hasMarkingCode(): bool
    {
        return $this->markingCode !== null;
    }

    /**
     * Проверить корректность суммы позиции
     */
    public function isSumCorrect(): bool
    {
        $calculatedSum = round($this->price * $this->quantity, 2);
        return abs($calculatedSum - $this->sum) < 0.01; // Учитываем погрешность округления
    }

    /**
     * Вычислить корректную сумму на основе цены и количества
     */
    public function calculateCorrectSum(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    /**
     * Сериализация для API-слоя
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'sum' => $this->sum,
            'marking_code' => $this->markingCode ? $this->markingCode->getRawCode() : null
        ];
    }

    /**
     * Валидация входных данных
     */
    private function validateInputs(string $name, float $price, int $quantity, float $sum): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Наименование товара не может быть пустым');
        }

        if ($price <= 0) {
            throw new InvalidArgumentException('Цена должна быть больше нуля');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Количество должно быть больше нуля');
        }

        if ($sum <= 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля');
        }
    }
}