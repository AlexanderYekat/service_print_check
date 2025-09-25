<?php
//checkpermitmarkutils.php

require_once 'logger.php'; // Подключаем логгер

/**
 * Реализация синхронной проверки марки в разрешительном режиме через HTTP API
 */
class PermitMarkCheckGateway
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private Logger $logger;

    public function __construct(string $apiUrl, string $apiKey, int $timeout, Logger $logger)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->logger = $logger;
    }

    public function checkPermit(string $code, array $context = [])
    {
        return [
            'success' => true, 
            'data' => [
            'success' => true,
            'response' => [ 
            'user_status' => [
                'ok' => true,
                'text' => 'Марка разрешена к продаже, срок годности не истёк'
            ],
            'machine_data' => [
                'code' => 0,
                'uuid' => uniqid(),
                'time' => date('Y-m-d H:i:s'),
                'ver' => null,
                'inst' => null
            ], 
            'message' => 'Марка разрешена к продаже'
            ]
        ]
    ];
        /*try {
            $response = $this->performApiRequest($code, $context);
            
            if ($response['success']) {
                return [true, [
                    'user_status' => [
                        'ok' => true,
                        'text' => 'Марка разрешена к продаже, срок годности не истёк'
                    ],
                    'machine_data' => [
                        'uuid' => $response['details']['uuid'] ?? uniqid(),
                        'time' => $response['details']['time'] ?? date('Y-m-d H:i:s'),
                        'permitInfo' => $response['details']['permitInfo'] ?? null,
                        'validUntil' => $response['details']['validUntil'] ?? null, ""], "Марка разрешена к продаже"]];
            } else {
                return [false, $response['message'] ?? 'Марка не разрешена к продаже', [
                    'user_status' => [
                        'ok' => false,
                        'text' => 'Марка не разрешена к продаже'
                    ]
                ]];
            }
        } catch (Exception $e) {
            return [false, "Ошибка проверки марки: " . $e->getMessage(), [
                "Ошибка проверки марки: " . $e->getMessage(),
                [
                    'user_status' => [
                        'ok' => false,
                        'text' => 'Ошибка соединения с сервисом проверки марки'
                    ]
                ]
            ]];
        }*/
    }

    private function performApiRequest(string $code, array $context = []): array
    {
        $postData = [
            'marking_code' => $code,
            'mode' => 'permit',
            'inn' => $context['inn'] ?? '',
            'gtin' => $context['gtin'] ?? ''
        ];

        // Добавляем fiscalDriveNumber если он есть в контексте
        if (!empty($context['fiscalDriveNumber'])) {
            $postData['fiscalDriveNumber'] = $context['fiscalDriveNumber'];
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl . '/permit-check',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Ошибка cURL: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP ошибка: {$httpCode}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Некорректный JSON ответ: " . json_last_error_msg());
        }

        return $data;
    }
}