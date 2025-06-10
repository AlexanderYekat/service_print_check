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

        $emulation = $this->FptrDriver->getEmulation();

        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => "Ошибка подключения к ККТ: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})"];
            }
        }

        $formattedCheck = $this->FptrDriver->formatCheckJSON($checkData);
        if (!$formattedCheck['success']) {
            $this->logger->error("Ошибка форматирования JSON для чека: " . $formattedCheck['message']);
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => $formattedCheck['message']];
        }
        $checkJsonData = $formattedCheck['checkData'];

        $this->logger->info("Отправка команды печати чека на ККТ. Эмуляция: " . ($emulation ? 'Да' : 'Нет'));
        list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->sendCommandAndGetAnswerFromKKT($checkJsonData);

        $this->FptrDriver->Close();

        if ($success) {
            $this->logger->info("Чек успешно напечатан. Ответ: {$responseJson}");
            return ['success' => true, 'message' => 'Чек успешно напечатан', 'data' => ['response' => json_decode($responseJson, true)]];
        } else {
            $this->logger->error("Ошибка печати чека: {$responseJson} (Код: {$commandErrorDesc})");
            return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
        }
    }

    /**
     * @return array
     */
    public function CloseShift(string $cashier) {
        $this->logger->info("Попытка закрытия смены.");
        if ($cashier === "") {
            $this->logger->error("Ошибка закрытия смены: не указан кассир.");
        	return ['success' => false, 'message' => 'Ошибка закрытия смены: не указан кассир.'];
        }
        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        $emulation = $this->FptrDriver->getEmulation();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при закрытии смены: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' .  $this->FptrDriver->GetTypeConnection() . " (Код: " . $connectErrorDesc . ")"];
            }
        }

        try {
            // если смена уже закрыта (дополнительная проверка), то не открываем её
            list($success, $commandErrorDesc) = $this->FptrDriver->IsShiftOpened();
            if (!$this->FptrDriver->IsShiftOpened() && $commandErrorDesc === "") {
                $this->logger->warning(message: "Смена уже закрыта");
                return ['success' => false, 'message' => 'Ошибка закрытия смены: смена уже закрыта'];
            }

            if ($commandErrorDesc!="") {
                $this->logger->error("Ошибка закрытия смены: {$commandErrorDesc}");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "Ошибка закрытия смены: {$commandErrorDesc}"];
            }

            list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->CloseShift($cashier);
            if (!$success) {
                $this->logger->error("Ошибка закрытия смены: {$responseJson} (Код: {$commandErrorDesc})");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
            }
            $this->logger->info("Смена успешно закрыта.");
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Смена успешно закрыта', 'data' => ['response' => json_decode($responseJson, true)]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка закрытия смены: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка закрытия смены: ' . $e->getMessage()];
        }
    }

    public function printXReport(string $cashier = "") {
        $this->logger->info("Попытка печати X-отчёта.");
        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        $emulation = $this->FptrDriver->getEmulation();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при закрытии смены: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => 'Ошибка подключения к ККТ: ' .  $this->FptrDriver->GetTypeConnection() . " (Код: " . $connectErrorDesc . ")"];
            }
        }

        if ($cashier === "") {
            $cashier = "Кассир";
        }

        try {
            list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->PrintXReport($cashier);
            if (!$success) {
                $this->logger->error("Ошибка печати X-отчета. (Код: {$commandErrorDesc})");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
            }

            $this->logger->info("X-отчёт успешно напечатан.");
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'X-отчёт успешно напечатан', 'data' => ['response' => json_decode($responseJson, true)]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка печати X-отчёта: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка печати X-отчёта: ' . $e->getMessage()];
        }
    }

    public function cashIn($cashier, $amount) {
        $this->logger->info("Попытка внесения наличных: {$amount}");
        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        $emulation = $this->FptrDriver->getEmulation();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при внесении наличных: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => 'Ошибка подключения к ККТ при внесении наличных: ' .  $this->FptrDriver->GetTypeConnection() . " (Код: " . $connectErrorDesc . ")"];
            }
        }

        try {
            list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->CashIn($amount, $cashier);
            if (!$success) {
                $this->logger->error("Ошибка внесения наличных. (Код: {$commandErrorDesc})");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
            }
            $this->logger->info("Внесение наличных успешно выполнено. Сумма: {$amount}.");
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Внесение успешно выполнено', 'data' => ['response' => json_decode($responseJson, true)]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка внесения наличных: " . $e->getMessage());
            $this->FptrDriver->Close();
            return ['success' => false, 'message' => 'Ошибка внесения наличных: ' . $e->getMessage()];
        }
    }

    public function cashOut($cashier, $amount) {
        $this->logger->info("Попытка выплаты наличных: {$amount}");
        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        $emulation = $this->FptrDriver->getEmulation();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при выплате наличных: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => 'Ошибка подключения к ККТ при выплате наличных: ' .  $this->FptrDriver->GetTypeConnection() . " (Код: " . $connectErrorDesc . ")"];
            }
        }

        try {
            list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->CashOut($amount, $cashier);
            if (!$success) {
                $this->logger->error("Ошибка выплаты наличных. (Код: {$commandErrorDesc})");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
            }
            $this->logger->info("Выплата наличных успешно выполнена. Сумма: {$amount}.");
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Выплата успешно выполнена', 'data' => ['response' => json_decode($responseJson, true)]];
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

    public function printBankSlip(array $slipLines) {
        $this->logger->info("Попытка печати банковского слипа.");
        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        $emulation = $this->FptrDriver->getEmulation();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ при печати банковского слипа: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => 'Ошибка подключения к ККТ при печати банковского слипа: ' .  $this->FptrDriver->GetTypeConnection() . " (Код: " . $connectErrorDesc . ")"];
            }
        }

        try {
            $items = [];
            foreach ($slipLines as $line) {
                $items[] = [
                    "type" => "text",
                    "text" => $line,
                    "alignment" => "center"
                ];
            }

            $nonFiscalDocument = [
                "type" => "nonFiscal",
                "items" => $items
            ];

            list($success, $responseJson, $commandErrorDesc) = $this->FptrDriver->sendCommandAndGetAnswerFromKKT(json_encode($nonFiscalDocument, JSON_UNESCAPED_UNICODE));
            if (!$success) {
                $this->logger->error("Ошибка печати банковского слипа. (Код: {$commandErrorDesc})");
                $this->FptrDriver->Close();
                return ['success' => false, 'message' => "{$responseJson} (Код: {$commandErrorDesc})"];
            }

            $this->logger->info("Банковский слип успешно напечатан.");
            $this->FptrDriver->Close();
            return ['success' => true, 'message' => 'Банковский слип успешно напечатан', 'data' => ['response' => json_decode($responseJson, true)]];
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
            $this->logger->error("COM-объект банка не инициализирован.");
            return ['success' => false, 'message' => 'COM-объект банка не инициализирован.'];
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