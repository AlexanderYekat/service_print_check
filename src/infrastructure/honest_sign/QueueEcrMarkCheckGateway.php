<?php

namespace App\Infrastructure\HonestSign;

use App\Interface\EcrMarkCheckGateway;
use App\Domain\Model\OperationResult;
use App\Domain\Model\MarkingCode;
use App\Infrastructure\Queue\EcrMarkCheckQueue;

/**
 * Реализация асинхронной проверки марки на ККТ через очередь
 */
class QueueEcrMarkCheckGateway implements EcrMarkCheckGateway
{
    private EcrMarkCheckQueue $queue;

    public function __construct(?EcrMarkCheckQueue $queue = null)
    {
        $this->queue = $queue ?? new EcrMarkCheckQueue();
    }

    /**
     * Ставит задачу проверки марки на ККТ в очередь
     */
    public function enqueueMarkCheck(MarkingCode $code, array $context = []): string
    {
        return $this->queue->enqueueTask($code, $context);
    }

    /**
     * Получает результат проверки по taskId
     */
    public function getMarkCheckResult(string $taskId): OperationResult
    {
        return $this->queue->getTaskResult($taskId);
    }

    /**
     * Получает очередь для доступа к дополнительным методам
     */
    public function getQueue(): EcrMarkCheckQueue
    {
        return $this->queue;
    }
}