<?php

namespace Atlas\DTO;

use Atlas\ValueObjects\Url;
use Atlas\ValueObjects\Score;

final readonly class SearchResult
{
    /**
     * @param array<string, mixed> $actions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $id,
        public Score $score,
        public string $title,
        public string $excerpt,
        public Url $url,
        public array $actions,
        public string $source,
        public array $metadata
    ) {}
}