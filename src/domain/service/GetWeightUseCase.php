<?php

require_once __DIR__ . '/../model/OperationResult.php';
require_once __DIR__ . '/../../interface/ScaleInterface.php';

// domain/service/GetWeightUseCase.php
class GetWeightUseCase {
    private ScaleInterface $scale;
    
    public function __construct(ScaleInterface $scale) {
        $this->scale = $scale;
    }
    
    public function execute(): OperationResult {
        $weightResult = $this->scale->getWeight();
        
        // Преобразование в доменный результат
        if ($weightResult->success) {
            return OperationResult::success(
                [
                    'weight' => $weightResult->weight    // Вес в доменных данных
                ],
                $weightResult->message ?? 'Вес получен успешно'
            );
        } else {
            return OperationResult::failure(
                $weightResult->message ?? 'Ошибка получения веса'
            );
        }
    }
}
