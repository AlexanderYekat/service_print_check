<?php
require_once __DIR__ . '/../domain/model/OperationResult.php';

interface PrinterInterface {
    public function printCheck(Check $check): OperationResult;
}