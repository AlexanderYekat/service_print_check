<?php

// Простой PSR-7 воркер для RoadRunner, который проксирует запросы в существующий runServer()
// и обеспечивает реюз COM через KktDriverRegistry в долгоживущем процессе.

// Отключаем вывод предупреждений в STDOUT как можно раньше
ini_set('display_errors', '0');

require __DIR__ . '/vendor/autoload.php';

// Помечаем запуск под RoadRunner, чтобы не вызывать main() при require atolservice.php
define('RUNNING_UNDER_RR', true);

require_once 'settings_storage/JsonFileSettingsStorage.php';
require_once 'models.php';
require_once 'logger.php';
require_once 'KktDriverRegistry.php';
require_once 'handlers.php';
require_once 'TaskManager.php';
require_once 'atolservice.php';

// Константы задаются в atolservice.php, дублировать не нужно

// Глобальная инициализация процесса (живет весь срок воркера)
// Инициализация настроек/логгера (константы берутся из atolservice.php)
$settingsStorage = new JsonFileSettingsStorage(SETTINGS_FILE);
$currentSettings = new Settings($settingsStorage);
$currentSettings->load();
$logger = Logger::getInstance(LOG_PATH, $currentSettings->debug, !$currentSettings->disableLogging);

// В RR нельзя печатать предупреждения в STDOUT — это ломает протокол.
// Логируем через наш логгер
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logger) {
    $level = ($errno === E_ERROR || $errno === E_USER_ERROR) ? 'error' : 'warning';
    if (method_exists($logger, $level)) {
        $logger->$level("PHP: {$errstr} in {$errfile}:{$errline}");
    }
    return true; // предотвращаем вывод по умолчанию
});

// Убедимся, что директории существуют
if (!is_dir(SETTINGS_DIR)) { @mkdir(SETTINGS_DIR, 0777, true); }
if (!is_dir(LOG_PATH)) { @mkdir(LOG_PATH, 0777, true); }

$psr17 = new Nyholm\Psr7\Factory\Psr17Factory();
$worker = Spiral\RoadRunner\Worker::create();
$http = new Spiral\RoadRunner\Http\PSR7Worker($worker, $psr17, $psr17, $psr17);

while ($request = $http->waitRequest()) {
    try {
        // Прокидываем минимальные суперglobals для совместимости с текущим runServer()
        $method = $request->getMethod() ?: 'GET';
        $path = (string)$request->getUri()->getPath();
        if ($path === '') { $path = '/'; }
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        // query string для совместимости
        $query = $request->getUri()->getQuery();
        parse_str($query, $_GET);
        // тело запроса сохраняем в глобал, чтобы читать как заменитель php://input
        $rawBody = (string)$request->getBody();
        $GLOBALS['__RAW_BODY'] =  $rawBody;
        //$logger->debug("Raw request body from RoadRunner: " . substr($GLOBALS['__RAW_BODY'], 0, 200) . (strlen($GLOBALS['__RAW_BODY']) > 200 ? '...' : ''));

        // **ДОБАВЛЕНО: Проверка $GLOBALS['__RAW_BODY']**
        //if (empty($rawBody)) {
        //    $logger->error("Error: RAW_BODY is empty. Request body might not be set correctly");
        //}

        // Выполняем существующий маршрутизатор и собираем вывод
        ob_start();
        runServer();
        $body = ob_get_clean();
        //$logger->info("Response body: " . $body);

        $response = $psr17->createResponse(200);
        $response->getBody()->write($body);
        $http->respond($response);
    } catch (\Throwable $e) {
        $logger->error('Unhandled exception in RR worker: ' . $e->getMessage());
        $response = $psr17->createResponse(500);
        $response->getBody()->write('Internal Server Error');
        $http->respond($response);
    }
}


