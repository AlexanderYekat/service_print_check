<?php

/**
 * Улучшенная модель банковской транзакции
 * Поддерживает "незавершенное" состояние и эволюцию
 */
class ImprovedBankTransaction
{
    private string $type;
    private float $amount;
    private \DateTime $createdAt;

    // Результат операции - заполняется после выполнения
    private ?bool $isSuccessful = null;
    private ?int $errorCode = null;
    private ?string $errorMessage = null;
    private ?string $slip = null;
    private ?\DateTime $completedAt = null;

    public function __construct(string $type, float $amount)
    {
        // Проверяем только базовые параметры
        $this->assertValidType($type);
        $this->assertValidAmount($amount);

        $this->type = $type;
        $this->amount = $amount;
        $this->createdAt = new \DateTime();
    }

    // Основные геттеры
    public function getType(): string { return $this->type; }
    public function getAmount(): float { return $this->amount; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    // Состояние операции
    public function isCompleted(): bool { return $this->isSuccessful !== null; }
    public function isSuccessful(): ?bool { return $this->isSuccessful; }
    public function isPending(): bool { return !$this->isCompleted(); }

    // Результат операции
    public function getErrorCode(): ?int { return $this->errorCode; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getSlip(): ?string { return $this->slip; }
    public function getCompletedAt(): ?\DateTime { return $this->completedAt; }

    /**
     * Установить результат успешной операции
     */
    public function setSuccessResult(?string $slip = null): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('Транзакция уже завершена');
        }

        $this->isSuccessful = true;
        $this->errorCode = null;
        $this->errorMessage = null;
        $this->slip = $slip;
        $this->completedAt = new \DateTime();
    }

    /**
     * Установить результат неуспешной операции
     */
    public function setFailureResult(int $errorCode, string $errorMessage): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('Транзакция уже завершена');
        }

        $this->isSuccessful = false;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->slip = null;
        $this->completedAt = new \DateTime();
    }

    /**
     * Бизнес-методы
     */
    public function hasError(): bool
    {
        return $this->isCompleted() && !$this->isSuccessful;
    }

    public function isMoneyOperation(): bool
    {
        return in_array($this->type, ['payment', 'refund', 'cancellation']);
    }

    /**
     * Получить длительность операции (если завершена)
     */
    public function getDuration(): ?\DateInterval
    {
        if (!$this->isCompleted()) {
            return null;
        }

        return $this->createdAt->diff($this->completedAt);
    }

    /**
     * Сериализация с учетом состояния
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'amount' => $this->amount,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'is_completed' => $this->isCompleted(),
            'is_pending' => $this->isPending(),
        ];

        if ($this->isCompleted()) {
            $result['is_successful'] = $this->isSuccessful;
            $result['completed_at'] = $this->completedAt->format('Y-m-d H:i:s');
            $result['duration_seconds'] = $this->getDuration()->s;

            if ($this->hasError()) {
                $result['error_code'] = $this->errorCode;
                $result['error_message'] = $this->errorMessage;
            } else {
                $result['slip'] = $this->slip;
            }
        }

        return $result;
    }

    // Factory методы
    public static function createPayment(float $amount): self
    {
        return new self('payment', $amount);
    }

    public static function createRefund(float $amount): self
    {
        return new self('refund', $amount);
    }

    public static function createCancellation(float $amount): self
    {
        return new self('cancellation', $amount);
    }

    public static function createShiftClose(): self
    {
        return new self('close_shift', 0);
    }

    // Валидация
    private function assertValidType(string $type): void
    {
        $allowed = ['payment', 'refund', 'cancellation', 'close_shift'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException("Недопустимый тип операции: $type");
        }
    }

    private function assertValidAmount(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Сумма не может быть отрицательной");
        }

        // Для денежных операций сумма должна быть больше 0
        if (in_array($this->type ?? '', ['payment', 'refund', 'cancellation']) && $amount <= 0) {
            throw new \InvalidArgumentException("Для денежных операций сумма должна быть больше 0");
        }
    }
}

// ========================================
// ПРИМЕР ИСПОЛЬЗОВАНИЯ
// ========================================

echo "🏦 Пример использования улучшенной модели банковской транзакции:\n\n";

// 1. Создаем транзакцию (пока не выполнена)
$transaction = ImprovedBankTransaction::createPayment(1500.00);
echo "📝 Создана транзакция: {$transaction->getType()}, сумма: {$transaction->getAmount()}\n";
echo "⏳ Статус: " . ($transaction->isPending() ? "Ожидает выполнения" : "Завершена") . "\n\n";

// 2. Имитируем обращение к банковскому терминалу
echo "🔄 Отправляем в банковский терминал...\n";
sleep(1); // Имитация задержки

// 3. Получили успешный результат
$transaction->setSuccessResult("SLIP: Bank approved, auth code: 123456");
echo "✅ Операция завершена успешно!\n";
echo "📄 Слип: {$transaction->getSlip()}\n";
echo "⏱️ Длительность: {$transaction->getDuration()->s} секунд\n\n";

// 4. Пример неуспешной транзакции
$failedTransaction = ImprovedBankTransaction::createPayment(2000.00);
$failedTransaction->setFailureResult(101, "Карта заблокирована банком");
echo "❌ Неуспешная транзакция:\n";
echo "   Код ошибки: {$failedTransaction->getErrorCode()}\n";
echo "   Сообщение: {$failedTransaction->getErrorMessage()}\n\n";

// 5. Сериализация
echo "📊 Полная информация о транзакции:\n";
print_r($transaction->toArray());