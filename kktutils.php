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
    private $emulationwait;
    private $logger;

    public function __construct($comport = 0, $ipKkt = "", $portIpKkt = 0, $ipServKkt = "", $logger = null, $emulation = false, $emulationwait = false) {
        $this->comport = $comport;
        $this->ipKkt = $ipKkt;
        $this->portIpKkt = $portIpKkt;
        $this->ipServKkt = $ipServKkt;
        $this->emulation = $emulation;
        $this->emulationwait = $emulationwait;
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

    public function OpenShift(string $cashier, string $cashierVatin = "") {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }
        $this->fptr->setParam(1021, $cashier);
        if (!empty($cashierVatin)) {
            $this->fptr->setParam(1203, $cashierVatin);
        }
        $this->fptr->operatorLogin;
    
        $result = $this->fptr->OpenShift();
        if ($result !== 0) {
            $errorDescription = $this->fptr->errorDescription();
            $commandErrorDesc = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription  ?? '');
            if (!$this->emulation) {
                return [false, $commandErrorDesc];
            }
        }
        return [true, ""];
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
        //return [$shiftOpened, $commandErrorDesc, $result];
        $successGetInfoShift = true;
        if ($commandErrorDesc !== "") {
            $successGetInfoShift = false;
        }
        if ($this->emulation) {
            $commandErrorDesc = "";
            $successGetInfoShift = true;
            $result = 1;
            $shiftOpened = true;
        }
        return [$successGetInfoShift, json_encode(['isShiftOpened' => $shiftOpened, 'constOfSmeny' => $result], JSON_UNESCAPED_UNICODE), $commandErrorDesc];
    }

    public function setTimeZone(int $timeZone) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }
        $this->fptr->setSingleSetting($this->fptr->LIBFPTR_SETTING_TIME_ZONE, $timeZone);
        $this->fptr->applySingleSettings();
        return [true, "", ""];
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

		// Ожидаем завершения проверки КМ (поллинг статуса)
		$maxWaitSeconds = 60; // таймаут ожидания
		$isReady = false;
		$lastResponseJson = "";
		$prevNonEmptyResponseJson = "";
		for ($i = 0; $i < $maxWaitSeconds; $i++) {
			$checkMarkingCodeValidation = $this->checkMarkingCodeValidation();
			if (!$checkMarkingCodeValidation[0]) {
				return [false, "", $checkMarkingCodeValidation[2]];
			}
			$lastResponseJson = (string)$checkMarkingCodeValidation[1];
            $this->logger->debug("checkMarkingCode: lastResponseJson: " . $lastResponseJson);
			if ($lastResponseJson === "") {
				$this->logger->debug("checkMarkingCode: пустой ответ статуса, повторю запрос...");
				sleep(1);
				continue;
			}
			$prevNonEmptyResponseJson = $lastResponseJson;
			$resp = json_decode($lastResponseJson, true);
			if (!is_array($resp)) {
				// если парсинг не удался, но ранее был валидный ответ — используем его
				if ($prevNonEmptyResponseJson !== "") {
					$this->logger->debug("checkMarkingCode: не удалось распарсить ответ, использую предыдущий валидный");
					$resp = json_decode($prevNonEmptyResponseJson, true);
					if (!is_array($resp)) {
						return [false, "", "Некорректный ответ при проверке КМ"];
					}
				} else {
					return [false, "", "Некорректный ответ при проверке КМ"];
				}
			}
			// Проверяем флаг готовности
			if (!empty($resp["ready"])) {
				// Проверяем ошибки драйвера, если есть
				$driverErrorCode = $resp["driverError"]["code"] ?? 0;
				if ($driverErrorCode !== 0) {
					return [false, "", "Ошибка драйвера при проверке КМ: код " . $driverErrorCode];
				}
				$isReady = true;
				break;
			}
			sleep(1);
		}

		if (!$isReady) {
			// По возможности отменяем проверку, чтобы не оставлять висящее состояние
			try { $this->cancelMarkingCodeValidation(); } catch (\Throwable $e) {}
			return [false, "", "Истек таймаут ожидания готовности проверки КМ"];
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
        $this->logger->debug("beginMarkingCodeValidation: imc: " . $imc . " (длина: " . strlen($imc) . ")");
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
            if (strpos($commandErrorDesc, "Процедура проверки КМ уже запущена") !== false) {
                $cancelMarkingCodeValidation = $this->cancelMarkingCodeValidation();
                if (!$cancelMarkingCodeValidation[0]) {
                    $this->logger->error("Ошибка отмены валидации кода маркировки: " . $cancelMarkingCodeValidation[2]);
                    return [false, "", "Ошибка отмены валидации кода маркировки: " . $cancelMarkingCodeValidation[2]];
                }    
                list($success, $responseJson, $commandErrorDesc) =  $this->sendCommandAndGetAnswerFromKKT($beginMarkingCodeValidationJson); 
            }            
            if (!$success) {
                $this->logger->error("Ошибка начала валидации кода маркировки: " . $commandErrorDesc);
                return [false, "", "Ошибка начала валидации кода маркировки: " . $commandErrorDesc];
            }
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
            $this->logger->info("Эмуляция checkMarkingCodeValidation...");
            if ($this->emulationwait) {
                $this->logger->info("Эмуляция задержки 60 секунд...");
                sleep(5);
                $this->logger->info("Эмуляция задержки 60 секунд завершена...");                
            }
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
    public function clearMarkingCodes()
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
        $this->logger->debug("sendCommandAndGetAnswerFromKKT: comJson: " . $comJson);
        $this->fptr->setParam($this->fptr->LIBFPTR_PARAM_JSON_DATA, $comJson);

        // отправка команды (если не эмуляция)
        if (!$this->emulation) {
            $this->fptr->processJson();
            $errorCode = $this->fptr->errorCode();
            $this->logger->debug("sendCommandAndGetAnswerFromKKT: errorCode: " . $errorCode);
            if ($errorCode !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription  ?? '');
                $this->logger->debug("sendCommandAndGetAnswerFromKKT: errorDescription: " . $errorDescription);
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
                case 'clearMarkingCodeValidationResult':
                    $resJson = '{ "success": true, "message": "Результат очистки валидации кода маркировки успешно очищен (мок)" }';
                    break;
                default: // По умолчанию для других команд, включая printCheck
                    $resJson = '{ "fiscalParams" : { "fiscalDocumentDateTime" : "2018-03-06T13:52:00+03:00", "fiscalDocumentNumber" : 71, "fiscalDocumentSign" : "1494325660", "fiscalReceiptNumber" : 1, "fnNumber" : "9999078900000961", "registrationNumber" : "0000000001002292", "shiftNumber" : 12, "total" : 390.75, "fnsUrl": "www.nalog.gov.ru" }, "warnings": null }';
                    break;
            }
            return [true, $resJson, ""];
        }
    
		// Получаем ответ от ККТ
		$jsonAnswerRaw = $this->fptr->GetParamString($this->fptr->LIBFPTR_PARAM_JSON_DATA);
		$this->logger->debug("sendCommandAndGetAnswerFromKKT: jsonAnswer: " . $jsonAnswerRaw);
		// Ответ от драйвера может быть в Windows-1251, конвертируем в UTF-8 перед разбором
		$jsonAnswerUtf8 = iconv('Windows-1251', 'UTF-8//IGNORE', $jsonAnswerRaw  ?? '');
		$decoded = json_decode($jsonAnswerUtf8, true);
		if ($decoded === null) {
			// Если декодирование не удалось, но ответ не пустой — вернём строку как есть (UTF-8)
			if (trim((string)$jsonAnswerUtf8) !== '') {
				return [true, $jsonAnswerUtf8, ""];
			}
			return [true, "", ""];
		}
		return [true, json_encode($decoded, JSON_UNESCAPED_UNICODE), ""];
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
                    // Преобразуем маркировку в base64 для ККТ
                    $markingCodeBase64 = base64_encode($item['markingCode']);
                    
                    // Инициализируем imcParams с базовыми данными маркировки
                    $imcParams = [
                        "imc" => $markingCodeBase64,
                        "imcType" => "auto",
                        "itemEstimatedStatus" => $item['itemEstimatedStatus'] ?? $itemEstimatedStatus,
                        "imcModeProcessing" => 0
                    ];

                    // Если в данных позиции ($item) присутствуют дополнительные параметры маркировки
                    // (например, результаты проверки марки), объединяем их с базовыми imcParams.
                    // Это предотвращает "затирание" предыдущих данных и формирует единый объект imcParams.
                    
                    // Проверяем наличие результатов проверки маркировки в kktCheckResult
                    if (isset($item['kktCheckResult']['data']['response']['itemInfoCheckResult'])) {
                        $responseData = $item['kktCheckResult']['data']['response'];
                        $itemInfoCheckResult = $responseData['itemInfoCheckResult'];
                        
                        // Объединяем поля из response в $imcParams
                        $imcParams['itemInfoCheckResult'] = $itemInfoCheckResult;
                        
                        // Добавляем itemEstimatedStatus из response, если он есть
                        if (isset($responseData['itemEstimatedStatus'])) {
                            $imcParams['itemEstimatedStatus'] = $responseData['itemEstimatedStatus'];
                        }
                        
                        $this->logger->info("Добавлены результаты проверки ККТ в imcParams: " . json_encode($itemInfoCheckResult));
                    }
                    // Добавляем сформированный объект imcParams как свойство позиции
                    $positionItem['imcParams'] = $imcParams;
                    //разрешительный режим маркировки
                    $this->logger->info("Разрешительный режим маркировки industryInfo: " . json_encode($item['permitCheckResult']));
                    // Универсальная проверка OK в разных вариантах ответа API
                    $permitResponse = $item['permitCheckResult']['data']['response'] ?? [];
                    $okFlag = null;
                    if (isset($permitResponse['user_status']['ok'])) {
                        $okFlag = $permitResponse['user_status']['ok'];
                    } elseif (isset($permitResponse['']['ok'])) { // как в логах пользователя: ключ ""
                        $okFlag = $permitResponse['']['ok'];
                    } elseif (isset($permitResponse['ok'])) { // запасной вариант
                        $okFlag = $permitResponse['ok'];
                    }

                    if ($okFlag === true) {
                        $this->logger->info("Разрешительный режим маркировки status успешно: " . json_encode($item['permitCheckResult']));
                        // Достаём machine_data из ответа, если доступно
                        $machineData = $permitResponse['machine_data'] ?? [];
                        $this->logger->info("Разрешительный режим маркировки machineData: " . json_encode($machineData));
                        $uuid = $machineData['uuid'] ?? '';
                        $time = $machineData['timeStamp'] ?? '';
                        $inst = $machineData['inst'] ?? '';
                        $ver = $machineData['ver'] ?? '';
                        
                        $industryDetails = "UUID=" . $uuid . "&Time=" . $time;
                        if (!empty($inst) && $inst !== "N/A") {
                            $industryDetails .= "&Inst=" . $inst;
                        }
                        if (!empty($ver) && $ver !== "N/A") {
                            $industryDetails .= "&Ver=" . $ver;
                        }
                        
                        // Определяем тип документа на основе категории товара
                        $category = $item['category'] ?? '';
                        $categoryInfo = $this->getCategoryDocumentInfo($category);
                        $fois = $categoryInfo['fois'];
                        $documentDate = $categoryInfo['date'];
                        $documentNumber = $categoryInfo['number'];
                        
                        // Логируем информацию о категории и документе
                        if ($this->logger) {
                            $this->logger->debug("Категория товара: '{$category}', FOIS: {$fois}, Дата: {$documentDate}, Номер: {$documentNumber}");
                        }
                                                
                        // Формируем industryInfo
                        $positionItem['industryInfo'] = [
                            "fois" => $fois,
                            "date" => $documentDate,
                            "number" => $documentNumber,
                            "industryAttribute" => $industryDetails
                        ];
                    }
                }
                // Добавляем позицию в общий список элементов чека
                $checkItems[] = $positionItem;
            }
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

    /**
     * Определяет FOIS, дату и номер документа на основе категории товара
     * 
     * @param string $category Категория товара
     * @return array Массив с ключами: fois, date, number
     */
    private function getCategoryDocumentInfo($category) {
        // Маппинг категорий товаров на соответствующие документы
        $categoryMapping = [
            'dairy' => [ // Молочные продукты
                'fois' => '030',
                'date' => '2020.12.15',
                'number' => '2099'
            ],
            'beverages' => [ // Газированные напитки, соки, компоты, упакованная вода
                'fois' => '030',
                'date' => '2023.05.31',
                'number' => '887'
            ],
            'canned_food' => [ // Консервы (рыбные, мясные, овощные)
                'fois' => '030',
                'date' => '2024.05.27',
                'number' => '677'
            ],
            'vegetable_oils' => [ // Растительные масла
                'fois' => '030',
                'date' => '2024.05.27',
                'number' => '676'
            ],
            'seafood_caviar' => [ // Морепродукты (икра)
                'fois' => '030',
                'date' => '2023.11.29',
                'number' => '2028'
            ],
        ];
        // Нормализуем через централизованную функцию моделей (без дублирования логики)
        $selectedKey = CheckItem::normalizeCategoryKey($category);
        if (!isset($categoryMapping[$selectedKey])) {
            $this->logger->error("Категория товара не найдена: " . $category);
            return [
                'fois' => '030',
                'date' => '2024.05.27',
                'number' => '674'
            ];
        }
        return $categoryMapping[$selectedKey];
    }
}