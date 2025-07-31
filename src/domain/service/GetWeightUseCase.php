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
        // Адаптер уже возвращает правильный OperationResult
        return $this->scale->getWeight();
    }
}
