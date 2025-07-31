<?php

namespace App\Interface;

interface SettingsStorageInterface {
    public function load(): array;
    public function save(array $data): void;
    
    // Новые методы для работы с fiscalDriveNumber
    public function getFiscalDriveNumber(): ?string;
    public function setFiscalDriveNumber(string $fiscalDriveNumber): void;
    
    // Дополнительные методы для удобства
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
}