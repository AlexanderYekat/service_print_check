<?php

class WeightResult
{
    public bool $success;
    public ?string $message;
    public ?string $weight;

    public function __construct(bool $success, ?string $message = null, ?string $weight = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->weight = $weight;
    }
}
