<?php

namespace Atlas\Repositories;

use Atlas\DTO\Document;

interface DocumentRepositoryInterface
{
    public function save(Document $document): int;
    public function delete(int $sourceId, string $sourceType): bool;
}