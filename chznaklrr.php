<?php

require_once 'logger.php'; // Подключаем логгер


/**
 * Получить X-API-KEY из .env
 * @return string|false
 */
function getApiTokenFromEnv() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return false;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'X_API_KEY=') === 0) {
            return trim(substr($line, strlen('X_API_KEY=')));
        }
    }
    return false;
}

/**
 * Выполнить curl-запрос с обработкой ошибок (универсально для GET/POST)
 * @param string $url
 * @param array $headers
 * @param string|null $postFields
 * @return array ['success'=>bool, 'http_code'=>int, 'response'=>mixed, 'message'=>string]
 */
function performCurlRequest($url, $headers, $postFields = null) {
    $result = [
        'success' => false,
        'http_code' => 0,
        'response' => null,
        'message' => ''
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $result['http_code'] = $httpCode;
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
            } else {
                $result['message'] .= ", CURL error: $curlError";
            }
        } else {
            $result['message'] .= ", HTTP code: $httpCode, response: '" . var_export($response, true) . "'";
        }
        return $result;
    }
    $result['success'] = true;
    $result['response'] = $response;
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
 * @return array ['success'=>bool, 'http_code'=>int, 'data'=>mixed, 'message'=>string, 'raw_response'=>string]
 */
function apiJsonRequest($url, $headers, $postFields = null) {
    $curlResult = performCurlRequest($url, $headers, $postFields);
    if (!$curlResult['success']) {
        return [
            'success' => false,
            'http_code' => $curlResult['http_code'],
            'data' => null,
            'message' => $curlResult['message'],
            'raw_response' => $curlResult['response']
        ];
    }
    $data = json_decode($curlResult['response'], true);
    if ($data === null) {
        return [
            'success' => false,
            'http_code' => $curlResult['http_code'],
            'data' => null,
            'message' => 'Ошибка декодирования JSON',
            'raw_response' => $curlResult['response']
        ];
    }
    return [
        'success' => true,
        'http_code' => $curlResult['http_code'],
        'data' => $data,
        'message' => $data['description'] ?? '',
        'raw_response' => $curlResult['response']
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
    $host = $production
        ? 'https://cdn.crpt.ru'
        : 'https://markirovka.sandbox.crptech.ru';
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

function checkMarkPermitAPI($mark, $fiscalDriveNumber = null, $production = true) {
    $returnResult = [
        'success' => false,
        'message' => ''
    ];
    $token = getApiTokenFromEnv();
    if (!$token) {
        $returnResult['message'] = 'нет файла .env с данными о токене или нет токена';
        return $returnResult;
    }
    // 1. Получаем кэш CDN
    clearUnavailableCdns();
    $cdnCache = loadCdnCache();
    $now = time();
    $needUpdate = false;
    if (!$cdnCache || (isset($cdnCache[0]['updated_at']) && $cdnCache[0]['updated_at'] < $now - 6 * 3600)) {
        // Обновить кэш CDN
        $cdnInfo = getCdnInfo($production);
        if (!$cdnInfo['success']) {
            $returnResult['message'] = 'Ошибка получения списка CDN: ' . $cdnInfo['message'];
            return $returnResult;
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
    foreach ($availableCdns as $cdn) {
        $host = $cdn['host'];
        $url = rtrim($host, '/') . '/api/v4/true-api/codes/check';
        $start = microtime(true);
        $apiResult = apiJsonRequest($url, $headers, json_encode($body));
        $latency = (int)((microtime(true) - $start) * 1000);
        $attempts++;
        // Таймаут 1.5 сек
        if ($latency > 1500) {
            logger('CDN таймаут: ' . $host . ', latency=' . $latency . 'ms');
            if ($attempts >= 3) {
                markCdnUnavailable($host);
            }
            continue;
        }
        if (!$apiResult['success']) {
            logger('Ошибка запроса к CDN ' . $host . ': ' . $apiResult['message']);
            continue;
        }
        $data = $apiResult['data'];
        if ($data === null) {
            $returnResult['message'] = 'Ошибка декодирования JSON';
            $returnResult['data'] = $apiResult['raw_response'];
            return $returnResult;
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
            $apiResult2 = apiJsonRequest($url, $headers, json_encode($body));
            $data2 = $apiResult2['data'];
            if (!$apiResult2['success'] || $apiResult2['http_code'] == $code) {
                markCdnUnavailable($host);
                logger('CDN ' . $host . ' помечен как недоступный на 15 минут');
                continue;
            }
            $data = $data2;
        }
        if ($code >= 500 && isset($data['code']) && $data['code'] == 5000) {
            // 5xx с code=5000: не помечаем CDN недоступным, повторяем 1 раз
            logger('CDN ' . $host . ' ответил 5000, повторная попытка...');
            $apiResult2 = apiJsonRequest($url, $headers, json_encode($body));
            $data2 = $apiResult2['data'];
            if (!$apiResult2['success'] || ($apiResult2['http_code'] >= 500 && isset($data2['code']) && $data2['code'] == 5000)) {
                $returnResult['message'] = 'Ошибка 5000: ' . ($data2['description'] ?? '');
                $returnResult['data'] = $data2;
                return $returnResult;
            }
            $data = $data2;
        }
        if (!isset($data['code']) || $data['code'] !== 0) {
            $returnResult['message'] = $data['description'] ?? 'Ошибка проверки маркировки';
            $returnResult['data'] = $data;
            return $returnResult;
        }
        $returnResult['success'] = true;
        $returnResult['message'] = $data['description'] ?? '';
        $returnResult['data'] = $data;
        return $returnResult;
    }
    $returnResult['message'] = 'Не удалось проверить маркировку: все CDN недоступны или ошибка запроса';
    return $returnResult;
}

function getCdnHealthCheck($host, $production = true) {
    $returnResult = [
        'success' => false,
        'message' => '',
        'latency' => null,
        'data' => null
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
 * @param string $host
 * @param string $basicAuth base64(username:password), по умолчанию admin:admin
 * @return array
 */
function lmczInit($token, $clientId = null, $host = 'http://127.0.0.1:5995', $basicAuth = 'YWRtaW46YWRtaW4=') {
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
 * @param string $host
 * @param string $basicAuth
 * @return array
 */
function lmczStatus($clientId = null, $host = 'http://127.0.0.1:5995', $basicAuth = 'YWRtaW46YWRtaW4=') {
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
 * @param string $host
 * @param string $basicAuth
 * @return array
 */
function lmczCheckCis($cis, $clientId = null, $host = 'http://127.0.0.1:5995', $basicAuth = 'YWRtaW46YWRtaW4=') {
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
