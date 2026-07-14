<?php

namespace Atlas\DTO;

final readonly class ConversationResponse
{
    /**
     * @param array<string, mixed> $actions
     */
    public function __construct(
        public string $text,
        public array $actions = [],
        public bool $hasResults = true
    ) {}
}