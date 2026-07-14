<?php

namespace Atlas\Repositories;

interface QuestionRepositoryInterface
{
    /**
     * Registra una pregunta sin respuesta o incrementa su contador si ya existe.
     */
    public function logUnanswered(string $question, string $url, int $userId): void;
}