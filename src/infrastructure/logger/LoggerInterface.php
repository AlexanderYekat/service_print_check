<?php

namespace App\Infrastructure\Logger;

/**
 * Интерфейс для логгеров в системе
 * Обеспечивает единообразное логирование во всех компонентах
 */
interface LoggerInterface
{
    /**
     * Логирование отладочной информации
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Логирование информационных сообщений
     */
    public function info(string $message, array $context = []): void;

    /**
     * Логирование предупреждений
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Логирование ошибок
     */
    public function error(string $message, array $context = []): void;

    /**
     * Логирование критических ошибок
     */
    public function critical(string $message, array $context = []): void;
}