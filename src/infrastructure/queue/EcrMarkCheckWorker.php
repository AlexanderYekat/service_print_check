<?php

require_once __DIR__ . '/EcrMarkCheckQueue.php';
// HttpHonestSignGateway больше не нужен - EcrMarkCheckWorker работает напрямую с API
require_once __DIR__ . '/../../domain/model/MarkingCode.php';

/**
 * Воркер для обработки асинхронных задач проверки марки на ККТ
 */
class EcrMarkCheckWorker
{
    private EcrMarkCheckQueue $queue;
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(
        EcrMarkCheckQueue $queue,
        string $apiUrl,
        string $apiKey,
        int $timeout = 30
    ) {
        $this->queue = $queue;
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Обрабатывает задачи из очереди
     */
    public function processQueue(): array
    {
        $pendingTasks = $this->queue->getPendingTasks();
        $results = [];

        foreach ($pendingTasks as $task) {
            $taskId = $task['task_id'];
            
            try {
                echo "Обработка задачи {$taskId}...\n";
                
                // Восстанавливаем MarkingCode из данных задачи
                $codeData = $task['code'];
                $markingCode = new MarkingCode($codeData['value']);

                // Формируем контекст с дополнительными данными
                $context = [
                    'inn' => $codeData['inn'] ?? '',
                    'gtin' => $codeData['gtin'] ?? ''
                ];

                // Выполняем проверку марки на ККТ
                $checkResult = $this->performEcrMarkCheck($markingCode, $context);
                
                if ($checkResult['success']) {
                    $this->queue->saveTaskResult($taskId, true, $checkResult['data']);
                    $results[] = [
                        'task_id' => $taskId,
                        'status' => 'success',
                        'message' => 'Проверка марки на ККТ успешно выполнена'
                    ];
                    echo "Задача {$taskId} выполнена успешно\n";
                } else {
                    $this->queue->saveTaskResult($taskId, false, null, $checkResult['error']);
                    $results[] = [
                        'task_id' => $taskId,
                        'status' => 'failed',
                        'error' => $checkResult['error']
                    ];
                    echo "Задача {$taskId} завершилась с ошибкой: {$checkResult['error']}\n";
                }

            } catch (Exception $e) {
                $this->queue->saveTaskResult($taskId, false, null, $e->getMessage());
                $results[] = [
                    'task_id' => $taskId,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                echo "Ошибка при обработке задачи {$taskId}: {$e->getMessage()}\n";
            }
        }

        return $results;
    }

    /**
     * Выполняет проверку марки на ККТ через API
     */
    private function performEcrMarkCheck(MarkingCode $code, array $context = []): array
    {
        $postData = [
            'marking_code' => $code->value,
            'mode' => 'ecr',
            'inn' => $context['inn'] ?? '',
            'gtin' => $context['gtin'] ?? ''
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl . '/ecr-check',
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
            return [
                'success' => false,
                'error' => "Ошибка cURL: {$error}"
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => "HTTP ошибка: {$httpCode}"
            ];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => "Некорректный JSON ответ: " . json_last_error_msg()
            ];
        }

        if ($data['success'] ?? false) {
            return [
                'success' => true,
                'data' => [
                    'ecrStandAloneFlag' => $data['details']['ecrStandAloneFlag'] ?? false,
                    'imcCheckFlag' => $data['details']['imcCheckFlag'] ?? true,
                    'imcCheckResult' => $data['details']['imcCheckResult'] ?? true,
                    'imcEstimatedStatusCorrect' => $data['details']['imcEstimatedStatusCorrect'] ?? true,
                    'imcStatusInfo' => $data['details']['imcStatusInfo'] ?? true
                ]
            ];
        } else {
            return [
                'success' => false,
                'error' => $data['message'] ?? 'Марка не прошла проверку на ККТ'
            ];
        }
    }
}