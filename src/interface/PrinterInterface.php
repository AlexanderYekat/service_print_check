<?php
require_once __DIR__ . '/../domain/model/OperationResult.php';
require_once __DIR__ . '/../domain/model/Check.php';

interface PrinterInterface {
    /**
     * Печатает чек
     * @param Check $check Чек для печати
     * @param array $markCheckData Данные проверок маркировок: cleanCode => данные проверки
     * @return OperationResult
     */
    public function printCheck(Check $check, array $markCheckData = []): OperationResult;
}