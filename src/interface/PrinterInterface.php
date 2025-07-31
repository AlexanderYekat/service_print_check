<?php

namespace App\Interface;

use App\Domain\Model\OperationResult;
use App\Domain\Model\Check;

interface PrinterInterface {
    /**
     * Печатает чек
     * @param Check $check Чек для печати
     * @param array $markCheckData Данные проверок маркировок: cleanCode => данные проверки
     * @return OperationResult
     */
    public function printCheck(Check $check, array $markCheckData = []): OperationResult;
}