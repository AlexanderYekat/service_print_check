<?php

require_once 'logger.php'; // Подключаем логгер

// Подключаем библиотеку phpdotenv если она установлена
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

/**
 * Получить конфигурационное значение из .env с fallback значением
 * @param string $key
 * @param string|null $default
 * @return string|null
 */
function getEnvConfig($key, $default = null) {
    // Сначала проверяем переменные окружения
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    // Если переменная окружения не найдена, читаем из .env файла
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return $default;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue; // Пропускаем пустые строки и комментарии
        }
        
        if (strpos($line, $key . '=') === 0) {
            $value = trim(substr($line, strlen($key . '=')));
            // Убираем кавычки если они есть
            if (($value[0] === '"' && substr($value, -1) === '"') || 
                ($value[0] === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            return $value;
        }
    }
    
    return $default;
}

/**
 * Получить X-API-KEY из .env
 * @return string|false
 */
function getApiTokenFromEnv() {
    $token = getEnvConfig('X_API_KEY');
    return $token ?: false;
}

/**
 * Получить конфигурацию CDN хостов
 * @param bool $production
 * @return array
 */
function getCdnConfig($production = true) {
    if ($production) {
        return [
            'host' => getEnvConfig('CDN_PROD_HOST', 'https://cdn.crpt.ru'),
            'sandbox_host' => getEnvConfig('CDN_SANDBOX_HOST', 'https://markirovka.sandbox.crptech.ru')
        ];
    }
    return [
        'host' => getEnvConfig('CDN_SANDBOX_HOST', 'https://markirovka.sandbox.crptech.ru'),
        'sandbox_host' => getEnvConfig('CDN_PROD_HOST', 'https://cdn.crpt.ru')
    ];
}

/**
 * Получить конфигурацию локального модуля ЧЗ
 * @return array
 */
function getLmczConfig() {
    return [
        'host' => getEnvConfig('LMCZ_HOST', 'http://127.0.0.1:5995'),
        'username' => getEnvConfig('LMCZ_USERNAME', 'admin'),
        'password' => getEnvConfig('LMCZ_PASSWORD', 'admin'),
        'basic_auth' => getEnvConfig('LMCZ_BASIC_AUTH', 'YWRtaW46YWRtaW4=') // admin:admin в base64
    ];
}

/**
 * Выполнить curl-запрос с обработкой ошибок (универсально для GET/POST)
 * @param string $url
 * @param array $headers
 * @param string|null $postFields
 * @param int $timeout Таймаут в секундах
 * @return array ['success'=>bool, 'http_code'=>int, 'response'=>mixed, 'message'=>string, 'timeout'=>bool]
 */
function performCurlRequest($url, $headers, $postFields = null, $timeout = 30) {
    $result = [
        'success' => false,
        'http_code' => 0,
        'response' => null,
        'message' => '',
        'timeout' => false
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    $result['http_code'] = $httpCode;
    $result['response'] = $response;
    
    // Детальное логирование для диагностики
    $logContext = [
        'url' => $url,
        'http_code' => $httpCode,
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
        'response_length' => $response ? strlen($response) : 0,
        'timeout' => $timeout
    ];
    
    // Проверяем на таймаут
    if ($curlErrno == CURLE_OPERATION_TIMEDOUT || $curlErrno == CURLE_OPERATION_TIMEOUTED) {
        $result['timeout'] = true;
        $result['message'] = 'Превышен таймаут ожидания ответа';
        logger('CURL timeout: ' . json_encode($logContext));
        return $result;
    }
    
    if ($response === false || $httpCode !== 200) {
        $result['message'] = 'не удалось получить данные';
        
        if ($curlError) {
            if (strpos($curlError, 'SSL certificate') !== false) {
                $result['message'] = "Ошибка безопасности: не удалось проверить подлинность сервера (SSL).\n"
                    . "Возможные причины:\n"
                    . "- Устарел или отсутствует файл корневых сертификатов (cacert.pem) для PHP.\n"
                    . "- Проблемы с интернет-соединением или настройками безопасности.\n\n"
                    . "Что делать:\n"
                    . "1. Сообщите администратору или техническому специалисту.\n"
                    . "2. Проверьте, что файл cacert.pem скачан с https://curl.se/ca/cacert.pem и путь к нему прописан в php.ini (curl.cainfo).\n"
                    . "3. Если не помогло — обратитесь в поддержку.";
                logger('SSL certificate error: ' . json_encode($logContext));
            } else {
                $result['message'] .= ", CURL error: $curlError (errno: $curlErrno)";
                logger('CURL error: ' . json_encode($logContext));
            }
        } else {
            $result['message'] .= ", HTTP code: $httpCode";
            // Логируем первые 500 символов ответа для диагностики
            $logContext['response_preview'] = $response ? substr($response, 0, 500) : null;
            logger('HTTP error: ' . json_encode($logContext));
        }
        return $result;
    }
    
    $result['success'] = true;
    return $result;
}

/**
 * Сформировать заголовки для API
 */
function makeApiHeaders($token) {
    return [
        'Content-Type: application/json',
        'X-API-KEY: ' . $token
    ];
}

/**
 * Универсальный запрос к API с автоматическим декодированием JSON и обработкой ошибок
 * @param string $url
 * @param array $headers
 * @param string|null $postFields
 * @param int $timeout Таймаут в секундах
 * @return array ['success'=>bool, 'http_code'=>int, 'data'=>mixed, 'message'=>string, 'raw_response'=>string, 'timeout'=>bool]
 */
function apiJsonRequest($url, $headers, $postFields = null, $timeout = 30) {
    $curlResult = performCurlRequest($url, $headers, $postFields, $timeout);
    if (!$curlResult['success']) {
        return [
            'success' => false,
            'http_code' => $curlResult['http_code'],
            'data' => null,
            'message' => $curlResult['message'],
            'raw_response' => $curlResult['response'],
            'timeout' => $curlResult['timeout'] ?? false
        ];
    }
    $data = json_decode($curlResult['response'], true);
    if ($data === null) {
        logger('JSON decode error for response: ' . substr($curlResult['response'], 0, 200));
        return [
            'success' => false,
            'http_code' => $curlResult['http_code'],
            'data' => null,
            'message' => 'Ошибка декодирования JSON',
            'raw_response' => $curlResult['response'],
            'timeout' => false
        ];
    }
    return [
        'success' => true,
        'http_code' => $curlResult['http_code'],
        'data' => $data,
        'message' => $data['description'] ?? '',
        'raw_response' => $curlResult['response'],
        'timeout' => false
    ];
}

/**
 * Извлечь код идентификации (CIS) из кода маркировки
 * @param string $mark Код маркировки
 * @return string|false Код идентификации или false при ошибке
 */
function extractCisFromMark($mark) {
    // Удаляем пробелы и переводы строк
    $mark = trim($mark);
    
    // Для табачной продукции - удаляем МРЦ (последние 4 символа после разделителя)
    // Проверяем, является ли это табачной продукцией по GTIN
    if (preg_match('/^01(\d{14})/', $mark, $matches)) {
        $gtin = $matches[1];
        // Табачные продукты имеют GTIN начинающийся с определенных цифр
        // Для примера используем упрощенную проверку
        if (in_array(substr($gtin, 0, 3), ['046', '047', '048'])) {
            // Для табачной продукции удаляем МРЦ
            if (preg_match('/^(01\d{14}21[^)]+)(\x1D|$)/', $mark, $tobaccoMatches)) {
                return $tobaccoMatches[1];
            }
        }
    }
    
    // Для остальных товарных групп - удаляем криптографический код проверки
    // Паттерн для извлечения основной части без криптографического кода
    // Формат: 01(GTIN)21(серийный номер)[другие AI]
    if (preg_match('/^(01\d{14}21[^)]+)(?:\x1D|$)/', $mark, $matches)) {
        return $matches[1];
    }
    
    // Если не удалось разобрать стандартным способом, пробуем альтернативные варианты
    // Ищем до первого разделителя или конца строки
    if (preg_match('/^([01][^)]+)(?:\x1D|$)/', $mark, $matches)) {
        return $matches[1];
    }
    
    // Если ничего не подошло, возвращаем исходный код (может быть уже CIS)
    logger('Не удалось извлечь CIS из кода маркировки: ' . $mark);
    return $mark;
}

/**
 * Проверить статус локального модуля ЧЗ
 * @param string|null $clientId
 * @param string|null $host
 * @param string|null $basicAuth
 * @return array
 */
function isLmczReady($clientId = null, $host = null, $basicAuth = null) {
    $config = getLmczConfig();
    $host = $host ?: $config['host'];
    $basicAuth = $basicAuth ?: $config['basic_auth'];
    
    $statusResult = lmczStatus($clientId, $host, $basicAuth);
    
    if (!$statusResult['success']) {
        return [
            'ready' => false,
            'message' => 'Не удалось получить статус ЛМ ЧЗ: ' . $statusResult['message']
        ];
    }
    
    $data = $statusResult['data'];
    
    // Проверяем основные условия готовности
    if (!isset($data['status']) || $data['status'] !== 'ready') {
        return [
            'ready' => false,
            'message' => 'ЛМ ЧЗ не готов к работе. Статус: ' . ($data['status'] ?? 'неизвестен')
        ];
    }
    
    // Проверяем время последней синхронизации (не более 72 часов)
    if (isset($data['lastSync'])) {
        $lastSync = intval($data['lastSync']) / 1000; // Переводим из миллисекунд в секунды
        $now = time();
        $syncAge = $now - $lastSync;
        
        if ($syncAge > 72 * 3600) { // 72 часа в секундах
            return [
                'ready' => false,
                'message' => 'ЛМ ЧЗ не синхронизировался более 72 часов. Последняя синхронизация: ' . date('Y-m-d H:i:s', $lastSync)
            ];
        }
    }
    
    return [
        'ready' => true,
        'message' => 'ЛМ ЧЗ готов к работе'
    ];
}

/**
 * Получить список CDN-площадок через API Честного знака
 * @param bool $production true — использовать продуктивный контур, false — тестовый
 * @return array|false Массив ответа или false при ошибке
 */
function getCdnInfo($production = true) {
    $returnResult = [
        'success' => false,
        'message' => ""
    ];
    
    $token = getApiTokenFromEnv();
    if (!$token) {
        $returnResult['message'] = 'нет файла .env с данными о токене или нет токена';
        return $returnResult;
    }
    
    $cdnConfig = getCdnConfig($production);
    $host = $production ? $cdnConfig['host'] : $cdnConfig['sandbox_host'];
    $url = $host . '/api/v4/true-api/cdn/info';
    $headers = makeApiHeaders($token);
    
    $apiResult = apiJsonRequest($url, $headers);
    if (!$apiResult['success']) {
        $returnResult['message'] = $apiResult['message'];
        return $returnResult;
    }
    
    if (!isset($apiResult['data']['hosts'])) {
        return [
            'success' => false,
            'message' => $apiResult['data']['description'] ?? 'Нет hosts в ответе'
        ];
    }
    
    return [
        'success' => true,
        'message' => "",
        'data' => [
            'response' => $apiResult['data']['hosts'],
            'success' => true,
            'coderesult' => $apiResult['http_code']
        ]
    ];
}

function checkMarkPermitAPI($mark, $fiscalDriveNumber = null, $production = true, $clientId = null, $lmczHost = null, $lmczAuth = null) {
    $returnResult = [
        'success' => false,
        'message' => '',
        'checked_offline' => false
    ];
    
    $token = getApiTokenFromEnv();
    if (!$token) {
        $returnResult['message'] = 'нет файла .env с данными о токене или нет токена';
        return $returnResult;
    }

    // Получаем конфигурацию ЛМ ЧЗ
    $lmczConfig = getLmczConfig();
    $lmczHost = $lmczHost ?: $lmczConfig['host'];
    $lmczAuth = $lmczAuth ?: $lmczConfig['basic_auth'];

    // 1. Получаем кэш CDN
    clearUnavailableCdns();
    $cdnCache = loadCdnCache();
    $now = time();
    $needUpdate = false;
    if (!$cdnCache || (isset($cdnCache[0]['updated_at']) && $cdnCache[0]['updated_at'] < $now - 6 * 3600)) {
        // Обновить кэш CDN
        $cdnInfo = getCdnInfo($production);
        if (!$cdnInfo['success']) {
            logger('Ошибка получения списка CDN: ' . $cdnInfo['message'] . '. Переходим к офлайн проверке.');
            return checkOfflineViaLmcz($mark, $clientId, $lmczHost, $lmczAuth);
        }
        $cdnList = [];
        foreach ($cdnInfo['data']['response'] as $cdn) {
            $health = getCdnHealthCheck($cdn['host'], $production);
            $cdnList[] = [
                'host' => $cdn['host'],
                'latency' => $health['latency'],
                'unavailable_until' => null
            ];
        }
        usort($cdnList, function($a, $b) { return $a['latency'] <=> $b['latency']; });
        foreach ($cdnList as &$c) $c['updated_at'] = $now;
        saveCdnCache($cdnList);
        $cdnCache = $cdnList;
    }

    // 2. Сортируем по latency, фильтруем недоступные
    $availableCdns = array_filter($cdnCache, function($cdn) use ($now) {
        return !isset($cdn['unavailable_until']) || $cdn['unavailable_until'] < $now;
    });
    if (empty($availableCdns)) {
        // Все CDN недоступны, сбрасываем недоступность и пробуем снова
        clearUnavailableCdns();
        $cdnCache = loadCdnCache();
        $availableCdns = $cdnCache;
    }

    $body = [ 'codes' => [ $mark ] ];
    if ($fiscalDriveNumber !== null) $body['fiscalDriveNumber'] = $fiscalDriveNumber;
    $headers = makeApiHeaders($token);
    $attempts = 0;
    $onlineCheckFailed = false;

    foreach ($availableCdns as $cdn) {
        $host = $cdn['host'];
        $url = rtrim($host, '/') . '/api/v4/true-api/codes/check';
        
        // Устанавливаем таймаут 1.5 секунды для онлайн проверки
        $start = microtime(true);
        $apiResult = apiJsonRequest($url, $headers, json_encode($body), 2); // 2 секунды с запасом
        $latency = (int)((microtime(true) - $start) * 1000);
    if (!$apiResult['success']) {
        $returnResult['message'] = $apiResult['message'];
        $returnResult['latency'] = $latency;
        return $returnResult;
    }
    $returnResult['success'] = ($apiResult['data']['code'] === 0);
    $returnResult['message'] = $apiResult['data']['description'] ?? '';
    $returnResult['latency'] = $latency;
    $returnResult['data'] = $apiResult['data'];
    return $returnResult;
}

function saveCdnCache($cdnList) {
    $cacheFile = __DIR__ . '/cdn_cache.json';
    file_put_contents($cacheFile, json_encode($cdnList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function loadCdnCache() {
    $cacheFile = __DIR__ . '/cdn_cache.json';
    if (!file_exists($cacheFile)) return null;
    $data = file_get_contents($cacheFile);
    return json_decode($data, true);
}

function markCdnUnavailable($cdnHost) {
    $cache = loadCdnCache();
    if (!$cache) return;
    $now = time();
    foreach ($cache as &$cdn) {
        if ($cdn['host'] === $cdnHost) {
            $cdn['unavailable_until'] = $now + 15 * 60; // 15 минут
        }
    }
    saveCdnCache($cache);
}

function clearUnavailableCdns() {
    $cache = loadCdnCache();
    if (!$cache) return;
    $now = time();
    foreach ($cache as &$cdn) {
        if (isset($cdn['unavailable_until']) && $cdn['unavailable_until'] < $now) {
            unset($cdn['unavailable_until']);
        }
    }
    saveCdnCache($cache);
}

/**
 * Инициализация ЛМ ЧЗ
 * @param string $token X-API-KEY
 * @param string|null $clientId
 * @param string|null $host
 * @param string|null $basicAuth base64(username:password)
 * @return array
 */
function lmczInit($token, $clientId = null, $host = null, $basicAuth = null) {
    $config = getLmczConfig();
    $host = $host ?: $config['host'];
    $basicAuth = $basicAuth ?: $config['basic_auth'];
    
    $url = rtrim($host, '/') . '/api/v1/init';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $basicAuth
    ];
    if ($clientId) $headers[] = 'X-ClientId: ' . $clientId;
    $body = json_encode([ 'token' => $token ]);
    return apiJsonRequest($url, $headers, $body);
}

/**
 * Проверка статуса ЛМ ЧЗ
 * @param string|null $clientId
 * @param string|null $host
 * @param string|null $basicAuth
 * @return array
 */
function lmczStatus($clientId = null, $host = null, $basicAuth = null) {
    $config = getLmczConfig();
    $host = $host ?: $config['host'];
    $basicAuth = $basicAuth ?: $config['basic_auth'];
    
    $url = rtrim($host, '/') . '/api/v1/status';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $basicAuth
    ];
    if ($clientId) $headers[] = 'X-ClientId: ' . $clientId;
    return apiJsonRequest($url, $headers);
}

/**
 * Проверка КИ в ЛМ ЧЗ (по чёрным спискам)
 * @param string $cis
 * @param string|null $clientId
 * @param string|null $host
 * @param string|null $basicAuth
 * @return array
 */
function lmczCheckCis($cis, $clientId = null, $host = null, $basicAuth = null) {
    $config = getLmczConfig();
    $host = $host ?: $config['host'];
    $basicAuth = $basicAuth ?: $config['basic_auth'];
    
    $cisEnc = rawurlencode($cis);
    $url = rtrim($host, '/') . '/api/v1/cis/check?cis=' . $cisEnc;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $basicAuth
    ];
    if ($clientId) $headers[] = 'X-ClientId: ' . $clientId;
    return apiJsonRequest($url, $headers);
}

// Пример использования:
// $result = getCdnInfo();
// var_dump($result);

// Пример проверки с поддержкой офлайн режима:
// $result = checkMarkPermitAPI('01234567890123456789', 'FN123456', true, 'CLIENT123');
// if ($result['success']) {
//     echo "Проверка пройдена" . ($result['checked_offline'] ? " (офлайн)" : " (онлайн)");
// } else {
//     echo "Ошибка: " . $result['message'];
// });
        $attempts++;

        // Проверяем таймаут 1.5 сек согласно ППРФ 1944 п. 17
        if ($latency > 1500 || (isset($apiResult['timeout']) && $apiResult['timeout'])) {
            logger('CDN таймаут более 1.5 сек: ' . $host . ', latency=' . $latency . 'ms');
            if ($attempts >= 3) {
                markCdnUnavailable($host);
            }
            $onlineCheckFailed = true;
            continue;
        }

        if (!$apiResult['success']) {
            logger('Ошибка запроса к CDN ' . $host . ': ' . $apiResult['message']);
            $onlineCheckFailed = true;
            continue;
        }

        $data = $apiResult['data'];
        if ($data === null) {
            $returnResult['message'] = 'Ошибка декодирования JSON';
            $returnResult['data'] = $apiResult['raw_response'];
            $onlineCheckFailed = true;
            continue;
        }

        // Обработка ошибок по таблице
        $code = $apiResult['http_code'];
        if ($code >= 400 && $code < 500 && $code != 401 && $code != 429) {
            $returnResult['message'] = 'Ошибка в запросе: ' . ($data['description'] ?? '');
            $returnResult['data'] = $data;
            return $returnResult;
        }
        if ($code == 401) {
            $returnResult['message'] = 'Ошибка авторизации (401): ' . ($data['description'] ?? '');
            $returnResult['data'] = $data;
            return $returnResult;
        }
        if ($code == 429 || ($code >= 500 && $code < 600)) {
            // 429 или 5xx: повторить, если снова ошибка — пометить CDN недоступным
            logger('CDN ' . $host . ' ответил ошибкой ' . $code . ', повторная попытка...');
            sleep(1); // небольшая задержка
            $apiResult2 = apiJsonRequest($url, $headers, json_encode($body), 2);
            $data2 = $apiResult2['data'];
            if (!$apiResult2['success'] || $apiResult2['http_code'] == $code) {
                markCdnUnavailable($host);
                logger('CDN ' . $host . ' помечен как недоступный на 15 минут');
                $onlineCheckFailed = true;
                continue;
            }
            $data = $data2;
        }
        if ($code >= 500 && isset($data['code']) && $data['code'] == 5000) {
            // 5xx с code=5000: не помечаем CDN недоступным, повторяем 1 раз
            logger('CDN ' . $host . ' ответил 5000, повторная попытка...');
            $apiResult2 = apiJsonRequest($url, $headers, json_encode($body), 2);
            $data2 = $apiResult2['data'];
            if (!$apiResult2['success'] || ($apiResult2['http_code'] >= 500 && isset($data2['code']) && $data2['code'] == 5000)) {
                $returnResult['message'] = 'Ошибка 5000: ' . ($data2['description'] ?? '');
                $returnResult['data'] = $data2;
                $onlineCheckFailed = true;
                continue;
            }
            $data = $data2;
        }
        if (!isset($data['code']) || $data['code'] !== 0) {
            $returnResult['message'] = $data['description'] ?? 'Ошибка проверки маркировки';
            $returnResult['data'] = $data;
            $onlineCheckFailed = true;
            continue;
        }

        // Успешная онлайн проверка
        $returnResult['success'] = true;
        $returnResult['message'] = $data['description'] ?? '';
        $returnResult['data'] = $data;
        $returnResult['checked_offline'] = false;
        return $returnResult;
    }

    // Если онлайн проверка не удалась, переходим к офлайн проверке
    if ($onlineCheckFailed) {
        logger('Онлайн проверка не удалась, переходим к проверке через ЛМ ЧЗ');
        return checkOfflineViaLmcz($mark, $clientId, $lmczHost, $lmczAuth);
    }

    $returnResult['message'] = 'Не удалось проверить маркировку: все CDN недоступны или ошибка запроса';
    return $returnResult;
}

/**
 * Проверка маркировки через локальный модуль ЧЗ (офлайн режим)
 * @param string $mark Код маркировки
 * @param string|null $clientId
 * @param string|null $lmczHost
 * @param string|null $lmczAuth
 * @return array
 */
function checkOfflineViaLmcz($mark, $clientId = null, $lmczHost = null, $lmczAuth = null) {
    $returnResult = [
        'success' => false,
        'message' => '',
        'checked_offline' => true
    ];

    // Получаем конфигурацию если параметры не переданы
    $lmczConfig = getLmczConfig();
    $lmczHost = $lmczHost ?: $lmczConfig['host'];
    $lmczAuth = $lmczAuth ?: $lmczConfig['basic_auth'];

    // Проверяем готовность ЛМ ЧЗ
    $readyCheck = isLmczReady($clientId, $lmczHost, $lmczAuth);
    if (!$readyCheck['ready']) {
        $returnResult['message'] = 'ЛМ ЧЗ не готов: ' . $readyCheck['message'];
        return $returnResult;
    }

    // Извлекаем код идентификации из кода маркировки
    $cis = extractCisFromMark($mark);
    if (!$cis) {
        $returnResult['message'] = 'Не удалось извлечь код идентификации из кода маркировки';
        return $returnResult;
    }

    logger('Проверяем код идентификации через ЛМ ЧЗ: ' . $cis);

    // Выполняем проверку через ЛМ ЧЗ
    $lmczResult = lmczCheckCis($cis, $clientId, $lmczHost, $lmczAuth);
    
    if (!$lmczResult['success']) {
        $returnResult['message'] = 'Ошибка проверки через ЛМ ЧЗ: ' . $lmczResult['message'];
        return $returnResult;
    }

    $data = $lmczResult['data'];
    
    // Проверяем результат
    if (!isset($data['code']) || $data['code'] !== 0) {
        $returnResult['message'] = 'Ошибка от ЛМ ЧЗ: ' . ($data['description'] ?? 'Неизвестная ошибка');
        $returnResult['data'] = $data;
        return $returnResult;
    }

    // Проверяем наличие кодов в ответе
    if (!isset($data['codes']) || empty($data['codes'])) {
        $returnResult['message'] = 'ЛМ ЧЗ не вернул информацию о коде';
        $returnResult['data'] = $data;
        return $returnResult;
    }

    $codeInfo = $data['codes'][0];
    
    // Проверяем, заблокирован ли код
    if (isset($codeInfo['isBlocked']) && $codeInfo['isBlocked'] === true) {
        $returnResult['success'] = false;
        $returnResult['message'] = 'Код заблокирован по решению ОГВ (офлайн проверка)';
        $returnResult['data'] = [
            'code' => 1, // Код ошибки для заблокированного товара
            'description' => 'Код заблокирован по решению ОГВ',
            'codes' => [
                [
                    'code' => $mark,
                    'offline_check' => true,
                    'blocked' => true,
                    'gtin' => $codeInfo['gtin'] ?? null
                ]
            ]
        ];
        return $returnResult;
    }

    // Код не заблокирован
    $returnResult['success'] = true;
    $returnResult['message'] = 'Код не заблокирован (офлайн проверка)';
    $returnResult['data'] = [
        'code' => 0,
        'description' => 'Код не заблокирован (проверено офлайн)',
        'codes' => [
            [
                'code' => $mark,
                'offline_check' => true,
                'blocked' => false,
                'gtin' => $codeInfo['gtin'] ?? null
            ]
        ]
    ];

    return $returnResult;
}

function getCdnHealthCheck($host, $production = true) {
    $returnResult = [
        'success' => false,
        'message' => '',
        'latency' => null,
        'data'    => null
    ];
    $token = getApiTokenFromEnv();
    if (!$token) {
        $returnResult['message'] = 'нет файла .env с данными о токене или нет токена';
        return $returnResult;
    }
    $url = rtrim($host, '/') . '/api/v4/true-api/cdn/health/check';
    $headers = makeApiHeaders($token);
    $start = microtime(true);
    $apiResult = apiJsonRequest($url, $headers);

    $latency = (int)((microtime(true) - $start) * 1000);

    // Формируем итоговый результат
    $returnResult['latency'] = $latency;
    if (!$apiResult['success']) {
        // В случае неуспешного ответа от API
        $returnResult['message'] = $apiResult['message'];
        return $returnResult;
    }

    // Успешная проверка здоровья CDN
    $returnResult['success'] = true;
    $returnResult['data']    = $apiResult['data'];
    return $returnResult;
}