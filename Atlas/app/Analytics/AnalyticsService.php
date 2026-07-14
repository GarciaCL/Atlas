<?php

namespace Atlas\Analytics;

use Atlas\Repositories\AnalyticsRepositoryInterface;

class AnalyticsService
{
    private AnalyticsRepositoryInterface $repository;

    public function __construct(AnalyticsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtiene el listado de las preguntas más críticas que necesitan respuesta.
     */
    public function getCriticalGaps(int $limit = 10): array
    {
        return $this->repository->getTopUnansweredQuestions($limit);
    }
}