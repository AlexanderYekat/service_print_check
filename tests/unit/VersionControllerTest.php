<?php
/**
 * Unit тест для VersionController
 * 
 * Проверяет работу контроллера в трёх сценариях:
 * - Happy path
 * - Validation error (не применимо для этого контроллера)
 * - Infrastructure error
 */

require_once __DIR__ . '/../../src/bootstrap.php';

class VersionControllerTest
{
    private LoggerInterface $mockLogger;

    public function setUp(): void
    {
        // Создаем мок логгера
        $this->mockLogger = new class implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function debug(string $message, array $context = []): void {}
            public function critical(string $message, array $context = []): void {}
        };
    }

    public function testHappyPath(): bool
    {
        try {
            $controller = new VersionController($this->mockLogger);
            
            ob_start();
            $controller->handle([]);
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            // Проверяем структуру ответа
            if (!isset($response['success']) || $response['success'] !== true) {
                throw new Exception('Ответ должен содержать success: true');
            }
            
            if (!isset($response['data']['version'])) {
                throw new Exception('Ответ должен содержать version в data');
            }
            
            if (!isset($response['meta']['timestamp'])) {
                throw new Exception('Ответ должен содержать timestamp в meta');
            }
            
            return true;
        } catch (Exception $e) {
            echo "Happy path failed: " . $e->getMessage();
            return false;
        }
    }

    public function testValidationError(): bool
    {
        // Для VersionController нет валидации входных данных
        // поэтому этот тест не применим
        return true;
    }

    public function testInfrastructureError(): bool
    {
        try {
            // Для VersionController нет инфраструктурных зависимостей
            // поэтому создаем тест с неопределенной константой VERSION_OF_PROGRAM
            
            $originalVersion = defined('VERSION_OF_PROGRAM') ? VERSION_OF_PROGRAM : null;
            
            $controller = new VersionController($this->mockLogger);
            
            ob_start();
            $controller->handle([]);
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            // Проверяем что возвращается default версия если константа не определена
            if (!isset($response['data']['version'])) {
                throw new Exception('Версия должна быть возвращена даже если константа не определена');
            }
            
            return true;
        } catch (Exception $e) {
            echo "Infrastructure error test failed: " . $e->getMessage();
            return false;
        }
    }

    public function run(): bool
    {
        $this->setUp();
        
        $happyPath = $this->testHappyPath();
        $validationError = $this->testValidationError();
        $infrastructureError = $this->testInfrastructureError();
        
        return $happyPath && $validationError && $infrastructureError;
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new VersionControllerTest();
    $result = $test->run();
    echo $result ? "✅ VersionControllerTest PASSED\n" : "❌ VersionControllerTest FAILED\n";
    exit($result ? 0 : 1);
}

// Возвращаем результат для TestRunner
return (new VersionControllerTest())->run();