<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../infrastructure/monitoring/HealthChecker.php';

class HealthController extends BaseController
{
    private HealthChecker $healthChecker;

    public function __construct(HealthChecker $healthChecker, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->healthChecker = $healthChecker;
    }

    protected function validateRequest(array $request): array
    {
        // Для health check валидация не требуется
        return $request;
    }

    protected function executeUseCase(array $request): array
    {
        return $this->healthChecker->checkAll();
    }

    /**
     * Переопределяем handle для кастомной обработки HTTP статусов
     */
    public function handle(array $request = []): void
    {
        try {
            $healthReport = $this->healthChecker->checkAll();
            $httpStatus = $this->getHttpStatusByHealth($healthReport['overall_status']);
            
            // Отправляем ответ с правильным статусом
            http_response_code($httpStatus);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($healthReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Ошибка проверки состояния: ' . $e->getMessage(), 500);
        }
    }

    private function getHttpStatusByHealth(string $status): int
    {
        switch ($status) {
            case 'healthy':
                return 200;
            case 'degraded':
                return 200; // Система работает, но есть проблемы
            case 'critical':
                return 503; // Service Unavailable
            default:
                return 500; // Internal Server Error
        }
    }
}