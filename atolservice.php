<?php
// jsontokkt.php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header("Access-Control-Allow-Origin: null");
} elseif ($origin) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: content-type, access-control-request-private-network");
header("Access-Control-Allow-Private-Network: true");

define('VERSION_OF_PROGRAM', '2025_05_31_01');

// Здесь должны быть ваши классы/модули для работы с ККТ и настройками
require_once 'handlers.php';
require_once 'kktutils.php';
require_once 'settings.php';

// Глобальные переменные
$glFptrDriver = new TFptr10Driver();
$currentSettings = new Settings();

function runServer() {
    global $glFptrDriver, $currentSettings;

    // Инициализация драйвера ККТ
    $err = $glFptrDriver->NewSafe();
    if ($err !== null) {
        error_log("Ошибка при инициализации драйвера ККТ: $err");
        http_response_code(500);
        echo json_encode(['error' => "Ошибка при инициализации драйвера ККТ: $err"]);
        exit;
    }

    $fetchHandler = new Handler(
        $currentSettings->comKkt,
        $currentSettings->ipKkt,
        $currentSettings->portIpKkt,
        $currentSettings->ipServKkt,
        $currentSettings->emulation,
        $glFptrDriver->GetFptr10(),
        VERSION_OF_PROGRAM
    );

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($uri === '/api/print-check' && $method === 'POST') {
        $fetchHandler->HandlePrintCheck();
    } elseif ($uri === '/api/close-shift' && $method === 'POST') {
        $fetchHandler->HandleCloseShift();
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
