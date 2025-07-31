<?php

require_once __DIR__ . '/../../interface/PermitMarkCheckGateway.php';
require_once __DIR__ . '/../../domain/model/OperationResult.php';
require_once __DIR__ . '/../../domain/model/MarkingCode.php';

/**
 * Реализация синхронной проверки марки в разрешительном режиме через HTTP API
 */
class HttpPermitMarkCheckGateway implements PermitMarkCheckGateway
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(string $apiUrl, string $apiKey, int $timeout = 30)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Синхронная проверка марки в разрешительном режиме
     */
    public function checkPermit(MarkingCode $code): OperationResult
    {
        try {
            $response = $this->performApiRequest($code);
            
            if ($response['success']) {
                return OperationResult::success([
                    'user_status' => [
                        'ok' => true,
                        'text' => 'Марка разрешена к продаже, срок годности не истёк'
                    ],
                    'machine_data' => [
                        'uuid' => $response['details']['uuid'] ?? uniqid(),
                        'time' => $response['details']['time'] ?? date('Y-m-d H:i:s'),
                        'permitInfo' => $response['details']['permitInfo'] ?? null,
                        'validUntil' => $response['details']['validUntil'] ?? null
                    ]
                ], 'Марка разрешена к продаже');
            } else {
                return OperationResult::failure(
                    $response['message'] ?? 'Марка не разрешена к продаже',
                    [
                        'user_status' => [
                            'ok' => false,
                            'text' => 'Марка не разрешена к продаже или истёк срок годности'
                        ]
                    ]
                );
            }
        } catch (Exception $e) {
            return OperationResult::failure(
                "Ошибка проверки марки: " . $e->getMessage(),
                [
                    'user_status' => [
                        'ok' => false,
                        'text' => 'Ошибка соединения с сервисом проверки марки'
                    ]
                ]
            );
        }
    }

    /**
     * Выполняет HTTP-запрос к API
     */
    private function performApiRequest(MarkingCode $code): array
    {
        $postData = [
            'marking_code' => $code->value,
            'mode' => 'permit',
            'inn' => $code->inn ?? '',
            'gtin' => $code->gtin ?? ''
        ];

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