<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../infrastructure/queue/EcrMarkCheckWorker.php';
require_once __DIR__ . '/../infrastructure/queue/EcrMarkCheckQueue.php';

class QueueController extends BaseController
{
    private EcrMarkCheckWorker $worker;
    private EcrMarkCheckQueue $queue;
    private string $action;

    public function __construct(EcrMarkCheckWorker $worker, EcrMarkCheckQueue $queue, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->worker = $worker;
        $this->queue = $queue;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    protected function validateRequest(array $request): array
    {
        // Для операций с очередью валидация не требуется
        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        if ($this->action === 'status') {
            return $this->getQueueStatus();
        }

        if ($this->action === 'process') {
            return $this->processQueue();
        }

        throw new ValidationException('Неизвестное действие');
    }

    private function getQueueStatus(): array
    {
        $status = $this->queue->getQueueStatus();
        return [
            'queue_status' => $status,
            'message' => 'Статус очереди получен'
        ];
    }

    private function processQueue(): array
    {
        $results = $this->worker->processQueue();
        return [
            'processed_items' => count($results),
            'results' => $results,
            'message' => 'Обработка очереди завершена'
        ];
    }

    public function handleStatus(): void
    {
        $this->setAction('status');
        $this->handle([]);
    }

    public function handleProcess(): void
    {
        $this->setAction('process');
        $this->handle([]);
    }
}