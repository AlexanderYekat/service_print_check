<?php

require_once __DIR__ . '/../domain/service/EcrMarkCheckUseCase.php';
require_once __DIR__ . '/../infrastructure/honest_sign/QueueEcrMarkCheckGateway.php';
require_once __DIR__ . '/../domain/model/MarkingCode.php';
require_once __DIR__ . '/../infrastructure/logger/FileLogger.php';

/**
 * Контроллер для асинхронной проверки марки на ККТ
 */
class EcrMarkCheckController
{
    private EcrMarkCheckUseCase $useCase;
    private FileLogger $logger;

    public function __construct()
    {
        $this->logger = new FileLogger('logs/api.log');
        
        // Инициализируем зависимости
        $gateway = new QueueEcrMarkCheckGateway();
        $this->useCase = new EcrMarkCheckUseCase($gateway);
    }

    /**
     * Постановка задачи проверки марки на ККТ в очередь
     * POST /api/ecr-mark-check/enqueue
     */
    public function enqueueMarkCheck(): void
    {
        try {
            $request = $this->getJsonInput();
            
            if (!isset($request['marking_code'])) {
                $this->sendError('Не указан код маркировки', 400);
                return;
            }

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

            $this->sendSuccess([
                'task_id' => $taskId,
                'user_status' => [
                    'ok' => true,
                    'text' => 'Задача проверки марки поставлена в очередь'
                ],
                'machine_data' => [
                    'taskId' => $taskId,
                    'taskStatus' => 'enqueued'
                ]
            ], 'Задача поставлена в очередь');

        } catch (Exception $e) {
            $this->logger->error("Ошибка постановки задачи проверки марки в очередь: " . $e->getMessage());
            $this->sendError('Внутренняя ошибка сервера', 500);
        }
    }

    /**
     * Получение результата проверки марки по taskId
     * GET /api/ecr-mark-check/result/{taskId}
     */
    public function getMarkCheckResult(string $taskId): void
    {
        try {
            if (empty($taskId)) {
                $this->sendError('Не указан ID задачи', 400);
                return;
            }

            $result = $this->useCase->getResult($taskId);

            if ($result->success) {
                $this->sendSuccess($result->getData(), $result->message);
            } else {
                // Для асинхронных операций, ошибка может означать что задача ещё не готова
                // Возвращаем код 202 (Accepted) для pending статусов
                $data = $result->getData();
                $taskStatus = $data['machine_data']['taskStatus'] ?? 'unknown';
                
                if (in_array($taskStatus, ['pending', 'processing'])) {
                    http_response_code(202); // Accepted - задача ещё выполняется
                    $this->sendSuccess($result->getData(), 'Задача ещё выполняется');
                } else {
                    $this->sendError($result->error, 400, $result->getData());
                }
            }

        } catch (Exception $e) {
            $this->logger->error("Ошибка получения результата проверки марки: " . $e->getMessage());
            $this->sendError('Внутренняя ошибка сервера', 500);
        }
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

    /**
     * Отправляет успешный ответ
     */
    private function sendSuccess(array $data, ?string $message = null): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->sendJsonResponse($response, 200);
    }

    /**
     * Отправляет ответ с ошибкой
     */
    private function sendError(string $error, int $statusCode = 500, ?array $data = null): void
    {
        $response = [
            'success' => false,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->sendJsonResponse($response, $statusCode);
    }

    /**
     * Отправляет JSON ответ
     */
    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}