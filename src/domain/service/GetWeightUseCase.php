<?php

namespace App\Domain\Service;

use App\Domain\Model\OperationResult;
use App\Interface\ScaleInterface;

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
