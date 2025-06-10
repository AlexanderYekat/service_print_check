<?php
// jsontokkt.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header("Access-Control-Allow-Origin: null");
} elseif ($origin) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Private-Network: true");

define('VERSION_OF_PROGRAM', '2025_05_31_01');

// Здесь должны быть ваши классы/модули для работы с ККТ и настройками
require_once 'handlers.php';
require_once 'kktutils.php';
require_once 'settings.php';
require_once 'models.php';

// Глобальные переменные (эти строки будут удалены или закомментированы)
// $glFptrDriver = new TFptr10Driver();
// $currentSettings = new Settings();

function runServer() {
    // global $glFptrDriver, $currentSettings; (эта строка будет удалена)

    $currentSettings = new Settings();
    $currentSettings->load();

    // Создаем экземпляр TFptr10Driver с параметрами подключения из настроек
    $FptrDriver = new TFptr10Driver(
        $currentSettings->comPort,
        $currentSettings->ipAddressKkt,
        $currentSettings->portKktAtol,
        $currentSettings->ipAddressServRKkt,
        $currentSettings->emulation
    );

    // Инициализация драйвера ККТ
    $err = $FptrDriver->NewSafe();
    if ($err !== null) {
        error_log("Ошибка при инициализации драйвера ККТ: $err");
        http_response_code(500);
        echo json_encode(['error' => "Ошибка при инициализации драйвера ККТ: $err"]);
        exit;
    }

    // Создаем экземпляр CheckService, передавая ему FptrDriver
    $bankComObject = null;
    try {
        $bankComObject = new COM("SBRFSRV.Server");
    } catch (Exception $e) {
        error_log("Не удалось создать COM-объект SBRFSRV.Server: " . $e->getMessage());
    }

    $scaleComObject = null;
    try {
        $scaleComObject = new COM("AddIn.Scale8");
    } catch (Exception $e) {
        error_log("Не удалось создать COM-объект AddIn.Scale8: " . $e->getMessage());
    }

    $checkService = new CheckService($FptrDriver, $bankComObject, $scaleComObject);

    $fetchHandler = new Handler(
        $checkService
    );

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($uri === '/api/print-check' && $method === 'POST') {
        $fetchHandler->HandlePrintCheck();
    } elseif ($uri === '/api/close-shift' && $method === 'POST') {
        $fetchHandler->HandleCloseShift();
    } elseif ($uri === '/api/x-report' && $method === 'POST') {
        $fetchHandler->HandleXReport();
    } elseif ($uri === '/api/cash-in' && $method === 'POST') {
        $fetchHandler->HandleCashIn();
    } elseif ($uri === '/api/cash-out' && $method === 'POST') {
        $fetchHandler->HandleCashOut();
    } elseif ($uri === '/api/bank-operation' && $method === 'POST') {
        $fetchHandler->HandleBankOperation();
    } elseif ($uri === '/api/get-weight' && $method === 'POST') {
        $fetchHandler->HandleGetWeight();
    } elseif ($uri === '/api/print-bank-slip' && $method === 'POST') {
        $fetchHandler->HandlePrintBankSlip();
    } elseif ($uri === '/api/return-many' && $method === 'POST') {
        $fetchHandler->HandleReturnMany();
    } elseif ($uri === '/api/close-bank-shift' && $method === 'POST') {
        $fetchHandler->HandleCloseBankShift();
    } elseif ($uri === '/api/get-settings' && $method === 'GET') {
        echo json_encode($currentSettings->toArray(), JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/save-settings' && $method === 'POST') {
        $input = file_get_contents('php://input');
        $newSettingsData = json_decode($input, true);
        $currentSettings->fillFromArray($newSettingsData);
        $currentSettings->save();
        echo json_encode(['success' => true, 'message' => 'Настройки сохранены'], JSON_UNESCAPED_UNICODE);
    } elseif ($method === 'OPTIONS') {
        // Для CORS preflight
        http_response_code(204);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function main() {
    //echo "запускаем как обычное приложение\n";
    runServer();
}

main();
