<?php

namespace App\Infrastructure\Logger;

/**
 * Консольный логгер для разработки и отладки
 */
class ConsoleLogger implements LoggerInterface
{
    private array $colors = [
        'debug' => "\033[0;37m",     // White
        'info' => "\033[0;36m",      // Cyan  
        'warning' => "\033[0;33m",   // Yellow
        'error' => "\033[0;31m",     // Red
        'critical' => "\033[1;31m",  // Bold Red
        'reset' => "\033[0m"         // Reset
    ];

    private bool $useColors;

    public function __construct(bool $useColors = true)
    {
        $this->useColors = $useColors && $this->supportsColors();
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
        $timestamp = date('H:i:s');
        $levelUpper = strtoupper($level);
        
        $color = $this->useColors ? $this->colors[$level] : '';
        $reset = $this->useColors ? $this->colors['reset'] : '';
        
        $output = "{$color}[{$timestamp}] [{$levelUpper}] {$message}{$reset}";
        
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $output .= " " . $contextJson;
        }
        
        echo $output . PHP_EOL;
    }

    private function supportsColors(): bool
    {
        // Проверяем поддержку цветов в терминале
        return DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
}