<?php
/**
 * Пример полного цикла работы с системой сопоставления маркировок
 * Демонстрирует: проверка -> сопоставление -> печать
 */

require_once __DIR__ . '/../src/domain/model/MarkingCode.php';
require_once __DIR__ . '/../src/domain/model/CheckItem.php';
require_once __DIR__ . '/../src/domain/model/Payment.php';
require_once __DIR__ . '/../src/domain/model/Check.php';
require_once __DIR__ . '/../src/domain/model/OperationResult.php';
require_once __DIR__ . '/../src/domain/service/MarkCheckRegistry.php';
require_once __DIR__ . '/../src/domain/service/MarkMatchingService.php';
require_once __DIR__ . '/../src/domain/service/PermitMarkCheckUseCase.php';
require_once __DIR__ . '/../src/domain/service/EcrMarkCheckUseCase.php';
require_once __DIR__ . '/../src/domain/service/PrintCheckUseCase.php';
require_once __DIR__ . '/../src/infrastructure/printer/FakePrinterAdapter.php';
require_once __DIR__ . '/../src/interface/PermitMarkCheckGateway.php';
require_once __DIR__ . '/../src/interface/EcrMarkCheckGateway.php';
require_once __DIR__ . '/../src/interface/SettingsStorageInterface.php';

// Заглушки для gateway и settings
class FakePermitMarkCheckGateway implements PermitMarkCheckGateway {
    public function checkPermit(MarkingCode $code, array $context = []): OperationResult {
        // Эмулируем успешную проверку в разрешительном режиме
        return OperationResult::success("Проверка в разрешительном режиме успешна", [
            'user_status' => 'VALID',
            'machine_data' => [
                'uuid' => uniqid('permit_'),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
}

class FakeEcrMarkCheckGateway implements EcrMarkCheckGateway {
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string {
        return 'task_' . uniqid();
    }
    
    public function getMarkCheckResult(string $taskId): OperationResult {
        // Эмулируем успешную проверку на ККТ
        return OperationResult::success("Проверка на ККТ успешна", [
            'user_status' => 'VALID',
            'machine_data' => [
                'uuid' => uniqid('ecr_'),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
}

class FakeSettingsStorage implements SettingsStorageInterface {
    public function load(): array {
        return ['test' => true];
    }
    
    public function save(array $settings): bool {
        return true;
    }
}

echo "=== Интеграционный тест системы сопоставления маркировок ===\n\n";

try {
    // 1. Инициализируем систему
    echo "1. Инициализация системы...\n";
    
    $registry = new MarkCheckRegistry();
    $matchingService = new MarkMatchingService($registry);
    
    $permitGateway = new FakePermitMarkCheckGateway();
    $ecrGateway = new FakeEcrMarkCheckGateway();
    $settings = new FakeSettingsStorage();
    $printer = new FakePrinterAdapter();
    
    $permitCheckUseCase = new PermitMarkCheckUseCase($permitGateway, $registry);
    $ecrCheckUseCase = new EcrMarkCheckUseCase($ecrGateway, $registry);
    $printCheckUseCase = new PrintCheckUseCase($printer, $settings, $matchingService);
    
    echo "✓ Система инициализирована\n\n";
    
    // 2. Создаем марки в разных форматах (эмулируем разные источники)
    echo "2. Создание марок в разных форматах...\n";
    
    // Одна и та же марка в разных представлениях
    $originalMark = '0104607001906529212WPG80WQKBGVDLVQ';
    $base64Mark = base64_encode($originalMark);
    $cyrillicMark = '0104607001906529212РПГ80РЙКБГВДЛВЙ'; // Эмуляция кириллицы
    
    $mark1 = new MarkingCode($originalMark);
    $mark2 = new MarkingCode($base64Mark);
    $mark3 = new MarkingCode($cyrillicMark);
    
    echo "  Исходная марка: {$originalMark}\n";
    echo "  Base64 марка: {$base64Mark}\n"; 
    echo "  Кириллическая марка: {$cyrillicMark}\n";
    echo "  CleanCode марки 1: {$mark1->getCleanCode()}\n";
    echo "  CleanCode марки 2: {$mark2->getCleanCode()}\n";
    echo "  CleanCode марки 3: {$mark3->getCleanCode()}\n";
    echo "  Марки идентичны: " . ($mark1->equals($mark2) && $mark1->equals($mark3) ? 'ДА' : 'НЕТ') . "\n\n";
    
    // 3. Проверяем марки (этап проверки)
    echo "3. Проверка марок...\n";
    
    // Проверяем первую марку в разрешительном режиме
    $permitResult1 = $permitCheckUseCase->execute($mark1, ['inn' => '123456789']);
    echo "  Разрешительный режим для марки 1: " . ($permitResult1->success ? 'УСПЕХ' : 'ОШИБКА') . "\n";
    
    // Проверяем вторую марку на ККТ
    $taskId = $ecrCheckUseCase->enqueue($mark2, ['gtin' => '4607001906529']);
    $ecrResult2 = $ecrCheckUseCase->getResult($taskId);
    echo "  ККТ проверка для марки 2: " . ($ecrResult2->success ? 'УСПЕХ' : 'ОШИБКА') . "\n";
    
    // Проверяем третью марку полностью
    $permitResult3 = $permitCheckUseCase->execute($mark3, ['inn' => '123456789']);
    $taskId3 = $ecrCheckUseCase->enqueue($mark3, ['gtin' => '4607001906529']);
    $ecrResult3 = $ecrCheckUseCase->getResult($taskId3);
    echo "  Полная проверка марки 3: Разрешительный=" . ($permitResult3->success ? 'ОК' : 'ОШИБКА') . 
         ", ККТ=" . ($ecrResult3->success ? 'ОК' : 'ОШИБКА') . "\n\n";
    
    // 4. Создаем чек с марками в четвертом формате (эмулируем различия в API чека)
    echo "4. Создание чека с марками в другом формате...\n";
    
    // В чеке марки приходят в еще одном формате
    $checkMarkFormat = trim($originalMark) . ' '; // С пробелом в конце
    $checkMark = new MarkingCode($checkMarkFormat);
    
    $items = [
        new CheckItem('Обычный товар', 100.00, 1, 100.00),
        new CheckItem('Маркированный товар 1', 250.50, 1, 250.50, $checkMark),
        new CheckItem('Еще один товар', 75.25, 2, 150.50)
    ];
    
    $payments = [Payment::cash(501.00)];
    
    $check = new Check($items, $payments, 'Иванов И.И.', Check::TYPE_SELL, 'osn');
    
    echo "  Чек создан с маркой: {$checkMark->getRawCode()}\n";
    echo "  CleanCode марки в чеке: {$checkMark->getCleanCode()}\n";
    echo "  Марка в чеке совпадает с проверенными: " . ($checkMark->equals($mark1) ? 'ДА' : 'НЕТ') . "\n\n";
    
    // 5. Проверяем статистику сопоставления
    echo "5. Статистика сопоставления...\n";
    
    $stats = $matchingService->getMatchingStats($check);
    echo "  Всего позиций в чеке: {$stats['total_items']}\n";
    echo "  Маркированных позиций: {$stats['marked_items']}\n";
    echo "  Проверенных марок: {$stats['checked_marks']}\n";
    echo "  Полностью проверенных: {$stats['fully_checked_marks']}\n";
    echo "  Покрытие проверками: {$stats['check_coverage_percent']}%\n";
    
    if (!empty($stats['unchecked_marks'])) {
        echo "  Непроверенные марки:\n";
        foreach ($stats['unchecked_marks'] as $unchecked) {
            echo "    - {$unchecked['item_name']}: {$unchecked['raw_code']}\n";
        }
    }
    echo "\n";
    
    // 6. Тестируем сопоставление
    echo "6. Тестирование сопоставления...\n";
    
    try {
        $matchData = $matchingService->matchCheckItems($check, false);
        echo "  Сопоставление выполнено успешно\n";
        echo "  Найдено совпадений: " . count($matchData) . "\n";
        
        foreach ($matchData as $cleanCode => $data) {
            $item = $data['item'];
            $hasPermit = $data['permit_check'] ? 'ДА' : 'НЕТ';
            $hasEcr = $data['ecr_check'] ? 'ДА' : 'НЕТ';
            $fullyChecked = $data['is_fully_checked'] ? 'ДА' : 'НЕТ';
            
            echo "    - {$item->getName()}: Разрешительный={$hasPermit}, ККТ={$hasEcr}, Полная проверка={$fullyChecked}\n";
        }
    } catch (MarkMatchingException $e) {
        echo "  ОШИБКА сопоставления: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 7. Печатаем чек
    echo "7. Печать чека...\n";
    
    $printResult = $printCheckUseCase->execute($check, false); // Не требуем полной проверки
    
    if ($printResult->success) {
        echo "  ✓ Чек напечатан успешно\n";
        $printData = $printResult->getData();
        echo "  Обработано маркированных позиций: {$printData['marked_items_processed']}\n";
        echo "  Получено данных проверок: {$printData['mark_check_data_received']}\n";
        
        echo "\n  Содержимое чека:\n";
        foreach ($printData['printed_lines'] as $line) {
            echo "    {$line}\n";
        }
    } else {
        echo "  ✗ Ошибка печати: " . $printResult->error . "\n";
    }
    echo "\n";
    
    // 8. Тестируем строгий режим
    echo "8. Тестирование строгого режима (требование полной проверки)...\n";
    
    $strictResult = $printCheckUseCase->execute($check, true);
    
    if ($strictResult->success) {
        echo "  ✓ Строгий режим пройден\n";
    } else {
        echo "  ✗ Строгий режим не пройден: " . $strictResult->error . "\n";
    }
    echo "\n";
    
    // 9. Статистика реестра
    echo "9. Статистика реестра проверок...\n";
    
    $registryStats = $registry->getCheckStats();
    echo "  Проверок в разрешительном режиме: {$registryStats['permit_total']} (успешных: {$registryStats['permit_success']})\n";
    echo "  Проверок на ККТ: {$registryStats['ecr_total']} (успешных: {$registryStats['ecr_success']})\n";
    echo "  Всего уникальных кодов: " . count($registry->getAllCheckedCodes()) . "\n";
    
    echo "\n✅ ИНТЕГРАЦИОННЫЙ ТЕСТ ЗАВЕРШЕН УСПЕШНО!\n";
    
} catch (Exception $e) {
    echo "\n❌ ОШИБКА В ТЕСТЕ: " . $e->getMessage() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
}