<?php

namespace App\Infrastructure\Logger;

/**
 * Файловый логгер с ротацией и форматированием
 */
class FileLogger implements LoggerInterface
{
    private string $logPath;
    private string $logLevel;
    private int $maxFileSize;
    private int $maxFiles;

    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];

    public function __construct(
        string $logPath = 'logs/app.log',
        string $logLevel = 'info',
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5
    ) {
        $this->logPath = $logPath;
        $this->logLevel = $logLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        
        $this->ensureLogDirectory();
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        // Проверяем уровень логирования
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }

        // Ротация файлов при необходимости
        $this->rotateIfNeeded();

        // Форматирование сообщения
        $formattedMessage = $this->formatMessage($level, $message, $context);

        // Запись в файл
        file_put_contents($this->logPath, $formattedMessage, FILE_APPEND | LOCK_EX);
    }

    private function formatMessage(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $processId = getmypid();
        
        $logEntry = "[{$timestamp}] [{$levelUpper}] [PID:{$processId}] {$message}";
        
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logEntry .= " Context: {$contextJson}";
        }
        
        return $logEntry . PHP_EOL;
    }

    private function ensureLogDirectory(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logPath)) {
            return;
        }

        if (filesize($this->logPath) < $this->maxFileSize) {
            return;
        }

        // Ротация файлов
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logPath . '.' . $i;
            $newFile = $this->logPath . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($oldFile); // Удаляем самый старый файл
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Переименовываем текущий файл
        if (file_exists($this->logPath)) {
            rename($this->logPath, $this->logPath . '.1');
        }
    }
}