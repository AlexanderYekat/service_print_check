<?php

class FakePrinterAdapter implements PrinterInterface
{
    public function printCheck(Check $check): PrintResult
    {
        return new PrintResult(true, "Печать в тестовом режиме");
    }
}
