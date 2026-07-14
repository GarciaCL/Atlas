<?php

namespace Atlas\Retrieval;

use Atlas\Contracts\RetrievalEngineInterface;
use Atlas\DTO\SearchResult;
use Atlas\Repositories\RetrievalRepositoryInterface;
use Atlas\ValueObjects\Score;
use Atlas\ValueObjects\Url;

class LexicalRetrievalEngine implements RetrievalEngineInterface
{
    private RetrievalRepositoryInterface $repository;

    public function __construct(RetrievalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return SearchResult[]
     */
    public function retrieve(string $query): array
    {
        $query = trim(mb_strtolower($query));
        if (empty($query)) {
            return [];
        }

        // Obtener datos de la capa de infraestructura
        $rawDocuments = $this->repository->searchDocuments($query);
        $results = [];

        foreach ($rawDocuments as $doc) {
            // Calculamos un Score de relevancia léxica básico de forma artesanal para el MVP (0.0 a 1.0)
            $scoreValue = $this->calculateBasicScore($query, $doc['title'], $doc['content']);
            
            // ACL: Convertimos el array asociativo crudo de infraestructura en nuestro DTO inmutable
            $results[] = new SearchResult(
                id: (int) $doc['id'],
                score: new Score($scoreValue),
                title: $doc['title'],
                excerpt: $doc['excerpt'] ?: wp_html_excerpt($doc['content'], 150) . '...',
                url: new Url($doc['slug'] ? '/' . $doc['slug'] : '/?p=' . $doc['source_id']),
                actions: json_decode($doc['actions'] ?? '[]', true) ?: [],
                source: $doc['source_type'],
                metadata: json_decode($doc['custom_fields'] ?? '[]', true) ?: []
            );
        }

        // Ordenamos los resultados de mayor a menor score
        usort($results, fn(SearchResult $a, SearchResult $b) => $b->score->value <=> $a->score->value);

        return $results;
    }

    private function calculateBasicScore(string $query, string $title, string $content): float
    {
        $title = mb_strtolower($title);
        $content = mb_strtolower($content);
        $score = 0.1; // Base mínima de coincidencia

        // Coincidencia exacta en título (máxima relevancia)
        if (str_contains($title, $query)) {
            $score += 0.6;
        }

        // Coincidencia exacta en contenido
        if (str_contains($content, $query)) {
            $score += 0.3;
        }

        return min($score, 1.0);
    }
}