<?php

namespace App\Infrastructure\Printer;

use App\Interface\PrinterInterface;
use App\Domain\Model\Check;
use App\Domain\Model\OperationResult;

class FakePrinterAdapter implements PrinterInterface
{
    public function printCheck(Check $check, array $markCheckData = []): OperationResult
    {
        $lines = ["ТЕСТОВЫЙ ЧЕК", "Эмуляция печати"];
        
        // Добавляем информацию о маркированных товарах для тестирования
        $markedItemsCount = 0;
        foreach ($check->getItems() as $item) {
            if ($item->hasMarkingCode()) {
                $markedItemsCount++;
                $markingCode = $item->getMarkingCode();
                $cleanCode = $markingCode->getCleanCode();
                
                $lines[] = "Маркированный товар: " . $item->getName();
                $lines[] = "Марка: " . $markingCode->getRawCode();
                
                if (isset($markCheckData[$cleanCode])) {
                    $checkData = $markCheckData[$cleanCode];
                    $permitStatus = $checkData['permit_check'] ? 'ОК' : 'НЕТ';
                    $ecrStatus = $checkData['ecr_check'] ? 'ОК' : 'НЕТ';
                    $lines[] = "Проверки: Разрешительный={$permitStatus}, ККТ={$ecrStatus}";
                } else {
                    $lines[] = "Проверки: НЕ НАЙДЕНЫ";
                }
            }
        }
        
        if ($markedItemsCount > 0) {
            $lines[] = "Всего маркированных позиций: {$markedItemsCount}";
        }
        
        return OperationResult::success("Печать в тестовом режиме", [
            'printed_lines' => $lines,
            'fiscal_data' => null,
            'marked_items_processed' => $markedItemsCount,
            'mark_check_data_received' => count($markCheckData)
        ]);
    }
}
