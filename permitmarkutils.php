<?php
//checkpermitmarkutils.php

require_once 'logger.php'; // Подключаем логгер

/**
 * Реализация проверки маркировки товаров через API Честного знака
 * с fallback на локальный модуль ЧЗ согласно ППРФ 1944
 * 
 */
class PermitMarkCheckGateway
{
    private string $apiKey;
    private int $timeout;
    private Logger $logger;
    public array $config;
    private string $cdnCachePath;

    public function __construct(string $apiKey, int $timeout, Logger $logger, array $config = [])
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->logger = $logger;
        
        // Конфигурация по умолчанию (аналог ПолучитьПараметрыПоУмолчанию )
        $this->config = array_merge([
            'onlineTimeout' => 1.5, // секунды
            'cdnUnavailableTime' => 900, // 15 минут в секундах
            'maxRetries' => 3,
            'cdnCachePath' => sys_get_temp_dir() . '/rr/cdns/cdn_cache.json',
            'permitMarkEnabled' => false,
            'clientId' => '',
            'lmHost' => 'http://127.0.0.1:5995',
            'lmAuth' => 'YWRtaW46YWRtaW4=', // admin:admin в base64
            'cdnBaseUrl' => 'https://cdn.crpt.ru',
            'sandboxUrl' => 'https://markirovka.sandbox.crptech.ru',
            'verifySSL' => true, // проверка SSL сертификатов
            'emulation' => false,
            'testLocalModule' => false,
            'testExpiredMarks' => false, // тестирование просроченных марок
            'asyncCDNHealthCheck' => true, // true = асинхронная (неделя), false = синхронная (месяц)
            'cdnCacheUpdateIntervalAsync' => 604800, // 1 неделя в секундах для асинхронной проверки
            'cdnCacheUpdateIntervalSync' => 2592000 // 1 месяц (30 дней) в секундах для синхронной проверки
        ], $config);
        
        $this->cdnCachePath = $this->config['cdnCachePath'];
        $this->ensureCacheDirectory();
    }

    public function getPermitMarkEnabled() {
        return $this->config['permitMarkEnabled'];
    }

    public function getTestLocalModule() {
        return $this->config['testLocalModule'];
    }

    public function getTestExpiredMarks() {
        return $this->config['testExpiredMarks'];
    }   

    /**
     * Основная функция проверки маркировки товара
     */
    public function checkPermit(string $code, array $context = []): array
    {
        $this->logger->info("Начинаем проверку маркировки: " . $code);
        
        // Логируем текущую конфигурацию testExpiredMarks
        $testExpiredMarks = isset($this->config['testExpiredMarks']) ? $this->config['testExpiredMarks'] : 'не установлено';
        $this->logger->info("Текущая конфигурация testExpiredMarks: " . ($testExpiredMarks === true ? 'true' : ($testExpiredMarks === false ? 'false' : $testExpiredMarks)));

        // Запускаем фоновое обновление кэша CDN (не блокирующее)
        $this->updateCDNCacheInBackground();

        // Сначала пробуем онлайн проверку
        if (!$this->getTestLocalModule()) {
            $onlineResult = $this->checkOnline($code, $context);
            $this->logger->info("Результат онлайн проверки1: " . json_encode($onlineResult));

            if ($onlineResult['success']) {
                $this->logger->info("Онлайн проверка была произведена");
                //$this->logger->debug("Результат онлайн проверки2: " . json_encode($onlineResult));
                $onlineResultFormatted = $this->processOnlineResult($onlineResult);
                //$this->logger->info("Результат онлайн проверки3: " . json_encode($onlineResult));
                //$this->logger->info("Результат онлайн проверки форматированный: " . json_encode($onlineResultFormatted));
                return $onlineResultFormatted;
            }    
            $this->logger->info("Онлайн проверка не удалась, переходим к офлайн проверке");
        } else {
            $this->logger->info("Прорускаем online проверку, переходим сразу в offline проверке: ");
        }        
                
        // Если онлайн проверка не удалась, переходим к офлайн
        $offlineResult = $this->checkOffline($code, $context);
        
        return $this->processOfflineResult($offlineResult);
    }

    /**
     * Онлайн проверка через CDN площадки
     */
    private function checkOnline(string $code, array $context = []): array
    {
        $this->logger->info("Начинаем онлайн проверку маркировки: " . $code);
        
        if ($this->config['emulation']) {
            $this->logger->info("Эмуляция разрешительного режима маркировки");
            return [
                'success' => true,
                'data' => ['success' => true, 'code' => 0, 'description' => 'Ok - эмуляция РР', 'codes' => [['errorCode' => 0, 'message' => 'Ok - эмуляция РР', 'found' => true, 'verified' => true, 'sold' => false, 'valid' => true, 'isBlocked' => false, 'expireDate' => null]], 'reqId' => '4dffd-fd-df-4334', 'reqTimestamp' => 1212434334],
                'message' => 'Маркировка проверена эмуляцией разрешительного режима маркировки',
                'timeout' => false,
                'checkedOffline' => false
            ];
        }

        // 1. Быстро загружаем кэш CDN без обновления
        $cdnCache = $this->loadCDNCache();
        
        // 2. Фильтруем доступные CDN из кэша
        $availableCDN = $this->filterAvailableCDN($cdnCache);
        
        // 3. Если кэш пуст или все CDN недоступны, делаем быструю проверку
        if (empty($availableCDN)) {
            $this->logger->info("CDN кэш пуст или все недоступны, делаем быструю проверку");
            $quickCDN = $this->getQuickCDNList();
            if (!empty($quickCDN)) {
                $availableCDN = $quickCDN;
            } else {
                $this->logger->warning("Не удалось получить быстрый список CDN, переходим к офлайн проверке");
                return ['success' => false, 'message' => 'CDN недоступны, переходим к офлайн проверке'];
            }
        }
        
        // 4. Сортируем CDN по задержке (если есть данные о задержке)
        $this->sortCDNByLatency($availableCDN);
        
        // 3. Формируем тело запроса
        $requestData = [
            'codes' => [$code]
        ];
        
        if (!empty($context['fiscalDriveNumber'])) {
            $requestData['fiscalDriveNumber'] = $context['fiscalDriveNumber'];
        }
        
        // 4. Обходим CDN площадки с быстрым переключением на офлайн
        $headers = $this->buildAPIHeaders();
        $networkErrorCount = 0;
        $maxNetworkErrors = 2; // Максимум 2 сетевые ошибки подряд
        
        foreach ($availableCDN as $cdn) {
            $host = $cdn['host'];
            $url = rtrim($host, '/') . '/api/v4/true-api/codes/check';
            
            $this->logger->info("Пробуем CDN: " . $host);
            
            $startTime = microtime(true);
            $result = $this->performJSONRequest($url, $headers, json_encode($requestData), 2);
            $endTime = microtime(true);
            
            $latency = $endTime - $startTime;
            
            // Проверяем на сетевые ошибки (быстрое переключение на офлайн)
            if (!$result['success']) {
                $isNetworkError = ($result['httpCode'] === 0 || 
                                 strpos($result['message'], 'cURL') !== false ||
                                 strpos($result['message'], 'timeout') !== false ||
                                 strpos($result['message'], 'connection') !== false);
                
                if ($isNetworkError) {
                    $networkErrorCount++;
                    $this->logger->warning("Сетевая ошибка #{$networkErrorCount} при обращении к CDN " . $host . ": " . $result['message']);
                    
                    // Если много сетевых ошибок подряд - быстро переключаемся на офлайн
                    if ($networkErrorCount >= $maxNetworkErrors) {
                        $this->logger->warning("Обнаружены множественные сетевые ошибки ({$networkErrorCount}), переключаемся на офлайн проверку");
                        return ['success' => false, 'message' => 'Сетевые проблемы, переходим к офлайн проверке'];
                    }
                } else {
                    // Сбрасываем счётчик при не сетевых ошибках
                    $networkErrorCount = 0;
                }
                
                $this->markCDNUnavailable($host);
                continue;
            }
            
            // Проверяем таймаут 1.5 сек согласно ППРФ 1944 п. 17
            if ($latency > 2 || ($result['timeout'] ?? false)) {
                $this->logger->warning("CDN таймаут более 1.5 сек: " . $host . ", задержка=" . $latency . "с");
                $this->markCDNUnavailable($host);
                continue;
            }
            
            // Обработка ошибок по HTTP кодам
            $httpCode = $result['httpCode'] ?? 0;
            
            // 203 - аварийная ситуация, переключаемся на офлайн на день
            if ($httpCode === 203) {
                $this->logger->error("АВАРИЙНАЯ СИТУАЦИЯ! CDN " . $host . " вернул HTTP 203 (Non-Authoritative Information). Переключаемся на офлайн режим на 24 часа.");
                
                // Помечаем ВСЕ CDN как недоступные на 24 часа (аварийная ситуация)
                $cdnCache = $this->loadCDNCache();
                $currentTime = time();
                foreach ($cdnCache as &$cdn) {
                    $cdn['unavailableUntil'] = $currentTime + 86400; // 24 часа
                    $this->logger->error("CDN " . $cdn['host'] . " помечен как недоступный на 24 часа из-за HTTP 203");
                }
                $this->saveCDNCache($cdnCache);
                
                return [
                    'success' => false,
                    'message' => 'Аварийная ситуация (HTTP 203), переходим к офлайн проверке'
                ];
            }
            
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
            'data' => array_merge($checkResult['data'], [
                'version' => $readinessResult['data']['version'] ?? '',
                'inst' => $readinessResult['data']['inst'] ?? ''
            ]),
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
                'message' => $result['data']['description'] ?? 'Ошибка online проверки',
                'errorCode' => $result['data']['code']
            ];
        }
        
        //if ($result['checkedOffline'] ?? false) {
        //foreach ($result['data']['codes'] ?? [] as $mark) {
        //    if ($mark['errorCode'] !== 0) {
        //        return [
        //            'success' => false,
        //            'message' => $mark['message'] ?? 'Ошибка online проверки',
        //            'errorCode' => $mark['errorCode'] ?? 0
        //        ];
        //    }
        //}
        //}
        $this->logger->info("Результат онлайн проверки: " . json_encode($result));
        $this->logger->info("Запрос проверки марки был успешно обработан");
        $this->logger->info("Codes: " . json_encode($result['data']['codes']));
        
        foreach ($result['data']['codes'] ?? [] as $mark) {
            $errorCode = $mark['errorCode'];
            $message = $mark['message'] ?? '';
            
            if ($errorCode !== 0) {
                return [
                    'success' => false,
                    'message' => $message,
                    'errorCode' => $errorCode
                ];
            }
            
            $this->logger->info("Маркировка mark успешно: " . json_encode($mark));
            // Проверяем дополнительные условия
            if ($mark['isBlocked'] ?? false) {
                $message = 'Марка заблокирована по решению органов государственной власти';
            }
            
            // Проверяем вариативные сроки годности для молочной продукции
            if (isset($mark['variableExpirations']) && is_array($mark['variableExpirations']) && !empty($mark['variableExpirations'])) {
                try {
                    // Используем UTC для текущего времени, чтобы корректно сравнивать с датами из API
                    $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
                    $expirationDates = [];
                    
                    // Собираем все даты истечения срока годности
                    foreach ($mark['variableExpirations'] as $key => $dateStr) {
                        if (!empty($dateStr)) {
                            try {
                                // Парсим дату с автоматическим определением часового пояса из строки
                                $date = new DateTime($dateStr);
                                // Конвертируем в UTC для корректного сравнения
                                $date->setTimezone(new DateTimeZone('UTC'));
                                $expirationDates[$key] = $date;
                            } catch (Exception $e) {
                                $this->logger->warning("Не удалось распарсить дату variableExpirations[{$key}]: {$dateStr} - " . $e->getMessage());
                            }
                        }
                    }
                    
                    if (!empty($expirationDates)) {
                        // Находим минимальную и максимальную даты
                        $minDate = min($expirationDates);
                        $maxDate = max($expirationDates);
                        
                        $this->logger->debug("Вариативные сроки годности (молочная продукция): минимальная дата=" . $minDate->format('Y-m-d H:i:s T') . 
                                           ", максимальная дата=" . $maxDate->format('Y-m-d H:i:s T') . 
                                           ", текущее время: " . $currentDateTime->format('Y-m-d H:i:s T'));
                        
                        // Проверяем минимальную дату (логируем, если истекла)
                        if ($minDate < $currentDateTime) {
                            $this->logger->warning("Минимальный срок годности истёк для марки (молочная продукция). " . 
                                                 "Минимальная дата: " . $minDate->format('Y-m-d H:i:s') . 
                                                 ", текущая дата: " . $currentDateTime->format('Y-m-d H:i:s'));
                        }
                        
                        // Проверяем максимальную дату (отклоняем товар, если истекла)
                        if ($maxDate < $currentDateTime) {
                            $message = 'У товара истёк срок годности (максимальная дата истекает: ' . $maxDate->format('d.m.Y H:i') . ')';
                            $this->logger->warning("Максимальный срок годности истёк для марки (молочная продукция). " . 
                                                 "Максимальная дата: " . $maxDate->format('Y-m-d H:i:s') . 
                                                 ", текущая дата: " . $currentDateTime->format('Y-m-d H:i:s'));
                        } else {
                            $this->logger->debug("Срок годности в порядке (молочная продукция). Максимальная дата истекает: " . $maxDate->format('d.m.Y H:i') . 
                                               " (осталось " . $currentDateTime->diff($maxDate)->days . " дней)");
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->warning("Ошибка обработки variableExpirations: " . $e->getMessage());
                }
            }
            
            // Если включен режим тестирования просроченных марок, устанавливаем просроченную дату
            $testExpiredMarks = isset($this->config['testExpiredMarks']) ? $this->config['testExpiredMarks'] : 'не установлено';
            $this->logger->info("Режим тестирования просроченных марок: " . ($testExpiredMarks === true ? 'true' : ($testExpiredMarks === false ? 'false' : $testExpiredMarks)));
            if ($this->config['testExpiredMarks']) {
                // Устанавливаем просроченную дату (вчера)
                $yesterday = new DateTime('yesterday', new DateTimeZone('UTC'));
                $mark['expireDate'] = $yesterday->format('Y-m-d\TH:i:s.000\Z');
                $this->logger->info("Режим тестирования просроченных марок: expireDate установлен на " . $mark['expireDate']);
            }
            
            // Проверяем обычный срок годности (формат: yyyy-MM-dd'T'HH:mm:ss.SSSz)
            if (isset($mark['expireDate']) && $mark['expireDate']) {
                try {
                    
                    // Парсим дату в формате ISO 8601 с автоматическим определением часового пояса
                    $expireDateTime = new DateTime($mark['expireDate']);
                    // Конвертируем в UTC для корректного сравнения
                    $expireDateTime->setTimezone(new DateTimeZone('UTC'));
                    // Используем UTC для текущего времени
                    $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
                    
                    $this->logger->debug("Проверка срока годности: expireDate=" . $mark['expireDate'] . 
                                       ", распарсено как: " . $expireDateTime->format('Y-m-d H:i:s T') . 
                                       ", текущее время: " . $currentDateTime->format('Y-m-d H:i:s T'));
                    
                    // Проверяем, истёк ли срок годности
                    if ($expireDateTime < $currentDateTime) {
                        $message = 'У товара истёк срок годности (истекает: ' . $expireDateTime->format('d.m.Y H:i') . ')';
                        $errorCode = 1; // Устанавливаем код ошибки для просроченной марки
                        $this->logger->warning("Срок годности истёк для марки. expireDate: " . $expireDateTime->format('Y-m-d H:i:s') . 
                                              ", текущая дата: " . $currentDateTime->format('Y-m-d H:i:s'));
                    } else {
                        $this->logger->debug("Срок годности в порядке. Истекает: " . $expireDateTime->format('d.m.Y H:i') . 
                                           " (осталось " . $currentDateTime->diff($expireDateTime)->days . " дней)");
                    }
                } catch (Exception $e) {
                    $this->logger->warning("Не удалось распарсить дату expireDate: " . $mark['expireDate'] . " - " . $e->getMessage());
                }
            }
            
            if ($mark['sold'] ?? false) {
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
            
            $this->logger->info("Маркировка markingCode успешно: " . json_encode($mark));

            return [
                'success' => true,
                'errorCode' => $errorCode,
                'message' => $message,
                'reqId' => $result['data']['reqId'] ?? '',
                'reqTimestamp' => $result['data']['reqTimestamp'] ?? '',
                'ver' => '',
                'inst' => ''
            ];
        }
        
        return $result;
    }

    /**
     * Обработка результата офлайн проверки
     */
    private function processOfflineResult(array $result): array
    {
        if (isset($result['data']['code']) && $result['data']['code'] !== 0) {
            return [
                'success' => false,
                'message' => $result['data']['description'] ?? 'Ошибка offline проверки',
                'errorCode' => $result['data']['code']
            ];
        }
        
        $this->logger->debug("Результат offline проверки: " . json_encode($result));
        $this->logger->debug("Запрос проверки марки был успешно обработан");
        //$this->logger->info("Codes: " . json_encode($result['data']['codes']));
        $message = 'Ok';
        foreach ($result['data']['codes'] ?? [] as $mark) {            
            $this->logger->debug("Маркировка mark успешно: " . json_encode($mark));
            // Проверяем дополнительные условия
            if ($mark['isBlocked'] ?? false) {
                $message = 'Марка заблокирована по решению органов государственной власти';
            }
                        
            $this->logger->debug("Маркировка markingCode успешно: " . json_encode($mark));

            return [
                'success' => true,
                'errorCode' => 0,
                'message' => $message,
                'reqId' => $result['data']['reqId'] ?? '',
                'reqTimestamp' => $result['data']['reqTimestamp'] ?? '',
                'ver' => $result['data']['version'],
                'inst' => $result['data']['inst']
            ];
        }
        
        return $result;
    }

    /**
     * Быстрое получение списка CDN без проверки здоровья
     */
    private function getQuickCDNList(): array
    {
        $baseUrl = $this->config['cdnBaseUrl'];
        $url = $baseUrl . '/api/v4/true-api/cdn/info';
        $headers = $this->buildAPIHeaders();
        
        $this->logger->info("Быстро запрашиваем список CDN площадок: " . $url);
        
        // Короткий таймаут для быстрой проверки
        $result = $this->performJSONRequest($url, $headers, '', 5);
        
        if (!$result['success']) {
            $this->logger->warning("Быстрая проверка CDN не удалась: " . $result['message']);
            return [];
        }
        
        if (!isset($result['data']['hosts'])) {
            $this->logger->warning("Некорректная структура ответа API при быстрой проверке");
            return [];
        }
        
        $hosts = $result['data']['hosts'];
        $this->logger->info("Быстро получено CDN площадок: " . count($hosts));
        
        // Возвращаем простой список без проверки здоровья
        $quickCDN = [];
        foreach ($hosts as $host) {
            $hostName = $host['host'] ?? $host;
            $quickCDN[] = [
                'host' => $hostName,
                'latency' => 0, // Неизвестна, будет определена при использовании
                'available' => true,
                'updatedAt' => time()
            ];
        }
        
        return $quickCDN;
    }

    /**
     * Получение списка CDN площадок через API Честного знака
     */
    private function getCDNList(): array
    {
        $baseUrl = $this->config['cdnBaseUrl'];
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

    public function getLocalModuleStatus(): array
    {
        return $this->checkLMReadiness();
    }


    public function permitLocalModuleInit(): array
    {
        $this->logger->info("Начинаем инициализацию локального модуля ЧЗ");
        
        // Проверяем наличие токена API
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Токен API не может быть пустым'
            ];
        }
        
        // Формируем URL для инициализации
        $url = rtrim($this->config['lmHost'], '/') . '/api/v1/init';
        
        // Формируем заголовки согласно документации
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $this->config['lmAuth']
        ];
        
        // Формируем тело запроса
        $requestData = [
            'token' => $this->apiKey
        ];
        
        $jsonBody = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        
        $this->logger->info("Инициализируем ЛМ ЧЗ: " . $url);
        $this->logger->info("Токен: " . $this->apiKey);
        
        // Выполняем запрос к ЛМ ЧЗ
        $result = $this->performJSONRequest($url, $headers, $jsonBody, 30, true);
        
        if (!$result['success']) {
            $this->logger->error("Ошибка запроса инициализации к ЛМ ЧЗ: " . $result['message']);
            return [
                'success' => false,
                'message' => 'Ошибка запроса инициализации к ЛМ ЧЗ: ' . $result['message']
            ];
        }
        
        // Для инициализации ЛМ ЧЗ HTTP код 200 не обязателен
        // Проверяем только на критические ошибки (4xx кроме 401, 5xx)
        $httpCode = $result['httpCode'] ?? 0;
        if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 401) {
            $this->logger->error("ЛМ ЧЗ вернул ошибку клиента: " . $httpCode);
            return [
                'success' => false,
                'message' => 'ЛМ ЧЗ вернул ошибку клиента: ' . $httpCode
            ];
        }
        
        if ($httpCode >= 500) {
            $this->logger->error("ЛМ ЧЗ вернул ошибку сервера: " . $httpCode);
            return [
                'success' => false,
                'message' => 'ЛМ ЧЗ вернул ошибку сервера: ' . $httpCode
            ];
        }
        
        // Инициализация успешна
        $this->logger->info("ЛМ ЧЗ в процессе инициализации");
        return [
            'success' => true,
            'message' => 'ЛМ ЧЗ в процессе инициализации'
        ];
    }

    /**
     * Проверка готовности локального модуля ЧЗ
     */
    private function checkLMReadiness(): array
    {
        $url = rtrim($this->config['lmHost'], '/') . '/api/v1/status';
        $headers = [
            'Authorization: Basic ' . $this->config['lmAuth'],
            'Content-Type: application/json'
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
        
        $this->logger->info("Результат проверки CIS в ЛМ ЧЗ: " . json_encode($result));

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

        if ($data['code'] !== 0) {
            return [
                'success' => false,
                'message' => 'Ошибка проверки CIS в ЛМ ЧЗ (код ' . $data['code'] . ')' . $data['description']
            ];
        }

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

        if (isset($checkResult['isGreyGtin'])) {
            if ($checkResult['isGreyGtin']) {
                $this->logger->info("CIS является серым GTIN");
            }
        }
        
        if ($checkResult['isBlocked']) {
            $description = $checkResult['description'] ?? '';
            return [
                'success' => false,
                'message' => 'Ошибка проверки CIS в ЛМ ЧЗ (код ' . $checkResult['isBlocked'] . ' заблокирован по решению органов государственной власти)' . $description
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
        
        // Служебный символ разделителя групп GS (ASCII код 29)
        $gsSeparator = chr(29);
        
        $pos = strpos($marking, $gsSeparator);
        
        if ($pos !== false) {
            // Возвращаем все символы до разделителя GS
            return substr($marking, 0, $pos);
        }
        
        // Если разделитель не найден, возвращаем исходную маркировку
        return $marking;
    }

    /**
     * Выполнение JSON запроса
     */
    private function performJSONRequest(string $url, array $headers, string $body = '', int $timeout = 30, bool $isLM = false): array
    {
        $ch = curl_init();
        $curlOptions = [
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
            CURLOPT_USERAGENT => 'CloudPosBridgePHP/1.0'
        ];
        
        // Для локального модуля отключаем SSL проверку
        if ($isLM) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        } else {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = $this->config['verifySSL'];
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = $this->config['verifySSL'] ? 2 : 0;
        }
        
        curl_setopt_array($ch, $curlOptions);
        
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
        
        // Для локального модуля не требуем строго HTTP 200
        if (!$isLM && $httpCode !== 200) {
            $this->logger->warning("HTTP код не 200: " . $httpCode . ", ответ: " . substr($response, 0, 500));
            return [
                'success' => false,
                'message' => "HTTP ошибка: {$httpCode}. Ответ: " . substr($response, 0, 200),
                'httpCode' => $httpCode,
                'response' => $response
            ];
        }
        
        // Для локального модуля логируем, но не считаем ошибкой
        if ($isLM && $httpCode !== 200) {
            $this->logger->info("ЛМ ЧЗ вернул HTTP код: " . $httpCode . ", ответ: " . substr($response, 0, 500));
        }
        
        // Для локального модуля может не быть JSON ответа
        if ($isLM) {
            return [
                'success' => true,
                'data' => $response ? json_decode($response, true) : null,
                'httpCode' => $httpCode,
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
     * Пометка CDN площадки как недоступной на длительный срок (для аварийных ситуаций)
     */
    private function markCDNUnavailableForLongTime(string $host, int $seconds): void
    {
        $cache = $this->loadCDNCache();
        if (empty($cache)) {
            return;
        }
        
        $currentTime = time();
        $changed = false;
        
        foreach ($cache as &$cdn) {
            if ($cdn['host'] === $host) {
                $cdn['unavailableUntil'] = $currentTime + $seconds;
                $changed = true;
                $untilDate = date('Y-m-d H:i:s', $cdn['unavailableUntil']);
                $hours = round($seconds / 3600, 1);
                $this->logger->error("CDN помечен как недоступный на {$hours} часов: " . $host . " до " . $untilDate);
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
            
            // Выбираем интервал обновления в зависимости от режима
            $updateInterval = $this->config['asyncCDNHealthCheck'] 
                ? $this->config['cdnCacheUpdateIntervalAsync']  // 1 неделя для асинхронного
                : $this->config['cdnCacheUpdateIntervalSync'];   // 1 месяц для синхронного
            
            $intervalDays = round($updateInterval / 86400);
            $this->logger->debug("Проверка необходимости обновления кэша CDN: прошло дней=" . round($timeDiff / 86400) . ", требуется=" . $intervalDays);
            
            return $timeDiff > $updateInterval;
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
     * Периодическое обновление кэша CDN в фоновом режиме (неблокирующее или синхронное)
     */
    public function updateCDNCacheInBackground(): void
    {
        // Проверяем, нужно ли обновлять кэш
        $cdnCache = $this->loadCDNCache();
        if (!$this->needUpdateCache($cdnCache)) {
            return;
        }
        
        // Выбираем режим обновления в зависимости от флага
        if ($this->config['asyncCDNHealthCheck']) {
            // АСИНХРОННЫЙ режим (неблокирующий, обновление раз в неделю)
            $this->logger->info("Запускаем неблокирующее обновление кэша CDN (асинхронный режим, раз в неделю)");
            
            // Простое решение: помечаем кэш как устаревший, но не обновляем сейчас
            $this->markCacheAsStale();
            $this->logger->info("Кэш CDN помечен как устаревший, будет обновлён при следующей проверке");
            
            // Альтернативно: запускаем обновление через планировщик задач Windows (как в установщике)
            // Раскомментируйте следующую строку, если хотите использовать планировщик задач:
            // $this->scheduleCDNUpdate();
        } else {
            // СИНХРОННЫЙ режим (блокирующий, обновление раз в месяц)
            $this->logger->info("Запускаем синхронное обновление кэша CDN (блокирующий режим, раз в месяц)");
            
            try {
                $updateResult = $this->updateCDNCache();
                if ($updateResult['success']) {
                    $this->logger->info("Синхронное обновление кэша CDN завершено успешно");
                } else {
                    $this->logger->warning("Ошибка синхронного обновления кэша CDN: " . $updateResult['message']);
                }
            } catch (Exception $e) {
                $this->logger->error("Исключение при синхронном обновлении кэша CDN: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Помечает кэш как устаревший (неблокирующий способ)
     */
    private function markCacheAsStale(): void
    {
        $cdnCache = $this->loadCDNCache();
        if (!empty($cdnCache)) {
            // Помечаем кэш как устаревший, уменьшив время обновления
            foreach ($cdnCache as &$cdn) {
                $cdn['updatedAt'] = time() - 3600; // Помечаем как устаревший час назад
            }
            $this->saveCDNCache($cdnCache);
        }
    }

    /**
     * Планирует обновление кэша CDN через планировщик задач Windows
     */
    private function scheduleCDNUpdate(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->logger->warning("Планировщик задач доступен только на Windows");
            return;
        }
        
        $scriptPath = __DIR__ . '/update_cdn_cache.php';
        $this->createUpdateScript($scriptPath);
        
        // Создаём задание в планировщике задач (как в установщике)
        $taskName = 'CloudPosBridgeCDNUpdate';
        $command = 'schtasks.exe /create /tn "' . $taskName . '" /tr "php \\"' . $scriptPath . '\\"" /sc ONCE /st ' . date('H:i:s', time() + 60) . ' /f';
        
        $this->logger->info("Создаём задание планировщика: " . $command);
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->logger->info("Задание планировщика создано успешно: " . implode(' ', $output));
        } else {
            $this->logger->warning("Ошибка создания задания планировщика (код: $returnCode): " . implode(' ', $output));
        }
    }

    /**
     * Создаёт скрипт для обновления кэша CDN
     */
    private function createUpdateScript(string $scriptPath): void
    {
        $scriptContent = '<?php
// Скрипт для обновления кэша CDN в фоновом режиме
// Работает в контексте Windows планировщика задач

// Определяем правильные пути для Windows
$appDir = dirname(__DIR__);
$logsDir = $appDir . "\\logs";

// Создаём директорию логов если не существует
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

require_once $appDir . "\\permitmarkutils.php";
require_once $appDir . "\\logger.php";

// Создаём минимальную конфигурацию
$config = [
    "permitMarkEnabled" => true,
    "lmHost" => "http://127.0.0.1:5995",
    "lmAuth" => "YWRtaW46YWRtaW4=",
    "verifySSL" => true,
    "emulation" => false,
    "testLocalModule" => false
];

$logger = Logger::getInstance($logsDir, 3, true);
$gateway = new PermitMarkCheckGateway("", 30, $logger, $config);

try {
    $logger->info("Запуск фонового обновления кэша CDN");
    $updateResult = $gateway->updateCDNCache();
    if ($updateResult["success"]) {
        $logger->info("Фоновое обновление кэша CDN завершено успешно");
    } else {
        $logger->warning("Ошибка фонового обновления кэша CDN: " . $updateResult["message"]);
    }
} catch (Exception $e) {
    $logger->error("Исключение при фоновом обновлении кэша CDN: " . $e->getMessage());
}

// Удаляем задание планировщика после выполнения
$taskName = "CloudPosBridgeCDNUpdate";
$deleteCommand = "schtasks.exe /delete /tn \"" . $taskName . "\" /f";
exec($deleteCommand);
$logger->info("Задание планировщика удалено после выполнения");
?>';
        
        file_put_contents($scriptPath, $scriptContent);
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
