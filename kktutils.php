<?php
// kktutils.php

require_once 'models.php';

class TFptr10Driver {
    private $fptr = null;
    private $comport;
    private $ipKkt;
    private $portIpKkt;
    private $ipServKkt;
    private $emulation;
    private $logger;

    public function __construct($comport = 0, $ipKkt = "", $portIpKkt = 0, $ipServKkt = "", $logger = null, $emulation = false) {
        $this->comport = $comport;
        $this->ipKkt = $ipKkt;
        $this->portIpKkt = $portIpKkt;
        $this->ipServKkt = $ipServKkt;
        $this->emulation = $emulation;
        $this->logger = $logger;
    }

    public function NewSafe() {
        try {
            if ($this->fptr === null) {
                $this->fptr = new COM("AddIn.Fptr10") or die("Не удалось создать объект драйвера ККТ");
            }
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function Open() {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }
        if ($this->IsOpened()) {
            return [true, ""];
        }
        try {
            // Применяем настройки перед открытием
            $this->applySettingsToFptr();
            $result = $this->fptr->Open();
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription ?? '');
                return [false, "Ошибка открытия соединения с ККТ: " . $errorDescription];
            }
            return [$this->IsOpened(), ""];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }

    public function IsOpened() {
        if ($this->fptr === null) {
            return false;
        }
        try {
            return $this->fptr->IsOpened();
        } catch (Exception $e) {
            return false;
        }
    }
    /*public function GetSettings() {
        if ($this->fptr === null) {
            return null;
        }
        try {
            $settings = new Settings();
            $settings->comport = $this->comport;
            $settings->ipKkt = $this->ipKkt;
            $settings->portIpKkt = $this->portIpKkt;
            $settings->ipServKkt = $this->ipServKkt;
            $settings->emulation = $this->emulation;
            return $settings;
        } catch (Exception $e) {
            return null;
        }
    }*/
    private function applySettingsToFptr() {
        
        $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_MODEL, $this->fptr->LIBFPTR_MODEL_ATOL_AUTO);

        if (!empty($this->ipServKkt)) {
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_REMOTE_SERVER_ADDR, $this->ipServKkt);
        }

        if ($this->comport == 0) {
            if (!empty($this->ipKkt)) {
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_TCPIP);
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_IPADDRESS, $this->ipKkt);
                if ($this->portIpKkt != 0) {
                    $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_IPPORT, $this->portIpKkt);
                }
            } else {
                $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_USB);
            }
        } else {
            $sComPorta = "COM" . $this->comport;
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_PORT, $this->fptr->LIBFPTR_PORT_COM);
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_COM_FILE, $sComPorta);
            $this->fptr->SetSingleSetting($this->fptr->LIBFPTR_SETTING_BAUDRATE, $this->fptr->LIBFPTR_PORT_BR_115200);
        }

        $this->fptr->ApplySingleSettings();
    }

    public function Close() {
        if ($this->fptr === null) {
            return;
        }
        try {
            $this->fptr->Close();
        } catch (Exception $e) {
            // Игнорируем ошибки при закрытии
        }
    }

    public function Version() {
        if ($this->fptr === null) {
            return "";
        }
        try {
            return $this->fptr->Version();
        } catch (Exception $e) {
            return "";
        }
    }

    public function GetFptr10() {
        return $this->fptr;
    }

    public function IsShiftOpened() {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }
        $emulation = $this->getEmulation();
        list($isOpened, $connectErrorDesc) = $this->Open();
        if (!$isOpened) {
            if (!$emulation) {
                return [false, "Ошибка подключения к ККТ: {$this->GetTypeConnection()} (Код: {$connectErrorDesc})"];
            }
        }
        $result = -1;
        $shiftOpened = false;
        $commandErrorDesc = "";
        try {
            $this->fptr->SetParam($this->fptr->LIBFPTR_PARAM_DATA_TYPE, $this->fptr->LIBFPTR_DT_SHIFT_STATE);
            $result = $this->fptr->QueryData();            
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $commandErrorDesc = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription  ?? '');
            }
            $result = $this->fptr->GetParamInt($this->fptr->LIBFPTR_PARAM_SHIFT_STATE);
            
            $shiftOpened = ($result === 1 || $result === 2); //LIBFPTR_SS_OPENED = 1, LIBFPTR_SS_EXCHANGE = 2
        } catch (Exception $e) {
            $commandErrorDesc = $e->getMessage();
        } finally {
            $this->Close();
        }
        return [$shiftOpened, $commandErrorDesc, $result];
    }

    public function PrintXReport(string $cashier) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $XReportJson = json_encode([
            "type" => "reportX",
            "operator" => [
                "name" => $cashier
            ]
        ], JSON_UNESCAPED_UNICODE);

        // Используем sendCommandAndGetAnswerFromKKT для отправки JSON-команды
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($XReportJson);
        
        // Возвращаем результат
        return [$success, $responseJson, $commandErrorDesc];
    }

    public function PrintSlip($listOfLines) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $nonFiscalJson = [
            "type" => "nonFiscal",
            "items" => []
        ];

        foreach ($listOfLines as $line) {
            // Проверяем на пустую строку, включая строки только с пробелами
            if (empty(trim($line))) {
                continue;
            }
            $nonFiscalJson["items"][] = [
                "type" => "text",
                "text" => $line,
                "alignment" => "left" //center
            ];
        }

        $jsonCommand = json_encode($nonFiscalJson, JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($jsonCommand);
        
        return [$success, $responseJson, $commandErrorDesc];
    }

    public function CashIn(float $amount, string $operatorName, string $operatorVatin = "") {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $cashInJson = [
            "type" => "cashIn",
            "operator" => [
                "name" => $operatorName,
            ],
            "cashSum" => $amount
        ];

        // Добавляем Vatin, если он предоставлен
        if (!empty($operatorVatin)) {
            $cashInJson["operator"]["vatin"] = $operatorVatin;
        }

        $jsonCommand = json_encode($cashInJson, JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($jsonCommand);
        
        return [$success, $responseJson, $commandErrorDesc];
    }

    public function CashOut(float $amount, string $operatorName, string $operatorVatin = "") {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $cashOutJson = [
            "type" => "cashOut",
            "operator" => [
                "name" => $operatorName,
            ],
            "cashSum" => $amount
        ];

        // Добавляем Vatin, если он предоставлен
        if (!empty($operatorVatin)) {
            $cashOutJson["operator"]["vatin"] = $operatorVatin;
        }

        $jsonCommand = json_encode($cashOutJson, JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($jsonCommand);
        
        return [$success, $responseJson, $commandErrorDesc];
    }

    public function CloseShift(string $cashier) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $closeShiftJson = json_encode([
            "type" => "closeShift",
            "operator" => [
                "name" => $cashier
            ]
        ], JSON_UNESCAPED_UNICODE);

        // Используем sendCommandAndGetAnswerFromKKT для отправки JSON-команды
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($closeShiftJson);
        
        // Возвращаем результат
        return [$success, $responseJson, $commandErrorDesc];
    }

    public function checkMarkingCode(string $markingCode, string $sellOrReturn, $itemEstimatedStatus) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }
        
        // Используем переданный параметр, если он не пустой, иначе определяем по типу операции
        if (empty($itemEstimatedStatus)) {
            $itemEstimatedStatus = $sellOrReturn === "sell" ? "itemPieceSold" : "itemPieceReturn";
        }
        $beginMarkingCodeValidation = $this->beginMarkingCodeValidation($markingCode, $itemEstimatedStatus);
        if (!$beginMarkingCodeValidation[0]) {
            return [false, "", $beginMarkingCodeValidation[2]];
        }
        $checkMarkingCodeValidation = $this->checkMarkingCodeValidation();
        if (!$checkMarkingCodeValidation[0]) {
            return [false, "", $checkMarkingCodeValidation[2]];
        }
        $acceptMarkingCode = $this->acceptMarkingCode();
        if (!$acceptMarkingCode[0]) {
            return [false, "", $acceptMarkingCode[2]];
        }
        $jsonAnswer = $acceptMarkingCode[1];

        $jsonAnswerArray = json_decode($jsonAnswer, true);
        $jsonAnswerArray['itemEstimatedStatus'] = $itemEstimatedStatus;
        $jsonAnswer = json_encode($jsonAnswerArray, JSON_UNESCAPED_UNICODE);
        return [true, $jsonAnswer, ""]; //true, json_encode($jsonAnswer, JSON_UNESCAPED_UNICODE), ""
    }

    public function beginMarkingCodeValidation(string $imc, string $itemEstimatedStatus) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        // В режиме эмуляции возвращаем мок-ответ
        if ($this->emulation) {
            $mockResponse = [
                "offlineValidation" => [
                    "fmCheck" => true,
                    "fmCheckResult" => false,
                    "fmCheckErrorReason" => "noKeys"
                ]
            ];
            return [true, json_encode($mockResponse, JSON_UNESCAPED_UNICODE), ""];
        }

        $beginMarkingCodeValidationJson = json_encode([
            "type" => "beginMarkingCodeValidation",
            "params" => [
                "imcType" => "auto",
                "imc" => $imc,
                "itemEstimatedStatus" => $itemEstimatedStatus,
                "imcModeProcessing" => 0
            ]
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) =  $this->sendCommandAndGetAnswerFromKKT($beginMarkingCodeValidationJson);
        if (!$success && !$this->emulation) {
            return [false, "", $commandErrorDesc];
        }

        // В режиме эмуляции возвращаем мок-ответ
        if ($this->emulation) {
            $mockResponse = [
                "offlineValidation" => [
                    "fmCheck" => true,
                    "fmCheckResult" => false,
                    "fmCheckErrorReason" => "noKeys"
                ]
            ];
            //return [true, json_encode($mockResponse, JSON_UNESCAPED_UNICODE), ""];
            $responseJson = json_encode($mockResponse, JSON_UNESCAPED_UNICODE);
            $success = true;
        }

        return [true, $responseJson, ""];
    }

    public function checkMarkingCodeValidation()
    {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        // В режиме эмуляции возвращаем мок-ответ
        if ($this->emulation) {
            $mockResponse = [
                "ready" => true,
                "sentImcRequest" => true,
                "driverError" => [
                    "code" => 0
                ],
                "onlineValidation" => [
                    "itemInfoCheckResult" => [
                        "imcCheckFlag" => true,
                        "imcCheckResult" => true,
                        "imcStatusInfo" => true,
                        "ecrStandAloneFlag" => true
                    ],
                    "markOperatorItemStatus" => "itemEstimatedStatusCorrect",
                    "markOperatorResponse" => [
                        "responseStatus" => true,
                        "itemStatusCheck" => true
                    ],
                    "markOperatorResponseResult" => "correct",
                    "imcType" => "imcFmVerifyCode88",
                    "imcBarcode" => "MDEwMjkwMDAwMDQ3NTgzMDIxTWRFZng6WHA2WUZkNx05MTgwMjkdOTJhUUlRa0k3b0hYbXpHL21kS3h6Q1VDS1RKSFhvQk9EZG1DZE01azhRajdnYVpWMnhibjY2eEJYR0lLcnRmdnFQSU5BMmprYmp5ajMvTytreTZvdTFOQT09",
                    "imcModeProcessing" => 0
                ]
            ];
            return [true, json_encode($mockResponse, JSON_UNESCAPED_UNICODE), ""];
        }

        $checkMarkingCodeValidationJson = json_encode([
            "type" => "getMarkingCodeValidationStatus"
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($checkMarkingCodeValidationJson);

        if (!$success) {
            return [false, "", $commandErrorDesc];
        }

        return [true, $responseJson, ""];
    }

    /**
     * Принимает код маркировки
     */
    public function acceptMarkingCode()
    {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        // В режиме эмуляции возвращаем мок-ответ
        if ($this->emulation) {
            $mockResponse = [
                "itemInfoCheckResult" => [
                    "ecrStandAloneFlag" => false,
                    "imcCheckFlag" => true,
                    "imcCheckResult" => true,
                    "imcEstimatedStatusCorrect" => true,
                    "imcStatusInfo" => true
                ]
            ];
            return [true, json_encode($mockResponse, JSON_UNESCAPED_UNICODE), ""];
        }

        $acceptMarkingCodeJson = json_encode([
            "type" => "acceptMarkingCode"
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($acceptMarkingCodeJson);

        if (!$success) {
            return [false, "", $commandErrorDesc];
        }

        return [true, $responseJson, ""];
    }

    public function CancelMarkingCodeValidation()
    {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }
        
        $cancelMarkingCodeValidationJson = json_encode([
            "type" => "cancelMarkingCodeValidation"
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($cancelMarkingCodeValidationJson);
        
        if (!$success) {
            return [false, "", $commandErrorDesc];
        }

        return [true, $responseJson, ""];
    }

    /**
     * Очищает результат валидации кода маркировки
     */
    public function clearMarkingCodeValidationResult()
    {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $clearMarkingCodeValidationResultJson = json_encode([
            "type" => "clearMarkingCodeValidationResult"
        ], JSON_UNESCAPED_UNICODE);

        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($clearMarkingCodeValidationResultJson);
        if (!$success) {
            return [false, "", $commandErrorDesc];
        }

        return [true, $responseJson, ""];
    }

    public function sendCommandAndGetAnswerFromKKT($comJson) {
        $err = null;

        if ($this->fptr === null) {
            return [false, "", "не инициализирован драйвер ККТ"];
        }

        // Устанавливаем JSON-команду
        $comJson = iconv('UTF-8', 'Windows-1251', $comJson  ?? '');
        if ($comJson === false) {
            return [false, "", "Ошибка конвертации кодировки"];
        }
        $this->fptr->setParam($this->fptr->LIBFPTR_PARAM_JSON_DATA, $comJson);

        // отправка команды (если не эмуляция)
        if (!$this->emulation) {
            $result = $this->fptr->processJson();
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription  ?? '');
                return [false, "", "Ошибка отправки команды на ККТ: {$errorDescription}"];
            }
        } else { // Если эмуляция, возвращаем мок-ответ
            $decodedComJson = json_decode($comJson, true);
            $commandType = $decodedComJson['type'] ?? '';
            $resJson = '';

            switch ($commandType) {
                case 'cashIn':
                    $resJson = '{ "counters" : { "cashSum" : 1345.0 } }';
                    break;
                case 'closeShift':
                    $resJson = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2017-07-25T13:12:00+03:00", "fiscalDocumentNumber" : 69, "fiscalDocumentSign" : "1138986989", "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 11, "receiptsCount" : 3, "fnsUrl": "www.nalog.gov.ru" }, "warnings": { "notPrinted": false } }';
                    break;
                case 'reportX': // Для X-отчета
                    $resJson = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2018-03-06T13:52:00+03:00", "fiscalDocumentNumber" : 71, "fiscalDocumentSign" : "1494325660", "fiscalReceiptNumber" : 1, "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 12, "total" : 390.75, "fnsUrl": "www.nalog.gov.ru" }, "warnings": null }';
                    break;
                case 'nonFiscal': // Для печати банковского слипа (пример)
                    $resJson = '{ "success": true, "message": "Банковский слип успешно напечатан (мок)" }';
                    break;
                default: // По умолчанию для других команд, включая printCheck
                    $resJson = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2018-03-06T13:52:00+03:00", "fiscalDocumentNumber" : 71, "fiscalDocumentSign" : "1494325660", "fiscalReceiptNumber" : 1, "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 12, "total" : 390.75, "fnsUrl": "www.nalog.gov.ru" }, "warnings": null }';
                    break;
            }
            return [true, $resJson, ""];
        }
    
        // Получаем ответ от ККТ
        $jsonAnswer = $this->fptr->GetParamString($this->fptr->LIBFPTR_PARAM_JSON_DATA);
        $jsonAnswer = json_decode($jsonAnswer, true);

        if ($jsonAnswer === null) {
            return [true, "", ""];
        }
        //return [true, "", ""];
        return [true, json_encode($jsonAnswer), ""];
    }
    
    public function SuccessCommand($resultJson) {
        // Если передан массив, преобразуем его в строку
        if (is_array($resultJson)) {
            $resultJson = json_encode($resultJson);
        }        
        // Проверяем наличие слов "ошибка" или "error" в ответе
        $hasError = (mb_stripos($resultJson, 'ошибка') !== false) || (mb_stripos($resultJson, 'error') !== false);
        return !$hasError;
    }

    public function GetTypeConnection(): string {
        $typeConnect = "";

        // Используем внутренние параметры TFptr10Driver
        $comportint = $this->getComport();
        $ipaddresskktper = $this->getIpKkt();
        $portkktper = $this->getPortIpKkt();
        $ipaddresssrvkktper = $this->getIpServKkt();
        
        if (!empty($ipaddresssrvkktper)) {
            $typeConnect = "через сервер ККТ по IP $ipaddresssrvkktper";
        }
    
        if ($comportint == 0) {
            if (!empty($ipaddresskktper)) {
                $typeConnect .= " по IP $ipaddresskktper ККТ на порт $portkktper";
            } else {
                $typeConnect .= " по USB";
            }
        } else {
            $sComPorta = "COM" . $comportint;
            $typeConnect .= " по COM порту $sComPorta";
        }    

        return $typeConnect;
    }

    public function formatCheckJSON($checkDataArr) {
        $originalCheckData = null; // Инициализируем для предотвращения ошибки линтера

        /*// Если передан объект, преобразуем в массив
        if ($checkDataArr instanceof CheckData) {
            $originalCheckData = $checkDataArr; // Сохраняем ссылку на оригинальный объект
            $checkDataArr = [
                    'taxationType' => $originalCheckData->taxationType,
                    'type' => $originalCheckData->type,
                    'cashier' => $originalCheckData->cashier,
                'tableData' => [],
                'payments' => [],
            ];
            foreach ($originalCheckData->tableData as $item) {
                $checkDataArr['tableData'][] = [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'taxNDS' => $item['taxNDS'],
                    'markingCode' => $item['markingCode'],
                ];
            }
            foreach ($originalCheckData->payments as $pay) {
            $checkDataArr['payments'][] = [
                    'type' => $pay['type'],
                    'amount' => $pay['amount']];
            }
        }*/

        $checkType = !empty($checkDataArr['type']) ? $checkDataArr['type'] : "sell";
        $itemEstimatedStatus = $checkType === "sell" ? "itemPieceSold" : "itemPieceReturn";

        // Формируем позиции чека
        $this->logger->debug("Формируем позиции чека: " . json_encode($checkDataArr, JSON_UNESCAPED_UNICODE));

        $checkItems = [];
        if (!empty($checkDataArr['tableData'])) {
            foreach ($checkDataArr['tableData'] as $item) {
                $taxType = "none";
                if (!empty($item['taxNDS'])) {
                    if (strpos($item['taxNDS'], "vat") === 0) {
                        $taxType = $item['taxNDS'];
                    } else {
                        $taxType = "vat" . $item['taxNDS'];
                    }
                }
            }
            $quantity = floatval($item['quantity']);
            $price = floatval($item['price']);
            $positionItem = [
                "type" => "position",
                "name" => $item['name'],
                "price" => $price,
                "quantity" => $quantity,
                "amount" => $price * $quantity,
                "tax" => [
                    "type" => $taxType
                ]
            ];

            if (!empty($item['markingCode'])) {
                // Инициализируем imcParams с базовыми данными маркировки
                $imcParams = [
                    "imc" => $item['markingCode'],
                    "imcType" => "auto",
                    "itemEstimatedStatus" => $item['itemEstimatedStatus'] ?? $itemEstimatedStatus,
                    "imcModeProcessing" => 0
                ];

                // Если в данных позиции ($item) присутствуют дополнительные параметры маркировки
                // (например, результаты проверки марки), объединяем их с базовыми imcParams.
                // Это предотвращает "затирание" предыдущих данных и формирует единый объект imcParams.
                
                // Проверяем наличие результатов проверки маркировки в kktCheckResult.machineData
                if (isset($item['kktCheckResult']['machineData']['itemInfoCheckResult'])) {
                    $machineData = $item['kktCheckResult']['machineData'];
                    $itemInfoCheckResult = $machineData['itemInfoCheckResult'];
                    
                    // Объединяем поля из machineData в $imcParams
                    $imcParams['itemInfoCheckResult'] = $itemInfoCheckResult;
                    
                    // Добавляем itemEstimatedStatus из machineData, если он есть
                    if (isset($machineData['itemEstimatedStatus'])) {
                        $imcParams['itemEstimatedStatus'] = $machineData['itemEstimatedStatus'];
                    }
                }
                // Добавляем сформированный объект imcParams как свойство позиции
                $positionItem['imcParams'] = $imcParams;
            }
            // Добавляем позицию в общий список элементов чека
            $checkItems[] = $positionItem;
        }

        // Считаем общую сумму
        $totalAmount = 0.0;
        foreach ($checkItems as $item) {
            $totalAmount += $item['amount'];
        }

        // Формируем оплаты
        $payments = [];
        if (empty($checkDataArr['payments'])) {
            $payments[] = [
                "type" => "cash",
            "sum" => $totalAmount,
            ];
        } else {
            foreach ($checkDataArr['payments'] as $payment) {
                $payments[] = [
                    "type" => $payment['type'],
                    "sum" => floatval($payment['amount'])
                ];
            }
        }

        $checkJSON = [
            "type" => $checkType,
            "operator" => [
                "name" => $checkDataArr['cashier']
            ],
            "items" => $checkItems,
            "payments" => $payments,
        ];

        if (!empty($checkDataArr['taxationType'])) {
            $checkJSON['taxationType'] = $checkDataArr['taxationType'];
        }

        return ['success' => true, 'checkData' => json_encode($checkJSON, JSON_UNESCAPED_UNICODE)];
    }

    // Геттеры для параметров (если нужны)
    public function getComport():int { return $this->comport; }
    public function getIpKkt():string { return $this->ipKkt; }
    public function getPortIpKkt():int { return $this->portIpKkt; }
    public function getIpServKkt():string { return $this->ipServKkt; }
    public function getEmulation():bool { return $this->emulation; }

    public function CancelReceipt() {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }
        $result = $this->fptr->CancelReceipt();
        $success = true;
        $commandErrorDesc = "";
        if ($result !== 0) {
            $success = false;
            $errorDescription = $this->fptr->errorDescription();
            $commandErrorDesc = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription  ?? '');
        }
        return [$success, $commandErrorDesc];
    }
}