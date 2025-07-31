<?php
// myapp_dist/app/test_service.php

// Подключаем наш логгер
require_once 'logger.php';

// Определяем путь для логов
define('LOG_PATH', __DIR__ . '/logs');

// Определяем пути для настроек
define('SETTINGS_DIR', __DIR__ . '/settings');
define('SETTINGS_FILE', SETTINGS_DIR . '/settings.json');

// Подключаем необходимые файлы для работы с настройками
require_once 'models.php';
require_once 'settings_storage/JsonFileSettingsStorage.php';

// Создаем директорию для настроек, если она не существует
if (!is_dir(SETTINGS_DIR)) {
    mkdir(SETTINGS_DIR, 0777, true);
}

// Инициализируем хранилище настроек
$settingsStorage = new JsonFileSettingsStorage(SETTINGS_FILE);
$currentSettings = new Settings($settingsStorage);
$currentSettings->load(); // Загружаем настройки

// Инициализируем логгер с текущим уровнем отладки и настройкой отключения логирования из настроек
$logger = Logger::getInstance(LOG_PATH, $currentSettings->debug, !$currentSettings->disableLogging);

$logger->info("Test service started successfully!");

while (true) {
    // В реальном приложении здесь можно было бы использовать $currentSettings
    // для изменения поведения, например, частоты логирования или других параметров.
    $logger->info("Test service is running... Current debug level: " . $currentSettings->debug . ", Logging disabled: " . ($currentSettings->disableLogging ? 'Yes' : 'No') . " - " . date('Y-m-d H:i:s'));
    sleep(5); // Ждем 5 секунд
}

?> 