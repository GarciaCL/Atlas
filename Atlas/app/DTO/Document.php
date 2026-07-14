<?php

namespace Atlas\DTO;

final readonly class Document
{
    /**
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $actions
     * @param array<string, mixed> $customFields
     */
    public function __construct(
        public int $sourceId,
        public string $sourceType,
        public string $title,
        public string $slug,
        public string $content,
        public ?string $excerpt = null,
        public array $seo = [],
        public array $actions = [],
        public array $customFields = [],
        public string $language = 'en',
        public ?string $updatedAt = null
    ) {}
}