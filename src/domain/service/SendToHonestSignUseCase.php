<?php

require_once __DIR__ . '/../model/MarkingCode.php';
require_once __DIR__ . '/../model/HonestSignResult.php';
require_once __DIR__ . '/../../interface/ValidateMarkGateway.php';

class SendToHonestSignUseCase
{
    private ValidateMarkGateway $gateway;

    public function __construct(ValidateMarkGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function execute(MarkingCode $code): HonestSignResult
    {
        return $this->gateway->validateMark($code);
    }

    public function processQueue(): array
    {
        if ($this->gateway instanceof HttpHonestSignGateway) {
            return $this->gateway->processQueuedItems();
        }
        
        return [];
    }

    public function getQueueStatus(): array
    {
        if ($this->gateway instanceof HttpHonestSignGateway) {
            return $this->gateway->getQueueStatus();
        }
        
        return ['total' => 0, 'pending' => 0, 'completed' => 0, 'failed' => 0];
    }
}