<?php
/**
 * Unit-тесты для MarkCheckRegistry
 * 
 * Тестирует бизнес-логику реестра проверок маркировочных кодов:
 * - Сохранение результатов проверок (permit и ecr)
 * - Поиск результатов по cleanCode
 * - Сопоставление марок в разных форматах
 * - Статистика проверок
 * - Очистка старых результатов
 */

require_once __DIR__ . '/../../../src/domain/service/MarkCheckRegistry.php';
require_once __DIR__ . '/../../../src/domain/model/MarkingCode.php';
require_once __DIR__ . '/../../../src/domain/model/OperationResult.php';

class MarkCheckRegistryTest
{
    public function testStorePermitCheckResult(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890', 'gtin' => '04635652312345'];
            
            $result = OperationResult::success([
                'user_status' => ['status' => 'VALID'],
                'machine_data' => ['uuid' => 'test-uuid']
            ], 'Проверка успешна');
            
            // Сохраняем результат проверки permit
            $registry->storePermitCheckResult($markingCode, $result, $context);
            
            // Проверяем, что результат сохранен
            if (!$registry->hasPermitCheck($markingCode)) {
                throw new Exception('Результат permit проверки должен быть сохранен');
            }
            
            // Проверяем детали сохраненного результата
            $stored = $registry->getPermitCheckResult($markingCode);
            if ($stored === null) {
                throw new Exception('Сохраненный результат должен быть доступен');
            }
            
            if ($stored['check_type'] !== 'permit') {
                throw new Exception('Тип проверки должен быть permit');
            }
            
            if ($stored['context']['inn'] !== $context['inn']) {
                throw new Exception('Контекст должен сохраняться');
            }
            
            if (!$stored['result']->isSuccess()) {
                throw new Exception('Результат должен быть успешным');
            }
            
            echo "✅ Сохранение permit результата: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сохранение permit результата: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testStoreEcrCheckResult(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            $context = ['inn' => '1234567890'];
            
            $result = OperationResult::success([
                'user_status' => ['status' => 'VALID'],
                'machine_data' => ['itemInfoCheckResult' => 'OK']
            ], 'ECR проверка успешна');
            
            // Сохраняем результат проверки ecr
            $registry->storeEcrCheckResult($markingCode, $result, $context);
            
            // Проверяем, что результат сохранен
            if (!$registry->hasEcrCheck($markingCode)) {
                throw new Exception('Результат ecr проверки должен быть сохранен');
            }
            
            // Проверяем детали сохраненного результата
            $stored = $registry->getEcrCheckResult($markingCode);
            if ($stored['check_type'] !== 'ecr') {
                throw new Exception('Тип проверки должен быть ecr');
            }
            
            echo "✅ Сохранение ecr результата: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Сохранение ecr результата: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testDifferentMarkingFormats(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            
            // Одна и та же марка в разных форматах
            $markingCode1 = new MarkingCode("01 04635652312345 15 21 23456789");
            $markingCode2 = new MarkingCode("0104635652312345152123456789");
            $markingCode3 = new MarkingCode("0104635652312345\x1D152123456789"); // С GS
            
            $result = OperationResult::success(['status' => 'VALID'], 'OK');
            
            // Сохраняем результат для первого формата
            $registry->storePermitCheckResult($markingCode1, $result);
            
            // Проверяем, что другие форматы той же марки тоже находятся
            if (!$registry->hasPermitCheck($markingCode2)) {
                throw new Exception('Марка в другом формате должна находиться');
            }
            
            if (!$registry->hasPermitCheck($markingCode3)) {
                throw new Exception('Марка с GS символами должна находиться');
            }
            
            // Проверяем, что получаем тот же результат
            $stored1 = $registry->getPermitCheckResult($markingCode1);
            $stored2 = $registry->getPermitCheckResult($markingCode2);
            $stored3 = $registry->getPermitCheckResult($markingCode3);
            
            if ($stored1 !== $stored2 || $stored2 !== $stored3) {
                throw new Exception('Разные форматы должны возвращать одинаковый результат');
            }
            
            echo "✅ Разные форматы маркировки: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Разные форматы маркировки: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetAllCheckResults(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            $permitResult = OperationResult::success(['status' => 'PERMIT_OK'], 'Permit OK');
            $ecrResult = OperationResult::success(['status' => 'ECR_OK'], 'ECR OK');
            
            // Сохраняем оба типа проверок
            $registry->storePermitCheckResult($markingCode, $permitResult);
            $registry->storeEcrCheckResult($markingCode, $ecrResult);
            
            // Получаем все результаты
            $allResults = $registry->getAllCheckResults($markingCode);
            
            if ($allResults['permit'] === null || $allResults['ecr'] === null) {
                throw new Exception('Оба результата должны быть доступны');
            }
            
            if ($allResults['permit']['check_type'] !== 'permit') {
                throw new Exception('Permit результат должен иметь правильный тип');
            }
            
            if ($allResults['ecr']['check_type'] !== 'ecr') {
                throw new Exception('ECR результат должен иметь правильный тип');
            }
            
            echo "✅ Получение всех результатов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Получение всех результатов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testIsFullyChecked(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            // Изначально марка не проверена полностью
            if ($registry->isFullyChecked($markingCode)) {
                throw new Exception('Непроверенная марка не должна быть полностью проверена');
            }
            
            // Добавляем только permit проверку
            $permitResult = OperationResult::success(['status' => 'OK'], 'OK');
            $registry->storePermitCheckResult($markingCode, $permitResult);
            
            if ($registry->isFullyChecked($markingCode)) {
                throw new Exception('Марка с одной проверкой не должна быть полностью проверена');
            }
            
            // Добавляем ecr проверку
            $ecrResult = OperationResult::success(['status' => 'OK'], 'OK');
            $registry->storeEcrCheckResult($markingCode, $ecrResult);
            
            if (!$registry->isFullyChecked($markingCode)) {
                throw new Exception('Марка с обеими проверками должна быть полностью проверена');
            }
            
            echo "✅ Проверка полноты проверок: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Проверка полноты проверок: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testCheckStats(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            
            // Создаем несколько результатов
            $mark1 = new MarkingCode("0104635652312345152111111111");
            $mark2 = new MarkingCode("0104635652312345152122222222");
            $mark3 = new MarkingCode("0104635652312345152133333333");
            
            $successResult = OperationResult::success(['status' => 'OK'], 'OK');
            $failureResult = OperationResult::failure('Error');
            
            // Permit проверки: 2 успешных, 1 неуспешная
            $registry->storePermitCheckResult($mark1, $successResult);
            $registry->storePermitCheckResult($mark2, $successResult);
            $registry->storePermitCheckResult($mark3, $failureResult);
            
            // ECR проверки: 1 успешная, 1 неуспешная
            $registry->storeEcrCheckResult($mark1, $successResult);
            $registry->storeEcrCheckResult($mark2, $failureResult);
            
            $stats = $registry->getCheckStats();
            
            if ($stats['permit_total'] !== 3) {
                throw new Exception("Ожидалось 3 permit проверки, получено: {$stats['permit_total']}");
            }
            
            if ($stats['permit_success'] !== 2) {
                throw new Exception("Ожидалось 2 успешных permit, получено: {$stats['permit_success']}");
            }
            
            if ($stats['permit_failed'] !== 1) {
                throw new Exception("Ожидалась 1 неуспешная permit, получено: {$stats['permit_failed']}");
            }
            
            if ($stats['ecr_total'] !== 2) {
                throw new Exception("Ожидалось 2 ecr проверки, получено: {$stats['ecr_total']}");
            }
            
            if ($stats['ecr_success'] !== 1) {
                throw new Exception("Ожидалась 1 успешная ecr, получено: {$stats['ecr_success']}");
            }
            
            if ($stats['ecr_failed'] !== 1) {
                throw new Exception("Ожидалась 1 неуспешная ecr, получено: {$stats['ecr_failed']}");
            }
            
            echo "✅ Статистика проверок: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Статистика проверок: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testClearRegistry(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            $result = OperationResult::success(['status' => 'OK'], 'OK');
            
            // Добавляем результаты
            $registry->storePermitCheckResult($markingCode, $result);
            $registry->storeEcrCheckResult($markingCode, $result);
            
            // Проверяем, что результаты есть
            if (!$registry->hasPermitCheck($markingCode) || !$registry->hasEcrCheck($markingCode)) {
                throw new Exception('Результаты должны быть сохранены');
            }
            
            // Очищаем реестр
            $registry->clear();
            
            // Проверяем, что результаты удалены
            if ($registry->hasPermitCheck($markingCode) || $registry->hasEcrCheck($markingCode)) {
                throw new Exception('После очистки результаты должны быть удалены');
            }
            
            $stats = $registry->getCheckStats();
            if ($stats['permit_total'] !== 0 || $stats['ecr_total'] !== 0) {
                throw new Exception('Статистика должна быть обнулена');
            }
            
            echo "✅ Очистка реестра: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Очистка реестра: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGetAllCheckedCodes(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            
            $mark1 = new MarkingCode("0104635652312345152111111111");
            $mark2 = new MarkingCode("0104635652312345152122222222");
            $mark3 = new MarkingCode("0104635652312345152133333333");
            
            $result = OperationResult::success(['status' => 'OK'], 'OK');
            
            // Добавляем разные типы проверок
            $registry->storePermitCheckResult($mark1, $result);
            $registry->storePermitCheckResult($mark2, $result);
            $registry->storeEcrCheckResult($mark2, $result); // mark2 проверена и permit и ecr
            $registry->storeEcrCheckResult($mark3, $result);
            
            $checkedCodes = $registry->getAllCheckedCodes();
            
            // Должно быть 3 уникальных cleanCode
            if (count($checkedCodes) !== 3) {
                throw new Exception("Ожидалось 3 уникальных кода, получено: " . count($checkedCodes));
            }
            
            // Проверяем, что все коды присутствуют
            $expectedCodes = [
                $mark1->getCleanCode(),
                $mark2->getCleanCode(),
                $mark3->getCleanCode()
            ];
            
            foreach ($expectedCodes as $expectedCode) {
                if (!in_array($expectedCode, $checkedCodes)) {
                    throw new Exception("Код {$expectedCode} должен быть в списке проверенных");
                }
            }
            
            echo "✅ Получение всех проверенных кодов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Получение всех проверенных кодов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testFailedResultsHandling(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            // Сохраняем неуспешные результаты
            $failedPermitResult = OperationResult::failure('Permit failed');
            $failedEcrResult = OperationResult::failure('ECR failed');
            
            $registry->storePermitCheckResult($markingCode, $failedPermitResult);
            $registry->storeEcrCheckResult($markingCode, $failedEcrResult);
            
            // Неуспешные результаты тоже должны сохраняться
            if (!$registry->hasPermitCheck($markingCode)) {
                throw new Exception('Неуспешный permit результат должен сохраняться');
            }
            
            if (!$registry->hasEcrCheck($markingCode)) {
                throw new Exception('Неуспешный ecr результат должен сохраняться');
            }
            
            // Марка считается полностью проверенной даже при неуспешных результатах
            if (!$registry->isFullyChecked($markingCode)) {
                throw new Exception('Марка с неуспешными результатами считается полностью проверенной');
            }
            
            echo "✅ Обработка неуспешных результатов: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Обработка неуспешных результатов: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testResultOverwrite(): bool
    {
        try {
            $registry = new MarkCheckRegistry();
            $markingCode = new MarkingCode("0104635652312345152123456789");
            
            // Первый результат
            $firstResult = OperationResult::success(['version' => 1], 'First check');
            $registry->storePermitCheckResult($markingCode, $firstResult, ['attempt' => 1]);
            
            $stored = $registry->getPermitCheckResult($markingCode);
            if ($stored['context']['attempt'] !== 1) {
                throw new Exception('Первый результат должен быть сохранен');
            }
            
            // Перезаписываем результат
            $secondResult = OperationResult::success(['version' => 2], 'Second check');
            $registry->storePermitCheckResult($markingCode, $secondResult, ['attempt' => 2]);
            
            $updatedStored = $registry->getPermitCheckResult($markingCode);
            if ($updatedStored['context']['attempt'] !== 2) {
                throw new Exception('Результат должен быть обновлен');
            }
            
            // Проверяем, что результат действительно новый
            $resultData = $updatedStored['result']->getData('version');
            if ($resultData !== 2) {
                throw new Exception('Данные результата должны быть обновлены');
            }
            
            echo "✅ Перезапись результата: PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Перезапись результата: FAILED - " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function run(): bool
    {
        echo "🧪 Запуск тестов MarkCheckRegistry...\n\n";
        
        $tests = [
            'testStorePermitCheckResult',
            'testStoreEcrCheckResult',
            'testDifferentMarkingFormats',
            'testGetAllCheckResults',
            'testIsFullyChecked',
            'testCheckStats',
            'testClearRegistry',
            'testGetAllCheckedCodes',
            'testFailedResultsHandling',
            'testResultOverwrite'
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

// Запуск если файл вызван напрямую
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new MarkCheckRegistryTest();
    $result = $test->run();
    echo $result ? "✅ MarkCheckRegistryTest PASSED\n" : "❌ MarkCheckRegistryTest FAILED\n";
    exit($result ? 0 : 1);
}