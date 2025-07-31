<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../../interface/ValidateMarkGateway.php';

class SendToHonestSignUseCase
{
    private ValidateMarkGateway $gateway;

    public function __construct(ValidateMarkGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Отправка марки в Честный ЗНАК для проверки
     */
    public function execute(MarkingCode $code, string $mode = 'permit'): OperationResult
    {
        $honestSignResult = $this->gateway->validateMark($code);
        
        return $this->convertToStructuredResult($honestSignResult, $mode);
    }

    /**
     * Обработка очереди отложенных запросов
     */
    public function processQueue(): OperationResult
    {
        if (method_exists($this->gateway, 'processQueuedItems')) {
            try {
                $results = $this->gateway->processQueuedItems();
                
                return OperationResult::success(
                    [
                        'processed_items' => $results,
                        'total_processed' => count($results)
                    ],
                    'Очередь обработана успешно'
                );
            } catch (Exception $e) {
                return OperationResult::failure(
                    'Ошибка обработки очереди: ' . $e->getMessage()
                );
            }
        }
        
        return OperationResult::success(
            ['total_processed' => 0],
            'Очередь пуста или не поддерживается'
        );
    }

    /**
     * Получение статуса очереди
     */
    public function getQueueStatus(): OperationResult
    {
        if (method_exists($this->gateway, 'getQueueStatus')) {
            try {
                $status = $this->gateway->getQueueStatus();
                
                return OperationResult::success(
                    $status,
                    'Статус очереди получен'
                );
            } catch (Exception $e) {
                return OperationResult::failure(
                    'Ошибка получения статуса очереди: ' . $e->getMessage()
                );
            }
        }
        
        return OperationResult::success(
            ['total' => 0, 'pending' => 0, 'completed' => 0, 'failed' => 0],
            'Очередь не поддерживается'
        );
    }
    
    /**
     * Преобразует результат проверки в структурированный OperationResult
     */
    private function convertToStructuredResult($honestSignResult, string $mode): OperationResult {
        if ($honestSignResult->success) {
            // Успешная проверка
            $userText = $mode === 'ecr' 
                ? 'Марка корректна и прошла проверку на ККТ'
                : 'Марка разрешена к продаже, срок годности не истёк';
                
            $machineData = $this->prepareMachineData($honestSignResult->details, $mode);
            
            return OperationResult::success(
                [
                    'mode' => $mode,
                    'user_status' => [
                        'ok' => true,
                        'text' => $userText
                    ],
                    'machine_data' => $machineData
                ],
                $mode === 'ecr' ? 'Марка корректна' : 'Марка разрешена к продаже'
            );
        } else {
            // Ошибка проверки
            $userText = $mode === 'ecr'
                ? 'Марка некорректна или не прошла проверку ККТ'
                : 'Марка не разрешена к продаже или истёк срок годности';
                
            return OperationResult::failure(
                $honestSignResult->message ?? 'Ошибка проверки марки',
                [
                    'mode' => $mode,
                    'user_status' => [
                        'ok' => false,
                        'text' => $userText
                    ]
                ]
            );
        }
    }
    
    /**
     * Подготавливает машинные данные в зависимости от режима
     */
    private function prepareMachineData(?array $details, string $mode): array {
        if (!$details) {
            return [];
        }
        
        if ($mode === 'ecr') {
            // Для ККТ - структура itemInfoCheckResult
            return [
                'itemInfoCheckResult' => [
                    'ecrStandAloneFlag' => $details['ecrStandAloneFlag'] ?? false,
                    'imcCheckFlag' => $details['imcCheckFlag'] ?? true,
                    'imcCheckResult' => $details['imcCheckResult'] ?? true,
                    'imcEstimatedStatusCorrect' => $details['imcEstimatedStatusCorrect'] ?? true,
                    'imcStatusInfo' => $details['imcStatusInfo'] ?? true
                ]
            ];
        } else {
            // Для разрешительного режима - uuid, time и другие необходимые поля
            return [
                'uuid' => $details['uuid'] ?? null,
                'time' => $details['time'] ?? date('Y-m-d H:i:s'),
                // другие технические параметры для чека
            ];
        }
    }
}