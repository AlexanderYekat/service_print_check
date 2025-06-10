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

    public function GetFptr10() {
        return $this->fptr;
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
    $fptr->setSingleSetting('MODEL', 'ATOL_AUTO');

    if (!empty($ipaddresssrvkktper)) {
        $fptr->setSingleSetting('REMOTE_SERVER_ADDR', $ipaddresssrvkktper);
        $typeConnect = "через сервер ККТ по IP $ipaddresssrvkktper";
    }

    if ($comportint == 0) {
        if (!empty($ipaddresskktper)) {
            $fptr->setSingleSetting('PORT', 'TCPIP');
            $fptr->setSingleSetting('IPADDRESS', $ipaddresskktper);
            if ($portkktper != 0) {
                $fptr->setSingleSetting('IPPORT', $portkktper);
            }
            $typeConnect .= " по IP $ipaddresskktper ККТ на порт $portkktper";
        } else {
            $fptr->setSingleSetting('PORT', 'USB');
            $typeConnect .= " по USB";
        }
    } else {
        $sComPorta = "COM" . $comportint;
        $fptr->setSingleSetting('PORT', 'COM');
        $fptr->setSingleSetting('COM_FILE', $sComPorta);
        $fptr->setSingleSetting('BAUDRATE', '115200');
        $typeConnect .= " по COM порту $sComPorta";
    }

    // Применяем настройки и открываем соединение
    $fptr->applySingleSettings();
    $fptr->open();

    // Проверяем, открылось ли соединение
    $isOpened = $fptr->isOpened;

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