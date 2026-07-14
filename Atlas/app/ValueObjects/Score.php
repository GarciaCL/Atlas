<?php

namespace Atlas\ValueObjects;

final readonly class Score
{
    public float $value;

    public function __construct(float $value)
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new \InvalidArgumentException('Score must be between 0.0 and 1.0');
        }
        $this->value = $value;
    }
}