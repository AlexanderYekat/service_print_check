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

    public function __construct(TFptr10Driver $FptrDriver, Logger $logger, ?TBankDriver $bankDriver = null, ?TScale8Driver $scaleObject = null) {
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
            $isCommandTrulySuccessful = $this->FptrDriver->SuccessCommand($actualResponseString);
            if ($isCommandTrulySuccessful) {
                $finalMessage = "Операция '{$operationName}' выполнена успешно. Ответ: " . ($actualResponseString ? $actualResponseString : "Нет ответа");
            } else {
                $finalMessage = "Операция '{$operationName}' завершилась с ошибкой: " . $actualResponseString;
            }
            $this->logger->info($finalMessage);
            return ['success' => true, 'message' => $finalMessage, 'data' => ['response' => json_decode($actualResponseString, true), 'success' => $isCommandTrulySuccessful]];
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

    public function printCheck($checkData): array {
        // Проверяем, что $checkData является массивом
        if (!is_array($checkData)) {
            $this->logger->error("Неверный формат данных чека: данные не являются массивом.");
            return ['success' => false, 'message' => 'Неверный формат данных чека.'];
        }

        $formattedCheck = $this->FptrDriver->formatCheckJSON($checkData);
        if (!$formattedCheck['success']) {
            $this->logger->error("Ошибка форматирования JSON для чека: " . $formattedCheck['message']);
            return ['success' => false, 'message' => $formattedCheck['message']];
        }
        $checkJsonData = $formattedCheck['checkData'];

        // --- Попытка 1 --- 
        $result = $this->_executeFptrOperation([$this->FptrDriver, 'sendCommandAndGetAnswerFromKKT'], [$checkJsonData], 'printCheck_attempt1');
        $this->logger->info("Попытка 1 печати чека. Результат: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        if (!$result['success']) {
            return $result; // Возвращаем ошибку, если попытка 1 не удалась
        }

        // Проверяем успех команды с помощью SuccessCommand
        if (isset($result['data']['success'])) {
            if ($result['data']['success']) {
                return $result;
            }
        }

        // --- Попытка 2: continuePrint + повтор оригинальной команды --- 
        $this->logger->warning("Попытка 1 печати чека не удалась или команда неуспешна. Попытка 2: continuePrint + повторная печать.");
        $continuePrintJson = json_encode(["type" => "continuePrint"], JSON_UNESCAPED_UNICODE);
        $continueResult = $this->_executeFptrOperation([$this->FptrDriver, 'sendCommandAndGetAnswerFromKKT'], [$continuePrintJson], 'continuePrint_for_printCheck');
        $this->logger->info("Результат 'continuePrint': " . json_encode($continueResult, JSON_UNESCAPED_UNICODE));

        // Повторяем оригинальную команду печати чека после continuePrint
        $result = $this->_executeFptrOperation([$this->FptrDriver, 'sendCommandAndGetAnswerFromKKT'], [$checkJsonData], 'printCheck_attempt2');
        $this->logger->info("Попытка 2 печати чека. Результат: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        if (!$result['success']) {
            return $result; // Возвращаем ошибку, если попытка 2 не удалась
        }

        // Проверяем успех команды с помощью SuccessCommand
        if (isset($result['data']['success'])) {
            if ($result['data']['success']) {
                return $result;
            }
        }

        // --- Попытка 3: CancelReceipt + повтор оригинальной команды --- 
        $this->logger->warning("Попытка 2 печати чека не удалась или команда неуспешна. Попытка 3: CancelReceipt + повторная печать.");
        list($cancelSuccess, $cancelErrorDesc) = $this->FptrDriver->CancelReceipt();
        $this->logger->info("Результат 'CancelReceipt': success=" . ($cancelSuccess ? "true" : "false") . ", error=" . $cancelErrorDesc);

        if (!$cancelSuccess) {
            $this->logger->error("Не удалось отменить чек перед последней попыткой печати. Продолжаем последнюю попытку.");
        }
        
        // Последняя попытка печати оригинальной команды
        $result = $this->_executeFptrOperation([$this->FptrDriver, 'sendCommandAndGetAnswerFromKKT'], [$checkJsonData], 'printCheck_attempt3');
        $this->logger->info("Попытка 3 печати чека. Результат: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    /**
     * Универсальная обработка печати чека с поддержкой обратной совместимости
     */
    public function processPrintCheck($data): array {
        $session_id = $data['session_id'] ?? null;
        $cashier = $data['cashier'] ?? '';
        $payments = $data['payments'] ?? [];
        $type = $data['type'] ?? 'sell';
        if ($session_id) {
            global $CHECK_SESSIONS;
            $positions = $CHECK_SESSIONS[$session_id] ?? [];
            if (empty($positions)) {
                return ['success' => false, 'message' => 'Нет позиций для данного session_id', 'http_code' => 400];
            }
            foreach ($positions as $pos) {
                if (!empty($pos['mark_code']) && ($pos['mark_kkt_status'] ?? 'ожидание') === 'ожидание') {
                    return ['success' => false, 'message' => 'Есть маркированные позиции со статусом проверки ККТ: ожидание. Чек не может быть пробит.', 'http_code' => 400];
                }
            }
            $checkData = [
                'tableData' => $positions,
                'cashier' => $cashier,
                'payments' => $payments,
                'type' => $type,
                'taxationType' => $data['taxationType'] ?? null
            ];
            $result = $this->printCheck($checkData);
            if (!$result['success']) {
                return ['success' => false, 'message' => $result['message'], 'http_code' => 500];
            }
            unset($CHECK_SESSIONS[$session_id]);
            return ['success' => true, 'data' => $result['data']];
        } else {
            return $this->printCheck($data);

        }
    }

    /**
     * Добавление позиции в чек (в память по session_id)
     */
    public function addCheckPosition($session_id, $position) {
        global $CHECK_SESSIONS;
        if (!$session_id || !$position) {
            return ['success' => false, 'message' => 'Не передан session_id или position'];
        }
        // Генерируем position_id, если не передан
        if (empty($position['position_id'])) {
            $position['position_id'] = uniqid('pos_', true);
        }
        // Проверка марки (разрешительный режим)
        if (!empty($position['mark_code'])) {
            $rr_result = checkMarkPermitAPI($position['mark_code']);
            $position['mark_permit_status'] = $rr_result['status'];
            $position['mark_permit_result'] = $rr_result['result'];
            $position['mark_kkt_status'] = 'ожидание';
        }
        // Добавляем позицию в массив
        if (!isset($CHECK_SESSIONS[$session_id])) {
            $CHECK_SESSIONS[$session_id] = [];
        }
        $CHECK_SESSIONS[$session_id][] = $position;
        return [
            'success' => true,
            'position_id' => $position['position_id'],
            'mark_permit_status' => $position['mark_permit_status'] ?? null,
            'mark_permit_result' => $position['mark_permit_result'] ?? null,
            'mark_kkt_status' => $position['mark_kkt_status'] ?? null
        ];
    }

    /**
     * Синхронная проверка марки через localhost API (разрешительный режим)
     * Здесь пока заглушка, но можно реализовать реальный HTTP-запрос
     */
    private function checkMarkPermitAPI($mark_code) {
        // TODO: заменить на реальный HTTP-запрос к API разрешительного режима
        // Пример успешного разрешения:
        return [
            'status' => 'разрешено',
            'result' => 'Марка разрешена (заглушка)'
        ];
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
        list($isShiftOpened, $shiftErrorDesc, $constOfSmeny) = $this->FptrDriver->IsShiftOpened();
        $this->logger->info("Проверка открытой смены: isShiftOpened=" . ($isShiftOpened ? "true" : "false") . ", shiftErrorDesc=" . $shiftErrorDesc . ", constOfSmeny=" . $constOfSmeny);
        ////$this->logger->info("Проверка открытой смены: isShiftOpened=" . ($isShiftOpened ? "true" : "false") . ", shiftErrorDesc=" . $shiftErrorDesc);
        if (!$isShiftOpened && $shiftErrorDesc === "") {
            $this->logger->warning(message: "Смена уже закрыта");
            return ['success' => false, 'message' => 'Ошибка закрытия смены: смена уже закрыта'];
        }
        if ($shiftErrorDesc != "") {
            $this->logger->error("Ошибка при проверке открытой смены: {$shiftErrorDesc}");
            if (!$this->FptrDriver->getEmulation()) {
                return ['success' => false, 'message' => "Ошибка при проверке открытой смены: {$shiftErrorDesc}"];
            }
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

        $success = true;
        list($isOpened, $connectErrorDesc) = $this->bankDriver->Open();
        if (!$isOpened) {
            $this->logger->error("Ошибка подключения к банковскому терминалу для {$operationName}: {$connectErrorDesc}");
            if (!$this->bankDriver->getEmulation()) {
                return ['success' => false, 'message' => "Ошибка подключения к банковскому терминалу: {$connectErrorDesc}"];
            }
        }

        $message = "";
        $result = []; // Инициализируем переменную для хранения результата
        try {
            $result = call_user_func_array($operationCallable, $params);
        } catch (Exception $e) {
            $success = false;
            $this->logger->error("Исключение при выполнении банковской операции '{$operationName}': " . $e->getMessage());
            $message = 'Исключение при выполнении банковской операции: ' . $e->getMessage();
        } finally {
            $this->bankDriver->Close();
        }
        if (!$success) {
            $this->logger->error("Ошибка выполнения банковской операции '{$operationName}': " . $message);
            return ['success' => false, 'message' => $message];
        }
        return $result;
    }

    public function bankOperation($operation, $params) {
        // Операции, требующие альтернативного PowerShell-скрипта
        $altOps = [
            'PayMoney'    => 'pay',
            'ReturnMoney' => 'return',
            'CancelPay'   => 'cancel'
        ];

        // Операции, требующие сумму
        $needAmount = [
            'PayMoneyOld', 'PayMoney', 'ReturnMoney', 'CancelPayOld', 'CancelPay'
        ];

        if (in_array($operation, $needAmount) && (!isset($params['amount']) || !is_numeric($params['amount']))) {
            $this->logger->error("Не указана сумма для операции $operation.");
            return ['success' => false, 'message' => "Не указана сумма для операции $operation."];
        }

        if (isset($altOps[$operation])) {
            $this->logger->info("Попытка операции '$operation' банковской картой: " . $params['amount']);
            require_once __DIR__ . '/bank/bank-operation.php';
            $value = (int)($params['amount'] * 100);
            $result = bank_operation_via_ps1($altOps[$operation], $value, $this->logger);
            $this->logger->info("Результат операции '$operation': " . json_encode($result));
            return $result;
        }

        switch ($operation) {
            case 'PayMoneyOld':
                return $this->_executeBankOperation([$this->bankDriver, 'PayMoney'], [$params['amount']], 'PayMoney');
            case 'CancelPayOld':
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
        return $this->_executeFptrOperation([$this->FptrDriver, 'PrintSlip'], [$slipLines], 'PrintSlip');
    }
}