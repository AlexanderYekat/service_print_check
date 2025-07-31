<?php
/**
 * Unit-тесты для EcrMarkCheckUseCase
 * 
 * Тестирует бизнес-логику асинхронной проверки марки на ККТ:
 * - Постановка задачи в очередь
 * - Получение результата по taskId
 * - Управление taskMapping
 * - Сохранение результатов в реестр
 * - Обработка различных статусов задач
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Domain\Service\EcrMarkCheckUseCase;
use App\Domain\Service\MarkCheckRegistry;
use App\Domain\Model\MarkingCode;
use App\Domain\Model\OperationResult;
use App\Interface\EcrMarkCheckGateway;

class EcrMarkCheckUseCaseTest
{
    public function testSuccessfulTaskEnqueue(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890', 'gtin' => '04635652312345'];
            
            $mockGateway = new MockSuccessfulEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            $taskId = $useCase->enqueue($markingCode, $context);
            
            // Проверяем, что taskId возвращен
            if (empty($taskId)) {
                throw new Exception('TaskId не должен быть пустым');
            }
            
            // Проверяем формат taskId
            if (!preg_match('/^task_[a-f0-9]+$/', $taskId)) {
                throw new Exception("Некорректный формат taskId: {$taskId}");
            }
            
            echo "✅ Постановка задачи в очередь: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Постановка задачи в очередь: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testSuccessfulResultRetrieval(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890'];
            
            $mockGateway = new MockSuccessfulEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            // Ставим задачу в очередь
            $taskId = $useCase->enqueue($markingCode, $context);
            
            // Получаем результат
            $result = $useCase->getResult($taskId);
            
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
            if (empty($machineData['itemInfoCheckResult'])) {
                throw new Exception('Результат должен содержать itemInfoCheckResult');
            }
            
            // Проверяем, что результат сохранен в реестр
            if (!$registry->hasEcrCheck($markingCode)) {
                throw new Exception('Результат должен быть сохранен в реестр');
            }
            
            echo "✅ Получение успешного результата: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Получение успешного результата: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testPendingTaskStatus(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            $mockGateway = new MockPendingEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            // Ставим задачу в очередь
            $taskId = $useCase->enqueue($markingCode);
            
            // Пытаемся получить результат незавершенной задачи
            $result = $useCase->getResult($taskId);
            
            // Для незавершенной задачи результат должен быть неуспешным со статусом "pending"
            if ($result->isSuccess()) {
                throw new Exception('Незавершенная задача должна возвращать неуспешный результат');
            }
            
            $status = $result->getData('status');
            if ($status !== 'PENDING') {
                throw new Exception('Незавершенная задача должна иметь статус PENDING');
            }
            
            // Результат не должен сохраняться в реестр пока не завершен успешно
            if ($registry->hasEcrCheck($markingCode)) {
                throw new Exception('Незавершенный результат не должен сохраняться в реестр');
            }
            
            echo "✅ Статус незавершенной задачи: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Статус незавершенной задачи: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testFailedTaskResult(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            $mockGateway = new MockFailedEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            // Ставим задачу в очередь
            $taskId = $useCase->enqueue($markingCode);
            
            // Получаем неуспешный результат
            $result = $useCase->getResult($taskId);
            
            // Проверяем неуспешность
            if ($result->isSuccess()) {
                throw new Exception('Результат должен быть неуспешным');
            }
            
            // Проверяем сообщение об ошибке
            $errorMessage = $result->getErrorMessage();
            if (strpos($errorMessage, 'Марка заблокирована') === false) {
                throw new Exception("Некорректное сообщение об ошибке: {$errorMessage}");
            }
            
            // Неуспешный результат НЕ сохраняется в реестр (только успешные результаты)
            if ($registry->hasEcrCheck($markingCode)) {
                throw new Exception('Неуспешный результат не должен сохраняться в реестр');
            }
            
            echo "✅ Неуспешный результат задачи: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Неуспешный результат задачи: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testUnknownTaskId(): bool
    {
        try {
            $mockGateway = new MockUnknownTaskEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            // Пытаемся получить результат несуществующей задачи
            $result = $useCase->getResult('unknown_task_id');
            
            // Должно быть неуспешно
            if ($result->isSuccess()) {
                throw new Exception('Результат для несуществующей задачи должен быть неуспешным');
            }
            
            $errorMessage = $result->getErrorMessage();
            if (strpos($errorMessage, 'не найдена') === false) {
                throw new Exception("Некорректное сообщение об ошибке: {$errorMessage}");
            }
            
            echo "✅ Несуществующий taskId: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Несуществующий taskId: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testTaskMappingManagement(): bool
    {
        try {
            $markingCode1 = new MarkingCode("0104635652312345152123456789");
            $markingCode2 = new MarkingCode("0104635652312345152199999999");
            $context1 = ['inn' => '1111111111'];
            $context2 = ['inn' => '2222222222'];
            
            $mockGateway = new MockSuccessfulEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            // Ставим несколько задач
            $taskId1 = $useCase->enqueue($markingCode1, $context1);
            $taskId2 = $useCase->enqueue($markingCode2, $context2);
            
            // Получаем результаты
            $result1 = $useCase->getResult($taskId1);
            $result2 = $useCase->getResult($taskId2);
            
            if (!$result1->isSuccess() || !$result2->isSuccess()) {
                throw new Exception('Оба результата должны быть успешными');
            }
            
            // Проверяем, что результаты сохранены с правильным контекстом
            $registryEntry1 = $registry->getEcrCheckResult($markingCode1);
            $registryEntry2 = $registry->getEcrCheckResult($markingCode2);
            
            if ($registryEntry1['context']['inn'] !== $context1['inn']) {
                throw new Exception('Контекст первой задачи должен быть сохранен правильно');
            }
            
            if ($registryEntry2['context']['inn'] !== $context2['inn']) {
                throw new Exception('Контекст второй задачи должен быть сохранен правильно');
            }
            
            echo "✅ Управление taskMapping: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Управление taskMapping: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testDirectResultStore(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890'];
            
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase(new MockSuccessfulEcrGateway(), $registry);
            
            // Создаем результат напрямую
            $result = OperationResult::success([
                'user_status' => ['status' => 'VALID'],
                'machine_data' => ['itemInfoCheckResult' => 'OK']
            ], 'Проверка завершена');
            
            // Сохраняем результат напрямую
            $useCase->storeResult($markingCode, $result, $context);
            
            // Проверяем, что результат сохранен
            if (!$useCase->hasBeenChecked($markingCode)) {
                throw new Exception('Марка должна быть помечена как проверенная');
            }
            
            $registryEntry = $registry->getEcrCheckResult($markingCode);
            if ($registryEntry['context']['inn'] !== $context['inn']) {
                throw new Exception('Контекст должен быть сохранен');
            }
            
            echo "✅ Прямое сохранение результата: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Прямое сохранение результата: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testContextWithGtinAndInn(): bool
    {
        try {
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = [
                'inn' => '7727563778',
                'gtin' => '04635652312345',
                'product_name' => 'Тестовый товар',
                'price' => 150.00
            ];
            
            $mockGateway = new MockContextAwareEcrGateway();
            $registry = new MarkCheckRegistry();
            $useCase = new EcrMarkCheckUseCase($mockGateway, $registry);
            
            $taskId = $useCase->enqueue($markingCode, $context);
            $result = $useCase->getResult($taskId);
            
            // Проверяем, что контекст правильно передался в gateway
            $receivedContext = $result->getData('received_context');
            if ($receivedContext['inn'] !== $context['inn']) {
                throw new Exception('ИНН должен передаваться в gateway');
            }
            
            if ($receivedContext['gtin'] !== $context['gtin']) {
                throw new Exception('GTIN должен передаваться в gateway');
            }
            
            echo "✅ Контекст с ИНН и GTIN: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Контекст с ИНН и GTIN: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов EcrMarkCheckUseCase...\n\n";
        
        $tests = [
            'testSuccessfulTaskEnqueue',
            'testSuccessfulResultRetrieval',
            'testPendingTaskStatus',
            'testFailedTaskResult',
            'testUnknownTaskId',
            'testTaskMappingManagement',
            'testDirectResultStore',
            'testContextWithGtinAndInn'
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
 * Мок успешного ECR gateway
 */
class MockSuccessfulEcrGateway implements EcrMarkCheckGateway
{
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        return 'task_' . substr(md5($code->getCleanCode() . time()), 0, 8);
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        return OperationResult::success([
            'status' => 'COMPLETED',
            'user_status' => [
                'status' => 'VALID',
                'product_name' => 'Тестовый товар'
            ],
            'machine_data' => [
                'itemInfoCheckResult' => 'OK',
                'uuid' => 'ecr-uuid-' . $taskId,
                'time' => date('c')
            ]
        ], 'Проверка на ККТ завершена');
    }
}

/**
 * Мок ECR gateway с незавершенными задачами
 */
class MockPendingEcrGateway implements EcrMarkCheckGateway
{
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        return 'task_pending_' . substr(md5($code->getCleanCode()), 0, 8);
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        return OperationResult::failure('Задача еще выполняется', [
            'status' => 'PENDING',
            'message' => 'Задача выполняется'
        ]);
    }
}

/**
 * Мок ECR gateway с неуспешными результатами
 */
class MockFailedEcrGateway implements EcrMarkCheckGateway
{
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        return 'task_failed_' . substr(md5($code->getCleanCode()), 0, 8);
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        return OperationResult::failure('Марка заблокирована ФНС', [
            'status' => 'FAILED',
            'error_code' => 'BLOCKED_BY_FNS',
            'user_status' => [
                'status' => 'INVALID'
            ]
        ]);
    }
}

/**
 * Мок ECR gateway для несуществующих задач
 */
class MockUnknownTaskEcrGateway implements EcrMarkCheckGateway
{
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        return 'task_' . uniqid();
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        return OperationResult::failure('Задача не найдена', [
            'error_code' => 'TASK_NOT_FOUND',
            'task_id' => $taskId
        ]);
    }
}

/**
 * Мок ECR gateway, который возвращает переданный контекст
 */
class MockContextAwareEcrGateway implements EcrMarkCheckGateway
{
    private array $enqueuedContext = [];
    
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        $taskId = 'task_ctx_' . uniqid();
        $this->enqueuedContext[$taskId] = $context;
        return $taskId;
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        $context = $this->enqueuedContext[$taskId] ?? [];
        
        return OperationResult::success([
            'status' => 'COMPLETED',
            'user_status' => ['status' => 'VALID'],
            'machine_data' => ['itemInfoCheckResult' => 'OK'],
            'received_context' => $context // Возвращаем контекст для проверки
        ], 'Проверка завершена с контекстом');
    }
}

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new EcrMarkCheckUseCaseTest();
    $result = $test->run();
    echo $result ? "✅ EcrMarkCheckUseCaseTest PASSED\n" : "❌ EcrMarkCheckUseCaseTest FAILED\n";
    exit($result ? 0 : 1);
}