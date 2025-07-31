<?php

// Отладка JsonFileSettingsStorage парсинга

$settings = [
    'scale' => [
        'com_port' => 1001,
        'baud_rate' => 18,
        'model' => 38,
        'com_class' => 'AddIn.Scale8',
        'emulation' => true
    ]
];

echo "=== ОТЛАДКА JsonFileSettingsStorage ===\n";

// 1. Создаем JSON как в нашем тесте
$originalJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "1. Оригинальный JSON:\n";
echo $originalJson . "\n\n";

// 2. Проверяем что JSON валиден
$testParse = json_decode($originalJson, true);
echo "2. Оригинальный JSON валиден: " . (json_last_error() === JSON_ERROR_NONE ? "ДА" : "НЕТ: " . json_last_error_msg()) . "\n\n";

// 3. Применяем обработку как в JsonFileSettingsStorage (строки 42-43)
$content = $originalJson;

// Удаляем комментарии из JSON (// и /**/)
echo "3. Применяем regex обработку...\n";
echo "ПЕРЕД regex:\n" . $content . "\n\n";

$content = preg_replace('/\/\/.*$/m', '', $content);
echo "После удаления // комментариев:\n" . $content . "\n\n";

$content = preg_replace('/\/\*.*?\*\//s', '', $content);
echo "После удаления /* */ комментариев:\n" . $content . "\n\n";

// 4. Пытаемся парсить обработанный JSON
echo "4. Парсинг обработанного JSON...\n";
$data = json_decode($content, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ ОШИБКА ПАРСИНГА: " . json_last_error_msg() . "\n";
    echo "Поврежденный JSON:\n" . $content . "\n";
    echo "HEX поврежденного JSON:\n" . bin2hex($content) . "\n";
} else {
    echo "✅ JSON парсится успешно\n";
}

// 5. Тестируем с URL в JSON (возможная проблема)
echo "\n=== ТЕСТ С URL (возможная проблема) ===\n";
$settingsWithUrl = [
    'honest_sign' => [
        'api_url' => 'https://markirovka.nalog.ru/api/v3'
    ]
];

$jsonWithUrl = json_encode($settingsWithUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "JSON с URL:\n" . $jsonWithUrl . "\n\n";

// Применяем regex
$contentWithUrl = $jsonWithUrl;
$contentWithUrl = preg_replace('/\/\/.*$/m', '', $contentWithUrl);
$contentWithUrl = preg_replace('/\/\*.*?\*\//s', '', $contentWithUrl);

echo "После regex обработки:\n" . $contentWithUrl . "\n\n";

$dataWithUrl = json_decode($contentWithUrl, true);
if ($dataWithUrl === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ URL ПОВРЕЖДАЕТ JSON: " . json_last_error_msg() . "\n";
} else {
    echo "✅ URL обрабатывается корректно\n";
}