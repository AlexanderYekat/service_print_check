<?php
/**
 * Полный тест получения веса от начала до конца
 * 
 * Запускает все тесты связанные с получением веса:
 * - Unit тесты GetWeightUseCase
 * - End-to-End тесты через HTTP API
 */

// Буферизация вывода для предотвращения "headers already sent"
ob_start();

echo "🚀 Полное тестирование получения веса\n";
echo "==================================\n\n";

$passed = 0;
$total = 0;

// 1. Запуск Unit тестов GetWeightUseCase
echo "📋 Запуск Unit тестов GetWeightUseCase...\n";
require_once __DIR__ . '/../unit/domain/GetWeightUseCaseTest.php';

try {
    $unitTest = new GetWeightUseCaseDomainTest();
    if ($unitTest->run()) {
        echo "✅ Unit тесты GetWeightUseCase: PASSED\n\n";
        $passed++;
    } else {
        echo "❌ Unit тесты GetWeightUseCase: FAILED\n\n";
    }
} catch (Exception $e) {
    echo "❌ Unit тесты GetWeightUseCase: ERROR - " . $e->getMessage() . "\n\n";
}
$total++;

// 2. Запуск End-to-End тестов
echo "📋 Запуск End-to-End тестов получения веса...\n";
require_once __DIR__ . '/GetWeightEndToEndTest.php';

try {
    $e2eTest = new GetWeightEndToEndTest();
    if ($e2eTest->run()) {
        echo "✅ End-to-End тесты получения веса: PASSED\n\n";
        $passed++;
    } else {
        echo "❌ End-to-End тесты получения веса: FAILED\n\n";
    }
} catch (Exception $e) {
    echo "❌ End-to-End тесты получения веса: ERROR - " . $e->getMessage() . "\n\n";
}
$total++;

// Общий результат
echo "📊 ОБЩИЙ РЕЗУЛЬТАТ ТЕСТИРОВАНИЯ ПОЛУЧЕНИЯ ВЕСА\n";
echo "=============================================\n";
echo "Пройдено: $passed/$total блоков тестов\n";

if ($passed === $total) {
    echo "🎉 ВСЕ ТЕСТЫ ПОЛУЧЕНИЯ ВЕСА УСПЕШНО ПРОЙДЕНЫ!\n";
    echo "\n✅ Система получения веса работает корректно:\n";
    echo "   • Unit тесты бизнес-логики пройдены\n";
    echo "   • End-to-End тесты HTTP API пройдены\n";
    echo "   • Обработка ошибок работает правильно\n";
    echo "   • Различные конфигурации обрабатываются корректно\n";
    exit(0);
} else {
    echo "⚠️  НЕКОТОРЫЕ ТЕСТЫ НЕ ПРОЙДЕНЫ\n";
    echo "\n🔍 Рекомендации:\n";
    echo "   • Проверьте журналы ошибок выше\n";
    echo "   • Убедитесь в правильности конфигурации весов\n";
    echo "   • Проверьте доступность COM компонентов (если нужны)\n";
    echo "   • Исправьте найденные проблемы и повторите тестирование\n";
    exit(1);
}

// Завершение буферизации
ob_end_flush();