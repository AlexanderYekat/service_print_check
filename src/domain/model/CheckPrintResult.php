<?php

class PrintResult
{
    public bool $success;
    public ?string $message;
    public ?array $printedLines;

    public function __construct(bool $success, ?string $message = null, ?array $printedLines = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->printedLines = $printedLines;
    }
}
