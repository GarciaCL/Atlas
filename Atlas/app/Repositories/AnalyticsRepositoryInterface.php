<?php

namespace Atlas\Repositories;

interface AnalyticsRepositoryInterface
{
    /**
     * Recupera las preguntas sin respuesta más repetidas.
     * @return array<int, array<string, mixed>>
     */
    public function getTopUnansweredQuestions(int $limit): array;
}