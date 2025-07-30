<?php

require_once __DIR__ . '/../infrastructure/monitoring/HealthChecker.php';

class HealthController
{
    private HealthChecker $healthChecker;

    public function __construct(HealthChecker $healthChecker)
    {
        $this->healthChecker = $healthChecker;
    }

    public function handle(): void
    {
        try {
            $healthReport = $this->healthChecker->checkAll();
            
            $httpStatus = $this->getHttpStatusByHealth($healthReport['overall_status']);
            
            http_response_code($httpStatus);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode($healthReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'overall_status' => 'error',
                'message' => 'Ошибка проверки состояния: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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