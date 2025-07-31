<?php
/**
 * Unit-тесты для PermitMarkCheckUseCase
 * 
 * Тестирует бизнес-логику синхронной проверки марки в разрешительном режиме:
 * - Успешная проверка марки
 * - Обработка ошибок проверки
 * - Сохранение результатов в реестр
 * - Работа с контекстом (ИНН, GTIN)
 * - Кэширование результатов
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domain\Service\PermitMarkCheckUseCase;
use App\Domain\Service\MarkCheckRegistry;
use App\Domain\Model\MarkingCode;
use App\Domain\Model\OperationResult;
use App\Interface\PermitMarkCheckGateway;

class PermitMarkCheckUseCaseTest
{
    public function testSuccessfulMarkCheck(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890', 'gtin' => '04635652312345'];
            
            // Создаем мок успешного gateway
            $mockGateway = new MockSuccessfulPermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            $result = $useCase->execute($markingCode, $context);
            
            // Проверяем успешность операции
            if (!$result->isSuccess()) {
                throw new Exception('Результат должен быть успешным');
            }
            
            // Проверяем структуру данных
            $userData = $result->getData('user_status');
            if (empty($userData)) {
                throw new Exception('Результат должен содержать user_status');
            }
            
            $machineData = $result->getData('machine_data');
            if (empty($machineData)) {
                throw new Exception('Результат должен содержать machine_data');
            }
            
            // Проверяем, что результат сохранен в реестр
            if (!$registry->hasPermitCheck($markingCode)) {
                throw new Exception('Результат должен быть сохранен в реестр');
            }
            
            echo "✅ Успешная проверка марки: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Успешная проверка марки: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testFailedMarkCheck(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890', 'gtin' => '04635652312345'];
            
            // Создаем мок неуспешного gateway
            $mockGateway = new MockFailedPermitGateway('Марка не найдена в базе данных');
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            $result = $useCase->execute($markingCode, $context);
            
            // Проверяем неуспешность операции
            if ($result->isSuccess()) {
                throw new Exception('Результат должен быть неуспешным');
            }
            
            // Проверяем сообщение об ошибке
            $errorMessage = $result->getErrorMessage();
            if (strpos($errorMessage, 'не найдена') === false) {
                throw new Exception("Некорректное сообщение об ошибке: {$errorMessage}");
            }
            
            // Проверяем, что неуспешный результат тоже сохраняется
            if (!$registry->hasPermitCheck($markingCode)) {
                throw new Exception('Неуспешный результат тоже должен сохраняться в реестр');
            }
            
            echo "✅ Неуспешная проверка марки: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Неуспешная проверка марки: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testMarkCheckWithContext(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = [
                'inn' => '1234567890',
                'gtin' => '04635652312345',
                'fiscal_drive_number' => 'FD123456789'
            ];
            
            $mockGateway = new MockContextAwarePermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            $result = $useCase->execute($markingCode, $context);
            
            if (!$result->isSuccess()) {
                throw new Exception('Результат должен быть успешным');
            }
            
            // Проверяем, что контекст передался в gateway
            $receivedContext = $result->getData('received_context');
            if ($receivedContext['inn'] !== $context['inn']) {
                throw new Exception('ИНН должен передаваться в gateway');
            }
            
            if ($receivedContext['fiscal_drive_number'] !== $context['fiscal_drive_number']) {
                throw new Exception('Номер фискального накопителя должен передаваться');
            }
            
            // Проверяем сохранение контекста в реестре
            $registryEntry = $registry->getPermitCheckResult($markingCode);
            if ($registryEntry['context']['inn'] !== $context['inn']) {
                throw new Exception('Контекст должен сохраняться в реестре');
            }
            
            echo "✅ Проверка с контекстом: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Проверка с контекстом: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testMarkAlreadyChecked(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $mockGateway = new MockSuccessfulPermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            // Первая проверка
            $result1 = $useCase->execute($markingCode);
            if (!$result1->isSuccess()) {
                throw new Exception('Первая проверка должна быть успешной');
            }
            
            // Проверяем, что марка помечена как проверенная
            if (!$useCase->hasBeenChecked($markingCode)) {
                throw new Exception('Марка должна быть помечена как проверенная');
            }
            
            // Вторая проверка той же марки (должна пройти, но обновить результат)
            $result2 = $useCase->execute($markingCode);
            if (!$result2->isSuccess()) {
                throw new Exception('Повторная проверка должна быть успешной');
            }
            
            // Проверяем, что в реестре по-прежнему одна запись
            $registryEntry = $registry->getPermitCheckResult($markingCode);
            if ($registryEntry === null) {
                throw new Exception('Запись должна остаться в реестре');
            }
            
            echo "✅ Повторная проверка марки: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Повторная проверка марки: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testDifferentMarkingFormats(): bool
    {
        try {
            // Одна и та же марка в разных форматах
            $markingCode1 = new MarkingCode("01 04635652312345 15 21 23456789");
            $markingCode2 = new MarkingCode("0104635652312345152123456789");
            $markingCode3 = new MarkingCode("0104635652312345\x1D152123456789"); // С GS
            
            $mockGateway = new MockSuccessfulPermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            // Проверяем первый формат
            $result1 = $useCase->execute($markingCode1);
            if (!$result1->isSuccess()) {
                throw new Exception('Первая проверка должна быть успешной');
            }
            
            // Проверяем, что другие форматы той же марки определяются как уже проверенные
            if (!$useCase->hasBeenChecked($markingCode2)) {
                throw new Exception('Марка в другом формате должна определяться как проверенная');
            }
            
            if (!$useCase->hasBeenChecked($markingCode3)) {
                throw new Exception('Марка с GS символами должна определяться как проверенная');
            }
            
            echo "✅ Разные форматы маркировки: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Разные форматы маркировки: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGatewayException(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            // Gateway, который выбрасывает исключение
            $mockGateway = new MockExceptionPermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            // Исключение должно пробрасываться наверх (не перехватываться в use case)
            $exceptionThrown = false;
            try {
                $useCase->execute($markingCode);
            } catch (Exception $e) {
                $exceptionThrown = true;
                if (strpos($e->getMessage(), 'Сервис недоступен') === false) {
                    throw new Exception("Неправильное исключение: " . $e->getMessage());
                }
            }
            
            if (!$exceptionThrown) {
                throw new Exception('Исключение должно пробрасываться');
            }
            
            // Проверяем, что при исключении запись в реестр не создается
            if ($registry->hasPermitCheck($markingCode)) {
                throw new Exception('При исключении запись не должна создаваться в реестре');
            }
            
            echo "✅ Обработка исключений gateway: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка исключений gateway: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testRegistryIntegration(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890'];
            
            $mockGateway = new MockSuccessfulPermitGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new PermitMarkCheckUseCase($mockGateway, $registry);
            
            $result = $useCase->execute($markingCode, $context);
            
            // Проверяем детали записи в реестре
            $registryEntry = $registry->getPermitCheckResult($markingCode);
            
            if ($registryEntry['check_type'] !== 'permit') {
                throw new Exception('Тип проверки должен быть permit');
            }
            
            if (empty($registryEntry['checked_at'])) {
                throw new Exception('Время проверки должно быть записано');
            }
            
            if ($registryEntry['context']['inn'] !== $context['inn']) {
                throw new Exception('Контекст должен сохраняться');
            }
            
            if (!$registryEntry['result']->isSuccess()) {
                throw new Exception('Результат в реестре должен быть успешным');
            }
            
            echo "✅ Интеграция с реестром: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Интеграция с реестром: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов PermitMarkCheckUseCase...\n\n";
        
        $tests = [
            'testSuccessfulMarkCheck',
            'testFailedMarkCheck',
            'testMarkCheckWithContext',
            'testMarkAlreadyChecked',
            'testDifferentMarkingFormats',
            'testGatewayException',
            'testRegistryIntegration'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        echo "\n📊 Результат: {$passed}/{$total} тестов пройдено\n";
        
        return $passed === $total;
    }
}

// ========================================
// MOCK КЛАССЫ ДЛЯ ТЕСТИРОВАНИЯ
// ========================================

/**
 * Мок успешного gateway для проверки марки
 */
class MockSuccessfulPermitGateway implements PermitMarkCheckGateway
{
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult
    {
        return OperationResult::success([
            'user_status' => [
                'status' => 'VALID',
                'product_name' => 'Тестовый товар',
                'owner_name' => 'ООО Тест'
            ],
            'machine_data' => [
                'uuid' => 'test-uuid-' . substr($code->getCleanCode(), 0, 10),
                'time' => date('c'),
                'requestId' => uniqid('req_')
            ]
        ], 'Марка прошла проверку');
    }
}

/**
 * Мок неуспешного gateway для проверки марки
 */
class MockFailedPermitGateway implements PermitMarkCheckGateway
{
    private string $errorMessage;
    
    public function __construct(string $errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }
    
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult
    {
        return OperationResult::failure($this->errorMessage, [
            'error_code' => 'MARK_NOT_FOUND',
            'marking_code' => $code->getCleanCode()
        ]);
    }
}

/**
 * Мок gateway, который проверяет переданный контекст
 */
class MockContextAwarePermitGateway implements PermitMarkCheckGateway
{
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult
    {
        return OperationResult::success([
            'user_status' => [
                'status' => 'VALID',
                'product_name' => 'Тестовый товар'
            ],
            'machine_data' => [
                'uuid' => 'test-uuid',
                'time' => date('c')
            ],
            'received_context' => $context // Возвращаем полученный контекст для проверки
        ], 'Марка проверена с контекстом');
    }
}

/**
 * Мок gateway, который выбрасывает исключение
 */
class MockExceptionPermitGateway implements PermitMarkCheckGateway
{
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult
    {
        throw new Exception('Сервис недоступен: HTTP 503');
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PermitMarkCheckUseCaseTest();
    $result = $test->run();
    echo $result ? "✅ PermitMarkCheckUseCaseTest PASSED\n" : "❌ PermitMarkCheckUseCaseTest FAILED\n";
    exit($result ? 0 : 1);
}