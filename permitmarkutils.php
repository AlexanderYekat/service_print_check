<?php
//checkpermitmarkutils.php

require_once 'logger.php'; // Подключаем логгер

/**
 * Реализация проверки маркировки товаров через API Честного знака
 * с fallback на локальный модуль ЧЗ согласно ППРФ 1944
 * 
 * Перенесено с рабочего кода 1С
 */
class PermitMarkCheckGateway
{
    private string $apiKey;
    private int $timeout;
    private Logger $logger;
    private array $config;
    private string $cdnCachePath;

    public function __construct(string $apiKey, int $timeout, Logger $logger, array $config = [])
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->logger = $logger;
        
        // Конфигурация по умолчанию (аналог ПолучитьПараметрыПоУмолчанию из 1С)
        $this->config = array_merge([
            'onlineTimeout' => 1.5, // секунды
            'cdnUnavailableTime' => 900, // 15 минут в секундах
            'maxRetries' => 3,
            'cdnCachePath' => sys_get_temp_dir() . '/rr/cdns/cdn_cache.json',
            'productionMode' => true,
            'clientId' => '',
            'lmHost' => 'http://127.0.0.1:5995',
            'lmAuth' => 'YWRtaW46YWRtaW4=', // admin:admin в base64
            'cdnBaseUrl' => 'https://cdn.crpt.ru',
            'sandboxUrl' => 'https://markirovka.sandbox.crptech.ru',
            'verifySSL' => true // проверка SSL сертификатов
        ], $config);
        
        $this->cdnCachePath = $this->config['cdnCachePath'];
        $this->ensureCacheDirectory();
    }

    /**
     * Основная функция проверки маркировки товара
     * Аналог ПроверитьМаркировку из 1С
     */
    public function checkPermit(string $code, array $context = []): array
    {
        $this->logger->info("Начинаем проверку маркировки: " . $code);
        
        // Сначала пробуем онлайн проверку
        $onlineResult = $this->checkOnline($code, $context);

        $this->logger->info("Результат онлайн проверки: " . json_encode($onlineResult));
        
        if ($onlineResult['success']) {
            $this->logger->info("Онлайн проверка успешна");
            return $this->processOnlineResult($onlineResult);
        }
        
        // Если онлайн проверка не удалась, переходим к офлайн
        $this->logger->info("Онлайн проверка не удалась, переходим к офлайн проверке");
        $offlineResult = $this->checkOffline($code, $context);
        
        return $this->processOfflineResult($offlineResult);
    }

    /**
     * Онлайн проверка через CDN площадки
     * Аналог ПроверитьОнлайн из 1С
     */
    private function checkOnline(string $code, array $context = []): array
    {
        $this->logger->info("Начинаем онлайн проверку маркировки: " . $code);
        
        // 1. Получаем кэш CDN и очищаем недоступные
        $this->clearUnavailableCDN();
        $cdnCache = $this->loadCDNCache();
        
        // Проверяем необходимость обновления кэша (не более 6 часов)
        if ($this->needUpdateCache($cdnCache)) {
            $this->logger->info("Обновляем кэш CDN площадок");
            $updateResult = $this->updateCDNCache();
            if (!$updateResult['success']) {
                $this->logger->warning("Ошибка обновления кэша CDN: " . $updateResult['message']);
                return ['success' => false, 'message' => 'Не удалось получить список CDN площадок'];
            }
            $cdnCache = $this->loadCDNCache();
        }
        
        // 2. Фильтруем доступные CDN
        $availableCDN = $this->filterAvailableCDN($cdnCache);
        
        // Если все CDN недоступны, сбрасываем недоступность
        if (empty($availableCDN)) {
            $this->logger->info("Все CDN недоступны, сбрасываем недоступность");
            $this->clearUnavailableCDN();
            $cdnCache = $this->loadCDNCache();
            $availableCDN = $cdnCache;
        }
        
        // Сортируем CDN по задержке
        $this->sortCDNByLatency($availableCDN);
        
        // 3. Формируем тело запроса
        $requestData = [
            'codes' => [$code]
        ];
        
        if (!empty($context['fiscalDriveNumber'])) {
            $requestData['fiscalDriveNumber'] = $context['fiscalDriveNumber'];
        }
        
        // 4. Обходим CDN площадки
        $headers = $this->buildAPIHeaders();
        
        foreach ($availableCDN as $cdn) {
            $host = $cdn['host'];
            $url = rtrim($host, '/') . '/api/v4/true-api/codes/check';
            
            $this->logger->info("Пробуем CDN: " . $host);
            
            $startTime = microtime(true);
            $result = $this->performJSONRequest($url, $headers, json_encode($requestData), 2);
            $endTime = microtime(true);
            
            $latency = $endTime - $startTime;
            
            // Проверяем таймаут 1.5 сек согласно ППРФ 1944 п. 17
            if (!$result['success'] && ($latency > 2 || ($result['timeout'] ?? false))) {
                $this->logger->warning("CDN таймаут более 1.5 сек: " . $host . ", задержка=" . $latency . "с");
                $this->markCDNUnavailable($host);
                continue;
            }
            
            // Проверяем успешность запроса
            if (!$result['success']) {
                $this->logger->warning("Ошибка запроса к CDN " . $host . ": " . $result['message']);
                $this->markCDNUnavailable($host);
                continue;
            }
            
            // Обработка ошибок по HTTP кодам
            $httpCode = $result['httpCode'] ?? 0;
            
            // 4xx ошибки (кроме 401 и 429) - возвращаем ошибку
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 401 && $httpCode !== 429) {
                $description = $result['data']['description'] ?? '';
                return [
                    'success' => false,
                    'message' => 'Ошибка в запросе: ' . $description,
                    'data' => $result['data'],
                    'httpCode' => $httpCode
                ];
            }
            
            // 401 - ошибка авторизации
            if ($httpCode === 401) {
                $description = $result['data']['description'] ?? '';
                return [
                    'success' => false,
                    'message' => 'Ошибка авторизации (401): ' . $description,
                    'data' => $result['data'],
                    'httpCode' => $httpCode
                ];
            }
            
            // 429 или 5xx - повторная попытка
            if ($httpCode === 429 || ($httpCode >= 500 && $httpCode < 600)) {
                $this->logger->warning("CDN " . $host . " ответил ошибкой " . $httpCode . ", повторная попытка...");
                
                // Повторная попытка
                $result2 = $this->performJSONRequest($url, $headers, json_encode($requestData), 2);
                
                if (!$result2['success'] || $result2['httpCode'] === $httpCode) {
                    $this->markCDNUnavailable($host);
                    $this->logger->warning("CDN " . $host . " помечен как недоступный на 15 минут");
                    continue;
                }
                
                $result = $result2;
            }
            
            // 5xx с code=5000 - повторяем 1 раз
            if ($httpCode >= 500 && isset($result['data']['code']) && $result['data']['code'] === 5000) {
                $this->logger->warning("CDN " . $host . " ответил 5000, повторная попытка...");
                
                $result2 = $this->performJSONRequest($url, $headers, json_encode($requestData), 2);
                
                if (!$result2['success'] || ($result2['httpCode'] >= 500 && isset($result2['data']['code']) && $result2['data']['code'] === 5000)) {
                    $description = $result2['data']['description'] ?? '';
                    return [
                        'success' => false,
                        'message' => 'Ошибка 5000: ' . $description,
                        'data' => $result2['data'],
                        'httpCode' => $result2['httpCode']
                    ];
                }
                
                $result = $result2;
            }
            
            // Успешный ответ
            $this->logger->info("CDN " . $host . " ответил успешно, задержка: " . $latency . "c");
            
            return [
                'success' => true,
                'data' => $result['data'],
                'message' => 'Маркировка проверена онлайн через CDN ' . $host,
                'timeout' => false,
                'checkedOffline' => false
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Не удалось проверить маркировку: все CDN недоступны или ошибка запроса'
        ];
    }

    /**
     * Офлайн проверка через локальный модуль ЧЗ
     * Аналог ПроверитьОфлайн из 1С
     */
    private function checkOffline(string $code, array $context = []): array
    {
        $this->logger->info("Начинаем офлайн проверку маркировки: " . $code);
        
        // 1. Проверяем готовность локального модуля ЧЗ
        $readinessResult = $this->checkLMReadiness();
        if (!$readinessResult['success']) {
            return [
                'success' => false,
                'message' => 'Локальный модуль ЧЗ не готов: ' . $readinessResult['message']
            ];
        }
        
        $this->logger->info("Локальный модуль ЧЗ готов к работе");
        
        // 2. Извлекаем CIS из маркировки
        $cis = $this->extractCISFromMarking($code);
        $this->logger->info("Извлечен CIS: " . $cis);
        
        // 3. Проверяем CIS в локальном модуле ЧЗ
        $checkResult = $this->checkCISInLM($cis);
        if (!$checkResult['success']) {
            return [
                'success' => false,
                'message' => 'Ошибка проверки CIS в ЛМ ЧЗ: ' . $checkResult['message']
            ];
        }
        
        // 4. Формируем успешный результат
        $this->logger->info("Офлайн проверка завершена успешно");
        
        return [
            'success' => true,
            'data' => $checkResult['data'],
            'message' => 'Маркировка проверена офлайн через локальный модуль ЧЗ',
            'timeout' => false,
            'checkedOffline' => true
        ];
    }

    /**
     * Обработка результата онлайн проверки
     */
    private function processOnlineResult(array $result): array
    {
        if (isset($result['data']['code']) && $result['data']['code'] !== 0) {
            return [
                'success' => false,
                'message' => $result['data']['description'] ?? 'Ошибка проверки марки',
                'errorCode' => $result['data']['code']
            ];
        }
        
        if ($result['checkedOffline'] ?? false) {
            foreach ($result['data']['codes'] ?? [] as $mark) {
                if ($mark['isBlocked'] ?? false) {
                    return [
                        'success' => false,
                        'message' => 'Марка заблокирована по решению органов государственной власти',
                        'errorCode' => '200'
                    ];
                }
            }
        }
        
        $this->logger->info("Запрос проверки марки успешно обработан");
        
        foreach ($result['data']['codes'] ?? [] as $mark) {
            $errorCode = $mark['errorCode'] ?? 0;
            $message = $result['data']['message'] ?? '';
            
            if ($errorCode !== 0) {
                return [
                    'success' => false,
                    'message' => $message,
                    'errorCode' => $errorCode
                ];
            }
            
            // Проверяем дополнительные условия
            if (!($mark['isBlocked'] ?? true)) {
                $message = 'Марка заблокирована по решению органов государственной власти';
            }
            
            if (isset($mark['expireDate']) && $mark['expireDate']) {
                $message = 'У товара истёк срок годности';
            }
            
            if (!($mark['sold'] ?? true)) {
                $message = 'Марка уже выведена из оборота';
            }
            
            if (!($mark['verified'] ?? true)) {
                $message = 'Марка не прошла проверку';
            }
            
            if (!($mark['found'] ?? true)) {
                $message = 'Марка не найдена';
            }
            
            if (!($mark['valid'] ?? true)) {
                $message = 'Не валидная марка';
            }
            
            return [
                'success' => true,
                'code' => $result['data']['code'],
                'message' => $message,
                'reqId' => $result['data']['reqId'] ?? '',
                'reqTimestamp' => $result['data']['reqTimestamp'] ?? '',
                'errorCode' => $errorCode
            ];
        }
        
        return $result;
    }

    /**
     * Обработка результата офлайн проверки
     */
    private function processOfflineResult(array $result): array
    {
        return $result;
    }

    /**
     * Получение списка CDN площадок через API Честного знака
     */
    private function getCDNList(): array
    {
        $baseUrl = $this->config['productionMode'] ? $this->config['cdnBaseUrl'] : $this->config['sandboxUrl'];
        $url = $baseUrl . '/api/v4/true-api/cdn/info';
        $headers = $this->buildAPIHeaders();
        $this->logger->info("Запрашиваем список CDN площадок: " . $url);
        $this->logger->info("Заголовки: " . json_encode($headers));
        
        $result = $this->performJSONRequest($url, $headers, '', 30);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Ошибка получения списка CDN: ' . $result['message']
            ];
        }
        
        if (!isset($result['data']['hosts'])) {
            return [
                'success' => false,
                'message' => 'Некорректная структура ответа API: отсутствует поле hosts'
            ];
        }
        
        $this->logger->info("Получено CDN площадок: " . count($result['data']['hosts']));
        
        return [
            'success' => true,
            'data' => $result['data']['hosts'],
            'message' => 'Список CDN площадок успешно получен'
        ];
    }

    /**
     * Проверка здоровья CDN площадки
     */
    private function checkCDNHealth(string $host): array
    {
        $url = rtrim($host, '/') . '/api/v4/true-api/cdn/health/check';
        $headers = $this->buildAPIHeaders();
        
        $this->logger->info("Проверяем здоровье CDN: " . $host);
        
        $startTime = microtime(true);
        $result = $this->performJSONRequest($url, $headers, '', 5);
        $endTime = microtime(true);
        
        $latency = ($endTime - $startTime) * 1000; // в миллисекундах
        
        return [
            'success' => $result['success'],
            'data' => [
                'host' => $host,
                'latency' => $latency,
                'available' => $result['success']
            ],
            'message' => $result['success'] ? 
                "CDN доступен, задержка: " . $latency . " мс" : 
                "CDN недоступен: " . $result['message']
        ];
    }

    /**
     * Обновление кэша CDN площадок
     */
    private function updateCDNCache(): array
    {
        $this->logger->info("Начинаем обновление кэша CDN");
        
        $cdnList = $this->getCDNList();
        if (!$cdnList['success']) {
            return [
                'success' => false,
                'message' => 'Не удалось получить список CDN: ' . $cdnList['message']
            ];
        }
        
        $newCache = [];
        $currentTime = time();
        
        foreach ($cdnList['data'] as $cdnHost) {
            $host = $cdnHost['host'] ?? $cdnHost;
            $this->logger->info("Проверяем CDN: " . $host);
            
            $health = $this->checkCDNHealth($host);
            
            $newCache[] = [
                'host' => $host,
                'latency' => $health['data']['latency'],
                'available' => $health['data']['available'],
                'updatedAt' => $currentTime
            ];
        }
        
        // Сортируем по задержке
        $this->sortCDNByLatency($newCache);
        
        // Сохраняем обновленный кэш
        if ($this->saveCDNCache($newCache)) {
            $this->logger->info("Кэш CDN успешно обновлен, площадок: " . count($newCache));
            return [
                'success' => true,
                'message' => 'Кэш CDN обновлен, площадок: ' . count($newCache),
                'data' => $newCache
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Не удалось сохранить обновленный кэш CDN'
            ];
        }
    }

    /**
     * Проверка готовности локального модуля ЧЗ
     */
    private function checkLMReadiness(): array
    {
        $url = rtrim($this->config['lmHost'], '/') . '/api/v1/status';
        $headers = [
            'Authorization: Basic ' . $this->config['lmAuth'],
            'Content-Type: application\\json'
        ];
        
        $this->logger->info("Проверяем готовность ЛМ ЧЗ: " . $url);
        
        $result = $this->performJSONRequest($url, $headers, '', 10, true);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Ошибка запроса к ЛМ ЧЗ: ' . $result['message']
            ];
        }
        
        if ($result['httpCode'] !== 200) {
            return [
                'success' => false,
                'message' => 'ЛМ ЧЗ вернул HTTP код: ' . $result['httpCode']
            ];
        }
        
        $data = $result['data'];
        if (!isset($data['status'])) {
            return [
                'success' => false,
                'message' => 'В ответе ЛМ ЧЗ отсутствует поле status'
            ];
        }
        
        if ($data['status'] !== 'ready') {
            return [
                'success' => false,
                'message' => 'ЛМ ЧЗ не готов к работе: ' . $data['status']
            ];
        }
        
        $this->logger->info("ЛМ ЧЗ готов к работе");
        
        return [
            'success' => true,
            'message' => 'ЛМ ЧЗ готов к работе',
            'data' => $data
        ];
    }

    /**
     * Проверка CIS в локальном модуле ЧЗ
     */
    private function checkCISInLM(string $cis): array
    {
        $url = rtrim($this->config['lmHost'], '/') . '/api/v1/cis/check?cis=' . urlencode($cis);
        $headers = [
            'Authorization: Basic ' . $this->config['lmAuth'],
            'Content-Type: application/json'
        ];
        
        $this->logger->info("Проверяем CIS в ЛМ ЧЗ: " . $cis);
        
        $result = $this->performJSONRequest($url, $headers, '', 30, true);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Ошибка запроса к ЛМ ЧЗ: ' . $result['message']
            ];
        }
        
        if ($result['httpCode'] !== 200) {
            return [
                'success' => false,
                'message' => 'ЛМ ЧЗ вернул HTTP код: ' . $result['httpCode']
            ];
        }
        
        $data = $result['data'];
        if (!isset($data['codes']) || !is_array($data['codes']) || empty($data['codes'])) {
            return [
                'success' => false,
                'message' => 'В ответе ЛМ ЧЗ отсутствуют результаты проверки'
            ];
        }
        
        $checkResult = $data['codes'][0];
        if (!isset($checkResult['isBlocked'])) {
            return [
                'success' => false,
                'message' => 'В результате проверки отсутствует поле isBlocked'
            ];
        }
        
        if ($checkResult['isBlocked']) {
            $description = $checkResult['description'] ?? '';
            return [
                'success' => false,
                'message' => 'Ошибка проверки CIS в ЛМ ЧЗ (код ' . $checkResult['isBlocked'] . ')' . $description
            ];
        }
        
        $this->logger->info("CIS успешно проверен в ЛМ ЧЗ");
        
        return [
            'success' => true,
            'message' => 'CIS успешно проверен в ЛМ ЧЗ',
            'data' => $data
        ];
    }

    /**
     * Извлечение CIS из маркировки
     */
    private function extractCISFromMarking(string $marking): string
    {
        if (empty($marking)) {
            return '';
        }
        
        $marking = trim($marking);
        $this->logger->info("Извлечение CIS из маркировки: " . $marking);
        
        // TODO: Реализовать логику извлечения CIS согласно стандарту GS1
        // Пока возвращаем исходный код
        return $marking;
    }

    /**
     * Выполнение JSON запроса
     */
    private function performJSONRequest(string $url, array $headers, string $body = '', int $timeout = 30, bool $isLM = false): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => !empty($body) ? 'POST' : 'GET',
            CURLOPT_POSTFIELDS => !empty($body) ? $body : '{"data":"string"}', // Используем рабочий формат
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->config['verifySSL'],
            CURLOPT_SSL_VERIFYHOST => $this->config['verifySSL'] ? 2 : 0,
            CURLOPT_USERAGENT => 'CloudPosBridgePHP/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        
        // Дополнительная информация о запросе
        $this->logger->info("cURL информация: " . json_encode([
            'method' => !empty($body) ? 'POST' : 'GET',
            'content_type' => $curlInfo['content_type'] ?? 'неизвестно',
            'total_time' => $curlInfo['total_time'] ?? 0,
            'connect_time' => $curlInfo['connect_time'] ?? 0,
            'redirect_count' => $curlInfo['redirect_count'] ?? 0
        ]));
        
        curl_close($ch);
        
        // Логируем детали запроса для отладки
        $this->logger->info("cURL запрос к: " . $url);
        $this->logger->info("Заголовки запроса: " . json_encode($headers));
        $this->logger->info("Тело запроса: " . ($body ?: 'пустое'));
        $this->logger->info("HTTP код: " . $httpCode);
        $this->logger->info("cURL ошибка: " . ($error ?: 'Нет'));
        $this->logger->info("Размер ответа: " . strlen($response ?: ''));
        
        if ($response === false) {
            $this->logger->error("cURL выполнение не удалось: " . $error);
            return [
                'success' => false,
                'message' => "Ошибка cURL: {$error}",
                'httpCode' => 0
            ];
        }
        
        if ($httpCode !== 200) {
            $this->logger->warning("HTTP код не 200: " . $httpCode . ", ответ: " . substr($response, 0, 500));
            return [
                'success' => false,
                'message' => "HTTP ошибка: {$httpCode}. Ответ: " . substr($response, 0, 200),
                'httpCode' => $httpCode,
                'response' => $response
            ];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => "Некорректный JSON ответ: " . json_last_error_msg(),
                'httpCode' => $httpCode
            ];
        }
        
        return [
            'success' => true,
            'data' => $data,
            'httpCode' => $httpCode
        ];
    }

    /**
     * Формирование заголовков для API запросов
     */
    private function buildAPIHeaders(): array
    {
        return [
            'Content-Type: application/json', // Используем экранированный формат как в рабочем коде
            'X-API-KEY: ' . $this->apiKey
        ];
    }

    /**
     * Загрузка кэша CDN из файла
     */
    private function loadCDNCache(): array
    {
        if (!file_exists($this->cdnCachePath)) {
            $this->logger->info("Файл кэша CDN не найден: " . $this->cdnCachePath);
            return [];
        }
        
        $content = file_get_contents($this->cdnCachePath);
        if (empty($content)) {
            $this->logger->info("Файл кэша CDN пуст");
            return [];
        }
        
        $cache = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Ошибка парсинга кэша CDN: " . json_last_error_msg());
            return [];
        }
        
        $this->logger->info("Кэш CDN загружен из файла: " . $this->cdnCachePath . ", площадок: " . count($cache));
        return $cache;
    }

    /**
     * Сохранение кэша CDN в файл
     */
    private function saveCDNCache(array $cache): bool
    {
        try {
            $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $this->logger->error("Ошибка кодирования кэша CDN в JSON");
                return false;
            }
            
            if (file_put_contents($this->cdnCachePath, $json) === false) {
                $this->logger->error("Ошибка записи кэша CDN в файл");
                return false;
            }
            
            $this->logger->info("Кэш CDN сохранен в файл: " . $this->cdnCachePath . ", площадок: " . count($cache));
            return true;
        } catch (Exception $e) {
            $this->logger->error("Ошибка сохранения кэша CDN: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Очистка недоступных CDN площадок
     */
    private function clearUnavailableCDN(): void
    {
        $cache = $this->loadCDNCache();
        if (empty($cache)) {
            return;
        }
        
        $currentTime = time();
        $changed = false;
        
        foreach ($cache as &$cdn) {
            if (isset($cdn['unavailableUntil']) && $cdn['unavailableUntil'] < $currentTime) {
                unset($cdn['unavailableUntil']);
                $changed = true;
            }
        }
        
        if ($changed) {
            $this->saveCDNCache($cache);
            $this->logger->info("Очищены пометки о недоступности CDN");
        }
    }

    /**
     * Пометка CDN площадки как недоступной
     */
    private function markCDNUnavailable(string $host): void
    {
        $cache = $this->loadCDNCache();
        if (empty($cache)) {
            return;
        }
        
        $currentTime = time();
        $changed = false;
        
        foreach ($cache as &$cdn) {
            if ($cdn['host'] === $host) {
                $cdn['unavailableUntil'] = $currentTime + $this->config['cdnUnavailableTime'];
                $changed = true;
                $this->logger->info("CDN помечен как недоступный: " . $host . " до " . date('Y-m-d H:i:s', $cdn['unavailableUntil']));
                break;
            }
        }
        
        if ($changed) {
            $this->saveCDNCache($cache);
        }
    }

    /**
     * Фильтрация доступных CDN площадок
     */
    private function filterAvailableCDN(array $cache): array
    {
        $currentTime = time();
        $available = [];
        
        foreach ($cache as $cdn) {
            if (!isset($cdn['unavailableUntil']) || $cdn['unavailableUntil'] <= $currentTime) {
                $available[] = $cdn;
            }
        }
        
        return $available;
    }

    /**
     * Сортировка CDN по задержке
     */
    private function sortCDNByLatency(array &$cdnList): void
    {
        usort($cdnList, function($a, $b) {
            return ($a['latency'] ?? 0) <=> ($b['latency'] ?? 0);
        });
    }

    /**
     * Проверка необходимости обновления кэша
     */
    private function needUpdateCache(array $cache): bool
    {
        if (empty($cache)) {
            return true;
        }
        
        if (count($cache) > 0 && isset($cache[0]['updatedAt'])) {
            $updateTime = $cache[0]['updatedAt'];
            $currentTime = time();
            $timeDiff = $currentTime - $updateTime;
            
            // Обновляем если прошло более 6 часов
            return $timeDiff > 6 * 3600;
        }
        
        return true;
    }

    /**
     * Создание директории для кэша
     */
    private function ensureCacheDirectory(): void
    {
        $dir = dirname($this->cdnCachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Проверка CDN площадок (публичный метод для API)
     * Аналог permitCheckCdn из CheckService
     */
    public function checkCdn(): array
    {
        $this->logger->info("Начинаем проверку CDN площадок");
        
        try {
            // Обновляем кэш CDN
            $updateResult = $this->updateCDNCache();
            
            if (!$updateResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Ошибка обновления кэша CDN: ' . $updateResult['message']
                ];
            }
            
            // Загружаем обновленный кэш
            $cdnCache = $this->loadCDNCache();
            
            // Фильтруем доступные CDN
            $availableCDN = $this->filterAvailableCDN($cdnCache);
            
            // Формируем результат
            $result = [
                'success' => true,
                'message' => 'CDN площадки проверены успешно',
                'data' => [
                    'total' => count($cdnCache),
                    'available' => count($availableCDN),
                    'cdnList' => $availableCDN
                ]
            ];
            
            $this->logger->info("CDN площадки проверены: всего " . count($cdnCache) . ", доступно " . count($availableCDN));
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error("Ошибка проверки CDN: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка проверки CDN: ' . $e->getMessage()
            ];
        }
    }
}
