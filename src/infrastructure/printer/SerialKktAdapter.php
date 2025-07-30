<?php
require_once __DIR__ . '/../../interface/PrinterInterface.php';
require_once __DIR__ . '/../../domain/model/PrintResult.php';

class SerialKktAdapter implements PrinterInterface
{
    private $driverClass;
    private $comPort;
    private $emulation;

    public function __construct($driverClass, $comPort, $emulation = false)
    {
        $this->driverClass = $driverClass;
        $this->comPort = $comPort;
        $this->emulation = $emulation;
    }

    public function printCheck(Check $check): PrintResult
    {
        try {
            // 1. Создаём COM-объект (или эмулятор, если нужно)
            if ($this->emulation) {
                // Тестовая печать — имитируем успешный чек
                return new PrintResult(true, "Эмуляция печати чека", ["ТЕСТОВЫЙ ЧЕК", "ОК"]);
            }
            $com = new COM($this->driverClass); // например, "ShtrihM.FPrnM45" или другое
            $com->OpenPort($this->comPort);

            // 2. Формируем чек (пример, псевдокод!)
            foreach ($check->tableData as $item) {
                $com->AddItem($item['name'], $item['price'], $item['quantity']);
            }

            foreach ($check->payments as $payment) {
                $com->AddPayment($payment['type'], $payment['amount']);
            }

            // 3. Устанавливаем тип операции (продажа/возврат)
            if ($check->type === 'return') {
                $com->SetCheckType('return');
            } else {
                $com->SetCheckType('sell');
            }

            $com->SetCashier($check->cashier);

            // 4. Печать чека
            $com->PrintCheck();

            // 5. Получаем строки для ответа (например, содержимое чека или статус)
            $lines = [$com->GetLastCheckText() ?? "Чек успешно напечатан"];

            $com->ClosePort();

            return new PrintResult(true, null, $lines);
        } catch (Throwable $e) {
            return new PrintResult(false, "Ошибка печати чека: " . $e->getMessage());
        }
    }
}
