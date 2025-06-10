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
define('SETTINGS_DIR', __DIR__ . '/settings');
define('SETTINGS_FILE', SETTINGS_DIR . '/settings.json');
define('LOG_PATH', __DIR__ . '/logs');

// Здесь должны быть ваши классы/модули для работы с ККТ и настройками
require_once 'handlers.php';
require_once 'kktutils.php';
require_once 'models.php';
require_once 'settings_storage/JsonFileSettingsStorage.php';
require_once 'logger.php'; // Подключаем наш новый логгер

// Глобальные переменные (эти строки будут удалены или закомментированы)
// $glFptrDriver = new TFptr10Driver();
// $currentSettings = new Settings();

function runServer() {
    // global $glFptrDriver, $currentSettings; (эта строка будет удалена)

    // Инициализируем хранилище настроек
    $settingsStorage = new JsonFileSettingsStorage(SETTINGS_FILE);
    $currentSettings = new Settings($settingsStorage);
    $currentSettings->load();

    // Инициализируем логгер с текущим уровнем отладки
    $logger = Logger::getInstance(LOG_PATH, $currentSettings->debug);

    // Создаем экземпляр TFptr10Driver с параметрами подключения из настроек
    $FptrDriver = new TFptr10Driver(
        $currentSettings->comKkt,
        $currentSettings->ipKkt,
        $currentSettings->portIpKkt,
        $currentSettings->ipServKkt,
        $currentSettings->emulation
    );

    // Инициализация драйвера ККТ
    $err = $FptrDriver->NewSafe();
    if ($err !== null) {
        $logger->critical("Ошибка при инициализации драйвера ККТ: $err");
        http_response_code(500);
        echo json_encode(['error' => "Ошибка при инициализации драйвера ККТ: $err"]);
        exit;
    }

    // Создаем экземпляр CheckService, передавая ему FptrDriver и логгер
    $bankComObject = null;
    try {
        $bankComObject = new COM("SBRFSRV.Server");
        $logger->info("COM-объект SBRFSRV.Server успешно создан.");
    } catch (Exception $e) {
        $logger->warning("Не удалось создать COM-объект SBRFSRV.Server: " . $e->getMessage());
    }

    $scaleComObject = null;
    try {
        $scaleComObject = new COM("AddIn.Scale8");
        $logger->info("COM-объект AddIn.Scale8 успешно создан.");
    } catch (Exception $e) {
        $logger->warning("Не удалось создать COM-объект AddIn.Scale8: " . $e->getMessage());
    }

    $checkService = new CheckService($FptrDriver, $logger, $bankComObject, $scaleComObject);

    $fetchHandler = new Handler(
        $checkService, 
        $logger
    );

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle settings API
    if ($uri === '/api/settings') {
        if ($method === 'GET') {
            $logger->debug("Запрос на получение настроек.");
            echo json_encode($currentSettings->toArray(), JSON_UNESCAPED_UNICODE);
        } elseif ($method === 'POST') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            // Обработка сброса настроек по умолчанию
            if (isset($data['resetDefaults']) && $data['resetDefaults'] === true) {
                $currentSettings->resetToDefaults();
                $currentSettings->save();
                $logger->info("Настройки сброшены по умолчанию.");
                echo json_encode(['status' => 'success', 'message' => 'Настройки сброшены по умолчанию'], JSON_UNESCAPED_UNICODE);
            } else {
                // Сохранение обычных настроек
                $currentSettings->fillFromArray($data);
                $currentSettings->save();
                $logger->info("Настройки успешно сохранены.");
                echo json_encode(['status' => 'success', 'message' => 'Настройки сохранены'], JSON_UNESCAPED_UNICODE);
            }
        }
    } elseif ($uri === '/api/settingspath' && $method === 'GET') {
        $logger->debug("Запрос на получение пути к файлу настроек.");
        echo SETTINGS_FILE;
    } elseif ($uri === '/api/logpath' && $method === 'GET') {
        $logger->debug("Запрос на получение пути к логам.");
        echo LOG_PATH;
    } elseif ($uri === '/api/openlogs' && $method === 'POST') {
        $logPath = LOG_PATH;
        $command = 'start "" "' . $logPath . '" >NUL 2>&1'; // Для Windows
        // Для Linux/macOS: $command = 'xdg-open ' . escapeshellarg($logPath);
        // Для macOS: $command = 'open ' . escapeshellarg($logPath);
        // Для кроссплатформенности можно использовать: if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') { ... } else { ... }

        pclose(popen($command, 'r'));
        $logger->info("Открыта папка с логами: $logPath");
        echo json_encode(['status' => 'success', 'message' => 'Папка с логами открыта'], JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/version' && $method === 'GET') {
        $logger->debug("Запрос на получение версии программы.");
        echo VERSION_OF_PROGRAM;
    } elseif ($uri === '/api/restart' && $method === 'POST') {
        $logger->info("Получен запрос на перезапуск службы.");
        // Реальный перезапуск PHP-приложения через веб-сервер сложен и обычно требует
        // внешних инструментов (например, systemd, supervisor или перезапуска веб-сервера).
        // Здесь мы просто возвращаем успешный статус.
        echo json_encode(['status' => 'success', 'message' => 'Запрос на перезапуск получен. Для реального перезапуска службы требуется ручное вмешательство или настройка внешнего менеджера процессов.'], JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/print-check' && $method === 'POST') {
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
    } elseif ($method === 'OPTIONS') {
        // Для CORS preflight
        http_response_code(204);
    } else {
        $logger->warning("Эндпоинт не найден: $uri");
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function main() {
    // Создаем директорию для настроек, если она не существует
    if (!is_dir(SETTINGS_DIR)) {
        mkdir(SETTINGS_DIR, 0777, true);
    }
    // Создаем директорию для логов, если она не существует
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0777, true);
    }

    // Инициализируем хранилище настроек для получения настроек логирования
    $settingsStorageForLogs = new JsonFileSettingsStorage(SETTINGS_FILE);
    $initialSettings = new Settings($settingsStorageForLogs);
    $initialSettings->load();

    // Если включена очистка логов при запуске
    if ($initialSettings->clearLogs) {
        $logFile = LOG_PATH . '/application.log';
        if (file_exists($logFile)) {
            if (unlink($logFile)) {
                // После удаления, создаем логгер для записи сообщения об очистке
                $logger = Logger::getInstance(LOG_PATH, $initialSettings->debug);
                $logger->info("Логи очищены при запуске.");
            } else {
                // Если не удалось удалить, создаем логгер для записи ошибки
                $logger = Logger::getInstance(LOG_PATH, $initialSettings->debug);
                $logger->error("Не удалось очистить файл логов: $logFile");
            }
        } else {
             // Если файла нет, но включена очистка, это нормально. Просто логируем
             $logger = Logger::getInstance(LOG_PATH, $initialSettings->debug);
             $logger->info("Файл логов не существует, очистка не требуется.");
        }
    }

    runServer();
}

main();
