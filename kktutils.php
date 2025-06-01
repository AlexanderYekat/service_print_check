<?php
// kktutils.php

require_once 'models.php';

class TFptr10Driver {
    private $fptr = null;

    public function NewSafe() {
        try {
            if ($this->fptr === null) {
                // Здесь должна быть инициализация драйвера ККТ
                // В PHP это может быть COM-объект или другой способ подключения к драйверу
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
        if ($this->fptr === null) {
            return "Драйвер не инициализирован";
        }
        try {
            $this->fptr->ApplySingleSettings();
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
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
}

function kktutils_formatCheckJSON($checkDataArr) {
    // Если передан объект, преобразуем в массив
    if ($checkDataArr instanceof CheckData) {
        $checkDataArr = [
            'taxationType' => $checkDataArr->taxationType,
            'type' => $checkDataArr->type,
            'cashier' => $checkDataArr->cashier,
            'tableData' => [],
            'payments' => [],
        ];
        foreach ($checkDataArr->tableData as $item) {
            $checkDataArr['tableData'][] = [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'taxNDS' => $item->taxNDS,
            ];
        }
        foreach ($checkDataArr->payments as $pay) {
            $checkDataArr['payments'][] = [
                'type' => $pay->type,
                'amount' => $pay->amount,
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

function kktutils_connectWithKassa($fptr, $comportint, $ipaddresskktper, $portkktper, $ipaddresssrvkktper) {
    $typeConnect = "";

    // Пример: установка модели (если поддерживается драйвером)
    if (method_exists($fptr, 'SetSingleSetting')) {
        $fptr->SetSingleSetting('MODEL', 'ATOL_AUTO');
    }

    if (!empty($ipaddresssrvkktper)) {
        if (method_exists($fptr, 'SetSingleSetting')) {
            $fptr->SetSingleSetting('REMOTE_SERVER_ADDR', $ipaddresssrvkktper);
        }
        $typeConnect = "через сервер ККТ по IP $ipaddresssrvkktper";
    }

    if ($comportint == 0) {
        if (!empty($ipaddresskktper)) {
            if (method_exists($fptr, 'SetSingleSetting')) {
                $fptr->SetSingleSetting('PORT', 'TCPIP');
                $fptr->SetSingleSetting('IPADDRESS', $ipaddresskktper);
                if ($portkktper != 0) {
                    $fptr->SetSingleSetting('IPPORT', $portkktper);
                }
            }
            $typeConnect .= " по IP $ipaddresskktper ККТ на порт $portkktper";
        } else {
            if (method_exists($fptr, 'SetSingleSetting')) {
                $fptr->SetSingleSetting('PORT', 'USB');
            }
            $typeConnect .= " по USB";
        }
    } else {
        $sComPorta = "COM" . $comportint;
        if (method_exists($fptr, 'SetSingleSetting')) {
            $fptr->SetSingleSetting('PORT', 'COM');
            $fptr->SetSingleSetting('COM_FILE', $sComPorta);
            $fptr->SetSingleSetting('BAUDRATE', '115200');
        }
        $typeConnect .= " по COM порту $sComPorta";
    }

    // Применяем настройки и открываем соединение
    if (method_exists($fptr, 'ApplySingleSettings')) {
        $fptr->ApplySingleSettings();
    }
    if (method_exists($fptr, 'Open')) {
        $fptr->Open();
    }

    // Проверяем, открылось ли соединение
    $isOpened = false;
    if (method_exists($fptr, 'IsOpened')) {
        $isOpened = $fptr->IsOpened();
    } elseif (property_exists($fptr, 'isOpened')) {
        $isOpened = $fptr->isOpened;
    }

    return [$isOpened, $typeConnect];
}

function kktutils_sendCommandAndGetAnswerFromKKT($fptr, $comJson, $emulation) {
    $err = null;

    if ($fptr === null) {
        return [null, "не инициализирован драйвер ККТ"];
    }

    // Устанавливаем JSON-команду
    if (method_exists($fptr, 'SetParam')) {
        $fptr->SetParam('JSON_DATA', $comJson);
    } elseif (method_exists($fptr, 'setParam')) {
        $fptr->setParam('JSON_DATA', $comJson);
    }

    // Валидация и отправка команды (если не эмуляция)
    if (!$emulation) {
        if (method_exists($fptr, 'ProcessJson')) {
            $err = $fptr->ProcessJson();
        } elseif (method_exists($fptr, 'processJson')) {
            $err = $fptr->processJson();
        }
    }

    if ($err) {
        if (!$emulation) {
            $errorDescr = "Ошибка ФР при отправке команды JSON: $err, описание: $comJson";
            return [null, $errorDescr];
        }
    }

    // Получаем ответ
    $resJson = null;
    if (method_exists($fptr, 'GetParamString')) {
        $resJson = $fptr->GetParamString('JSON_DATA');
    } elseif (method_exists($fptr, 'getParamString')) {
        $resJson = $fptr->getParamString('JSON_DATA');
    }

    return [$resJson, null];
}

function kktutils_closeKassa($fptr) {
    if ($fptr === null) {
        // Можно добавить логирование, если нужно
        return;
    }

    if (method_exists($fptr, 'Close')) {
        $fptr->Close();
    } elseif (method_exists($fptr, 'close')) {
        $fptr->close();
    }
}

function kktutils_successCommand($resultJson) {
    // Проверяем наличие слов "ошибка" или "error" в ответе
    $hasError = (mb_stripos($resultJson, 'ошибка') !== false) || (mb_stripos($resultJson, 'error') !== false);
    return !$hasError;
}

function kktutils_version($fptr) {
    if ($fptr === null) {
        return "";
    }
    
    if (method_exists($fptr, 'Version')) {
        return $fptr->Version();
    } elseif (method_exists($fptr, 'version')) {
        return $fptr->version();
    }
    return "";
}

function kktutils_getFptr10($fptr) {
    return $fptr;
}

function kktutils_destroy($fptr) {
    if ($fptr !== null) {
        if (method_exists($fptr, 'Destroy')) {
            $fptr->Destroy();
        } elseif (method_exists($fptr, 'destroy')) {
            $fptr->destroy();
        }
    }
}
