<?php

// Отладка JSON создания как в нашем тесте

$scaleSettings = [
    'com_port' => 1001,
    'baud_rate' => 18,
    'model' => 38,
    'com_class' => 'AddIn.Scale8',
    'emulation' => true
];

$settings = [
    'scale' => $scaleSettings,
    'printer' => [
        'com_class' => 'AddIn.Fptr10',
        'com_port' => 'COM1',
        'emulation' => true
    ],
    'bank' => [
        'binary_path' => '',
        'emulation' => true,
        'timeout' => 30
    ],
    'honest_sign' => [
        'api_url' => 'https://markirovka.nalog.ru/api/v3',
        'api_key' => '',
        'use_queue' => true,
        'timeout' => 30
    ]
];

echo "=== ОТЛАДКА JSON СОЗДАНИЯ ===\n";

// Тестируем разные варианты кодирования
$variants = [
    'JSON_PRETTY_PRINT' => JSON_PRETTY_PRINT,
    'JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    'JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
];

foreach ($variants as $name => $flags) {
    echo "\n--- $name ---\n";
    $jsonContent = json_encode($settings, $flags);
    
    if ($jsonContent === false) {
        echo "ОШИБКА: " . json_last_error_msg() . "\n";
        continue;
    }
    
    // Тестируем парсинг обратно
    $decoded = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ОШИБКА ПАРСИНГА: " . json_last_error_msg() . "\n";
        echo "RAW CONTENT:\n";
        var_dump($jsonContent);
    } else {
        echo "✅ JSON валиден\n";
        echo "Длина: " . strlen($jsonContent) . " байт\n";
        
        // Проверяем на управляющие символы
        if (preg_match('/[\x00-\x1F\x7F]/', $jsonContent)) {
            echo "❌ НАЙДЕНЫ УПРАВЛЯЮЩИЕ СИМВОЛЫ!\n";
            // Показываем в hex
            echo "HEX: " . bin2hex($jsonContent) . "\n";
        } else {
            echo "✅ Управляющих символов нет\n";
        }
    }
}

echo "\n=== ТЕСТ ЗАПИСИ В ФАЙЛ ===\n";

$testFile = 'test_settings.json';
$jsonContent = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Записываем файл
file_put_contents($testFile, $jsonContent);

// Читаем и тестируем
$readContent = file_get_contents($testFile);
$decoded = json_decode($readContent, true);

echo "Записано в файл: $testFile\n";
echo "Длина файла: " . strlen($readContent) . " байт\n";

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ ОШИБКА ЧТЕНИЯ ИЗ ФАЙЛА: " . json_last_error_msg() . "\n";
    echo "Содержимое файла (первые 200 символов):\n";
    echo substr($readContent, 0, 200) . "...\n";
    echo "HEX последних 20 байт:\n";
    echo bin2hex(substr($readContent, -20)) . "\n";
} else {
    echo "✅ Файл читается корректно\n";
}

// Удаляем тестовый файл
unlink($testFile);