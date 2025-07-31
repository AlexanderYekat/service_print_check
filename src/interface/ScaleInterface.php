<?php

namespace App\Interface;

use App\Domain\Model\OperationResult;

interface ScaleInterface {
    public function getWeight(): OperationResult;
}