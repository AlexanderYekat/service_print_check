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
            return "Драйвер не инициализирован";
        }
        try {
            // Применяем настройки перед открытием
            $this->applySettingsToFptr();
            $this->fptr->Open();
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
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

    public function ApplySingleSettings() {
        // Этот метод теперь используется внутренне Open()
        return null;
    }

    private function applySettingsToFptr() {
        // Пример: установка модели (если поддерживается драйвером)
        if (method_exists($this->fptr, 'SetSingleSetting')) {
            $this->fptr->SetSingleSetting('MODEL', 'ATOL_AUTO');
        }

        if (!empty($this->ipServKkt)) {
            if (method_exists($this->fptr, 'SetSingleSetting')) {
                $this->fptr->SetSingleSetting('REMOTE_SERVER_ADDR', $this->ipServKkt);
            }
        }

        if ($this->comport == 0) {
            if (!empty($this->ipKkt)) {
                if (method_exists($this->fptr, 'SetSingleSetting')) {
                    $this->fptr->SetSingleSetting('PORT', 'TCPIP');
                    $this->fptr->SetSingleSetting('IPADDRESS', $this->ipKkt);
                    if ($this->portIpKkt != 0) {
                        $this->fptr->SetSingleSetting('IPPORT', $this->portIpKkt);
                    }
                }
            } else {
                if (method_exists($this->fptr, 'SetSingleSetting')) {
                    $this->fptr->SetSingleSetting('PORT', 'USB');
                }
            }
        } else {
            $sComPorta = "COM" . $this->comport;
            if (method_exists($this->fptr, 'SetSingleSetting')) {
                $this->fptr->SetSingleSetting('PORT', 'COM');
                $this->fptr->SetSingleSetting('COM_FILE', $sComPorta);
                $this->fptr->SetSingleSetting('BAUDRATE', '115200');
            }
        }

        if (method_exists($this->fptr, 'ApplySingleSettings')) {
            $this->fptr->ApplySingleSettings();
        }
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

    public function Destroy() {
        if ($this->fptr !== null) {
            try {
                $this->fptr->Destroy();
            } catch (Exception $e) {
                // Игнорируем ошибки при уничтожении
            }
            $this->fptr = null;
        }
    }

    // Геттеры для параметров (если нужны)
    public function getComport() { return $this->comport; }
    public function getIpKkt() { return $this->ipKkt; }
    public function getPortIpKkt() { return $this->portIpKkt; }
    public function getIpServKkt() { return $this->ipServKkt; }
    public function getEmulation() { return $this->emulation; }
}

function kktutils_formatCheckJSON($checkDataArr) {
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

    return json_encode($checkJSON, JSON_UNESCAPED_UNICODE);
}

function kktutils_connectWithKassa(TFptr10Driver $fptrDriver) {
    $typeConnect = "";

    // Используем внутренние параметры TFptr10Driver
    $comportint = $fptrDriver->getComport();
    $ipaddresskktper = $fptrDriver->getIpKkt();
    $portkktper = $fptrDriver->getPortIpKkt();
    $ipaddresssrvkktper = $fptrDriver->getIpServKkt();

    $error = $fptrDriver->NewSafe();
    if ($error) {
        return [false, "Ошибка инициализации драйвера: " . $error];
    }

    $error = $fptrDriver->Open();
    if ($error) {
        return [false, "Ошибка открытия соединения с ККТ: " . $error];
    }

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

    // Проверяем, открылось ли соединение
    $isOpened = $fptrDriver->IsOpened();

    return [$isOpened, $typeConnect];
}

function kktutils_sendCommandAndGetAnswerFromKKT($fptr, $comJson, $emulation) {
    $err = null;

    if ($fptr === null) {
        return [null, "не инициализирован драйвер ККТ"];
    }

    // Устанавливаем JSON-команду
    $fptr->setParam('JSON_DATA', $comJson);

    // Валидация и отправка команды (если не эмуляция)
    if (!$emulation) {
        $err = $fptr->processJson();
    }

    if ($err) {
        if (!$emulation) {
            $errorDescr = "Ошибка ФР при отправке команды JSON: $err, описание: $comJson";
            return [null, $errorDescr];
        }
    }

    // Получаем ответ
    $resJson = null;
    $resJson = $fptr->getParamString('JSON_DATA');

    return [$resJson, null];
}

function kktutils_closeKassa($fptr) {
    if ($fptr === null) {
        // Можно добавить логирование, если нужно
        return;
    }
    $fptr->close();
}

function kktutils_successCommand($resultJson) {
    // Проверяем наличие слов "ошибка" или "error" в ответе
    $hasError = (mb_stripos($resultJson, 'ошибка') !== false) || (mb_stripos($resultJson, 'error') !== false);
    return !$hasError;
}