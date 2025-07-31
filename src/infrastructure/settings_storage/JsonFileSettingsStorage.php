<?php

require_once __DIR__ . '/../../interface/SettingsStorageInterface.php';

/**
 * Реализация хранения настроек в JSON файле
 * Поддерживает кэширование fiscalDriveNumber согласно ТЗ
 */
class JsonFileSettingsStorage implements SettingsStorageInterface 
{
    private string $filePath;
    private array $cache = [];
    private bool $isLoaded = false;

    public function __construct(string $filePath) 
    {
        $this->filePath = $filePath;
        $this->ensureDirectoryExists();
    }

    /**
     * Загружает настройки из JSON файла
     */
    public function load(): array 
    {
        if ($this->isLoaded) {
            return $this->cache;
        }

        if (!file_exists($this->filePath)) {
            $this->cache = [];
            $this->isLoaded = true;
            return $this->cache;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new Exception("Не удалось прочитать файл настроек: {$this->filePath}");
        }

        // Удаляем комментарии из JSON (// и /**/)
        $content = preg_replace('/\/\/.*$/m', '', $content);
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Ошибка парсинга JSON в файле настроек: " . json_last_error_msg());
        }

        $this->cache = $data ?? [];
        $this->isLoaded = true;
        return $this->cache;
    }

    /**
     * Сохраняет настройки в JSON файл
     */
    public function save(array $data): void 
    {
        $this->cache = $data;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            throw new Exception("Ошибка сериализации данных в JSON: " . json_last_error_msg());
        }

        if (file_put_contents($this->filePath, $json, LOCK_EX) === false) {
            throw new Exception("Не удалось записать файл настроек: {$this->filePath}");
        }
    }

    /**
     * Получает номер фискального накопителя из настроек
     */
    public function getFiscalDriveNumber(): ?string 
    {
        $data = $this->load();
    
        // Проверяем есть ли сохраненный номер ФН
        if (!isset($data['fiscalDriveNumber'])) {
            return null;
        }
        
        // Проверяем время последнего обновления
        $lastUpdated = $data['fiscalDriveNumberUpdatedAt'] ?? null;
        if ($lastUpdated === null) {
            // Если нет времени обновления - считаем что данные устарели
            $this->clearFiscalDriveNumber();
            return null;
        }
        
        // Проверяем прошло ли более 24 часов
        $now = time();
        $dayInSeconds = 24 * 60 * 60;
        
        if (($now - $lastUpdated) > $dayInSeconds) {
            // Кэш устарел - очищаем
            $this->clearFiscalDriveNumber();
            return null;
        }    
    }

    /**
     * Сохраняет номер фискального накопителя в настройки
     */
    public function setFiscalDriveNumber(string $fiscalDriveNumber): void 
    {
        $data = $this->load();
        $data['fiscalDriveNumber'] = $fiscalDriveNumber;
        $data['fiscalDriveNumberUpdatedAt'] = time();
        $this->save($data);
    }

    /**
    * Очищает кэш номера фискального накопителя
    */
    public function clearFiscalDriveNumber(): void 
    {
        $data = $this->load();
        unset($data['fiscalDriveNumber']);
        unset($data['fiscalDriveNumberUpdatedAt']);
        $this->save($data);
    }

    /**
     * Получает значение настройки по ключу
     */
    public function get(string $key, $default = null) 
    {
        $data = $this->load();
        return $this->getNestedValue($data, $key, $default);
    }

    /**
     * Устанавливает значение настройки по ключу
     */
    public function set(string $key, $value): void 
    {
        $data = $this->load();
        $this->setNestedValue($data, $key, $value);
        $this->save($data);
    }

    /**
     * Создает директорию для файла если она не существует
     */
    private function ensureDirectoryExists(): void 
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception("Не удалось создать директорию для настроек: {$directory}");
            }
        }
    }

    /**
     * Получает значение по вложенному ключу (например "bank.timeout")
     */
    private function getNestedValue(array $data, string $key, $default = null) 
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Устанавливает значение по вложенному ключу (например "bank.timeout")
     */
    private function setNestedValue(array &$data, string $key, $value): void 
    {
        $keys = explode('.', $key);
        $current = &$data;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $k = $keys[$i];
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current[end($keys)] = $value;
    }
}