<?php

require_once __DIR__ . '/../../interface/ScaleInterface.php';

class FakeScaleAdapter implements ScaleInterface
{
    public function getWeight(): OperationResult
    {
        return OperationResult::success(['weight' => 123.5], 'Вес получен успешно');
    }
}
