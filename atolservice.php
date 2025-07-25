<?php
// jsontokkt.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

//if ($origin === 'null') {
//    header("Access-Control-Allow-Origin: null");
//} elseif ($origin) {
//    header("Access-Control-Allow-Origin: $origin");
//} else {
//    header("Access-Control-Allow-Origin: *");
//}
//header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
//header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
//header("Access-Control-Allow-Private-Network: true");

header('Access-Control-Allow-Origin: *'); 
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');




define('VERSION_OF_PROGRAM', '2025_07_05_1216');
define('SETTINGS_DIR', __DIR__ . '/settings');
define('SETTINGS_FILE', SETTINGS_DIR . '/settings.json');
define('LOG_PATH', __DIR__ . '/logs');

// Здесь должны быть ваши классы/модули для работы с ККТ и настройками
require_once 'handlers.php';
require_once 'kktutils.php';
require_once 'models.php';
require_once 'settings_storage/JsonFileSettingsStorage.php';
require_once 'logger.php'; // Подключаем наш новый логгер
require_once 'bank/bankutils.php'; // Подключаем утилиты для работы с банком

// === Глобальный массив для хранения позиций чека по session_id ===
global $CHECK_SESSIONS;
if (!isset($CHECK_SESSIONS)) {
    $CHECK_SESSIONS = [];
}

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
        $logger->warning("Ошибка при инициализации драйвера ККТ: $err. Работа приложения продолжается.");
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

    // Handle root / -> simple settings
    if ($uri === '/' && $method === 'GET') {
        $logger->debug("Запрос на получение простой страницы настроек.");
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/templates/simple_settings.html');
    } elseif ($uri === '/settings' && $method === 'GET') { // Handle /settings -> full settings
        $logger->debug("Запрос на получение полной страницы настроек (для технических специалистов).");
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/templates/settings.html');
    } elseif ($uri === '/api/settings') {
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
                $currentSettings->load(); // Перечитать настройки после сохранения

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
        $logger->debug("Запрос на открытие папки с логами.");
        $logPath = LOG_PATH;
        $command = 'start "" /MIN ' . escapeshellarg($logPath);
        
        // Запускаем команду в фоновом режиме, чтобы не блокировать PHP-процесс
        $handle = popen($command, 'r');
        if ($handle === false) {
            $logger->error("Не удалось запустить команду popen для открытия папки логов: $command");
            echo json_encode(['status' => 'error', 'message' => 'Не удалось запустить команду для открытия папки логов'], JSON_UNESCAPED_UNICODE);
        } else {
            pclose($handle);
            $logger->info("Команда для открытия папки логов отправлена: $command");
            echo json_encode(['status' => 'success', 'message' => 'Папка с логами открыта'], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($uri === '/api/version' && $method === 'GET') {
        $logger->debug("Запрос на получение версии программы.");
        echo VERSION_OF_PROGRAM;
    } elseif ($uri === '/api/send-logs' && $method === 'POST') {
        $logger->debug("Запрос на отправку логов на почту.");
        header('Content-Type: application/json; charset=utf-8');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $recipientEmail = $data['email'] ?? '';
        $logDirPath = LOG_PATH; // Путь к директории с логами
        $zipFilePath = sys_get_temp_dir() . '/logs_' . date('Ymd_His') . '.zip'; // Временный файл ZIP

        $response = ['success' => false, 'message' => 'Произошла ошибка при отправке логов.'];

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $response = ['success' => false, 'message' => 'Неверный формат email адреса.'];
        } elseif (!is_dir($logDirPath)) {
            $response = ['success' => false, 'message' => 'Директория с логами не найдена: ' . $logDirPath];
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($logDirPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($logDirPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();

                $subject = 'Логи CloudPosBridgePHP';
                $message = 'В приложении CloudPosBridgePHP были запрошены логи. Файл логов приложен.';

                $fileContent = file_get_contents($zipFilePath);
                $encodedContent = chunk_split(base64_encode($fileContent));
                $fileName = basename($zipFilePath); // Имя файла для прикрепления

                $boundary = md5(time());
                $headers = 'From: no-reply@cloudposbridge.com' . "\r\n" .
                           'MIME-Version: 1.0' . "\r\n" .
                           "Content-Type: multipart/mixed; boundary=\"{$boundary}\"" . "\r\n";

                $emailBody = "--{$boundary}\r\n" .
                             "Content-Type: text/plain; charset=\"UTF-8\"\r\n" .
                             "Content-Transfer-Encoding: 7bit\r\n\r\n" .
                             $message . "\r\n\r\n" .
                             "--{$boundary}\r\n" .
                             "Content-Type: application/zip; name=\"{$fileName}\"\r\n" .
                             "Content-Transfer-Encoding: base64\r\n" .
                             "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n" .
                             $encodedContent . "\r\n" .
                             "--{$boundary}--";

                // Попытка отправки email
                if (@mail($recipientEmail, $subject, $emailBody, $headers)) {
                    $response = ['success' => true, 'message' => 'Логи успешно отправлены на ' . $recipientEmail];
                    $logger->info("Логи (ZIP-архив) успешно отправлены на " . $recipientEmail);
                } else {
                    $response = ['success' => false, 'message' => 'Не удалось отправить логи. Проверьте настройки почтового сервера.'];
                    $logger->error("Не удалось отправить логи (ZIP-архив) на " . $recipientEmail);
                }
            } else {
                $response = ['success' => false, 'message' => 'Не удалось создать ZIP-архив логов.'];
                $logger->error("Не удалось создать ZIP-архив логов: " . $zipFilePath);
            }
            // Удаляем временный ZIP-файл
            if (file_exists($zipFilePath)) {
                unlink($zipFilePath);
            }
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } elseif ($uri === '/api/diagnose' && $method === 'GET') {
        $logger->debug("Запрос на выполнение диагностики.");
        header('Content-Type: application/json; charset=utf-8');

        $results = [
            'kkt' => ['success' => false, 'message' => 'Не проводилось'],
            'terminal' => ['success' => false, 'message' => 'Не проводилось'],
            'scale' => ['success' => false, 'message' => 'Не проводилось'],
        ];

        // Diagnostic for KKT
        try {
            $kktDriver = new TFptr10Driver(
                $currentSettings->comKkt,
                $currentSettings->ipKkt,
                $currentSettings->portIpKkt,
                $currentSettings->ipServKkt,
                $currentSettings->emulation
            );
            $err = $kktDriver->NewSafe();
            if ($err !== null) {
                $results['kkt'] = ['success' => false, 'message' => "Драйвер ККТ не установлен. \n Ошибка инициализации COM-объекта ККТ: $err. "];
            } else {
                list($isOpen, $message) = $kktDriver->Open();
                if ($isOpen) {
                    $results['kkt'] = ['success' => true, 'message' => 'Соединение с ККТ установлено.'];
                } else {
                    $results['kkt'] = ['success' => false, 'message' => "Ошибка соединения с ККТ: $message"];
                }
            }
        } catch (Exception $e) {
            $results['kkt'] = ['success' => false, 'message' => "Исключение при диагностике ККТ: " . $e->getMessage()];
        } finally {
            if (isset($kktDriver)) {
                $kktDriver->Close();
            }
        }

        // Diagnostic for Terminal (Bank)
        try {
            $bankDriver = new TBankDriver($currentSettings->bankEmulation, $logger);
            list($isOpen, $message) = $bankDriver->Open();
            if ($isOpen) {
                $results['terminal'] = ['success' => true, 'message' => 'Терминал (банк) создан и готов к работе.'];
            } else {
                $results['terminal'] = ['success' => false, 'message' => "Ошибка создания/открытия терминала (банк) - библиотека sbrf.dll - не зарегистрирована: $message"];
            }
        } catch (Exception $e) {
            $results['terminal'] = ['success' => false, 'message' => "Исключение при диагностике терминала (банк): " . $e->getMessage()];
        } finally {
            if (isset($bankDriver)) {
                $bankDriver->Close();
            }
        }

        // Diagnostic for Scale
        try {
            $scaleDriver = new TScale8Driver(
                $currentSettings->comScale,
                $currentSettings->baudRateScale,
                $currentSettings->modelScale,
                $currentSettings->emulationScale,
                $logger
            );
            list($isOpen, $message) = $scaleDriver->Open();
            if ($isOpen) {
                $results['scale'] = ['success' => true, 'message' => 'Соединение с весами установлено.'];
            } else {
                $results['scale'] = ['success' => false, 'message' => "Ошибка соединения с весами: $message"];
            }
        } catch (Exception $e) {
            $results['scale'] = ['success' => false, 'message' => "Исключение при диагностике весов: " . $e->getMessage()];
        } finally {
            if (isset($scaleDriver)) {
                $scaleDriver->Close();
            }
        }

        echo json_encode($results, JSON_UNESCAPED_UNICODE);
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
        
        $command = "powershell -NoProfile -ExecutionPolicy Bypass -File \"" . $scriptPath . "\" -DownloadUrl " . $updateUrl . " -LogDirPath " . $logPath . " -ServiceNameToStop " . $serviceName . " -SkipServiceStop 2>&1";
        
        $logger->info("Команда для запуска PowerShell скрипта: $command");

        $handle = popen($command, 'r');
        if ($handle === false) {
            $errorMessage = "Не удалось запустить команду popen для обновления: $command";
            $logger->error($errorMessage);
            echo json_encode(['status' => 'error', 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $scriptOutput = stream_get_contents($handle);
        $exitCode = pclose($handle);

        if ($exitCode === 0) {
            $logger->info("Скрипт обновления успешно завершен. Вывод: " . $scriptOutput);
            echo json_encode(['status' => 'success', 'message' => 'Обновление файлов запущено. Проверьте логи обновления update_from_url.log для выяснения статуса обновления.'], JSON_UNESCAPED_UNICODE);
        } else {
            $errorMessage = "Скрипт обновления завершился с ошибкой (код выхода: $exitCode). Вывод: " . $scriptOutput;
            $logger->error($errorMessage);
            echo json_encode(['status' => 'error', 'message' => "Ошибка при запуске обновления. Подробности в логах приложения."], JSON_UNESCAPED_UNICODE);
        }
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
    } elseif ($uri === '/api/add-check-position' && $method === 'POST') {
        $fetchHandler->HandleAddCheckPosition();
    } elseif ($uri === '/api/checkrr' && $method === 'GET') {
        $fetchHandler->HandleCheckRR();
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

    // Если включена очистка логов
    if ($initialSettings->clearLogs) {
        $logFilesToClear = [
            'application.log',
            // 'nssm_stderr.log', // Управляется NSSM, не удаляем из PHP
            // 'nssm_stdout.log', // Управляется NSSM, не удаляем из PHP
            'update_from_url.log'
        ];

        foreach ($logFilesToClear as $logFile) {
            $fullLogFilePath = LOG_PATH . DIRECTORY_SEPARATOR . $logFile;
            if (file_exists($fullLogFilePath)) {
                if (@unlink($fullLogFilePath)) { // Добавлен @ для подавления ошибок
                    $logger->info("Лог-файл '{$logFile}' очищен при запуске (по запросу).");
                } else {
                    $logger->error("Не удалось очистить лог-файл '{$logFile}': $fullLogFilePath (по запросу). Возможно, файл используется.");
                }
            } else {
                $logger->info("Лог-файл '{$logFile}' не существует, очистка не требуется (по запросу).");
            }
        }
    }

    runServer();
}

main();
