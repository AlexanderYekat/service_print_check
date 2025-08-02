<?php

namespace App\Infrastructure\Logger;

// LoggerInterface уже подключен в bootstrap.php

/**
 * Null-объект логгера для тестирования
 * Не выводит никаких сообщений, предотвращая проблемы с HTTP заголовками в тестах
 */
class NullLogger implements LoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function debug(string $message, array $context = []): void
    {
        // Ничего не делаем
    }

    /**
     * {@inheritdoc}
     */
    public function info(string $message, array $context = []): void
    {
        // Ничего не делаем
    }

    /**
     * {@inheritdoc}
     */
    public function warning(string $message, array $context = []): void
    {
        // Ничего не делаем
    }

    /**
     * {@inheritdoc}
     */
    public function error(string $message, array $context = []): void
    {
        // Ничего не делаем
    }

    /**
     * {@inheritdoc}
     */
    public function critical(string $message, array $context = []): void
    {
        // Ничего не делаем
    }
}