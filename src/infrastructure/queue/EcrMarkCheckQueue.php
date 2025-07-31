<?php

require_once __DIR__ . '/../../domain/model/MarkingCode.php';
require_once __DIR__ . '/../../domain/model/OperationResult.php';

/**
 * Очередь для асинхронной проверки марки на ККТ
 * Поддерживает работу с taskId для получения результатов
 */
class EcrMarkCheckQueue
{
    private string $queuePath;
    private string $resultsPath;

    public function __construct(
        string $queuePath = 'logs/ecr_mark_queue.json',
        string $resultsPath = 'logs/ecr_mark_results.json'
    ) {
        $this->queuePath = $queuePath;
        $this->resultsPath = $resultsPath;
        $this->ensureFilesExist();
    }

    /**
     * Добавляет задачу в очередь и возвращает taskId
     */
    public function enqueueTask(MarkingCode $code, array $context = []): string
    {
        $taskId = uniqid('ecr_mark_', true);
        $queue = $this->loadQueue();
        
        $task = [
            'task_id' => $taskId,
            'code' => [
                'value' => $code->value,
                'inn' => $context['inn'] ?? '',
                'gtin' => $context['gtin'] ?? ''
            ],
            'operation' => 'ecr_check',
            'created_at' => date('Y-m-d H:i:s'),
            'attempts' => 0,
            'max_attempts' => 3,
            'status' => 'pending' // pending, processing, completed, failed
        ];

        $queue[] = $task;
        $this->saveQueue($queue);
        
        return $taskId;
    }

    /**
     * Получает результат проверки по taskId
     */
    public function getTaskResult(string $taskId): OperationResult
    {
        // Сначала проверим в очереди - возможно задача ещё выполняется
        $queue = $this->loadQueue();
        foreach ($queue as $task) {
            if ($task['task_id'] === $taskId) {
                if ($task['status'] === 'pending') {
                    return OperationResult::success([
                        'user_status' => [
                            'ok' => false,
                            'text' => 'Проверка марки в процессе выполнения'
                        ],
                        'machine_data' => [
                            'taskStatus' => 'pending',
                            'taskId' => $taskId
                        ]
                    ], 'Задача в очереди');
                } elseif ($task['status'] === 'processing') {
                    return OperationResult::success([
                        'user_status' => [
                            'ok' => false,
                            'text' => 'Проверка марки на ККТ в процессе выполнения'
                        ],
                        'machine_data' => [
                            'taskStatus' => 'processing',
                            'taskId' => $taskId
                        ]
                    ], 'Задача выполняется');
                } elseif ($task['status'] === 'failed') {
                    return OperationResult::failure(
                        'Проверка марки завершилась с ошибкой',
                        [
                            'user_status' => [
                                'ok' => false,
                                'text' => 'Марка некорректна или не прошла проверку ККТ'
                            ],
                            'machine_data' => [
                                'taskStatus' => 'failed',
                                'taskId' => $taskId
                            ]
                        ]
                    );
                }
            }
        }

        // Проверим в результатах
        $results = $this->loadResults();
        if (isset($results[$taskId])) {
            $result = $results[$taskId];
            
            if ($result['success']) {
                return OperationResult::success([
                    'user_status' => [
                        'ok' => true,
                        'text' => 'Марка корректна и прошла проверку на ККТ'
                    ],
                    'machine_data' => [
                        'taskStatus' => 'completed',
                        'taskId' => $taskId,
                        'itemInfoCheckResult' => [
                            'ecrStandAloneFlag' => $result['data']['ecrStandAloneFlag'] ?? false,
                            'imcCheckFlag' => $result['data']['imcCheckFlag'] ?? true,
                            'imcCheckResult' => $result['data']['imcCheckResult'] ?? true,
                            'imcEstimatedStatusCorrect' => $result['data']['imcEstimatedStatusCorrect'] ?? true,
                            'imcStatusInfo' => $result['data']['imcStatusInfo'] ?? true
                        ]
                    ]
                ], 'Марка корректна');
            } else {
                return OperationResult::failure(
                    $result['error'] ?? 'Ошибка проверки марки на ККТ',
                    [
                        'user_status' => [
                            'ok' => false,
                            'text' => 'Марка некорректна или не прошла проверку ККТ'
                        ],
                        'machine_data' => [
                            'taskStatus' => 'failed',
                            'taskId' => $taskId
                        ]
                    ]
                );
            }
        }

        // TaskId не найден
        return OperationResult::failure(
            'Задача с указанным ID не найдена',
            [
                'user_status' => [
                    'ok' => false,
                    'text' => 'Неизвестная задача проверки марки'
                ],
                'machine_data' => [
                    'taskStatus' => 'not_found',
                    'taskId' => $taskId
                ]
            ]
        );
    }

    /**
     * Получает задачи для обработки воркером
     */
    public function getPendingTasks(): array
    {
        $queue = $this->loadQueue();
        $pendingTasks = [];
        
        foreach ($queue as $task) {
            if ($task['status'] === 'pending' && $task['attempts'] < $task['max_attempts']) {
                $pendingTasks[] = $task;
            }
        }
        
        return $pendingTasks;
    }

    /**
     * Сохраняет результат обработки задачи
     */
    public function saveTaskResult(string $taskId, bool $success, ?array $data = null, ?string $error = null): void
    {
        // Обновляем статус в очереди
        $queue = $this->loadQueue();
        foreach ($queue as &$task) {
            if ($task['task_id'] === $taskId) {
                $task['status'] = $success ? 'completed' : 'failed';
                $task['processed_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        $this->saveQueue($queue);

        // Сохраняем результат
        $results = $this->loadResults();
        $results[$taskId] = [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'processed_at' => date('Y-m-d H:i:s')
        ];
        $this->saveResults($results);
    }

    /**
     * Получает статус очереди
     */
    public function getQueueStatus(): array
    {
        $queue = $this->loadQueue();
        $results = $this->loadResults();
        
        $stats = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($queue as $task) {
            $stats['total']++;
            $stats[$task['status']]++;
        }
        
        // Добавляем завершенные задачи из результатов, которых уже нет в очереди
        $completedFromResults = 0;
        foreach ($results as $taskId => $result) {
            $foundInQueue = false;
            foreach ($queue as $task) {
                if ($task['task_id'] === $taskId) {
                    $foundInQueue = true;
                    break;
                }
            }
            if (!$foundInQueue) {
                $completedFromResults++;
                if ($result['success']) {
                    $stats['completed']++;
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        $stats['total'] += $completedFromResults;
        
        return $stats;
    }

    private function ensureFilesExist(): void
    {
        if (!file_exists($this->queuePath)) {
            file_put_contents($this->queuePath, json_encode([]));
        }
        if (!file_exists($this->resultsPath)) {
            file_put_contents($this->resultsPath, json_encode([]));
        }
    }

    private function loadQueue(): array
    {
        $content = file_get_contents($this->queuePath);
        return json_decode($content, true) ?: [];
    }

    private function saveQueue(array $queue): void
    {
        file_put_contents($this->queuePath, json_encode($queue, JSON_PRETTY_PRINT));
    }

    private function loadResults(): array
    {
        $content = file_get_contents($this->resultsPath);
        return json_decode($content, true) ?: [];
    }

    private function saveResults(array $results): void
    {
        file_put_contents($this->resultsPath, json_encode($results, JSON_PRETTY_PRINT));
    }
}