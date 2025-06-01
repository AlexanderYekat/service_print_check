<?php
// settings.php

define('SETTINGSDIR', __DIR__ . '/settings/');
define('FILESETTINGS', 'settings.json');
define('FULL_FILE_NAME_SETTINGS', SETTINGSDIR . FILESETTINGS);

function InitializationsSettings() {
    if (!file_exists(SETTINGSDIR)) {
        mkdir(SETTINGSDIR, 0755, true);
    }
    if (!file_exists(FULL_FILE_NAME_SETTINGS)) {
        // Создаём файл с дефолтными настройками
        $default = new Settings();
        saveSettings($default, FULL_FILE_NAME_SETTINGS);
    }
    return loadSettings();
}

function saveSettings($settings, $filename = FULL_FILE_NAME_SETTINGS) {
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filename, $json);
}

function loadSettings($filename = FULL_FILE_NAME_SETTINGS) {
    if (!file_exists($filename)) {
        return new Settings();
    }
    $data = json_decode(file_get_contents($filename), true);
    return new Settings($data);
}
