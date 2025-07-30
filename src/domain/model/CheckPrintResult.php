<?php

class PrintResult
{
    public bool $success;
    public ?string $message;

    public function __construct(bool $success, ?string $message = null, ?array $printedLines = null)
    {
        $this->success = $success;
        $this->message = $message;
    }
}
