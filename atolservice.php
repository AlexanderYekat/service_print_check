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

define('VERSION_OF_PROGRAM', '2025_06_14_1106');
define('SETTINGS_DIR', __DIR__ . '/settings');
define('SETTINGS_FILE', SETTINGS_DIR . '/settings.json');
define('LOG_PATH', __DIR__ . '/logs');
define('CLEAR_LOGS_FLAG_FILE', SETTINGS_DIR . '/clear_logs_on_next_startup.flag');

// Здесь должны быть ваши классы/модули для работы с ККТ и настройками
require_once 'handlers.php';
require_once 'kktutils.php';
require_once 'models.php';
require_once 'settings_storage/JsonFileSettingsStorage.php';
require_once 'logger.php'; // Подключаем наш новый логгер
require_once 'bankutils.php'; // Подключаем утилиты для работы с банком

// Глобальные переменные (эти строки будут удалены или закомментированы)
// $glFptrDriver = new TFptr10Driver();
// $currentSettings = new Settings();

function runServer() {
    // global $glFptrDriver, $currentSettings; (эта строка будет удалена)

    // Инициализируем хранилище настроек
    $settingsStorage = new JsonFileSettingsStorage(SETTINGS_FILE);
    $currentSettings = new Settings($settingsStorage);
    $currentSettings->load();

    // Инициализируем логгер с текущим уровнем отладки и настройкой отключения
    $logger = Logger::getInstance(LOG_PATH, $currentSettings->debug, !$currentSettings->disableLogging);

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
    $bankObject = null;
    try {
        $bankObject = new TBankDriver($currentSettings->bankEmulation, $logger);
        $logger->info("Экземпляр TBankDriver успешно создан.");
    } catch (Exception $e) {
        $logger->warning("Не удалось создать экземпляр TBankDriver: " . $e->getMessage());
    }

    $scaleObject = null;
    try {
        $scaleObject = new TScale8Driver(
            $currentSettings->comScale, // Используем comScale из настроек
            $currentSettings->baudRateScale, // Используем baudRateScale из настроек
            $currentSettings->modelScale, // Используем modelScale из настроек
            $currentSettings->emulationScale, // Используем emulationScale из настроек
            $logger
        );
        $logger->info("Экземпляр TScale8Driver успешно создан.");
    } catch (Exception $e) {
        $logger->warning("Не удалось создать экземпляр TScale8Driver: " . $e->getMessage());
    }

    $checkService = new CheckService($FptrDriver, $logger, $bankObject, $scaleObject);

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
                $oldClearLogsSetting = $currentSettings->clearLogs; // Сохраняем старое значение
                $currentSettings->fillFromArray($data);
                $currentSettings->save();

                // Если clearLogs был включен И отличался от старого значения (или был только что включен)
                // ИЛИ если clearLogs был включен и не был установлен флаг (на случай, если файл флага был удален вручную)
                if ($currentSettings->clearLogs && (!$oldClearLogsSetting || !file_exists(CLEAR_LOGS_FLAG_FILE))) {
                    file_put_contents(CLEAR_LOGS_FLAG_FILE, ''); // Создаем файл-флаг
                    $logger->info("Файл-флаг для очистки логов при следующем запуске создан.");
                } elseif (!$currentSettings->clearLogs && file_exists(CLEAR_LOGS_FLAG_FILE)) {
                    unlink(CLEAR_LOGS_FLAG_FILE); // Удаляем файл-флаг, если clearLogs выключен
                    $logger->info("Файл-флаг для очистки логов удален.");
                }
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
        $command = 'start "" /MIN ' . escapeshellarg($logPath); // Для Windows, асинхронно
        // Для Linux/macOS: $command = 'xdg-open ' . escapeshellarg($logPath);
        // Для macOS: $command = 'open ' . escapeshellarg($logPath);
        // Для кроссплатформенности можно использовать: if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') { ... } else { ... }

        exec($command);
        $logger->info("Открыта папка с логами: $logPath");
        echo json_encode(['status' => 'success', 'message' => 'Папка с логами открыта'], JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/version' && $method === 'GET') {
        $logger->debug("Запрос на получение версии программы.");
        echo VERSION_OF_PROGRAM;
    } elseif ($uri === '/api/restart' && $method === 'POST') {
        $logger->info("Получен запрос на перезапуск службы.");
        
        // Путь к PowerShell скрипту
        $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'restart_service.ps1';
        
        // Формируем команду для запуска PowerShell скрипта
        $command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File \"" . $scriptPath . "\" > NUL 2>&1";
        
        // Запускаем команду в фоновом режиме
        pclose(popen($command, 'r'));
        
        $logger->info("Скрипт перезапуска службы запущен: $scriptPath");
        echo json_encode(['status' => 'success', 'message' => 'Скрипт перезапуска службы запущен. Проверьте логи службы для статуса.'], JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/update-branch' && $method === 'POST') {
        $logger->info("Получен запрос на обновление файлов из URL.");
        
        $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'update_from_url.ps1';

        // Получаем данные из тела запроса
        $input = file_get_contents('php://input');
        $requestData = json_decode($input, true);

        // Определяем URL для обновления: сначала из запроса, затем из настроек
        $updateUrl = null;
        if (isset($requestData['updateUrl']) && !empty($requestData['updateUrl'])) {
            $updateUrl = escapeshellarg($requestData['updateUrl']);
            $logger->info("URL для обновления получен из запроса: " . $requestData['updateUrl']);
        } else {
            $updateUrl = escapeshellarg($currentSettings->updateUrl);
            $logger->info("URL для обновления взят из настроек (из запроса пустой): " . $currentSettings->updateUrl);
        }

        $serviceName = escapeshellarg($currentSettings->serviceName);
        $logPath = escapeshellarg(LOG_PATH);
        
        // Формируем команду для запуска PowerShell скрипта в фоновом режиме
        //$command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File \"" . $scriptPath . "\" -DownloadUrl " . $updateUrl . " -LogDirPath " . $logPath . " -ServiceNameToStop " . $serviceName . " > NULL 2>&1";
        //$command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -File \"" . $scriptPath . "\" -DownloadUrl " . $updateUrl . " -LogDirPath " . $logPath . " -ServiceNameToStop " . $serviceName . " > $null 2>&1";
        $command = "powershell -NoProfile -ExecutionPolicy Bypass -File \"" . $scriptPath . "\" -DownloadUrl " . $updateUrl . " -LogDirPath " . $logPath . " -ServiceNameToStop " . $serviceName . " -SkipServiceStop";
        //$command = "powershell -NoProfile -ExecutionPolicy Bypass -File test_stop_service.ps1";
        
        $logger->info("Команда для запуска PowerShell скрипта: $command");
        pclose(popen($command, 'r'));
        
        $logger->info("Запущено обновление файлов из URL $updateUrl через PowerShell скрипт: $scriptPath");
        echo json_encode(['status' => 'success', 'message' => 'Обновление файлов из URL запущено через PowerShell скрипт. Проверьте логи для статуса.'], JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/check-for-update' && $method === 'GET') {
        $logger->debug("Запрос на проверку новой версии на GitHub.");

        $repoOwner = $currentSettings->githubRepoOwner;
        $repoName = $currentSettings->githubRepoName;

        if (empty($repoOwner) || empty($repoName)) {
            $logger->warning("Не указаны владелец или имя репозитория GitHub в настройках.");
            http_response_code(400);
            echo json_encode(['error' => 'Не указаны владелец или имя репозитория GitHub в настройках.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $githubApiUrl = "https://api.github.com/repos/{$repoOwner}/{$repoName}/releases/latest";

        // Инициализация cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $githubApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CloudPosBridgePHP-App'); // GitHub требует User-Agent
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Отключить проверку SSL (для локальной разработки, в продакшене лучше включить)
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $errorMessage = "Ошибка при запросе к GitHub API: HTTP $httpCode, cURL Error: $curlError";
            $logger->error($errorMessage);
            http_response_code(500);
            echo json_encode(['error' => $errorMessage], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $releaseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = "Ошибка при декодировании JSON ответа GitHub API: " . json_last_error_msg();
            $logger->error($errorMessage);
            http_response_code(500);
            echo json_encode(['error' => $errorMessage], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $latestVersionTag = $releaseData['tag_name'] ?? 'unknown';
        $downloadUrl = null;
        $currentVersion = VERSION_OF_PROGRAM; // Получаем текущую версию программы
        $updateAvailable = false;
        $message = "";

        if (version_compare($currentVersion, $latestVersionTag, '>=')) {
            $message = "Текущая версия ($currentVersion) равна или новее последней версии на GitHub ($latestVersionTag). Обновление не требуется.";
            $updateAvailable = false;
        } else {
            $message = "Найдена новая версия: $latestVersionTag. Ваша текущая версия: $currentVersion. Доступно обновление.";
            $updateAvailable = true;
        }

        $logger->info($message);

        if (isset($releaseData['assets']) && is_array($releaseData['assets'])) {
            foreach ($releaseData['assets'] as $asset) {
                if (isset($asset['name']) && $asset['name'] === 'release.zip' && isset($asset['browser_download_url'])) {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if ($updateAvailable && $downloadUrl === null) {
            $errorMessage = "Не удалось найти asset 'release.zip' в последнем релизе или отсутствует URL для скачивания, хотя обновление доступно.";
            $logger->warning($errorMessage);
            http_response_code(404);
            echo json_encode(['error' => $errorMessage], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'currentVersion' => $currentVersion, // Добавляем текущую версию в ответ
            'latestVersion' => $latestVersionTag,
            'downloadUrl' => $downloadUrl,
            'updateAvailable' => $updateAvailable, // Указываем, доступно ли обновление
            'message' => $message // Добавляем сообщение
        ], JSON_UNESCAPED_UNICODE);
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
    } elseif ($uri === '/settings.html' && $method === 'GET') {
        $logger->debug("Запрос на получение страницы настроек.");
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/templates/settings.html');
    } elseif (strpos($uri, '/static/') === 0 && $method === 'GET') {
        // Обработка статических файлов (CSS, JS)
        $filePath = __DIR__ . $uri;
        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath);
            header("Content-Type: $mimeType");
            readfile($filePath);
        } else {
            $logger->warning("Статический файл не найден: $filePath");
            http_response_code(404);
            echo json_encode(['error' => 'Static file not found']);
        }
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
    // fwrite(STDOUT, "Hello, Console!\n");
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

    // Инициализируем логгер для начальных сообщений, учитывая настройку отключения
    // Передаем только путь к директории логов, имя файла добавляется внутри Logger.
    $logger = Logger::getInstance(LOG_PATH, $initialSettings->debug, !$initialSettings->disableLogging);

    // Если включена очистка логов И существует файл-флаг (чтобы очистить только один раз после активации)
    if ($initialSettings->clearLogs && file_exists(CLEAR_LOGS_FLAG_FILE)) {
        // Формируем полный путь к лог-файлу для операций файловой системы.
        $fullLogFilePath = LOG_PATH . DIRECTORY_SEPARATOR . 'application.log';
        if (file_exists($fullLogFilePath)) {
            if (unlink($fullLogFilePath)) {
                // После удаления, используем существующий логгер и записываем сообщение.
                $logger->info("Логи очищены при запуске (по запросу).");
            } else {
                // Если не удалось удалить, используем существующий логгер и записываем ошибку.
                $logger->error("Не удалось очистить файл логов: $fullLogFilePath (по запросу)");
            }
        } else {
             // Если файла нет, но включена очистка, это нормально. Просто используем логгер и логируем.
             $logger->info("Файл логов не существует, очистка не требуется (по запросу).");
        }
        // После очистки, удаляем файл-флаг, чтобы очистка произошла только один раз
        unlink(CLEAR_LOGS_FLAG_FILE);
    }

    runServer();
}

main();
