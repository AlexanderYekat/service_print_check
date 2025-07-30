<?php
interface PrinterInterface {
    public function printCheck(Check $check): PrintResult;
}