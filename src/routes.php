<?php
// src/routes.php - Маршрутизация для чистой архитектуры

require_once __DIR__ . '/bootstrap.php';

function handleCleanArchitectureRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = $_GET;
    
    // Получение тела запроса для POST/PUT
    $input = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        $input = array_merge($input, $_POST); // Поддержка form-data
    }
    $input = array_merge($input, $query); // Добавляем query параметры

    // Роутинг
    switch ($path) {
        // API для печати чеков
        case '/api/print-check':
            if ($method === 'POST') {
                $GLOBALS['di']['print_check_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API для банковских операций
        case '/api/bank/pay':
            if ($method === 'POST') {
                $input['operation'] = 'pay';
                $GLOBALS['di']['bank_payment_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/bank/refund':
            if ($method === 'POST') {
                $input['operation'] = 'refund';
                $GLOBALS['di']['bank_payment_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/bank/close-shift':
            if ($method === 'POST') {
                $GLOBALS['di']['close_shift_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API для весов
        case '/api/get-weight':
            if ($method === 'GET' || $method === 'POST') {
                $GLOBALS['di']['get_weight_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API для Честного Знака (старая архитектура) - УДАЛЕНО
        // Заменено на новую архитектуру:
        // - POST /api/permit-mark-check для разрешительного режима
        // - POST /api/ecr-mark-check/enqueue для ККТ режима

        // API для проверки марки - новая архитектура
        case '/api/permit-mark-check':
            if ($method === 'POST') {
                require_once __DIR__ . '/api/PermitMarkCheckController.php';
                $controller = new PermitMarkCheckController();
                $controller->checkPermit();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/ecr-mark-check/enqueue':
            if ($method === 'POST') {
                require_once __DIR__ . '/api/EcrMarkCheckController.php';
                $controller = new EcrMarkCheckController();
                $controller->enqueueMarkCheck();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API для очереди
        case '/api/queue/status':
            if ($method === 'GET') {
                $GLOBALS['di']['queue_controller']->handleStatus();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/queue/process':
            if ($method === 'POST') {
                $GLOBALS['di']['queue_controller']->handleProcess();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API для мониторинга
        case '/api/health':
            if ($method === 'GET') {
                $GLOBALS['di']['health_controller']->handle();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // API версии
        case '/api/version':
            if ($method === 'GET') {
                $GLOBALS['di']['version_controller']->handle($input);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // Главная страница с информацией об API
        case '/':
        case '/api':
            handleApiDocumentation();
            break;

        default:
            // Обработка динамических маршрутов типа /api/ecr-mark-check/result/{taskId}
            if (preg_match('#^/api/ecr-mark-check/result/([^/]+)$#', $path, $matches)) {
                if ($method === 'GET') {
                    require_once __DIR__ . '/api/EcrMarkCheckController.php';
                    $controller = new EcrMarkCheckController();
                    $controller->getMarkCheckResult($matches[1]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                return;
            }

            // Если не найден ни один маршрут
            http_response_code(404);
            echo json_encode([
                'error' => 'Endpoint not found',
                'path' => $path,
                'available_endpoints' => [
                    'POST /api/print-check',
                    'POST /api/bank/pay',
                    'POST /api/bank/refund', 
                    'POST /api/bank/close-shift',
                    'GET /api/get-weight',
                    'POST /api/permit-mark-check',
                    'POST /api/ecr-mark-check/enqueue',
                    'GET /api/ecr-mark-check/result/{taskId}',
                    'GET /api/queue/status',
                    'POST /api/queue/process',
                    'GET /api/health',
                    'GET /api/version'
                ]
            ]);
            break;
    }
}

function handleApiDocumentation()
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'service' => 'CloudPosBridge Clean Architecture',
        'version' => '2.0.0',
        'description' => 'Сервис печати чеков с чистой архитектурой',
        'endpoints' => [
            'print_check' => [
                'method' => 'POST',
                'url' => '/api/print-check',
                'description' => 'Печать чека'
            ],
            'bank_operations' => [
                'pay' => ['method' => 'POST', 'url' => '/api/bank/pay'],
                'refund' => ['method' => 'POST', 'url' => '/api/bank/refund'],
                'close_shift' => ['method' => 'POST', 'url' => '/api/bank/close-shift']
            ],
            'weight' => [
                'method' => 'GET',
                'url' => '/api/get-weight',
                'description' => 'Получение веса с весов'
            ],
            'honest_sign' => [
                'permit_check' => ['method' => 'POST', 'url' => '/api/permit-mark-check', 'description' => 'Синхронная проверка в разрешительном режиме'],
                'ecr_enqueue' => ['method' => 'POST', 'url' => '/api/ecr-mark-check/enqueue', 'description' => 'Постановка проверки ККТ в очередь'],
                'ecr_result' => ['method' => 'GET', 'url' => '/api/ecr-mark-check/result/{taskId}', 'description' => 'Получение результата проверки ККТ']
            ],
            'queue' => [
                'status' => ['method' => 'GET', 'url' => '/api/queue/status'],
                'process' => ['method' => 'POST', 'url' => '/api/queue/process']
            ],
            'monitoring' => [
                'health' => ['method' => 'GET', 'url' => '/api/health']
            ],
            'info' => [
                'version' => ['method' => 'GET', 'url' => '/api/version']
            ]
        ],
        'architecture' => 'Clean Architecture with Infrastructure Patterns',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}