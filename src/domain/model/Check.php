<?php

/**
 * Доменная модель чека - строгая типизированная структура
 * Соответствует принципам чистой архитектуры
 */
class Check
{
    /** @var CheckItem[] */
    private array $items;
    
    /** @var Payment[] */
    private array $payments;
    
    private string $cashier;
    private string $type;
    private string $taxationSystem;

    // Допустимые типы чеков
    public const TYPE_SELL = 'sell';
    public const TYPE_REFUND = 'refund';
    public const TYPE_RETURN = 'return';
    public const TYPE_CORRECTION = 'correction';

    private const ALLOWED_TYPES = [
        self::TYPE_SELL,
        self::TYPE_REFUND,
        self::TYPE_RETURN,
        self::TYPE_CORRECTION
    ];

    /**
     * @param CheckItem[] $items
     * @param Payment[] $payments
     */
    public function __construct(
        array $items,
        array $payments,
        string $cashier,
        string $type,
        string $taxationSystem
    ) {
        $this->validateInputs($items, $payments, $cashier, $type);
        
        $this->items = $items;
        $this->payments = $payments;
        $this->cashier = trim($cashier);
        $this->type = $type;
        $this->taxationSystem = $taxationSystem;
    }

    /**
     * @return CheckItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function getCashier(): string
    {
        return $this->cashier;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTaxationSystem(): string
    {
        return $this->taxationSystem;
    }

    /**
     * Вычислить общую сумму всех позиций в чеке
     */
    public function getTotalAmount(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getSum();
        }
        return round($total, 2);
    }

    /**
     * Вычислить общую сумму всех платежей
     */
    public function getTotalPayments(): float
    {
        $total = 0.0;
        foreach ($this->payments as $payment) {
            $total += $payment->getAmount();
        }
        return round($total, 2);
    }

    /**
     * Проверить, что сумма платежей соответствует сумме товаров
     */
    public function isPaymentBalanced(): bool
    {
        return abs($this->getTotalAmount() - $this->getTotalPayments()) < 0.01;
    }

    /**
     * Получить количество позиций в чеке
     */
    public function getItemsCount(): int
    {
        return count($this->items);
    }

    /**
     * Получить позиции с маркировкой
     * @return CheckItem[]
     */
    public function getMarkedItems(): array
    {
        return array_filter($this->items, fn(CheckItem $item) => $item->hasMarkingCode());
    }

    /**
     * Проверить все ли суммы позиций корректны
     */
    public function areAllItemSumsCorrect(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isSumCorrect()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Получить платежи определенного типа
     * @return Payment[]
     */
    public function getPaymentsByType(string $type): array
    {
        return array_filter($this->payments, fn(Payment $payment) => $payment->getType() === $type);
    }

    /**
     * Получить сумму наличных платежей
     */
    public function getCashAmount(): float
    {
        $cashPayments = array_filter($this->payments, fn(Payment $payment) => $payment->isCash());
        return round(array_sum(array_map(fn(Payment $payment) => $payment->getAmount(), $cashPayments)), 2);
    }

    /**
     * Получить сумму безналичных платежей
     */
    public function getElectronicAmount(): float
    {
        $electronicPayments = array_filter($this->payments, fn(Payment $payment) => $payment->isElectronic());
        return round(array_sum(array_map(fn(Payment $payment) => $payment->getAmount(), $electronicPayments)), 2);
    }

    /**
     * Проверить тип чека
     */
    public function isSell(): bool
    {
        return $this->type === self::TYPE_SELL;
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isReturn(): bool
    {
        return $this->type === self::TYPE_RETURN;
    }

    /**
     * Сериализация для API-слоя (DTO)
     */
    public function toArray(): array
    {
        return [
            'tableData' => array_map(fn(CheckItem $item) => $item->toArray(), $this->items),
            'payments' => array_map(fn(Payment $payment) => $payment->toArray(), $this->payments),
            'cashier' => $this->cashier,
            'type' => $this->type,
            'taxationSystem' => $this->taxationSystem,
            'totalAmount' => $this->getTotalAmount(),
            'totalPayments' => $this->getTotalPayments(),
            'itemsCount' => $this->getItemsCount()
        ];
    }

    /**
     * Создание из массива (для обратной совместимости с API)
     */
    public static function fromArray(array $data): self
    {
        // Конвертация tableData в массив CheckItem
        $items = [];
        foreach ($data['tableData'] as $row) {
            $markingCode = null;
            if (isset($row['marking_code']) && !empty($row['marking_code'])) {
                $markingCode = new MarkingCode($row['marking_code']);
            }

            $items[] = new CheckItem(
                $row['name'],
                (float)$row['price'],
                (int)$row['quantity'],
                (float)$row['sum'],
                $markingCode
            );
        }

        // Конвертация payments в массив Payment
        $payments = [];
        foreach ($data['payments'] as $pay) {
            $payments[] = new Payment($pay['type'], (float)$pay['amount']);
        }

        return new self(
            $items,
            $payments,
            $data['cashier'],
            $data['type'],
            $data['taxationSystem']
        );
    }

    /**
     * Получить список допустимых типов чеков
     */
    public static function getAllowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    /**
     * Валидация входных данных
     */
    private function validateInputs(array $items, array $payments, string $cashier, string $type): void
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Чек должен содержать хотя бы одну позицию');
        }

        if (empty($payments)) {
            throw new InvalidArgumentException('Чек должен содержать хотя бы один платеж');
        }

        foreach ($items as $item) {
            if (!$item instanceof CheckItem) {
                throw new InvalidArgumentException('Все элементы items должны быть экземплярами CheckItem');
            }
        }

        foreach ($payments as $payment) {
            if (!$payment instanceof Payment) {
                throw new InvalidArgumentException('Все элементы payments должны быть экземплярами Payment');
            }
        }

        if (empty(trim($cashier))) {
            throw new InvalidArgumentException('Кассир не может быть пустым');
        }

        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new InvalidArgumentException(
                sprintf('Недопустимый тип чека: %s. Разрешены: %s', 
                    $type, 
                    implode(', ', self::ALLOWED_TYPES)
                )
            );
        }
    }
}
