<?php

require_once __DIR__ . '/../domain/service/SendToHonestSignUseCase.php';

class QueueController
{
    private SendToHonestSignUseCase $useCase;

    public function __construct(SendToHonestSignUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function handleStatus(): void
    {
        try {
            $status = $this->useCase->getQueueStatus();
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка получения статуса очереди: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    public function handleProcess(): void
    {
        try {
            $results = $this->useCase->processQueue();
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'Обработка очереди завершена',
                'data' => [
                    'processed_items' => count($results),
                    'results' => $results
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка обработки очереди: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
}