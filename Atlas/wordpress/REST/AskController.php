<?php

namespace Atlas\WordPress\REST;

use Atlas\Conversation\ConversationService;

class AskController
{
    private ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    public function registerRoutes(): void
    {
        register_rest_route('atlas/v1', '/ask', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleAsk'],
            'permission_callback' => '__return_true', // Público para el chat flotante
        ]);
    }

    public function handleAsk(\WP_REST_Request $request): \WP_REST_Response
    {
        $question = sanitize_text_field($request->get_param('question') ?? '');
        $url = esc_url_raw($request->get_param('url') ?? '');
        $userId = get_current_user_id();

        // Llamamos al servicio del Dominio
        $responseDto = $this->conversationService->ask($question, $url, $userId);

        // ACL: Convertimos el DTO inmutable del dominio al formato plano JSON de salida
        return new \WP_REST_Response([
            'text'    => $responseDto->text,
            'actions' => $responseDto->actions,
            'success' => $responseDto->hasResults
        ], 200);
    }
}