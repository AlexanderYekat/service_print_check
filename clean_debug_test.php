<?php
/**
 * Чистый тест без HTTP заголовков
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Устанавливаем тестовый режим
define('TESTING_MODE', true);

try {
    // Загружаем bootstrap
    require_once __DIR__ . '/src/bootstrap.php';
    
    // Создаем тестовые настройки
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
    
    // Тестируем компоненты по отдельности
    echo "=== ТЕСТИРОВАНИЕ UseCase НАПРЯМУЮ ===\n";
    
    // Получаем UseCase
    $useCase = $GLOBALS['di']['get_weight_use_case'];
    echo "UseCase получен: " . get_class($useCase) . "\n";
    
    // Тестируем UseCase
    $result = $useCase->execute();
    echo "UseCase результат:\n";
    echo "- Success: " . ($result->success ? 'true' : 'false') . "\n";
    echo "- Message: " . ($result->message ?? 'null') . "\n";
    echo "- Error: " . ($result->error ?? 'null') . "\n";
    echo "- Data: " . json_encode($result->data ?? null) . "\n";
    
    // Тестируем контроллер метод executeUseCase напрямую
    echo "\n=== ТЕСТИРОВАНИЕ КОНТРОЛЛЕРА ===\n";
    
    $controller = $GLOBALS['di']['get_weight_controller'];
    echo "Controller получен: " . get_class($controller) . "\n";
    
    // Используем рефлексию для доступа к protected методу
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('executeUseCase');
    $method->setAccessible(true);
    
    $controllerResult = $method->invoke($controller, []);
    echo "Controller результат:\n";
    echo json_encode($controllerResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трейс:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трейс:\n" . $e->getTraceAsString() . "\n";
}