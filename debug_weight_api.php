<?php
/**
 * Простой отладочный тест для API получения веса
 */

// Включаем отображение всех ошибок кроме warnings для CLI режима
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);

// Включаем отображение Fatal Error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n❌ FATAL ERROR: " . $error['message'] . "\n";
        echo "Файл: " . $error['file'] . ":" . $error['line'] . "\n";
    }
});

echo "🔍 Отладка API получения веса\n";
echo "============================\n\n";

try {
    // Устанавливаем тестовый режим
    define('TESTING_MODE', true);
    
    // Загружаем bootstrap
    echo "📋 Загрузка bootstrap...\n";
    
    try {
        require_once __DIR__ . '/src/bootstrap.php';
        echo "✅ Bootstrap загружен успешно\n";
        
        // Проверяем что DI контейнер инициализирован
        if (!isset($GLOBALS['di'])) {
            throw new Exception("DI контейнер не инициализирован после загрузки bootstrap!");
        }
        
        echo "✅ DI контейнер инициализирован\n";
        echo "Количество компонентов в DI: " . count($GLOBALS['di']) . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Ошибка при загрузке bootstrap: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // Проверяем DI контейнер
    echo "📋 Проверка DI контейнера...\n";
    if (isset($GLOBALS['di']['get_weight_controller'])) {
        echo "✅ GetWeightController найден в DI\n";
    } else {
        echo "❌ GetWeightController НЕ НАЙДЕН в DI\n";
        echo "Доступные контроллеры: " . implode(', ', array_keys($GLOBALS['di'])) . "\n";
    }
    echo "\n";
    
    // Создаем тестовые настройки
    echo "📋 Создание тестовых настроек...\n";
    $settingsPath = __DIR__ . '/config/settings.json';
    $testSettings = [
        'scale' => [
            'com_port' => 1001,
            'baud_rate' => 18,
            'model' => 38,
            'com_class' => 'AddIn.Scale8',
            'emulation' => true
        ]
    ];
    
    if (!is_dir(dirname($settingsPath))) {
        mkdir(dirname($settingsPath), 0755, true);
    }
    
    file_put_contents($settingsPath, json_encode($testSettings, JSON_UNESCAPED_UNICODE));
    echo "✅ Тестовые настройки созданы\n\n";
    
    // Тестируем UseCase напрямую
    echo "📋 Тестирование UseCase напрямую...\n";
    
    // Дополнительная проверка DI контейнера
    echo "🔍 Проверка доступных компонентов в DI:\n";
    $available = array_keys($GLOBALS['di']);
    echo "Всего компонентов: " . count($available) . "\n";
    foreach ($available as $key) {
        echo "  - {$key}\n";
    }
    echo "\n";
    
    // Проверяем существование контроллера перед обращением
    if (!isset($GLOBALS['di']['get_weight_controller'])) {
        throw new Exception("GetWeightController не найден в DI контейнере!");
    }
    
    echo "✅ GetWeightController существует в DI\n";
    echo "🔄 Получение контроллера из DI...\n";
    
    try {
        $controller = $GLOBALS['di']['get_weight_controller'];
        echo "✅ Контроллер успешно получен из DI\n";
        echo "Тип контроллера: " . get_class($controller) . "\n";
    } catch (Exception $e) {
        echo "❌ Ошибка при получении контроллера: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // Простой тест контроллера без буферизации
    echo "🔄 Простой тест контроллера...\n";
    
    // Устанавливаем минимальные $_SERVER переменные для CLI режима
    echo "  -> Настройка $_SERVER переменных для CLI...\n";
    if (!isset($_SERVER['REQUEST_METHOD'])) {
        $_SERVER['REQUEST_METHOD'] = 'CLI';
    }
    if (!isset($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '/debug-cli';
    }
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        $_SERVER['HTTP_USER_AGENT'] = 'PHP-CLI-Debug';
    }
    
    echo "  -> Проверка UseCase напрямую без контроллера...\n";
    
    try {
        // Получаем UseCase напрямую и тестируем его
        $useCase = $GLOBALS['di']['get_weight_use_case'];
        echo "  -> UseCase получен: " . get_class($useCase) . "\n";
        
        echo "  -> Вызываем execute() UseCase...\n";
        $result = $useCase->execute();
        echo "  -> UseCase выполнен\n";
        
        echo "✅ UseCase результат:\n";
        echo "  - Success: " . ($result->success ? 'true' : 'false') . "\n";
        echo "  - Message: " . ($result->message ?? 'нет') . "\n";
        echo "  - Error: " . ($result->error ?? 'нет') . "\n";
        if (isset($result->data)) {
            echo "  - Data: " . print_r($result->data, true) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Ошибка при выполнении UseCase: " . $e->getMessage() . "\n";
        echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Трейс:\n" . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трейс:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трейс:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🔍 Отладка завершена успешно\n";

// Принудительный flush всех буферов
if (ob_get_level()) {
    ob_end_flush();
}
flush();