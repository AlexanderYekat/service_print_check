<?php

require_once __DIR__ . '/../../interface/ValidateMarkGateway.php';
require_once __DIR__ . '/../../domain/model/HonestSignResult.php';
require_once __DIR__ . '/../queue/HonestSignQueue.php';

class HttpHonestSignGateway implements ValidateMarkGateway
{
    private string $apiUrl;
    private string $apiKey;
    private bool $useQueue;
    private HonestSignQueue $queue;
    private int $timeout;

    public function __construct(
        string $apiUrl, 
        string $apiKey, 
        bool $useQueue = true, 
        int $timeout = 30,
        ?HonestSignQueue $queue = null
    ) {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->useQueue = $useQueue;
        $this->timeout = $timeout;
        $this->queue = $queue ?? new HonestSignQueue();
    }

    public function validateMark(MarkingCode $code): HonestSignResult
    {
        try {
            return $this->performDirectValidation($code);
        } catch (Exception $e) {
            if ($this->useQueue) {
                $this->queue->addToQueue($code, 'validate');
                return new HonestSignResult(
                    false, 
                    "API недоступен, код добавлен в очередь: " . $e->getMessage()
                );
            }
            
            return new HonestSignResult(false, "Ошибка валидации: " . $e->getMessage());
        }
    }

    public function processQueuedItems(): array
    {
        return $this->queue->processQueue($this);
    }

    public function getQueueStatus(): array
    {
        return $this->queue->getQueueStatus();
    }

    private function performDirectValidation(MarkingCode $code): HonestSignResult
    {
        $postData = [
            'marking_code' => $code->value,
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
            CURLOPT_URL => $this->apiUrl . '/validate',
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

        return new HonestSignResult(
            $data['success'] ?? false,
            $data['message'] ?? null,
            $data['details'] ?? null
        );
    }
}