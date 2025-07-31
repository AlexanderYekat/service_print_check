<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../../interface/ValidateMarkGateway.php';

// domain/service/ValidateMarkUseCase.php
class ValidateMarkUseCase {
    private ValidateMarkGateway $gateway;
    
    public function __construct(ValidateMarkGateway $gateway) {
        $this->gateway = $gateway;
    }
    
    /**
     * Проверка марки в разрешительном режиме
     */
    public function validateMarkPermitMode(MarkingCode $code): OperationResult {
        $honestSignResult = $this->gateway->validateMark($code);
        
        return $this->convertToStructuredResult($honestSignResult, 'permit');
    }
    
    /**
     * Проверка марки для ККТ
     */
    public function validateMarkEcrMode(MarkingCode $code): OperationResult {
        $honestSignResult = $this->gateway->validateMark($code);
        
        return $this->convertToStructuredResult($honestSignResult, 'ecr');
    }
    
    /**
     * Универсальный метод проверки марки
     */
    public function validateMark(MarkingCode $code, string $mode = 'permit'): OperationResult {
        $honestSignResult = $this->gateway->validateMark($code);
        
        return $this->convertToStructuredResult($honestSignResult, $mode);
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
