<?php

namespace Atlas\Conversation;

use Atlas\Contracts\RetrievalEngineInterface;
use Atlas\DTO\ConversationResponse;
use Atlas\Repositories\QuestionRepositoryInterface;

class ConversationService
{
    private RetrievalEngineInterface $retrievalEngine;
    private QuestionRepositoryInterface $questionRepository;

    public function __construct(
        RetrievalEngineInterface $retrievalEngine,
        QuestionRepositoryInterface $questionRepository
    ) {
        $this->retrievalEngine = $retrievalEngine;
        $this->questionRepository = $questionRepository;
    }

    public function ask(string $question, string $currentUrl, int $userId): ConversationResponse
    {
        $question = trim($question);
        if (empty($question)) {
            return new ConversationResponse('Por favor, escribe una pregunta válida.', [], false);
        }

        // 1. Intentar recuperar conocimiento local
        $results = $this->retrievalEngine->retrieve($question);

        if (empty($results)) {
            // 2. Si no hay respuesta, registrar la duda (Mina de oro de analíticas para el Día 5)
            $this->questionRepository->logUnanswered($question, $currentUrl, $userId);

            return new ConversationResponse(
                'Lo siento, no he encontrado información sobre eso. Pero ya he notificado al administrador para que añada esta respuesta pronto.',
                [],
                false
            );
        }

        // 3. Tomamos el resultado con mayor score (MVP de un solo nodo de respuesta)
        $bestMatch = $results[0];

        // 4. Retornamos el DTO de respuesta estructurado para que el Frontend lo pinte
        return new ConversationResponse(
            text: $bestMatch->excerpt,
            actions: $bestMatch->actions,
            hasResults: true
        );
    }
}