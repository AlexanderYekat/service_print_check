<?php

/**
 * Универсальный валидатор запросов
 * Обеспечивает единообразную валидацию входящих данных
 */
class RequestValidator
{
    /**
     * Валидация обязательных полей
     */
    public static function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Отсутствует обязательное поле: {$field}");
            }
        }
    }

    /**
     * Валидация числовых значений
     */
    public static function validateNumeric(array $data, string $field, float $min = null, float $max = null): float
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Поле {$field} отсутствует");
        }

        $value = filter_var($data[$field], FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            throw new ValidationException("Поле {$field} должно быть числом");
        }

        if ($min !== null && $value < $min) {
            throw new ValidationException("Поле {$field} должно быть больше или равно {$min}");
        }

        if ($max !== null && $value > $max) {
            throw new ValidationException("Поле {$field} должно быть меньше или равно {$max}");
        }

        return $value;
    }

    /**
     * Валидация строковых значений
     */
    public static function validateString(array $data, string $field, int $minLength = 0, int $maxLength = null): string
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Поле {$field} отсутствует");
        }

        $value = trim((string)$data[$field]);
        
        if (strlen($value) < $minLength) {
            throw new ValidationException("Поле {$field} должно содержать минимум {$minLength} символов");
        }

        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new ValidationException("Поле {$field} должно содержать максимум {$maxLength} символов");
        }

        return $value;
    }

    /**
     * Валидация значений из списка
     */
    public static function validateEnum(array $data, string $field, array $allowedValues): string
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Поле {$field} отсутствует");
        }

        $value = $data[$field];
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            throw new ValidationException("Поле {$field} должно быть одним из: {$allowed}");
        }

        return $value;
    }

    /**
     * Валидация массивов
     */
    public static function validateArray(array $data, string $field, bool $allowEmpty = false): array
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Поле {$field} отсутствует");
        }

        if (!is_array($data[$field])) {
            throw new ValidationException("Поле {$field} должно быть массивом");
        }

        if (!$allowEmpty && empty($data[$field])) {
            throw new ValidationException("Поле {$field} не должно быть пустым массивом");
        }

        return $data[$field];
    }

    /**
     * Валидация email
     */
    public static function validateEmail(array $data, string $field): string
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Поле {$field} отсутствует");
        }

        $email = filter_var($data[$field], FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new ValidationException("Поле {$field} должно содержать корректный email адрес");
        }

        return $email;
    }
}