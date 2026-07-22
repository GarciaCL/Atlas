<?php

namespace Atlas\WordPress\Providers;

use Atlas\Contracts\ProviderInterface;
use Atlas\DTO\Document;

class WordPressProvider implements ProviderInterface
{
    public function getIdentifier(): string
    {
        return 'wordpress';
    }

    public function extract(mixed $source): Document
    {
        $cleanContent = $this->sanitizeContent($source->post_content);
        
        $globalActions = get_option('atlas_global_actions', []);
        $selectedActionIds = get_post_meta($source->ID, '_atlas_selected_actions', true) ?: [];

        $primaryButtons = [];

        foreach ($selectedActionIds as $actionId) {
            if (isset($globalActions[$actionId])) {
                $action = $globalActions[$actionId];
                
                $url = $action['url'];
                
                // Tipo de acción: WhatsApp
                if ($action['type'] === 'whatsapp' && !empty($url) && !str_starts_with($url, 'http')) {
                    $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $url);
                }

                // 🛒 Tipo de acción: Carrito WooCommerce (Añadir al Carro Directo)
                if ($action['type'] === 'cart' && !empty($url)) {
                    $productId = (int)preg_replace('/[^0-9]/', '', $url);
                    // Genera la URL nativa de WooCommerce para agregar directo al carrito
                    $url = home_url('/?add-to-cart=' . $productId);
                }

                $primaryButtons[] = [
                    'id' => $action['id'],
                    'type' => $action['type'],
                    'label' => $action['label'],
                    'url' => $url,
                    'icon' => $action['icon'] ?: null,
                    'styles' => [
                        'backgroundColor' => $action['color'],
                        'color' => $action['text_color'] ?? '#ffffff' // ◄ NUEVO: Se inyecta el color de texto e icono personalizado
                    ]
                ];
            }
        }

        $readLabel = "📖 Puedes revisar: " . $source->post_title;
        $secondaryButton = [
            'type' => 'link',
            'label' => $readLabel,
            'url' => get_permalink($source->ID),
            'styles' => [
                'backgroundColor' => '#f0f0f1',
                'color' => '#3c434a'
            ]
        ];

        $actions = [
            'primary_list' => $primaryButtons,
            'secondary'    => $secondaryButton
        ];

        return new Document(
            sourceId: $source->ID,
            sourceType: $source->post_type,
            title: $source->post_title,
            slug: $source->post_name,
            content: $cleanContent,
            excerpt: $source->post_excerpt ?: null,
            seo: [],
            actions: $actions,
            customFields: [],
            language: get_locale(),
            updatedAt: $source->post_modified
        );
    }

    private function sanitizeContent(string $content): string
    {
        $content = do_shortcode($content);
        $content = wp_strip_all_tags($content);
        return preg_replace('/\s+/', ' ', trim($content));
    }
}