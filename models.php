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

    // Объявление свойств без значений по умолчанию
    public $clearLogs;
    public $debug;
    public $comKkt;
    public $cassir;
    public $ipKkt;
    public $portIpKkt;
    public $ipServKkt;
    public $emulation;
    public $allowedOrigin;
    public $comScale;
    public $baudRateScale;
    public $modelScale;
    public $emulationScale;
    public $bankEmulation;
    public $disableLogging;
    public $updateUrl;
    public $serviceName;
    public $githubRepoOwner;
    public $githubRepoName;
    public $emailForLogs;

    public $permitMarkXApiKey;
    public $permitMarkBaseUrl;
    public $permitMarkLocalHost;
    public $permitMarkTimeout;
    public $permitMarkCdnUnavailableTime;
    public $permitMarkCdnInfoUrl;

    public function __construct(SettingsStorageInterface $storage) {
        $this->storage = $storage;
        $this->_setDefaults(); // Вызываем метод установки значений по умолчанию
    }

    private function _setDefaults(): void {
        $this->clearLogs = true;
        $this->debug = 3;
        $this->comKkt = 0;
        $this->cassir = "Кассир";
        $this->ipKkt = "";
        $this->portIpKkt = 0;
        $this->ipServKkt = "";
        $this->emulation = false;
        $this->allowedOrigin = "";
        $this->comScale = 1001;
        $this->baudRateScale = 18;
        $this->modelScale = 38;
        $this->emulationScale = false;
        $this->bankEmulation = false;
        $this->disableLogging = false;
        $this->updateUrl = "";
        $this->serviceName = "CloudPosBridgeServicePHP";
        $this->githubRepoOwner = "AlexanderYekat";
        $this->githubRepoName = "service_print_check";
        $this->emailForLogs = "";
        $this->permitMarkXApiKey = "";
        $this->permitMarkBaseUrl = "cdn.crpt.ru";
        $this->permitMarkLocalHost = "127.0.0.1:5995";
        $this->permitMarkTimeout = 30;
        $this->permitMarkCdnUnavailableTime = 900; // 15 minutes in seconds
        $this->permitMarkCdnInfoUrl = "/api/v4/true-api/cdn/info";
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
        $this->disableLogging = $data['disableLogging'] ?? $this->disableLogging;
        $this->updateUrl = $data['updateUrl'] ?? $this->updateUrl;
        $this->serviceName = $data['serviceName'] ?? $this->serviceName;
        $this->githubRepoOwner = $data['githubRepoOwner'] ?? $this->githubRepoOwner;
        $this->githubRepoName = $data['githubRepoName'] ?? $this->githubRepoName;
        $this->emailForLogs = $data['emailForLogs'] ?? $this->emailForLogs;
        $this->permitMarkXApiKey = $data['permitMarkXApiKey'] ?? $this->permitMarkXApiKey;
        $this->permitMarkBaseUrl = $data['permitMarkBaseUrl'] ?? $this->permitMarkBaseUrl;
        $this->permitMarkLocalHost = $data['permitMarkLocalHost'] ?? $this->permitMarkLocalHost;
        $this->permitMarkTimeout = $data['permitMarkTimeout'] ?? $this->permitMarkTimeout;
        $this->permitMarkCdnUnavailableTime = $data['permitMarkCdnUnavailableTime'] ?? $this->permitMarkCdnUnavailableTime;
        $this->permitMarkCdnInfoUrl = $data['permitMarkCdnInfoUrl'] ?? $this->permitMarkCdnInfoUrl;
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
            'disableLogging' => $this->disableLogging,
            'updateUrl' => $this->updateUrl,
            'serviceName' => $this->serviceName,
            'githubRepoOwner' => $this->githubRepoOwner,
            'githubRepoName' => $this->githubRepoName,
            'emailForLogs' => $this->emailForLogs,
            'permitMarkXApiKey' => $this->permitMarkXApiKey,
            'permitMarkBaseUrl' => $this->permitMarkBaseUrl,
            'permitMarkLocalHost' => $this->permitMarkLocalHost,
            'permitMarkTimeout' => $this->permitMarkTimeout,
            'permitMarkCdnUnavailableTime' => $this->permitMarkCdnUnavailableTime,
            'permitMarkCdnInfoUrl' => $this->permitMarkCdnInfoUrl,
        ];
    }

    public function resetToDefaults(): void {
        $this->_setDefaults();
    }
}

// Класс для ответа API (ApiResponse)
class ApiResponse {
    public $success;
    public $message;
    public $data;
    public $id;
    public $time;

    public function __construct(string $status, string $message, array $data = [], string $id = "") {
        $this->success = $status === 'success';
        $this->message = $message;
        $this->data = $data;
        $this->id = $id;
        $this->time = round(microtime(true) * 1000);
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'id' => $this->id,
            'time' => $this->time,
        ];
    }
}
