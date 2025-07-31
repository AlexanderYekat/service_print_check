<?php

namespace App\Domain\Model;

/**
 * Универсальный результат операции с ККТ
 */
class OperationResult
{
    public bool $success;
    public ?string $message;
    public ?array $data;
    public ?string $error;

    public function __construct(bool $success, ?string $message = null, ?array $data = null, ?string $error = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->error = $error;
    }

    /**
     * Создает успешный результат
     */
    public static function success(?array $data = null, ?string $message = null): self
    {
        return new self(true, $message, $data);
    }

    /**
     * Создает результат с ошибкой
     */
    public static function failure(string $error, ?array $data = null): self
    {
        return new self(false, null, $data, $error);
    }

    /**
     * Получает данные определенного типа или null
     */
    public function getData(string $key = null)
    {
        if ($key === null) {
            return $this->data;
        }
        
        return $this->data[$key] ?? null;
    }

    /**
     * Проверяет наличие данных
     */
    public function hasData(string $key = null): bool
    {
        if ($key === null) {
            return !empty($this->data);
        }
        
        return isset($this->data[$key]);
    }

    /**
     * Проверяет успешность операции
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Получает сообщение об ошибке
     */
    public function getErrorMessage(): ?string
    {
        return $this->error;
    }
}