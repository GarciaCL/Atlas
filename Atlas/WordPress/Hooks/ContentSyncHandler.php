<?php

namespace Atlas\WordPress\Hooks;

use Atlas\Content\ContentService;
use Atlas\WordPress\Providers\WordPressProvider;

class ContentSyncHandler
{
    private ContentService $contentService;
    private WordPressProvider $provider;

    public function __construct(ContentService $contentService, WordPressProvider $provider)
    {
        $this->contentService = $contentService;
        $this->provider = $provider;
    }

    public function handleSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        // Evitar revisiones, autosaves o ejecuciones en momentos incorrectos
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_status !== 'publish') {
            // Si el post deja de estar publicado, lo removemos del índice de Atlas
            $this->contentService->deleteFromIndex($postId, $post->post_type); // Se mapeará en el repositorio
            return;
        }

        // Ignorar tipos de post del sistema de WP que no aportan conocimiento
        // Ignorar tipos de post del sistema de WP que NO aportan conocimiento
$ignoredTypes = [
    'revision', 
    'nav_menu_item', 
    'custom_css', 
    'customize_changeset', 
    'attachment',
    'wp_global_styles', // ◄ AGREGAR ESTO
    'wp_navigation',    // ◄ AGREGAR ESTO
    'wp_block'          // ◄ AGREGAR ESTO
];

if (in_array($post->post_type, $ignoredTypes, true)) {
    return;
}

        // ACL en acción: WordPress entrega datos -> El Proveedor genera el DTO -> El Dominio lo procesa
        $document = $this->provider->extract($post);
        $this->contentService->ingest($document);
    }
}