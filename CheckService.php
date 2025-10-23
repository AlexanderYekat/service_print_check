<?php
// CheckService.php

require_once 'kktutils.php';
require_once 'logger.php'; // Подключаем логгер
require_once 'scaleutils.php'; // Подключаем утилиты для работы с весами
require_once 'permitmarkutils.php'; // Подключаем утилиты для работы резрешительным режимом

class CheckService {
    private $FptrDriver;
    private $bankDriver;
    private $scaleObject;
    private $logger;
    private $permitMarkCheckGateway;
    private $settings; 

    public function __construct(TFptr10Driver $FptrDriver, Logger $logger, ?TBankDriver $bankDriver = null, ?TScale8Driver $scaleObject = null, ?PermitMarkCheckGateway $permitMarkCheckGateway = null, ?Settings $settings = null) {
        $this->FptrDriver = $FptrDriver;
        $this->logger = $logger;
        $this->bankDriver = $bankDriver;
        $this->scaleObject = $scaleObject;
        $this->permitMarkCheckGateway = $permitMarkCheckGateway;
        $this->settings = $settings; // Инициализируем поле для настроек
    }

    /**
     * Обновляет объекты банка и весов после их создания
     */
    public function updateDrivers(?TBankDriver $bankDriver = null, ?TScale8Driver $scaleObject = null) {
        if ($bankDriver !== null) {
            $this->bankDriver = $bankDriver;
        }
        if ($scaleObject !== null) {
            $this->scaleObject = $scaleObject;
        }
    }

    public function getPermitMarkEnabled() {
        return $this->permitMarkCheckGateway->getPermitMarkEnabled();
    }

    /**
     * Вспомогательный метод для выполнения операций с ККТ.
     *
     * @param callable $operationCallable Callable, представляющий операцию с ККТ.
     * @param array $params Параметры для операции.
     * @param string $operationName Название операции для логирования.
     * @return array Результат операции.
     */
    private function _executeFptrOperation(callable $operationCallable, array $params, string $operationName, bool $disconnectFromKKT = true): array {
        
        $this->logger->info("Попытка выполнения операции с ККТ: {$operationName}. c параметрами: " . json_encode($params, JSON_UNESCAPED_UNICODE));
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
            if ($disconnectFromKKT) {
                $this->FptrDriver->Close();
            }
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
            return ['success' => true, 'message' => $finalMessage, 'data' => ['response' => json_decode($actualResponseString, true), 'success' => $isCommandTrulySuccessful, 'error' => $actualCommandErrorDesc]];
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

    /**
     * Извлекает марки из данных чека
     * 
     * @param array $checkData Данные чека
     * @return array Массив марок с информацией о необходимости проверки
     */
    private function extractMarksFromCheck($checkData, $typeCheck) {
        $marks = [];
        
        if (!isset($checkData['tableData']) || !is_array($checkData['tableData'])) {
            return $marks;
        }
        
        foreach ($checkData['tableData'] as $index => $item) {
            if (!empty($item['markingCode'])) {
                $this->logger->info("Обработка маркированного товара: " . $item['markingCode']);
                $this->logger->info("itemEstimatedStatus: " . ($item['itemEstimatedStatus'] ?? 'не задан'));
                
                // Проверяем наличие результатов проверки КМ в ККТ
                $needsKktCheck = false;
                
                // Альтернативная проверка с array_key_exists
                $needsKktCheck = !isset($item['kktCheckResult']) || 
                                     !isset($item['kktCheckResult']['data']) ||
                                     !isset($item['kktCheckResult']['data']['response']) ||
                                     !array_key_exists('itemInfoCheckResult', $item['kktCheckResult']['data']['response']) ||
                                     $item['kktCheckResult']['data']['response']['itemInfoCheckResult'] === null;
                $needsPermitCheck = !isset($item['permitCheckResult']) || 
                                     !isset($item['permitCheckResult']['data']) ||
                                     !isset($item['kktCheckResult']['data']['response']) || 
                                     $item['permitCheckResult']['success'] === false && ($typeCheck === 'sell' || $typeCheck === 'buyReturn') && $this->getPermitMarkEnabled();
                $mark = [
                    'index' => $index,
                    'markingCode' => $item['markingCode'],
                    'name' => $item['name'] ?? 'Товар',
                    'itemEstimatedStatus' => $item['itemEstimatedStatus'] ?? '',
                    'needsKktCheck' => $needsKktCheck,
                    'needsPermitCheck' => $needsPermitCheck
                    //'needsPermitCheck' => (!isset($item['permitCheckResult']) || 
                    //                       !isset($item['permitCheckResult']['status']) ||
                    //                       $item['permitCheckResult']['status'] !== 'success') && ($typeCheck === 'sell' || $typeCheck === 'buyReturn') && $this->getPermitMarkEnabled()

                ];
                $marks[] = $mark;
            }
        }
        
        return $marks;
    }

    /**
     * Проверяет все марки в чеке на ККТ
     * 
     * @param array $marks Массив марок для проверки
     * @param string $sellOrReturn Тип операции (sell/return)
     * @return array Результат проверки
     */
    private function checkAllMarksOnKKT($marks, $sellOrReturn) {
        $this->logger->info("Начинаем проверку " . count($marks) . " марок на ККТ");
        
        $results = [];
        $allSuccess = true;
        $errorMessages = [];

        $existMarksForCheck = false;
        foreach ($marks as $mark) {
            if ($mark['needsKktCheck']) {
                $existMarksForCheck = true;
                break;
            }
        }
        if (!$existMarksForCheck) {
            return ['success' => true, 'message' => 'Нет марок для проверки на ККТ'];
        }
        
        $resultCheckShiftOpened = $this->_executeFptrOperation([$this->FptrDriver, 'IsShiftOpened'], [], 'checkAllMarksOnKKT_IsShiftOpened', false);
        $isShiftOpened = $resultCheckShiftOpened['data']['response']['isShiftOpened'];

        if (!$isShiftOpened) {
            return ['success' => false, 'message' => 'Смена не открыта - поэтому не можем проверить марки на ККТ'];
        }
        
        foreach ($marks as $mark) {
            $this->logger->info("needsKktCheck: " . ($mark['needsKktCheck'] ? 'да' : 'нет'));
            if (!$mark['needsKktCheck']) {
                $this->logger->info("Марка '{$mark['name']}' уже проверена на ККТ, пропускаем");
                continue;
            }
            
            $this->logger->info("Проверяем марку на ККТ: {$mark['markingCode']} (товар: {$mark['name']})");
            
            $checkResult =$this->_executeFptrOperation([$this->FptrDriver, 'checkMarkingCode'], [$mark['markingCode'], $sellOrReturn, $mark['itemEstimatedStatus']], 'checkMarkingCode', false);
            
            $results[$mark['index']] = [
                'success' => $checkResult['success'],
                'result' => $checkResult['data'],
                'error' => $checkResult['message'],
                'markingCode' => $mark['markingCode']
            ];
            
            if (!$checkResult['success']) {
                $allSuccess = false;
                $positionNumber = $mark['index'] + 1; // Нумерация с 1 для пользователя
                $errorMsg = "Позиция №{$positionNumber} ({$mark['name']}): {$checkResult['message']}";
                $errorMessages[] = $errorMsg;
                $this->logger->error("Ошибка проверки марки на ККТ в позиции №{$positionNumber}: {$mark['markingCode']} - {$checkResult['message']}");
            } else {
                $this->logger->info("Марка успешно проверена на ККТ: {$mark['markingCode']}");
            }
        }
        
        $finalMessage = $allSuccess 
            ? 'Все марки успешно проверены на ККТ' 
            : 'Ошибки при проверке марок на ККТ: ' . implode('; ', $errorMessages);
        
        return [
            'success' => $allSuccess,
            'results' => $results,
            'message' => $finalMessage,
            'errorDetails' => $errorMessages
        ];
    }

    /**
     * Проверяет все марки в чеке в разрешительном режиме
     * 
     * @param array $marks Массив марок для проверки
     * @return array Результат проверки
     */
    private function checkAllMarksPermit($marks) {
        $this->logger->info("Начинаем проверку " . count($marks) . " марок в разрешительном режиме");
        
        $results = [];
        $allSuccess = true;
        $errorMessages = [];
        
        foreach ($marks as $mark) {
            if (!$mark['needsPermitCheck']) {
                $this->logger->info("Марка '{$mark['name']}' уже проверена в разрешительном режиме, пропускаем");
                continue;
            }
            
            $this->logger->info("Проверяем марку в разрешительном режиме: {$mark['markingCode']} (товар: {$mark['name']})");
            
            $checkResult = $this->checkPermitMark($mark['markingCode']);

            $this->logger->debug("checkResult = " . json_encode($checkResult));
            
            // Безопасно извлекаем данные из ответа
            $userResult = $checkResult['message'];
            $machineData = null;
            
            if ($checkResult['success'] && 
                isset($checkResult['data']['response']['user_status']['text'])) {
                $userResult = $checkResult['data']['response']['user_status']['text'];
            }
            
            if ($checkResult['success'] && 
                isset($checkResult['data']['response']['machine_data'])) {
                $machineData = $checkResult['data']['response']['machine_data'];
            }
            
            $results[$mark['index']] = [
                'status' => $checkResult['success'] ? 'success' : 'error',
                'userResult' => $userResult,
                'machineData' => $machineData,
                //'result' => $checkResult['data'],
                'markingCode' => $mark['markingCode']
            ];
            
            if (!$checkResult['success']) {
                $allSuccess = false;
                $positionNumber = $mark['index'] + 1; // Нумерация с 1 для пользователя
                $errorMsg = "Позиция №{$positionNumber} ({$mark['name']}): {$userResult}";
                $errorMessages[] = $errorMsg;
                $this->logger->error("Ошибка проверки марки в разрешительном режиме в позиции №{$positionNumber}: {$mark['markingCode']} - {$userResult}");
            } else {
                $this->logger->info("Марка успешно проверена в разрешительном режиме: {$mark['markingCode']}");
            }
        }
        
        $finalMessage = $allSuccess 
            ? 'Все марки успешно проверены в разрешительном режиме' 
            : 'Ошибки при проверке марок в разрешительном режиме: ' . implode('; ', $errorMessages);
        
        return [
            'success' => $allSuccess,
            'results' => $results,
            'message' => $finalMessage,
            'errorDetails' => $errorMessages
        ];
    }

    /**
     * Добавляет результаты проверки марок в данные чека
     * 
     * @param array $checkData Данные чека
     * @param array $kktResults Результаты проверки на ККТ
     * @param array $permitResults Результаты проверки в разрешительном режиме
     * @return array Обновленные данные чека
     */
    private function addMarkCheckResultsToCheckData($checkData, $kktResults, $permitResults) {
        if (!isset($checkData['tableData']) || !is_array($checkData['tableData'])) {
            return $checkData;
        }
        
        foreach ($checkData['tableData'] as $index => &$item) {
            if (!empty($item['markingCode'])) {
                // Добавляем результат проверки на ККТ
                if (isset($kktResults[$index])) {
                    $item['kktCheckResult'] = [
                        'success' => $kktResults[$index]['success'],
                        'message' => $kktResults[$index]['error'] ?? '',
                        'machineData' => $kktResults[$index]['result']['response'] ?? null
                    ];
                }
                
                $this->logger->debug("permitResults = " . json_encode($permitResults));

                // Добавляем результат проверки в разрешительном режиме
                if (isset($permitResults[$index])) {
                    $item['permitCheckResult'] = [
                        'status' => $permitResults[$index]['status'],
                        'userResult' => $permitResults[$index]['userResult'] ?? '',
                        'machineData' => $permitResults[$index]['machineData'] ?? null
                    ];
                }
            }
        }
        
        return $checkData;
    }

    public function printCheck($checkData) {
        // Проверяем, что $checkData является массивом
        if (!is_array($checkData)) {
            $this->logger->error("Неверный формат данных чека: данные не являются массивом.");
            return ['success' => false, 'message' => 'Неверный формат данных чека.'];
        }

        $cashier = $checkData['cashier'] ?? '';
        $cashierVatin = $checkData['cashierVatin'] ?? '';

        $resultCheckShiftOpened = $this->_executeFptrOperation([$this->FptrDriver, 'IsShiftOpened'], [], 'printCheck_IsShiftOpened', false);
        $isShiftOpened = $resultCheckShiftOpened['data']['response']['isShiftOpened'];

        if (!$isShiftOpened) {
            $resultOpenShift = $this->_executeFptrOperation([$this->FptrDriver, 'OpenShift'], [$cashier, $cashierVatin], 'printCheck_OpenShift', false);
            if (!$resultOpenShift['success']) {
                return ['success' => false, 'message' => 'Ошибка открытия смены: ' . $resultOpenShift['message']];
            }
        }

        // Извлекаем марки из чека
        $typeCheck = $checkData['type'] ?? 'sell';
        $marks = $this->extractMarksFromCheck($checkData, $typeCheck);
        
        if (!empty($marks)) {
            $this->logger->info("Найдено " . count($marks) . " марок в чеке, начинаем проверку");
            
            // Проверяем марки на ККТ
            $kktCheckResult = $this->checkAllMarksOnKKT($marks, $typeCheck);
            if (!$kktCheckResult['success']) {
                $this->logger->error("Ошибка проверки марок на ККТ: " . $kktCheckResult['message']);
                $this->FptrDriver->Close(); //закрываем соединение с ККТ
                return ['success' => false, 'message' => 'Ошибка проверки марок на ККТ: ' . $kktCheckResult['message']];
            }
            
            // Проверяем марки в разрешительном режиме
            $permitCheckResult = $this->checkAllMarksPermit($marks);
            if (!$permitCheckResult['success']) {
                $this->logger->error("Ошибка проверки марок в разрешительном режиме: " . $permitCheckResult['message']);
                return ['success' => false, 'message' => 'Ошибка проверки марок в разрешительном режиме: ' . $permitCheckResult['message']];
            }
            
            // Добавляем результаты проверки в данные чека
            $kktResults = $kktCheckResult['results'] ?? [];
            $permitResults = $permitCheckResult['results'] ?? [];
            $checkData = $this->addMarkCheckResultsToCheckData($checkData, $kktResults, $permitResults);
            
            $this->logger->info("Все марки успешно проверены, продолжаем печать чека");
        } else {
            $this->logger->info("Марки в чеке не найдены, печатаем чек без проверки марок");
        }

        $this->logger->info("Форматирование JSON для чека: " . json_encode($checkData, JSON_UNESCAPED_UNICODE));
        $formattedCheck = $this->FptrDriver->formatCheckJSON($checkData);
        $this->logger->info("Форматирование JSON для чека: " . json_encode($formattedCheck, JSON_UNESCAPED_UNICODE));
        if (!$formattedCheck['success']) {
            $this->logger->error("Ошибка форматирования JSON для чека: " . $formattedCheck['message']);
            return ['success' => false, 'message' => $formattedCheck['message']];
        }

        //выполняем команду установки часового пояса
        $result = $this->_executeFptrOperation([$this->FptrDriver, 'setTimeZone'], [$this->settings->timeZone], 'setTimeZone', false);
        $this->logger->info("Результат установки часового пояса: " . json_encode($result, JSON_UNESCAPED_UNICODE));


        $checkJsonData = $formattedCheck['checkData'];
        // Декодируем JSON строку в массив для красивого вывода
        $checkDataArray = json_decode($checkJsonData, true);
        $this->logger->info("JSON для чека: " . json_encode($checkDataArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

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

    public function clearMarkingCodes() {
        return $this->_executeFptrOperation([$this->FptrDriver, 'clearMarkingCodes'], [], 'clearMarkingCodes');
    }

    public function checkMarkingCode($markingCode, $sellOrReturn, $itemEstimatedStatus, $cashier = "", $cashierVatin = "") {
        $resultCheckShiftOpened = $this->_executeFptrOperation([$this->FptrDriver, 'IsShiftOpened'], [], 'checkMarkingCode_IsShiftOpened', false);

        $isShiftOpened = $resultCheckShiftOpened['data']['response']['isShiftOpened'];

        if (!$isShiftOpened) {
            if ($cashier === "") {
                $this->logger->error("Ошибка проверки марки - смена не открыта и не указан кассир для открытия смены");
                return ['success' => false, 'message' => 'Ошибка проверки марки - смена не открыта и не указан кассир для открытия смены'];
            }
            $resultOpenShift = $this->_executeFptrOperation([$this->FptrDriver, 'OpenShift'], [$cashier, $cashierVatin], 'checkMarkingCode_OpenShift', false);
            if (!$resultOpenShift['success']) {
                return ['success' => false, 'message' => 'Ошибка проверки марки - ошибка открытия смены: ' . $resultOpenShift['message']];
            }
        }

        return $this->_executeFptrOperation([$this->FptrDriver, 'checkMarkingCode'], [$markingCode, $sellOrReturn, $itemEstimatedStatus], 'checkMarkingCode');
    }

    public function checkPermitMark($permitMark) {
        $this->logger->info("Попытка проверки Разрешительный режим маркировки: " . $permitMark);
        
        $result = $this->permitMarkCheckGateway->checkPermit($permitMark);
        $this->logger->info("Результат проверки Разрешительный режим маркировки: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        
        if (!$result['success']) {
            $this->logger->error("Ошибка проверки Разрешительный режим маркировки: " . $result['message']);
            return [
                'success' => false, 
                'message' => $result['message'],
                'data' => [
                    'success' => false,
                    'response' => [
                        'user_status' => [
                            'ok' => false,
                            'text' => $result['message']
                        ]
                    ]
                ]
            ];
        }
        
        $this->logger->info("Разрешительный режим маркировки проверено.");
        
        // Проверяем, есть ли ошибка в результате (например, марка заблокирована или просрочена)
        $isBlocked = false;
        $errorMessage = '';
        
        if (isset($result['errorCode']) && $result['errorCode'] !== 0) {
            $isBlocked = true;
            $errorMessage = $result['message'] ?? 'Марка заблокирована';
        }
        
        // Формируем ответ в формате, ожидаемом клиентом
        $response = [
            'user_status' => [
                'ok' => !$isBlocked,
                'text' => $isBlocked ? $errorMessage : ($result['message'] ?? 'Марка разрешена к продаже')
            ],
            'machine_data' => [
                'code' => $result['errorCode'],
                'uuid' => $result['reqId'] ?? '',
                'timeStamp' => $result['reqTimestamp'] ?? '',
                'ver' => $result['ver'] ?? '',
                'inst' => $result['inst'] ?? ''
            ]
        ];
        
        return [
            'success' => true, 
            'message' => 'Разрешительный режим маркировки проверено', 
            'data' => [
                'success' => !$isBlocked, 
                'response' => $response
            ]
        ];
    }

    public function permitLocalModuleInit() {
        return $this->permitMarkCheckGateway->permitLocalModuleInit();
    }

    public function permitLocalModuleStatus() {
        return $this->permitMarkCheckGateway->getLocalModuleStatus();
    }

    public function permitCheckCdn() {
        return $this->permitMarkCheckGateway->checkCdn();
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
        //list($isShiftOpened, $shiftErrorDesc, $constOfSmeny) = $this->FptrDriver->IsShiftOpened();
        $resultCheckShiftOpened = $this->_executeFptrOperation([$this->FptrDriver, 'IsShiftOpened'], [], 'CloseShift_IsShiftOpened', false);
        if (!$resultCheckShiftOpened['success']) {
            $this->logger->error("Ошибка при проверке открытой смены: " . $resultCheckShiftOpened['message']);
            return ['success' => false, 'message' => "Ошибка при проверке открытой смены: " . $resultCheckShiftOpened['message']];
        }
        $isShiftOpened = $resultCheckShiftOpened['data']['response']['isShiftOpened'];
        $shiftErrorDesc = $resultCheckShiftOpened['data']['error'];
        $constOfSmeny = $resultCheckShiftOpened['data']['response']['constOfSmeny'];
        $this->logger->debug("Проверка открытой смены: isShiftOpened=" . ($isShiftOpened ? "true" : "false") . ", shiftErrorDesc=" . $shiftErrorDesc . ", constOfSmeny=" . $constOfSmeny);
        ////$this->logger->info("Проверка открытой смены: isShiftOpened=" . ($isShiftOpened ? "true" : "false") . ", shiftErrorDesc=" . $shiftErrorDesc);
        if (!$isShiftOpened) {
            $this->logger->warning(message: "Смена уже закрыта");
            return ['success' => false, 'message' => 'Ошибка закрытия смены: смена уже закрыта'];
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

        // Проверяем, что объект весов инициализирован
        if ($this->scaleObject === null) {
            $this->logger->error("Объект весов не инициализирован");
            return ['success' => false, 'message' => "Объект весов не инициализирован"];
        }

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

    public function updateConfig(array $configData) {
        try {
            $this->logger->info("Обновление конфигурации: " . json_encode($configData));
            
            // Обновляем конфигурацию в PermitMarkCheckGateway
            if (isset($configData['testExpiredMarks'])) {
                if ($this->permitMarkCheckGateway) {
                    $oldValue = $this->permitMarkCheckGateway->config['testExpiredMarks'] ?? 'не установлено';
                    $this->permitMarkCheckGateway->config['testExpiredMarks'] = (bool)$configData['testExpiredMarks'];
                    $newValue = $this->permitMarkCheckGateway->config['testExpiredMarks'];
                    $this->logger->info("testExpiredMarks изменен с '{$oldValue}' на '" . ($newValue ? 'true' : 'false') . "'");
                } else {
                    $this->logger->warning("PermitMarkCheckGateway не инициализирован, конфигурация не обновлена");
                }
                
                // Также обновляем настройки для сохранения
                if ($this->settings) {
                    $this->settings->testExpiredMarks = (bool)$configData['testExpiredMarks'];
                    $this->settings->save(); // Сохраняем изменения в файл
                    $this->logger->info("Настройки testExpiredMarks обновлены в Settings и сохранены в файл");
                } else {
                    $this->logger->warning("Settings не инициализированы, изменения не будут сохранены");
                }
            }
            
            return [
                'success' => true, 
                'message' => 'Конфигурация успешно обновлена',
                'data' => $configData
            ];
        } catch (Exception $e) {
            $this->logger->error("Ошибка обновления конфигурации: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Ошибка обновления конфигурации: ' . $e->getMessage()
            ];
        }
    }
}