<?php

require_once __DIR__ . '/../../interface/PrinterInterface.php';
require_once __DIR__ . '/../../interface/BankTerminalInterface.php';
require_once __DIR__ . '/../../interface/ValidateMarkGateway.php';
require_once __DIR__ . '/../../interface/ScaleInterface.php';

class HealthChecker
{
    private array $services = [];
    private array $results = [];

    public function addService(string $name, $service, string $type): void
    {
        $this->services[$name] = [
            'service' => $service,
            'type' => $type
        ];
    }

    public function checkAll(): array
    {
        $this->results = [];
        
        foreach ($this->services as $name => $config) {
            $this->results[$name] = $this->checkService($name, $config['service'], $config['type']);
        }

        return [
            'overall_status' => $this->getOverallStatus(),
            'timestamp' => date('Y-m-d H:i:s'),
            'services' => $this->results
        ];
    }

    public function checkService(string $name, $service, string $type): array
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->performHealthCheck($service, $type);
            $responseTime = (microtime(true) - $startTime) * 1000; // в мс
            
            return [
                'status' => $result['success'] ? 'healthy' : 'unhealthy',
                'message' => $result['message'] ?? 'OK',
                'response_time_ms' => round($responseTime, 2),
                'last_check' => date('Y-m-d H:i:s'),
                'details' => $result['details'] ?? null
            ];
        } catch (Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'response_time_ms' => round($responseTime, 2),
                'last_check' => date('Y-m-d H:i:s'),
                'details' => null
            ];
        }
    }

    private function performHealthCheck($service, string $type): array
    {
        switch ($type) {
            case 'printer':
                return $this->checkPrinter($service);
            
            case 'bank':
                return $this->checkBank($service);
            
            case 'honest_sign':
                return $this->checkHonestSign($service);
            
            case 'scale':
                return $this->checkScale($service);
            
            default:
                throw new Exception("Неизвестный тип сервиса: {$type}");
        }
    }

    private function checkPrinter(PrinterInterface $printer): array
    {
        if ($printer instanceof SerialKktAdapter) {
            // Для Serial принтера проверим эмуляцию
            $testCheck = new Check(
                [['name' => 'TEST', 'price' => 1.0, 'quantity' => 1]],
                'TEST_CASHIER',
                [['type' => 'cash', 'amount' => 1.0]],
                'sell',
                'osn'
            );
            
            $result = $printer->printCheck($testCheck);
            return [
                'success' => $result->success,
                'message' => $result->message ?? 'Принтер работает'
            ];
        }
        
        return ['success' => true, 'message' => 'Принтер доступен'];
    }

    private function checkBank(BankTerminalInterface $bank): array
    {
        if ($bank instanceof GoBankTerminalAdapter) {
            // Для банка сделаем тестовую операцию с минимальной суммой
            try {
                $result = $bank->pay(0.01);
                return [
                    'success' => true,
                    'message' => 'Банковский терминал доступен',
                    'details' => ['test_operation_result' => $result->success]
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Банковский терминал недоступен: ' . $e->getMessage()
                ];
            }
        }
        
        return ['success' => true, 'message' => 'Банковский терминал доступен'];
    }

    private function checkHonestSign(ValidateMarkGateway $gateway): array
    {
        if ($gateway instanceof HttpHonestSignGateway) {
            $queueStatus = $gateway->getQueueStatus();
            
            return [
                'success' => true,
                'message' => 'Сервис Честного Знака доступен',
                'details' => [
                    'queue_status' => $queueStatus,
                    'pending_items' => $queueStatus['pending']
                ]
            ];
        }
        
        return ['success' => true, 'message' => 'Сервис Честного Знака доступен'];
    }

    private function checkScale(ScaleInterface $scale): array
    {
        if ($scale instanceof SerialScaleAdapter) {
            $result = $scale->getWeight();
            return [
                'success' => $result->success,
                'message' => $result->message ?? 'Весы работают',
                'details' => $result->success ? ['current_weight' => $result->weight] : null
            ];
        }
        
        return ['success' => true, 'message' => 'Весы доступны'];
    }

    private function getOverallStatus(): string
    {
        if (empty($this->results)) {
            return 'unknown';
        }

        $hasError = false;
        $hasUnhealthy = false;

        foreach ($this->results as $result) {
            if ($result['status'] === 'error') {
                $hasError = true;
            } elseif ($result['status'] === 'unhealthy') {
                $hasUnhealthy = true;
            }
        }

        if ($hasError) {
            return 'critical';
        } elseif ($hasUnhealthy) {
            return 'degraded';
        }

        return 'healthy';
    }

    public function saveHealthReport(string $filePath): void
    {
        $report = $this->checkAll();
        
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filePath, $json);
    }
}