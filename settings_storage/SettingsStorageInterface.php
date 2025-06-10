<?php
// settings_storage/SettingsStorageInterface.php
 
interface SettingsStorageInterface {
    public function load(): array;
    public function save(array $data): void;
} 