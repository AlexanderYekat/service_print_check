<?php

class MarkingCode
{
    public string $value;
    public ?string $inn;
    public ?string $gtin;

    public function __construct(string $value, ?string $inn = null, ?string $gtin = null)
    {
        $this->value = $value;
        $this->inn = $inn;
        $this->gtin = $gtin;
    }
}