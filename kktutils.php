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

    public function __construct($comport = 0, $ipKkt = "", $portIpKkt = 0, $ipServKkt = "", $emulation = false) {
        $this->comport = $comport;
        $this->ipKkt = $ipKkt;
        $this->portIpKkt = $portIpKkt;
        $this->ipServKkt = $ipServKkt;
        $this->emulation = $emulation;
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
        try {
            // Применяем настройки перед открытием
            $this->applySettingsToFptr();
            $result = $this->fptr->Open();
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription);
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
        try {
            $this->fptr->SetParam($this->fptr->LIBFPTR_PARAM_DATA_TYPE, $this->fptr->LIBFPTR_DT_SHIFT_STATE);
            $this->fptr->QueryData();

            $result = $this->fptr->GetParamInt($this->fptr->LIBFPTR_PARAM_SHIFT_STATE);
            return [$result === 1, ""];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }

    public function PrintXReport(string $cashier) {
        if ($this->fptr === null) {
            return [false, "", "Драйвер не инициализирован"];
        }

        $closeShiftJson = json_encode([
            "type" => "reportX",
            "operator" => [
                "name" => $cashier
            ]
        ], JSON_UNESCAPED_UNICODE);

        // Используем sendCommandAndGetAnswerFromKKT для отправки JSON-команды
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($closeShiftJson);
        
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

    public function sendCommandAndGetAnswerFromKKT($comJson) {
        $err = null;

        if ($this->fptr === null) {
            return [false, "", "не инициализирован драйвер ККТ"];
        }

        // Устанавливаем JSON-команду
        $this->fptr->setParam($this->fptr->LIBFPTR_PARAM_JSON_DATA, $comJson);

        // отправка команды (если не эмуляция)
        if (!$this->emulation) {
            $result = $this->fptr->processJson();
            if ($result !== 0) {
                $errorDescription = $this->fptr->errorDescription();
                $errorDescription = iconv('Windows-1251', 'UTF-8//IGNORE', $errorDescription);
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
            return [false, "", "Ошибка обработки ответа от ККТ"];
        }

        return [true, $jsonAnswer, ""];
    }
    
    public function SuccessCommand($resultJson) {
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

    // Если передан объект, преобразуем в массив
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
                ];
            }
            foreach ($originalCheckData->payments as $pay) {
            $checkDataArr['payments'][] = [
                    'type' => $pay['type'],
                    'amount' => $pay['amount'],
            ];
        }
    }

    // Формируем позиции чека
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
            $checkItems[] = [
                "type" => "position",
                "name" => $item['name'],
                "price" => $price,
                "quantity" => $quantity,
                "amount" => $price * $quantity,
                "tax" => [
                    "type" => $taxType
                ]
            ];
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
            "sum" => $totalAmount
        ];
    } else {
        foreach ($checkDataArr['payments'] as $payment) {
            $payments[] = [
                "type" => $payment['type'],
                "sum" => floatval($payment['amount'])
            ];
        }
    }

    $checkType = !empty($checkDataArr['type']) ? $checkDataArr['type'] : "sell";

    $checkJSON = [
        "type" => $checkType,
        "operator" => [
            "name" => $checkDataArr['cashier']
        ],
        "items" => $checkItems,
        "payments" => $payments
    ];

    if (!empty($checkDataArr['taxationType'])) {
        $checkJSON['taxationType'] = $checkDataArr['taxationType'];
    }

        return ['success' => true, 'checkData' => json_encode($checkJSON, JSON_UNESCAPED_UNICODE)];
    }

    // Геттеры для параметров (если нужны)
    public function getComport() { return $this->comport; }
    public function getIpKkt() { return $this->ipKkt; }
    public function getPortIpKkt() { return $this->portIpKkt; }
    public function getIpServKkt() { return $this->ipServKkt; }
    public function getEmulation() { return $this->emulation; }

    public function CancelReceipt() {
        if ($this->fptr === null) {
            return [false, "Драйвер не инициализирован"];
        }

        $cancelJson = json_encode(["type" => "cancelReceipt"], JSON_UNESCAPED_UNICODE);
        list($success, $responseJson, $commandErrorDesc) = $this->sendCommandAndGetAnswerFromKKT($cancelJson);
        return [$success, $commandErrorDesc];
    }
}