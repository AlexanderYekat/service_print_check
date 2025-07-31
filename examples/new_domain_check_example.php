<?php
/**
 * Пример использования новой доменной модели чека
 * Демонстрирует создание и работу с строго типизированной структурой
 */

require_once __DIR__ . '/../src/domain/model/MarkingCode.php';
require_once __DIR__ . '/../src/domain/model/CheckItem.php';
require_once __DIR__ . '/../src/domain/model/Payment.php';
require_once __DIR__ . '/../src/domain/model/Check.php';

echo "=== Пример использования новой доменной модели чека ===\n\n";

try {
    // 1. Создаем позиции чека
    echo "1. Создание позиций чека:\n";
    
    $items = [];
    
    // Обычный товар без маркировки
    $items[] = new CheckItem(
        'Хлеб "Бородинский"',
        45.50,
        2,
        91.00
    );
    
    // Товар с маркировкой
    $markingCode = new MarkingCode('0104607001906529212WPG80WQKBGVDLVQ');
    $items[] = new CheckItem(
        'Молоко "Домик в деревне" 3.2%',
        89.90,
        1,
        89.90,
        $markingCode
    );
    
    // Товар с дробным количеством
    $items[] = new CheckItem(
        'Колбаса "Докторская" (вес)',
        450.00,
        1,
        360.00  // 0.8 кг
    );
    
    foreach ($items as $i => $item) {
        echo sprintf(
            "  Позиция %d: %s - %d шт. по %.2f руб. = %.2f руб.%s\n",
            $i + 1,
            $item->getName(),
            $item->getQuantity(),
            $item->getPrice(),
            $item->getSum(),
            $item->hasMarkingCode() ? ' (с маркировкой)' : ''
        );
    }
    
    // 2. Создаем платежи
    echo "\n2. Создание платежей:\n";
    
    $payments = [];
    $payments[] = Payment::cash(200.00);   // Наличные
    $payments[] = Payment::card(340.90);   // Карта
    
    foreach ($payments as $i => $payment) {
        echo sprintf(
            "  Платеж %d: %s - %.2f руб.\n",
            $i + 1,
            $payment->getType() === 'cash' ? 'Наличные' : 'Карта',
            $payment->getAmount()
        );
    }
    
    // 3. Создаем чек
    echo "\n3. Создание чека:\n";
    
    $check = new Check(
        $items,
        $payments,
        'Иванов И.И.',
        Check::TYPE_SELL,
        'osn'
    );
    
    echo sprintf("  Кассир: %s\n", $check->getCashier());
    echo sprintf("  Тип: %s\n", $check->getType());
    echo sprintf("  Система налогообложения: %s\n", $check->getTaxationSystem());
    
    // 4. Бизнес-логика и расчеты
    echo "\n4. Бизнес-расчеты:\n";
    
    echo sprintf("  Общая сумма товаров: %.2f руб.\n", $check->getTotalAmount());
    echo sprintf("  Общая сумма платежей: %.2f руб.\n", $check->getTotalPayments());
    echo sprintf("  Количество позиций: %d\n", $check->getItemsCount());
    echo sprintf("  Позиций с маркировкой: %d\n", count($check->getMarkedItems()));
    echo sprintf("  Платежи сбалансированы: %s\n", $check->isPaymentBalanced() ? 'Да' : 'Нет');
    echo sprintf("  Все суммы корректны: %s\n", $check->areAllItemSumsCorrect() ? 'Да' : 'Нет');
    
    // Детализация платежей
    echo sprintf("  Сумма наличными: %.2f руб.\n", $check->getCashAmount());
    echo sprintf("  Сумма безналично: %.2f руб.\n", $check->getElectronicAmount());
    
    // 5. Проверки типа чека
    echo "\n5. Проверки типа чека:\n";
    echo sprintf("  Это продажа: %s\n", $check->isSell() ? 'Да' : 'Нет');
    echo sprintf("  Это возврат: %s\n", $check->isRefund() ? 'Да' : 'Нет');
    
    // 6. Работа с маркированными товарами
    echo "\n6. Маркированные товары:\n";
    $markedItems = $check->getMarkedItems();
    foreach ($markedItems as $item) {
        $code = $item->getMarkingCode();
        echo sprintf(
            "  %s:\n    Raw код: %s\n    Clean код: %s\n    Short код: %s\n",
            $item->getName(),
            $code->getRawCode(),
            substr($code->getCleanCode(), 0, 30) . '...',
            $code->getShortCode()
        );
    }
    
    // 7. Сериализация для API
    echo "\n7. Сериализация для API-слоя:\n";
    $arrayData = $check->toArray();
    echo "  Структура для JSON API:\n";
    echo sprintf("    tableData: %d позиций\n", count($arrayData['tableData']));
    echo sprintf("    payments: %d платежей\n", count($arrayData['payments']));
    echo sprintf("    totalAmount: %.2f\n", $arrayData['totalAmount']);
    echo sprintf("    cashier: %s\n", $arrayData['cashier']);
    
    // 8. Создание из массива (обратная совместимость)
    echo "\n8. Обратная совместимость - создание из массива:\n";
    
    $legacyData = [
        'tableData' => [
            [
                'name' => 'Тестовый товар',
                'price' => 100.0,
                'quantity' => 1,
                'sum' => 100.0,
                'marking_code' => '0104607001906529212WPG80WQKBGVDLVQ'
            ]
        ],
        'payments' => [
            ['type' => 'cash', 'amount' => 100.0]
        ],
        'cashier' => 'Петров П.П.',
        'type' => 'sell',
        'taxationSystem' => 'osn'
    ];
    
    $checkFromArray = Check::fromArray($legacyData);
    echo sprintf("  Создан чек из массива: %.2f руб., %d позиций\n", 
        $checkFromArray->getTotalAmount(), 
        $checkFromArray->getItemsCount()
    );
    
    echo "\n=== Все примеры выполнены успешно! ===\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
}