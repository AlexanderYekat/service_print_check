<?php
// src/atolservice.php

require_once __DIR__ . '/bootstrap.php'; // всё подключение зависимостей и DI

function handleHttpRequest() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Базовые маршруты (роутинг)
    switch ("$method $path") {
        case 'POST /api/print-check':
            $controller = $GLOBALS['di']['printCheckController'];
            $controller->handle($input);
            break;

        case 'POST /api/bank-operation':
            $controller = $GLOBALS['di']['bankPaymentController'];
            $controller->handle($input);
            break;

        case 'POST /api/close-shift':
            $controller = $GLOBALS['di']['closeShiftController'];
            $controller->handle($input);
            break;

        case 'POST /api/get-weight':
            $controller = $GLOBALS['di']['getWeightController'];
            $controller->handle($input);
            break;

        case 'GET /api/version':
            $controller = $GLOBALS['di']['versionController'];
            $controller->handle();
            break;

        // ... и остальные контроллеры по аналогии

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Unknown endpoint']);
            break;
    }
}

handleHttpRequest();
