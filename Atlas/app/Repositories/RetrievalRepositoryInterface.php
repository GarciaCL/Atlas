<?php

namespace Atlas\Repositories;

interface RetrievalRepositoryInterface
{
    /**
     * Busca en la base de datos registros que coincidan con los términos.
     * Devuelve un array de arrays asociativos (ACL procesará esto en DTOs).
     *
     * @param string $query
     * @return array<int, array<string, mixed>>
     */
    public function searchDocuments(string $query): array;
}