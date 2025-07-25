<?php

require_once 'logger.php'; // Подключаем логгер

// Диагностика поиска cacert.pem
//echo "Текущая рабочая директория: " . getcwd() . PHP_EOL;
//echo "curl.cainfo: " . ini_get('curl.cainfo') . PHP_EOL;
//echo "openssl.cafile: " . ini_get('openssl.cafile') . PHP_EOL;

//$curlCainfo = ini_get('curl.cainfo');
//if ($curlCainfo) {
//    $fullPath = getcwd() . DIRECTORY_SEPARATOR . $curlCainfo;
//    echo "Пробуем найти файл: $fullPath" . PHP_EOL;
//    if (file_exists($fullPath)) {
//        echo "Файл найден!" . PHP_EOL;
//    } else {
//        echo "Файл НЕ найден!" . PHP_EOL;
//    }
//} else {
//    echo "curl.cainfo не задан в php.ini" . PHP_EOL;
//}

//ini_set('curl.cainfo', __DIR__ . '/extras/cacert.pem');
//ini_set('openssl.cafile', __DIR__ . '/extras/cacert.pem');

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

    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        $returnResult['message'] = 'нет файла .env с данными о токене';
        return $returnResult;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $token = null;
    foreach ($lines as $line) {
        if (strpos($line, 'X_API_KEY=') === 0) {
            $token = trim(substr($line, strlen('X_API_KEY=')));
            break;
        }
    }
    if (!$token) {
        $returnResult['message'] = 'нет токена в файле .env';
        return $returnResult;
    }

    $host = $production
        ? 'https://cdn.crpt.ru'
        : 'https://markirovka.sandbox.crptech.ru';
    $url = $host . '/api/v4/true-api/cdn/info';
    $headers = [
        'Content-Type: application/json',
        'X-API-KEY: ' . $token
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        $curlError = curl_error($ch);
        $returnResult['message'] = 'не удалось получить данные по CDN';
        if ($curlError) {
            $returnResult['message'] .= ", CURL error: $curlError";
        } else {
            $returnResult['message'] .= ", HTTP code: $httpCode, response: '" . var_export($response, true) . "'";
        }
        return $returnResult;
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $curlError = curl_error($ch);
        if (strpos($curlError, 'SSL certificate') !== false) {
            $returnResult['message'] = "Ошибка безопасности: не удалось проверить подлинность сервера (SSL).\n"
                . "Возможные причины:\n"
                . "- Устарел или отсутствует файл корневых сертификатов (cacert.pem) для PHP.\n"
                . "- Проблемы с интернет-соединением или настройками безопасности.\n\n"
                . "Что делать:\n"
                . "1. Сообщите администратору или техническому специалисту.\n"
                . "2. Проверьте, что файл cacert.pem скачан с https://curl.se/ca/cacert.pem и путь к нему прописан в php.ini (curl.cainfo).\n"
                . "3. Если не помогло — обратитесь в поддержку.";
        } else {
            $returnResult['message'] = 'Ошибка CURL: ' . $curlError;
        }
        curl_close($ch);
        return $returnResult;
    }    


    $responsedata = json_decode($response, true);
    if (!isset($responsedata['hosts'])) {
        return [
            'success' => false,
            'message' => $responsedata['description']
        ];
    }

    return [
        'success' => true,
        'message' => "",
        'data' => [
            'response' => $responsedata['hosts'],
            'success' => true,
            'coderesult' => $httpCode
        ]
    ];
}

function checkMarkPermitAPI($mark) {
    return [
        'status' => 'разрешено',
        'result' => 'Марка разрешена (заглушка)'
    ];
}

// Пример использования:
// $result = getCdnInfo();
// var_dump($result);
