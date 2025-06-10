<?php
// settings_storage/JsonFileSettingsStorage.php

require_once 'settings_storage/SettingsStorageInterface.php';

class JsonFileSettingsStorage implements SettingsStorageInterface {
    private $filePath;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    public function load(): array {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Ошибка декодирования JSON из файла настроек {$this->filePath}: " . json_last_error_msg());
            return [];
        }

        return $data;
    }

    public function save(array $data): void {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonContent === false) {
            error_log("Ошибка кодирования JSON для файла настроек {$this->filePath}: " . json_last_error_msg());
            return;
        }

        if (file_put_contents($this->filePath, $jsonContent) === false) {
            error_log("Ошибка записи файла настроек {$this->filePath}.");
        }
    }
} 