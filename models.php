<?php
// models.php

require_once 'settings_storage/SettingsStorageInterface.php';

// Элемент чека (CheckItem)
class CheckItem {
    public $name;
    public $quantity;
    public $price;
    public $taxNDS;

    public function __construct($data = null) {
        if ($data) {
            $this->name = $data['name'] ?? '';
            $this->quantity = $data['quantity'] ?? '';
            $this->price = $data['price'] ?? '';
            $this->taxNDS = $data['taxNDS'] ?? null;
        }
    }
}

// Информация об оплате (Payment)
class Payment {
    public $type;
    public $amount;

    public function __construct($data = null) {
        if ($data) {
            $this->type = $data['type'] ?? '';
            $this->amount = $data['amount'] ?? 0.0;
        }
    }
}

// Данные чека (CheckData)
class CheckData {
    public $taxationType;
    public $type;
    public $cashier;
    public $tableData = []; // array of CheckItem
    public $payments = [];  // array of Payment

    public function __construct($data = null) {
        if ($data) {
            $this->taxationType = $data['taxationType'] ?? null;
            $this->type = $data['type'] ?? '';
            $this->cashier = $data['cashier'] ?? '';
            if (!empty($data['tableData']) && is_array($data['tableData'])) {
                foreach ($data['tableData'] as $item) {
                    $this->tableData[] = new CheckItem($item);
                }
            }
            if (!empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $pay) {
                    $this->payments[] = new Payment($pay);
                }
            }
        }
    }
}

// Настройки приложения (Settings)
class Settings {
    private $storage;

    public $clearLogs = true;
    public $debug = 3;
    public $comKkt = 0;
    public $cassir = "Кассир";
    public $ipKkt = "";
    public $portIpKkt = 0;
    public $ipServKkt = "";
    public $emulation = false;
    public $allowedOrigin = "";
    public $comScale = 1000; // Номер COM-порта для весов по умолчанию
    public $baudRateScale = 18; // Скорость передачи данных (BaudRate) для весов по умолчанию (18 = 115200)
    public $modelScale = 38; // Модель весов по умолчанию (38 = АТОЛ Марта)
    public $emulationScale = false; // Эмуляция весов по умолчанию
    public $bankEmulation = false; // Эмуляция банковского терминала по умолчанию

    public function __construct(SettingsStorageInterface $storage) {
        $this->storage = $storage;
        // По умолчанию, если нет загруженных настроек, используются эти значения
        $this->clearLogs = true;
        $this->debug = 3;
        $this->comKkt = 0;
        $this->cassir = "Кассир";
        $this->ipKkt = "";
        $this->portIpKkt = 0;
        $this->ipServKkt = "";
        $this->emulation = false;
        $this->allowedOrigin = "";
        $this->comScale = 1000;
        $this->baudRateScale = 18;
        $this->modelScale = 38;
        $this->emulationScale = false;
        $this->bankEmulation = false;
    }

    public function load(): void {
        $data = $this->storage->load();
        $this->fillFromArray($data);
    }

    public function save(): void {
        $data = $this->toArray();
        $this->storage->save($data);
    }

    public function fillFromArray(array $data): void {
        $this->clearLogs = $data['clearLogs'] ?? $this->clearLogs;
        $this->debug = $data['debug'] ?? $this->debug;
        $this->comKkt = $data['comKkt'] ?? $this->comKkt;
        $this->cassir = $data['cassir'] ?? $this->cassir;
        $this->ipKkt = $data['ipKkt'] ?? $this->ipKkt;
        $this->portIpKkt = $data['portIpKkt'] ?? $this->portIpKkt;
        $this->ipServKkt = $data['ipServKkt'] ?? $this->ipServKkt;
        $this->emulation = $data['emulation'] ?? $this->emulation;
        $this->allowedOrigin = $data['allowedOrigin'] ?? $this->allowedOrigin;
        $this->comScale = $data['comScale'] ?? $this->comScale;
        $this->baudRateScale = $data['baudRateScale'] ?? $this->baudRateScale;
        $this->modelScale = $data['modelScale'] ?? $this->modelScale;
        $this->emulationScale = $data['emulationScale'] ?? $this->emulationScale;
        $this->bankEmulation = $data['bankEmulation'] ?? $this->bankEmulation;
    }

    public function toArray(): array {
        return [
            'clearLogs' => $this->clearLogs,
            'debug' => $this->debug,
            'comKkt' => $this->comKkt,
            'cassir' => $this->cassir,
            'ipKkt' => $this->ipKkt,
            'portIpKkt' => $this->portIpKkt,
            'ipServKkt' => $this->ipServKkt,
            'emulation' => $this->emulation,
            'allowedOrigin' => $this->allowedOrigin,
            'comScale' => $this->comScale,
            'baudRateScale' => $this->baudRateScale,
            'modelScale' => $this->modelScale,
            'emulationScale' => $this->emulationScale,
            'bankEmulation' => $this->bankEmulation,
        ];
    }

    public function resetToDefaults(): void {
        $this->clearLogs = true;
        $this->debug = 3;
        $this->comKkt = 0;
        $this->cassir = "Кассир";
        $this->ipKkt = "";
        $this->portIpKkt = 0;
        $this->ipServKkt = "";
        $this->emulation = false;
        $this->allowedOrigin = "";
        $this->comScale = 1000;
        $this->baudRateScale = 18;
        $this->modelScale = 38;
        $this->emulationScale = false;
        $this->bankEmulation = false;
    }
}

// Класс для ответа API (ApiResponse)
class ApiResponse {
    public $type;
    public $message;
    public $data;
    public $id;
    public $time;

    public function __construct(string $type, string $message, array $data = [], string $id = "") {
        $this->type = $type;
        $this->message = $message;
        $this->data = (object)$data; // Преобразуем массив в объект
        $this->id = $id;
        $this->time = round(microtime(true) * 1000);
    }

    public function toArray(): array {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'data' => $this->data,
            'id' => $this->id,
            'time' => $this->time,
        ];
    }
}
