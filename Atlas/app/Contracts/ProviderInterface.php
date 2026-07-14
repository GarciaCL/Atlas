<?php

namespace Atlas\Contracts;

use Atlas\DTO\Document;

interface ProviderInterface
{
    /**
     * Obtiene el identificador único del proveedor (ej. 'wordpress', 'notion').
     */
    public function getIdentifier(): string;

    /**
     * Transforma una entidad nativa del origen externo en un DTO Document de Atlas.
     */
    public function extract(mixed $source): Document;
}