<?php
// CheckService.php

require_once 'kktutils.php';

class CheckService {
    private $FptrDriver;
    private $bankComObject;
    private $scaleComObject;

    public function __construct(TFptr10Driver $FptrDriver, $bankComObject = null, $scaleComObject = null) {
        $this->FptrDriver = $FptrDriver;
        $this->bankComObject = $bankComObject;
        $this->scaleComObject = $scaleComObject;
    }

    public function printCheck($checkData) {
        // Проверяем, что $checkData является массивом
        if (!is_array($checkData)) {
            return ['success' => false, 'message' => 'Неверный формат данных чека.'];
        }

        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $formattedCheck = kktutils_formatCheckJSON($checkData);
        if ($formattedCheck['error']) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => $formattedCheck['message']];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        list($success, $message) = kktutils_sendCommandAndGetAnswerFromKKT($fptrCom, $formattedCheck['checkData'], $emulation);

        $this->FptrDriver->Close();

        if ($success) {
            return ['success' => true, 'message' => 'Чек успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } else {
            return ['success' => false, 'message' => $message];
        }
    }

    public function closeShift() {
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            // Открытие смены, если она закрыта (дополнительная проверка)
            if (!$fptrCom->IsShiftOpened()) {
                $fptrCom->OpenShift();
            }
            $fptrCom->CloseShift();
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Смена успешно закрыта', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка закрытия смены: ' . $e->getMessage()];
        }
    }

    public function printXReport() {
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->PrintXReport();
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'X-отчёт успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка печати X-отчёта: ' . $e->getMessage()];
        }
    }

    public function cashIn($amount) {
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->CashIn($amount);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Внесение успешно выполнено', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка внесения наличных: ' . $e->getMessage()];
        }
    }

    public function cashOut($amount) {
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->CashOut($amount);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Выплата успешно выполнена', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка выплаты наличных: ' . $e->getMessage()];
        }
    }

    public function bankOperation($bankComObjectFromHandler, $operation, $params) {
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован.'];
        }
        // Здесь должна быть логика работы с банковским COM-объектом
        // Пример:
        // try {
        //     $bankComObject->Connect();
        //     $bankComObject->DoOperation($operation, $params);
        //     $bankComObject->Disconnect();
        //     return ['success' => true, 'message' => 'Банковская операция выполнена', 'operation' => $operation, 'params' => $params];
        // } catch (Exception $e) {
        //     return ['success' => false, 'message' => 'Ошибка банковской операции: ' . $e->getMessage()];
        // }

        return ['success' => false, 'message' => 'Метод bankOperation еще не реализован полностью.'];
    }

    public function getWeight($scaleComObjectFromHandler) {
        $scaleComObject = $scaleComObjectFromHandler ?? $this->scaleComObject;
        if ($scaleComObject === null) {
            return ['success' => false, 'message' => 'COM-объект весов не инициализирован.'];
        }
        // Логика для получения веса, пока без изменений
        // Пример:
        // try {
        //     $scaleComObject->Connect();
        //     $weight = $scaleComObject->GetWeight();
        //     $scaleComObject->Disconnect();
        //     return ['success' => true, 'message' => 'Вес получен', 'weight' => $weight];
        // } catch (Exception $e) {
        //     return ['success' => false, 'message' => 'Ошибка получения веса: ' . $e->getMessage()];
        // }

        return ['success' => false, 'message' => 'Метод getWeight еще не реализован полностью.'];
    }

    public function printBankSlip($slipLines) {
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            foreach ($slipLines as $line) {
                $fptrCom->PrintString($line);
            }
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Банковский слип успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка печати банковского слипа: ' . $e->getMessage()];
        }
    }

    public function returnMany($bankComObjectFromHandler, $params) {
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован для returnMany.'];
        }
        // Пока без изменений, здесь должна быть логика с $bankComObject
        return ['success' => false, 'message' => 'Метод returnMany еще не реализован полностью.'];
    }

    public function closeBankShift($bankComObjectFromHandler) {
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован для closeBankShift.'];
        }
        // Пока без изменений, здесь должна быть логика с $bankComObject
        return ['success' => false, 'message' => 'Метод closeBankShift еще не реализован полностью.'];
    }
} 