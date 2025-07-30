<?php

class HonestSignQueue
{
    private string $queuePath;

    public function __construct(string $queuePath = 'logs/honest_sign_queue.json')
    {
        $this->queuePath = $queuePath;
        $this->ensureQueueFileExists();
    }

    public function addToQueue(MarkingCode $code, string $operation = 'validate'): void
    {
        $queue = $this->loadQueue();
        
        $item = [
            'id' => uniqid(),
            'code' => $code,
            'operation' => $operation,
            'created_at' => date('Y-m-d H:i:s'),
            'attempts' => 0,
            'max_attempts' => 3,
            'status' => 'pending'
        ];

        $queue[] = $item;
        $this->saveQueue($queue);
    }

    public function processQueue(ValidateMarkGateway $gateway): array
    {
        $queue = $this->loadQueue();
        $results = [];

        foreach ($queue as $index => $item) {
            if ($item['status'] !== 'pending' || $item['attempts'] >= $item['max_attempts']) {
                continue;
            }

            try {
                $result = $gateway->validateMark($item['code']);
                
                if ($result->success) {
                    $queue[$index]['status'] = 'completed';
                    $results[] = ['success' => true, 'item_id' => $item['id']];
                } else {
                    $queue[$index]['attempts']++;
                    if ($queue[$index]['attempts'] >= $item['max_attempts']) {
                        $queue[$index]['status'] = 'failed';
                    }
                    $results[] = ['success' => false, 'item_id' => $item['id'], 'error' => $result->message];
                }
            } catch (Exception $e) {
                $queue[$index]['attempts']++;
                if ($queue[$index]['attempts'] >= $item['max_attempts']) {
                    $queue[$index]['status'] = 'failed';
                }
                $results[] = ['success' => false, 'item_id' => $item['id'], 'error' => $e->getMessage()];
            }
        }

        $this->saveQueue($queue);
        return $results;
    }

    public function getQueueStatus(): array
    {
        $queue = $this->loadQueue();
        
        $status = [
            'total' => count($queue),
            'pending' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        foreach ($queue as $item) {
            $status[$item['status']]++;
        }

        return $status;
    }

    public function clearCompleted(): int
    {
        $queue = $this->loadQueue();
        $originalCount = count($queue);
        
        $queue = array_filter($queue, function($item) {
            return $item['status'] !== 'completed';
        });

        $this->saveQueue(array_values($queue));
        return $originalCount - count($queue);
    }

    private function loadQueue(): array
    {
        if (!file_exists($this->queuePath)) {
            return [];
        }

        $content = file_get_contents($this->queuePath);
        if ($content === false) {
            return [];
        }

        $queue = json_decode($content, true);
        return $queue ?? [];
    }

    private function saveQueue(array $queue): void
    {
        $dir = dirname($this->queuePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->queuePath, $json);
    }

    private function ensureQueueFileExists(): void
    {
        if (!file_exists($this->queuePath)) {
            $this->saveQueue([]);
        }
    }
}