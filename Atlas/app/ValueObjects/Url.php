<?php

namespace Atlas\ValueObjects;

final readonly class Url
{
    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
            throw new \InvalidArgumentException("Invalid URL or relative path: {$value}");
        }
        $this->value = $value;
    }
}