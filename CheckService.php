<?php
// CheckService.php

require_once 'kktutils.php';
require_once 'logger.php'; // Подключаем логгер

class CheckService {
    private $FptrDriver;
    private $bankComObject;
    private $scaleComObject;
    private $logger;

    public function __construct(TFptr10Driver $FptrDriver, Logger $logger, $bankComObject = null, $scaleComObject = null) {
        $this->FptrDriver = $FptrDriver;
        $this->logger = $logger;
        $this->bankComObject = $bankComObject;
        $this->scaleComObject = $scaleComObject;
    }

    /**
     * @return array
     */
    public function printCheck($checkData) {
        // Проверяем, что $checkData является массивом
        if (!is_array($checkData)) {
            $this->logger->error("Неверный формат данных чека: данные не являются массивом.");
            return ['success' => false, 'message' => 'Неверный формат данных чека.'];
        }

        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $formattedCheck = kktutils_formatCheckJSON($checkData);
        if ($formattedCheck['error']) {
            $this->logger->error("Ошибка форматирования JSON для чека: " . $formattedCheck['message']);
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => $formattedCheck['message']];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        $this->logger->info("Отправка команды печати чека на ККТ. Эмуляция: " . ($emulation ? 'Да' : 'Нет'));
        list($success, $message) = kktutils_sendCommandAndGetAnswerFromKKT($fptrCom, $formattedCheck['checkData'], $emulation);

        $this->FptrDriver->Close();

        if ($success) {
            $this->logger->info("Чек успешно напечатан. Тип подключения: " . $typeConnect);
            return ['success' => true, 'message' => 'Чек успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } else {
            $this->logger->error("Ошибка печати чека: " . $message);
            return ['success' => false, 'message' => $message];
        }
    }

    /**
     * @return array
     */
    public function closeShift() {
        $this->logger->info("Попытка закрытия смены.");
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при закрытии смены: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            // Открытие смены, если она закрыта (дополнительная проверка)
            if (!$fptrCom->IsShiftOpened()) {
                $this->logger->warning("Смена не была открыта, пытаемся открыть перед закрытием.");
                $fptrCom->OpenShift();
            }
            $fptrCom->CloseShift();
            $this->logger->info("Смена успешно закрыта. Тип подключения: " . $typeConnect);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Смена успешно закрыта', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка закрытия смены: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка закрытия смены: ' . $e->getMessage()];
        }
    }

    public function printXReport() {
        $this->logger->info("Попытка печати X-отчёта.");
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при печати X-отчёта: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->PrintXReport();
            $this->logger->info("X-отчёт успешно напечатан. Тип подключения: " . $typeConnect);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'X-отчёт успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка печати X-отчёта: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка печати X-отчёта: ' . $e->getMessage()];
        }
    }

    public function cashIn($amount) {
        $this->logger->info("Попытка внесения наличных: " . $amount);
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при внесении наличных: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->CashIn($amount);
            $this->logger->info("Внесение наличных успешно выполнено. Сумма: " . $amount . ", Тип подключения: " . $typeConnect);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Внесение успешно выполнено', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка внесения наличных: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка внесения наличных: ' . $e->getMessage()];
        }
    }

    public function cashOut($amount) {
        $this->logger->info("Попытка выплаты наличных: " . $amount);
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при выплате наличных: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            $fptrCom->CashOut($amount);
            $this->logger->info("Выплата наличных успешно выполнена. Сумма: " . $amount . ", Тип подключения: " . $typeConnect);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Выплата успешно выполнена', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка выплаты наличных: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка выплаты наличных: ' . $e->getMessage()];
        }
    }

    public function bankOperation($bankComObjectFromHandler, $operation, $params) {
        $this->logger->info("Попытка банковской операции: " . $operation);
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            $this->logger->error("COM-объект банка не инициализирован для bankOperation.");
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован.'];
        }
        // Здесь должна быть логика работы с банковским COM-объектом
        // Пример:
        // try {
        //     $bankComObject->Connect();
        //     $bankComObject->DoOperation($operation, $params);
        //     $bankComObject->Disconnect();
        //     $this->logger->info("Банковская операция \"" . $operation . "\" успешно выполнена.");
        //     return ['success' => true, 'message' => 'Банковская операция выполнена', 'operation' => $operation, 'params' => $params];
        // } catch (Exception $e) {
        //     $this->logger->error("Ошибка банковской операции: " . $e->getMessage());
        //     return ['success' => false, 'message' => 'Ошибка банковской операции: ' . $e->getMessage()];
        // }

        $this->logger->warning("Метод bankOperation еще не реализован полностью.");
        return ['success' => false, 'message' => 'Метод bankOperation еще не реализован полностью.'];
    }

    public function getWeight($scaleComObjectFromHandler) {
        $this->logger->info("Попытка получения веса.");
        $scaleComObject = $scaleComObjectFromHandler ?? $this->scaleComObject;
        if ($scaleComObject === null) {
            $this->logger->error("COM-объект весов не инициализирован для getWeight.");
            return ['success' => false, 'message' => 'COM-объект весов не инициализирован.'];
        }
        // Логика для получения веса, пока без изменений
        // Пример:
        // try {
        //     $scaleComObject->Connect();
        //     $weight = $scaleComObject->GetWeight();
        //     $scaleComObject->Disconnect();
        //     $this->logger->info("Вес успешно получен: " . $weight);
        //     return ['success' => true, 'message' => 'Вес получен', 'weight' => $weight];
        // } catch (Exception $e) {
        //     $this->logger->error("Ошибка получения веса: " . $e->getMessage());
        //     return ['success' => false, 'message' => 'Ошибка получения веса: ' . $e->getMessage()];
        // }

        $this->logger->warning("Метод getWeight еще не реализован полностью.");
        return ['success' => false, 'message' => 'Метод getWeight еще не реализован полностью.'];
    }

    public function printBankSlip($slipLines) {
        $this->logger->info("Попытка печати банковского слипа.");
        list($isOpened, $typeConnect) = kktutils_connectWithKassa($this->FptrDriver);
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при печати банковского слипа: " . $typeConnect);
            return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' . $typeConnect];
        }

        $fptrCom = $this->FptrDriver->GetFptr10();
        $emulation = $this->FptrDriver->getEmulation();

        try {
            foreach ($slipLines as $line) {
                $fptrCom->PrintString($line);
            }
            $this->logger->info("Банковский слип успешно напечатан. Тип подключения: " . $typeConnect);
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Банковский слип успешно напечатан', 'data' => ['typeConnect' => $typeConnect]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка печати банковского слипа: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка печати банковского слипа: ' . $e->getMessage()];
        }
    }

    public function returnMany($bankComObjectFromHandler, $params) {
        $this->logger->info("Попытка операции returnMany.");
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            $this->logger->error("COM-объект банка не инициализирован для returnMany.");
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован для returnMany.'];
        }
        // Пока без изменений, здесь должна быть логика с $bankComObject
        $this->logger->warning("Метод returnMany еще не реализован полностью.");
        return ['success' => false, 'message' => 'Метод returnMany еще не реализован полностью.'];
    }

    public function closeBankShift($bankComObjectFromHandler) {
        $this->logger->info("Попытка закрытия банковской смены.");
        $bankComObject = $bankComObjectFromHandler ?? $this->bankComObject;
        if ($bankComObject === null) {
            $this->logger->error("COM-объект банка не инициализирован для closeBankShift.");
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован для closeBankShift.'];
        }
        // Пока без изменений, здесь должна быть логика с $bankComObject
        $this->logger->warning("Метод closeBankShift еще не реализован полностью.");
        return ['success' => false, 'message' => 'Метод closeBankShift еще не реализован полностью.'];
    }
} 