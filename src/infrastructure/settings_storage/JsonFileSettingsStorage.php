<?php

require_once __DIR__ . '/../../interface/SettingsStorageInterface.php';

class JsonFileSettingsStorage implements SettingsStorageInterface
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new Exception("Не удалось прочитать файл настроек: {$this->filePath}");
        }

        $settings = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Некорректный JSON в файле настроек: " . json_last_error_msg());
        }

        return $settings ?? [];
    }

    public function save(array $settings): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception("Не удалось сериализовать настройки в JSON");
        }

        if (file_put_contents($this->filePath, $json) === false) {
            throw new Exception("Не удалось записать файл настроек: {$this->filePath}");
        }
    }
}