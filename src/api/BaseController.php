<?php

require_once __DIR__ . '/../infrastructure/logger/LoggerInterface.php';

/**
 * Базовый контроллер для всех API контроллеров
 * Обеспечивает единообразную обработку запросов, ошибок и логирования
 */
abstract class BaseController
{
    protected LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Основной метод обработки запроса с единообразной структурой
     */
    public function handle(array $request = []): void
    {
        $startTime = microtime(true);
        
        try {
            // Валидация входных данных
            $validatedRequest = $this->validateRequest($request);
            
            // Логирование запроса
            $this->logRequest($validatedRequest);
            
            // Выполнение бизнес-логики
            $result = $this->executeUseCase($validatedRequest);
            
            // Отправка успешного ответа
            $this->sendSuccessResponse($result, $startTime);
            
        } catch (ValidationException $e) {
            $this->sendErrorResponse($e->getMessage(), 400, $startTime);
        } catch (BusinessLogicException $e) {
            $this->sendErrorResponse($e->getMessage(), 422, $startTime);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error in controller', [
                'controller' => get_class($this),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendErrorResponse('Внутренняя ошибка сервера', 500, $startTime);
        }
    }

    /**
     * Валидация входящего запроса (должна быть переопределена в наследниках)
     */
    protected function validateRequest(array $request): array
    {
        return $request; // По умолчанию возвращаем как есть
    }

    /**
     * Выполнение бизнес-логики (должно быть переопределено в наследниках)
     */
    abstract protected function executeUseCase(array $request): array;

    /**
     * Логирование входящего запроса
     */
    protected function logRequest(array $request): void
    {
        $this->logger->info('API request received', [
            'controller' => get_class($this),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
            'request_data_size' => strlen(json_encode($request))
        ]);
    }

    /**
     * Отправка успешного ответа
     */
    protected function sendSuccessResponse(array $data, float $startTime): void
    {
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'response_time_ms' => $responseTime,
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0'
            ]
        ];

        $this->sendJsonResponse($response, 200);
        
        $this->logger->info('API response sent', [
            'controller' => get_class($this),
            'response_time_ms' => $responseTime,
            'success' => true
        ]);
    }

    /**
     * Отправка ответа с ошибкой
     */
    protected function sendErrorResponse(string $message, int $statusCode = 500, ?float $startTime = null): void
    {
        $responseTime = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : null;
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode
            ],
            'meta' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0.0'
            ]
        ];

        if ($responseTime !== null) {
            $response['meta']['response_time_ms'] = $responseTime;
        }

        $this->sendJsonResponse($response, $statusCode);
        
        $this->logger->warning('API error response sent', [
            'controller' => get_class($this),
            'error_message' => $message,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTime
        ]);
    }

    /**
     * Отправка JSON ответа с правильными заголовками
     */
    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * Исключение для ошибок валидации
 */
class ValidationException extends Exception {}

/**
 * Исключение для ошибок бизнес-логики
 */
class BusinessLogicException extends Exception {}