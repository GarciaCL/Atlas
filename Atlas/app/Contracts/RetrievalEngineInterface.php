<?php

namespace Atlas\Contracts;

use Atlas\DTO\SearchResult;

interface RetrievalEngineInterface
{
    /**
     * Recupera documentos relevantes basados en una cadena de búsqueda.
     * * @param string $query
     * @return SearchResult[]
     */
    public function retrieve(string $query): array;
}