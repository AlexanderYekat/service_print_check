<?php
require_once __DIR__ . '/../../interface/PrinterInterface.php';
require_once __DIR__ . '/../../domain/model/Check.php';
require_once __DIR__ . '/../../domain/model/OperationResult.php';

class FakePrinterAdapter implements PrinterInterface
{
    public function printCheck(Check $check): OperationResult
    {
        return OperationResult::success("Печать в тестовом режиме", [
            'printed_lines' => ["ТЕСТОВЫЙ ЧЕК", "Эмуляция печати"],
            'fiscal_data' => null
        ]);
    }
}
