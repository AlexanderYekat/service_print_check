<?php
// process_async_task.php - Обработчик асинхронных задач в отдельном процессе

// Включаем вывод ошибок во временный файл для отладки
$debugLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'async_task_debug.log';
file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Скрипт запущен\n", FILE_APPEND);
file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Аргументы: " . implode(', ', $argv) . "\n", FILE_APPEND);
file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Рабочая директория: " . getcwd() . "\n", FILE_APPEND);

// Не выводить ничего в stdout
ob_start();

// Включаем вывод ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $debugLog);

require_once __DIR__ . '/kktutils.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/settings_storage/JsonFileSettingsStorage.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/permitmarkutils.php';
require_once __DIR__ . '/scaleutils.php';
require_once __DIR__ . '/CheckService.php';

define('SETTINGS_DIR', __DIR__ . '/settings');
define('SETTINGS_FILE', SETTINGS_DIR . '/settings.json');
define('LOG_PATH', __DIR__ . '/logs');

// Получаем параметры из командной строки
if ($argc < 2) {
    file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] ОШИБКА: Недостаточно аргументов\n", FILE_APPEND);
    exit(1);
}

$taskId = $argv[1];
file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] TaskID: $taskId\n", FILE_APPEND);

// Инициализируем окружение
$settingsStorage = new JsonFileSettingsStorage(SETTINGS_FILE);
$currentSettings = new Settings($settingsStorage);
$currentSettings->load();

$logger = Logger::getInstance(LOG_PATH, $currentSettings->debug, !$currentSettings->disableLogging);
$logger->info("process_async_task.php: Начинаем обработку задачи {$taskId}");

$taskManager = new TaskManager($logger);

// Получаем задачу
$task = $taskManager->getTask($taskId);
if ($task === null) {
    $logger->error("process_async_task.php: Задача {$taskId} не найдена");
    exit(1);
}

// Проверяем тип задачи
if ($task['type'] !== 'check_marking_code') {
    $logger->error("process_async_task.php: Неизвестный тип задачи: {$task['type']}");
    exit(1);
}

// Обновляем статус на "processing"
$taskManager->updateTask($taskId, 'processing');

try {
    // Инициализируем драйвер ККТ
    $FptrDriver = new TFptr10Driver(
        $currentSettings->comKkt,
        $currentSettings->ipKkt,
        $currentSettings->portIpKkt,
        $currentSettings->ipServKkt,
        $logger,
        $currentSettings->emulation,
        $currentSettings->emulationwait
    );
    
    $err = $FptrDriver->NewSafe();
    if ($err !== null) {
        $logger->warning("process_async_task.php: Ошибка при инициализации драйвера ККТ: $err");
    }
    
    // Для проверки марок нам не нужны банк и весы
    $bankObject = null;
    $scaleObject = null;
    
    // Инициализируем PermitMark
    $cdnCacheIntervalDays = $currentSettings->permitMarkCDNCacheUpdateIntervalDays ?? 7;
    $cdnCacheIntervalSeconds = $cdnCacheIntervalDays * 86400;
    $isAsyncMode = $currentSettings->permitMarkAsyncCDNHealthCheck ?? true;
    
    if ($isAsyncMode) {
        $asyncInterval = $cdnCacheIntervalSeconds;
        $syncInterval = 2592000;
    } else {
        $asyncInterval = 604800;
        $syncInterval = $cdnCacheIntervalSeconds;
    }
    
    $permitMark = new PermitMarkCheckGateway(
        $currentSettings->permitMarkXApiKey,
        $currentSettings->permitMarkTimeout,
        $logger,
        [
            'permitMarkEnabled' => $currentSettings->permitMarkEnabled ?? false,
            'lmHost' => $currentSettings->permitMarkLmHost ?? 'http://127.0.0.1:5995',
            'lmAuth' => $currentSettings->permitMarkLmAuth ?? 'YWRtaW46YWRtaW4=',
            'verifySSL' => true,
            'emulation' => $currentSettings->permitMarkEmulation ?? false,
            'testLocalModule' => $currentSettings->testLocalModule ?? false,
            'testExpiredMarks' => $currentSettings->testExpiredMarks ?? false,
            'asyncCDNHealthCheck' => $isAsyncMode,
            'cdnCacheUpdateIntervalAsync' => $asyncInterval,
            'cdnCacheUpdateIntervalSync' => $syncInterval
        ]
    );
    
    // Создаем CheckService
    $checkService = new CheckService($FptrDriver, $logger, $bankObject, $scaleObject, $permitMark, $currentSettings);
    
    // Извлекаем параметры задачи
    $markingCode = $task['params']['markingCode'];
    $sellOrReturn = $task['params']['sellOrReturn'];
    $itemEstimatedStatus = $task['params']['itemEstimatedStatus'] ?? '';
    
    $logger->info("process_async_task.php: Выполняем проверку марки: {$markingCode}");
    
    // Выполняем проверку
    $result = $checkService->checkMarkingCode($markingCode, $sellOrReturn, $itemEstimatedStatus);
    
    // Сохраняем результат
    if ($result['success']) {
        $logger->info("process_async_task.php: Проверка марки завершена успешно для задачи {$taskId}");
        $taskManager->updateTask($taskId, 'completed', $result['data']);
    } else {
        $logger->error("process_async_task.php: Ошибка проверки марки для задачи {$taskId}: " . $result['message']);
        $taskManager->updateTask($taskId, 'error', null, $result['message']);
    }
    
} catch (Exception $e) {
    $errorMsg = "Исключение при обработке задачи {$taskId}: " . $e->getMessage();
    file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] ОШИБКА: $errorMsg\n", FILE_APPEND);
    if (isset($logger)) {
        $logger->error("process_async_task.php: " . $errorMsg);
    }
    if (isset($taskManager)) {
        $taskManager->updateTask($taskId, 'error', null, 'Исключение: ' . $e->getMessage());
    }
    exit(1);
}

file_put_contents($debugLog, "[" . date('Y-m-d H:i:s') . "] Обработка задачи {$taskId} завершена успешно\n", FILE_APPEND);
if (isset($logger)) {
    $logger->info("process_async_task.php: Обработка задачи {$taskId} завершена");
}
ob_end_clean();
exit(0);

