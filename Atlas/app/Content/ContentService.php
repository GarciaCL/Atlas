<?php

namespace Atlas\Content;

use Atlas\DTO\Document;
use Atlas\Repositories\DocumentRepositoryInterface;
use Atlas\Support\Logger;

class ContentService
{
    private DocumentRepositoryInterface $repository;

    public function __construct(DocumentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function ingest(Document $document): void
    {
        if (empty($document->content)) {
            Logger::info("Document ingestion skipped: Empty content.", [
                'source_id' => $document->sourceId,
                'type' => $document->sourceType
            ]);
            return;
        }

        // Aquí podríamos aplicar Sanitizers/Normalizers en la V2 antes de guardar
        $this->repository->save($document);
        
        Logger::info("Document ingested successfully into Atlas.", [
            'source_id' => $document->sourceId,
            'type' => $document->sourceType
        ]);
    }

    public function deleteFromIndex(int $sourceId, string $sourceType): void
{
    $this->repository->delete($sourceId, $sourceType);
    Logger::info("Document removed from Atlas.", [
        'source_id' => $sourceId,
        'type' => $sourceType
    ]);
}
}