<?php
// CheckService.php

require_once 'kktutils.php';
require_once 'logger.php'; // Подключаем логгер
require_once 'scaleutils.php'; // Подключаем утилиты для работы с весами

class CheckService {
    private $FptrDriver;
    private $bankDriver;
    private $scaleObject;
    private $logger;

    public function __construct(TFptr10Driver $FptrDriver, Logger $logger, TBankDriver $bankDriver = null, TScale8Driver $scaleObject = null) {
        $this->FptrDriver = $FptrDriver;
        $this->logger = $logger;
        $this->bankDriver = $bankDriver;
        $this->scaleObject = $scaleObject;
    }

    /**
     * Вспомогательный метод для выполнения операций с ККТ.
     *
     * @param callable $operationCallable Callable, представляющий операцию с ККТ.
     * @param array $params Параметры для операции.
     * @param string $operationName Название операции для логирования.
     * @return array Результат операции.
     */
    private function _executeFptrOperation(callable $operationCallable, array $params, string $operationName): array {
        $this->logger->info("Попытка выполнения операции с ККТ: {$operationName}.");

        if ($this->FptrDriver === null) {
            $this->logger->error("Драйвер ККТ не инициализирован для {$operationName}.");
            return ['success' => false, 'message' => 'Драйвер ККТ не инициализирован.'];
        }

        $emulation = $this->FptrDriver->getEmulation();

        $success = false;
        $actualResponseString = "";
        $actualCommandErrorDesc = "";
        $connectErrorDesc = "";

        list($isOpened, $connectErrorDesc) = $this->FptrDriver->Open();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к ККТ для {$operationName}: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})");
            if (!$emulation) {
                return ['success' => false, 'message' => "Ошибка подключения к ККТ: {$this->FptrDriver->GetTypeConnection()} (Код: {$connectErrorDesc})"];
            }
        }

        try {
            list($success, $actualResponseString, $actualCommandErrorDesc) = call_user_func_array($operationCallable, $params);
        } catch (Exception $e) {
            $this->logger->error("Исключение при выполнении операции '{$operationName}' с ККТ: " . $e->getMessage());
            $success = false;
            $actualResponseString = json_encode(['error' => $e->getMessage()]);
        } finally {
            $this->FptrDriver->Close();
        }

        $finalSuccess = $emulation ? true : $success;
        $finalMessage = "";
        
        if ($finalSuccess) {
            $finalMessage = "Операция '{$operationName}' успешно выполнена.";
            $this->logger->info("Операция '{$operationName}' успешно выполнена. Ответ: " . ($actualResponseString ? $actualResponseString : "Нет ответа"));
            return ['success' => true, 'message' => $finalMessage, 'data' => ['response' => json_decode($actualResponseString, true)]];
        } else {
            $finalMessage = "Ошибка выполнения операции '{$operationName}': ";
            if ($connectErrorDesc) {
                 $finalMessage .= "{$connectErrorDesc}";
            } else if ($actualResponseString) {
                $finalMessage .= "{$actualResponseString}";
            }
            if ($actualCommandErrorDesc) {
                $finalMessage .= " (Код: {$actualCommandErrorDesc})";
            } else if (!$connectErrorDesc && !$actualResponseString) {
                $finalMessage .= "Неизвестная ошибка.";
            }
            $this->logger->error($finalMessage);
            return ['success' => false, 'message' => $finalMessage];
        }
    }

    public function printCheck($checkData) {
        // Проверяем, что $checkData является массивом
        if (!is_array($checkData)) {
            $this->logger->error("Неверный формат данных чека: данные не являются массивом.");
            return ['success' => false, 'message' => 'Неверный формат данных чека.'];
        }

        $formattedCheck = $this->FptrDriver->formatCheckJSON($checkData);
        if (!$formattedCheck['success']) {
            $this->logger->error("Ошибка форматирования JSON для чека: " . $formattedCheck['message']);
            // В этом случае FptrDriver еще не открывался, поэтому Close не нужен
            return ['success' => false, 'message' => $formattedCheck['message']];
        }
        $checkJsonData = $formattedCheck['checkData'];

        return $this->_executeFptrOperation([$this->FptrDriver, 'sendCommandAndGetAnswerFromKKT'], [$checkJsonData], 'printCheck');
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

        // Дополнительная проверка на открытую смену
        list($isShiftOpened, $shiftErrorDesc) = $this->FptrDriver->IsShiftOpened();
        if (!$isShiftOpened && $shiftErrorDesc === "") {
            $this->logger->warning(message: "Смена уже закрыта");
            return ['success' => false, 'message' => 'Ошибка закрытия смены: смена уже закрыта'];
        }
        if ($shiftErrorDesc != "") {
            $this->logger->error("Ошибка при проверке открытой смены: {$shiftErrorDesc}");
            return ['success' => false, 'message' => "Ошибка при проверке открытой смены: {$shiftErrorDesc}"];
        }

        return $this->_executeFptrOperation([$this->FptrDriver, 'CloseShift'], [$cashier], 'CloseShift');
    }

    public function printXReport(string $cashier = "") {
        if ($cashier === "") {
            $cashier = "Кассир";
        }
        return $this->_executeFptrOperation([$this->FptrDriver, 'PrintXReport'], [$cashier], 'printXReport');
    }

    public function cashIn($cashier, $amount) {
        return $this->_executeFptrOperation([$this->FptrDriver, 'CashIn'], [$amount, $cashier], 'cashIn');
    }

    public function cashOut($cashier, $amount) {
        return $this->_executeFptrOperation([$this->FptrDriver, 'CashOut'], [$amount, $cashier], 'cashOut');
    }

    /**
     * Вспомогательный метод для выполнения банковских операций.
     *
     * @param callable $operationCallable Callable, представляющий банковскую операцию.
     * @param array $params Параметры для операции.
     * @param string $operationName Название операции для логирования.
     * @return array Результат операции.
     */
    private function _executeBankOperation(callable $operationCallable, array $params, string $operationName): array {
        $this->logger->info("Попытка выполнения банковской операции: {$operationName}.");

        list($isOpened, $connectErrorDesc) = $this->bankDriver->Open();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к банковскому терминалу для {$operationName}: {$connectErrorDesc}");
            if (!$this->bankDriver->getEmulation()) {
                return ['success' => false, 'message' => "Ошибка подключения к банковскому терминалу: {$connectErrorDesc}"];
            }
        }

        $success = false;
        $response = "";
        $message = "";

        try {
            list($success, $response) = call_user_func_array($operationCallable, $params);
            $message = $success ? "Операция '{$operationName}' успешно выполнена" : "Ошибка выполнения операции '{$operationName}'";
        } catch (Exception $e) {
            $this->logger->error("Исключение при выполнении банковской операции '{$operationName}': " . $e->getMessage());
            $message = 'Исключение при выполнении банковской операции: ' . $e->getMessage();
            $success = false;
        } finally {
            $this->bankDriver->Close();
        }

        if ($success) {
            $this->logger->info("Операция '{$operationName}' успешно выполнена. Ответ: {$response}");
            return ['success' => true, 'message' => $message, 'data' => ['response' => $response]];
        } else {
            $this->logger->error("Ошибка банковской операции '{$operationName}': {$response}");
            return ['success' => false, 'message' => $message . ": {$response}"];
        }
    }

    public function bankOperation($operation, $params) {
        switch ($operation) {
            case 'PayMoney':
                if (!isset($params['amount'])) {
                    $this->logger->error("Не указана сумма для операции PayMoney.");
                    return ['success' => false, 'message' => 'Не указана сумма для операции PayMoney.'];
                }
                return $this->_executeBankOperation([$this->bankDriver, 'PayMoney'], [$params['amount']], 'PayMoney');
            case 'ReturnMoney':
                if (!isset($params['amount'])) {
                    $this->logger->error("Не указана сумма для операции ReturnMoney.");
                    return ['success' => false, 'message' => 'Не указана сумма для операции ReturnMoney.'];
                }
                return $this->_executeBankOperation([$this->bankDriver, 'ReturnMoney'], [$params['amount']], 'ReturnMoney');
            case 'CloseShiftTerminal':
                return $this->_executeBankOperation([$this->bankDriver, 'CloseShiftTerminal'], [], 'CloseShiftTerminal');
            default:
                $this->logger->error("Неизвестная банковская операция: " . $operation);
                return ['success' => false, 'message' => 'Неизвестная банковская операция.'];
        }
    }

    public function getWeight() {
        $this->logger->info("Попытка получения веса.");

        list($isOpened, $connectErrorDesc) = $this->scaleObject->Open();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к весам: {$connectErrorDesc}");
            return ['success' => false, 'message' => "Ошибка подключения к весам: {$connectErrorDesc}"];
        }

        try {
            list($success, $readErrorDesc, $weight) = $this->scaleObject->ReadWeight();
            if (!$success) {
                $this->logger->error("Ошибка чтения веса: {$readErrorDesc}");
                $this->scaleObject->Close();
                return ['success' => false, 'message' => "Ошибка чтения веса: {$readErrorDesc}"];
            }

            $this->logger->info("Вес успешно получен: " . $weight);
            $this->scaleObject->Close();
            return ['success' => true, 'message' => 'Вес получен', 'data' => ['weight' => $weight]];
        } catch (Exception $e) {
            $this->logger->error("Ошибка получения веса: " . $e->getMessage());
            $this->scaleObject->Close();
            return ['success' => false, 'message' => 'Ошибка получения веса: ' . $e->getMessage()];
        }
    }

    public function printBankSlip(array $slipLines) {
        return $this->_executeFptrOperation([$this->FptrDriver, 'PrintString'], [$slipLines], 'printBankSlip');
    }
}