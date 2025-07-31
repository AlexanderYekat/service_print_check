<?php

namespace App\Infrastructure\Scale;

use App\Interface\ScaleInterface;
use App\Domain\Model\OperationResult;

class FakeScaleAdapter implements ScaleInterface
{
    public function getWeight(): OperationResult
    {
        return OperationResult::success(['weight' => 123.5], 'Вес получен успешно');
    }
}
