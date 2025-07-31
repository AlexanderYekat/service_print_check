<?php

require_once __DIR__ . '/../domain/model/OperationResult.php';

interface ScaleInterface {
    public function getWeight(): OperationResult;
}