<?php

namespace App\Api;

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../domain/service/EcrMarkCheckUseCase.php';
require_once __DIR__ . '/../infrastructure/honest_sign/QueueEcrMarkCheckGateway.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';

use App\Api\BaseController;
use App\Domain\Service\EcrMarkCheckUseCase;
// LoggerInterface в глобальном namespace

/**
 * Контроллер для асинхронной проверки марки на ККТ
 */
class EcrMarkCheckController extends BaseController
{
    private EcrMarkCheckUseCase $useCase;
    private string $action;

    public function __construct(EcrMarkCheckUseCase $useCase, \LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->useCase = $useCase;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    protected function validateRequest(array $request): array
    {
        if ($this->action === 'enqueue') {
            if (!isset($request['marking_code'])) {
                throw new ValidationException('Не указан код маркировки');
            }
            return $request;
        }

        if ($this->action === 'get_result') {
            if (!isset($request['task_id']) || empty($request['task_id'])) {
                throw new ValidationException('Не указан ID задачи');
            }
            return $request;
        }

        throw new ValidationException('Неизвестное действие');
    }

    protected function executeUseCase(array $request): array
    {
        if ($this->action === 'enqueue') {
            return $this->executeEnqueue($request);
        }

        if ($this->action === 'get_result') {
            return $this->executeGetResult($request);
        }

        throw new ValidationException('Неизвестное действие');
    }

    private function executeEnqueue(array $request): array
    {
        $markingCode = new MarkingCode($request['marking_code']);

        // Формируем контекст с дополнительными параметрами для ECR проверки
        $context = [];
        if (!empty($request['inn'])) {
            $context['inn'] = $request['inn'];
        }
        if (!empty($request['gtin'])) {
            $context['gtin'] = $request['gtin'];
        }

        $taskId = $this->useCase->enqueue($markingCode, $context);

        return [
            'task_id' => $taskId,
            'status' => [
                'ok' => true,
                'text' => 'Задача проверки марки поставлена в очередь'
            ],
            'machine_data' => [
                'taskId' => $taskId,
                'taskStatus' => 'enqueued'
            ]
        ];
    }

    private function executeGetResult(array $request): array
    {
        $result = $this->useCase->getResult($request['task_id']);

        if (!$result->success) {
            // Для асинхронных операций проверяем статус задачи
            $data = $result->getData();
            $taskStatus = $data['machine_data']['taskStatus'] ?? 'unknown';
            
            if (in_array($taskStatus, ['pending', 'processing'])) {
                // Задача ещё выполняется - это не ошибка, а нормальное состояние
                return [
                    'task_status' => $taskStatus,
                    'message' => 'Задача ещё выполняется',
                    'data' => $result->getData()
                ];
            } else {
                throw new BusinessLogicException($result->error ?: 'Ошибка выполнения задачи');
            }
        }

        return $result->getData();
    }

    /**
     * Постановка задачи проверки марки на ККТ в очередь
     * POST /api/ecr-mark-check/enqueue
     */
    public function enqueueMarkCheck(): void
    {
        $this->setAction('enqueue');
        $request = $this->getJsonInput();
        $this->handle($request);
    }

    /**
     * Получение результата проверки марки по taskId
     * GET /api/ecr-mark-check/result/{taskId}
     */
    public function getMarkCheckResult(string $taskId): void
    {
        $this->setAction('get_result');
        $request = ['task_id' => $taskId];
        $this->handle($request);
    }

    /**
     * Получает JSON данные из тела запроса
     */
    private function getJsonInput(): array
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        return $data ?? [];
    }


}