<?php

class HonestSignResult
{
    public bool $success;
    public ?string $message;
    public ?array $details;

    public function __construct(bool $success, ?string $message = null, ?array $details = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->details = $details;
    }
}