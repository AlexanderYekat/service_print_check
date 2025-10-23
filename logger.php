<?php

class Logger {
    private $logPath;
    private $debugLevel;
    private $loggingEnabled;
    private static $instance = null;

    private function __construct(string $logPath, int $debugLevel, bool $loggingEnabled = true) {
        $this->logPath = $logPath;
        $this->debugLevel = $debugLevel;
        $this->loggingEnabled = $loggingEnabled;
    }

    public static function getInstance(string $logPath = '', int $debugLevel = 0, bool $loggingEnabled = true): Logger {
        if (self::$instance === null) {
            self::$instance = new Logger($logPath, $debugLevel, $loggingEnabled);
        }
        return self::$instance;
    }

    public function setDebugLevel(int $level): void {
        $this->debugLevel = $level;
    }

    public function setLoggingEnabled(bool $enabled): void {
        $this->loggingEnabled = $enabled;
    }

    public function log(string $message, int $level = 0, string $type = 'INFO'): void {
        if (!$this->loggingEnabled) {
            return;
        }

        if ($level <= $this->debugLevel) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp][$type] $message" . PHP_EOL;
            file_put_contents($this->logPath . DIRECTORY_SEPARATOR . 'application.log', $logMessage, FILE_APPEND);
        }
    }

    public function info(string $message): void {
        $this->log($message, 1, 'INFO');
    }

    public function warning(string $message): void {
        $this->log($message, 2, 'WARNING');
    }

    public function error(string $message): void {
        $this->log($message, 0, 'ERROR'); // Errors are always logged
    }

    public function debug(string $message): void {
        $this->log($message, 3, 'DEBUG');
    }

    public function critical(string $message): void {
        $this->log($message, 0, 'CRITICAL'); // Critical errors are always logged
    }
}

// Функция-обёртка для совместимости со старым кодом
function logger($msg) {
    Logger::getInstance(__DIR__, 1)->info($msg);
} 