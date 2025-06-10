<?php
// models.php
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
    public $clearLogs = true;
    public $debug = 3;
    public $comKkt = 0;
    public $cassir = "Кассир";
    public $ipKkt = "";
    public $portIpKkt = 0;
    public $ipServKkt = "";
    public $emulation = false;
    public $allowedOrigin = "";

    public function __construct($data = null) {
        if ($data) {
            $this->clearLogs = $data['clearLogs'] ?? true;
            $this->debug = $data['debug'] ?? 3;
            $this->comKkt = $data['comKkt'] ?? 0;
            $this->cassir = $data['cassir'] ?? "Кассир";
            $this->ipKkt = $data['ipKkt'] ?? "";
            $this->portIpKkt = $data['portIpKkt'] ?? 0;
            $this->ipServKkt = $data['ipServKkt'] ?? "";
            $this->emulation = $data['emulation'] ?? false;
            $this->allowedOrigin = $data['allowedOrigin'] ?? "";
        }
    }
}
