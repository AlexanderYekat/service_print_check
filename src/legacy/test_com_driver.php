<?php
// Тестовый скрипт для проверки COM-драйвера АТОЛ

echo "<pre>";
try {
    $fptr = new COM("AddIn.Fptr10");
    echo "COM-объект AddIn.Fptr10 успешно создан!\n";
    if (property_exists($fptr, 'version')) {
        $version = $fptr->version;
        echo "Версия драйвера: $version\n";
    } else {
        echo "Метод Version не найден в объекте.\n";
    }
} catch (Exception $e) {
    echo "Ошибка при создании COM-объекта: " . $e->getMessage() . "\n";
}
echo "</pre>"; 