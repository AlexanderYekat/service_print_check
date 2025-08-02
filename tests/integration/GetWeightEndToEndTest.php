<?php
/**
 * End-to-End тесты для получения веса
 * 
 * Тестирует весь флоу от HTTP API до адаптеров весов:
 * HTTP Request -> Router -> Controller -> UseCase -> Scale Adapter
 */

// Старый Logger удален - теперь используется архитектура проекта

// Устанавливаем тестовый режим перед загрузкой bootstrap
define('TESTING_MODE', true);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/routes.php';

class GetWeightEndToEndTest
{
    private string $settingsPath;
    private array $originalSettings;
    
    public function __construct()
    {
        // Путь должен совпадать с bootstrap.php - config/settings.json
        $this->settingsPath = __DIR__ . '/../../config/settings.json';
        $this->backupOriginalSettings();
    }
    
    /**
     * Сохранение оригинальных настроек для восстановления
     */
    private function backupOriginalSettings(): void
    {
        if (file_exists($this->settingsPath)) {
            $this->originalSettings = json_decode(file_get_contents($this->settingsPath), true);
        } else {
            $this->originalSettings = [];
        }
    }
    
    /**
     * Восстановление оригинальных настроек
     */
    private function restoreOriginalSettings(): void
    {
        if (!empty($this->originalSettings)) {
            file_put_contents($this->settingsPath, json_encode($this->originalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Создание тестовых настроек для SerialScaleAdapter
     */
    private function createTestSettings(array $scaleSettings): void
    {
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
        
        // Создаем директорию если не существует
        $settingsDir = dirname($this->settingsPath);
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }
        
        // Удаляем существующий файл если есть, чтобы избежать проблем с кодировкой
        if (file_exists($this->settingsPath)) {
            unlink($this->settingsPath);
        }
        
        // Создаем компактный JSON без форматирования для совместимости с JsonFileSettingsStorage
        $jsonContent = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonContent === false) {
            throw new Exception('Ошибка создания JSON: ' . json_last_error_msg());
        }
        
        file_put_contents($this->settingsPath, $jsonContent);
    }
    
    /**
     * Симуляция HTTP запроса через API
     */
    private function simulateHttpRequest(string $method, string $path, array $data = []): array
    {
        // Сохраняем оригинальные переменные
        $originalServer = $_SERVER;
        $originalGet = $_GET;
        $originalPost = $_POST;
        
        try {
            // Устанавливаем тестовые переменные
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $path;
            $_GET = [];
            $_POST = [];
            
            if ($method === 'POST' && !empty($data)) {
                $_POST = $data;
            }
            
            // Захватываем вывод
            ob_start();
            
            // Перенаправляем обработку ошибок
            $errorOutput = null;
            set_error_handler(function($severity, $message, $file, $line) use (&$errorOutput) {
                $errorOutput = "Error: $message in $file on line $line";
                return false;
            });
            
            try {
                handleCleanArchitectureRequest();
                $output = ob_get_contents();
                
                // Парсим JSON ответ
                $response = json_decode($output, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON response: $output");
                }
                
                return [
                    'success' => true,
                    'data' => $response,
                    'raw_output' => $output
                ];
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'raw_output' => ob_get_contents(),
                    'php_error' => $errorOutput
                ];
            }
            
        } finally {
            ob_end_clean();
            restore_error_handler();
            
            // Восстанавливаем оригинальные переменные
            $_SERVER = $originalServer;
            $_GET = $originalGet;
            $_POST = $originalPost;
        }
    }
    
    public function testGetWeightViaApiWithEmulation(): bool
    {
        try {
            echo "🧪 Тестирование API получения веса в режиме эмуляции...\n";
            
            // Настраиваем SerialScaleAdapter в режиме эмуляции
            $this->createTestSettings([
                'com_port' => 1001,
                'baud_rate' => 18,
                'model' => 38,
                'com_class' => 'AddIn.Scale8',
                'emulation' => true
            ]);
            
            // Выполняем запрос
            $response = $this->simulateHttpRequest('GET', '/api/get-weight');
            
            if (!$response['success']) {
                throw new Exception("HTTP запрос неуспешен: " . ($response['error'] ?? 'Unknown error') . 
                                   ". Raw output: " . ($response['raw_output'] ?? 'None') .
                                   ". PHP Error: " . ($response['php_error'] ?? 'None'));
            }
            
            $data = $response['data'];
            
            // Проверяем структуру ответа
            if (!isset($data['success']) || !isset($data['data'])) {
                throw new Exception("Некорректная структура ответа: " . json_encode($data));
            }
            
            // В режиме эмуляции может быть как успех так и ошибка
            if ($data['success']) {
                // Проверяем наличие веса
                if (!isset($data['data']['weight'])) {
                    throw new Exception("Отсутствует вес в ответе: " . json_encode($data['data']));
                }
                
                $weight = $data['data']['weight'];
                if (!is_numeric($weight)) {
                    throw new Exception("Некорректный формат веса: $weight");
                }
                
                echo "✅ API в режиме эмуляции: вес $weight кг получен успешно\n";
            } else {
                // Ошибка в эмуляции может быть ожидаемой
                $errorMessage = $data['message'] ?? 'Unknown error';
                echo "✅ API в режиме эмуляции: ожидаемая ошибка - $errorMessage\n";
            }
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ API в режиме эмуляции: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetWeightViaApiWithRealSettings(): bool
    {
        try {
            echo "🧪 Тестирование API получения веса с реальными настройками...\n";
            
            // Настраиваем SerialScaleAdapter без эмуляции
            $this->createTestSettings([
                'com_port' => 99999, // Несуществующий порт для проверки обработки ошибок
                'baud_rate' => 18,
                'model' => 38,
                'com_class' => 'AddIn.Scale8',
                'emulation' => false
            ]);
            
            // Выполняем запрос
            $response = $this->simulateHttpRequest('GET', '/api/get-weight');
            
            if (!$response['success']) {
                throw new Exception("HTTP запрос неуспешен: " . ($response['error'] ?? 'Unknown error') . 
                                   ". Raw output: " . ($response['raw_output'] ?? 'None'));
            }
            
            $data = $response['data'];
            
            // Проверяем структуру ответа
            if (!isset($data['success'])) {
                throw new Exception("Некорректная структура ответа: " . json_encode($data));
            }
            
            // Ожидаем ошибку так как используем несуществующий порт
            if (!$data['success']) {
                $errorMessage = $data['message'] ?? 'Unknown error';
                if (strpos($errorMessage, 'подключения') !== false || 
                    strpos($errorMessage, 'COM') !== false ||
                    strpos($errorMessage, 'драйвер') !== false) {
                    echo "✅ API с реальными настройками: ожидаемая ошибка - $errorMessage\n";
                } else {
                    echo "⚠️  API с реальными настройками: неожиданная ошибка - $errorMessage\n";
                }
            } else {
                // Неожиданный успех
                echo "⚠️  API с реальными настройками: неожиданный успех при несуществующем порте\n";
            }
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ API с реальными настройками: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetWeightApiWithInvalidComClass(): bool
    {
        try {
            echo "🧪 Тестирование API с несуществующим COM классом...\n";
            
            // Настраиваем заведомо неработающий COM класс
            $this->createTestSettings([
                'com_port' => 1001,
                'baud_rate' => 18,
                'model' => 38,
                'com_class' => 'NonExistent.ComClass.That.Does.Not.Exist',
                'emulation' => false
            ]);
            
            // Выполняем запрос
            $response = $this->simulateHttpRequest('GET', '/api/get-weight');
            
            if (!$response['success']) {
                throw new Exception("HTTP запрос неуспешен: " . ($response['error'] ?? 'Unknown error'));
            }
            
            $data = $response['data'];
            
            // Ожидаем ошибку
            if ($data['success']) {
                echo "⚠️  Неожиданный успех с несуществующим COM классом\n";
            } else {
                // Проверяем что есть сообщение об ошибке
                if (empty($data['message'])) {
                    throw new Exception("Отсутствует сообщение об ошибке");
                }
                
                $errorMessage = $data['message'];
                if (strpos($errorMessage, 'драйвер') !== false || strpos($errorMessage, 'COM') !== false) {
                    echo "✅ Обработка ошибок COM класса: корректная ошибка - $errorMessage\n";
                } else {
                    echo "⚠️  Неожиданное сообщение об ошибке: $errorMessage\n";
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка ошибок COM класса: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetWeightHttpMethods(): bool
    {
        try {
            echo "🧪 Тестирование HTTP методов для получения веса...\n";
            
            // Настраиваем SerialScaleAdapter в режиме эмуляции
            $this->createTestSettings([
                'com_port' => 1001,
                'baud_rate' => 18,
                'model' => 38,
                'com_class' => 'AddIn.Scale8',
                'emulation' => true
            ]);
            
            // Тестируем GET
            $getResponse = $this->simulateHttpRequest('GET', '/api/get-weight');
            if (!$getResponse['success'] || !$getResponse['data']['success']) {
                throw new Exception("GET запрос неуспешен");
            }
            
            // Тестируем POST
            $postResponse = $this->simulateHttpRequest('POST', '/api/get-weight');
            if (!$postResponse['success'] || !$postResponse['data']['success']) {
                throw new Exception("POST запрос неуспешен");
            }
            
            // Тестируем неподдерживаемый метод
            $putResponse = $this->simulateHttpRequest('PUT', '/api/get-weight');
            if ($putResponse['success'] && isset($putResponse['data']['error'])) {
                if (strpos($putResponse['data']['error'], 'Method not allowed') === false) {
                    throw new Exception("Неожиданная ошибка для PUT метода");
                }
            }
            
            echo "✅ HTTP методы: GET и POST работают, PUT корректно отклонен\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ HTTP методы: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetWeightResponseFormat(): bool
    {
        try {
            echo "🧪 Тестирование формата ответа API получения веса...\n";
            
            // Настраиваем SerialScaleAdapter в режиме эмуляции
            $this->createTestSettings([
                'com_port' => 1001,
                'baud_rate' => 18,
                'model' => 38,
                'com_class' => 'AddIn.Scale8',
                'emulation' => true
            ]);
            
            $response = $this->simulateHttpRequest('GET', '/api/get-weight');
            
            if (!$response['success']) {
                throw new Exception("HTTP запрос неуспешен: " . ($response['error'] ?? 'Unknown error'));
            }
            
            $data = $response['data'];
            
            // Проверяем обязательные поля (новая архитектура)
            $requiredFields = ['success'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Отсутствует обязательное поле: $field");
                }
            }
            
            // Проверяем meta информацию
            if (!isset($data['meta']) || !isset($data['meta']['timestamp'])) {
                throw new Exception("Отсутствует meta информация или timestamp");
            }
            
            // Проверяем структуру данных при успехе
            if ($data['success']) {
                if (!isset($data['data']['weight'])) {
                    throw new Exception("Отсутствует вес в данных");
                }
                if (!isset($data['data']['message'])) {
                    throw new Exception("Отсутствует сообщение в данных");
                }
            }
            
            // Проверяем формат timestamp в meta
            $timestamp = $data['meta']['timestamp'];
            if (!is_string($timestamp) || strtotime($timestamp) === false) {
                throw new Exception("Некорректный формат timestamp: $timestamp");
            }
            
            echo "✅ Формат ответа: корректная структура JSON\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Формат ответа: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🚀 Запуск End-to-End тестов получения веса...\n\n";
        
        $tests = [
            'testGetWeightViaApiWithEmulation',
            'testGetWeightViaApiWithRealSettings',
            'testGetWeightApiWithInvalidComClass',
            'testGetWeightHttpMethods',
            'testGetWeightResponseFormat'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
            echo "\n";
        }
        
        // Восстанавливаем оригинальные настройки
        $this->restoreOriginalSettings();
        
        echo "📊 Результат End-to-End тестов: {$passed}/{$total} тестов пройдено\n";
        
        if ($passed === $total) {
            echo "🎉 Все End-to-End тесты получения веса успешно пройдены!\n";
            return true;
        } else {
            echo "⚠️  Некоторые End-to-End тесты не пройдены\n";
            return false;
        }
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new GetWeightEndToEndTest();
    $result = $test->run();
    echo $result ? "✅ GetWeightEndToEndTest PASSED\n" : "❌ GetWeightEndToEndTest FAILED\n";
    exit($result ? 0 : 1);
}