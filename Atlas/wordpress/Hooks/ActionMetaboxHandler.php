<?php

namespace Atlas\WordPress\Hooks;

/**
 * Gestiona el metabox en la pantalla de edición de publicaciones (Post, Page, CPTs, WooCommerce)
 * para asociar botones y acciones comerciales específicas a cada contenido.
 */
class ActionMetaboxHandler
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'registerMetabox']);
        add_action('save_post', [self::class, 'saveMetaboxData'], 10, 2);
    }

    public static function registerMetabox(): void
    {
        // 1. Tipos de post excluidos (sistemas, maquetas internas)
        $excludedTypes = [
            'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
            'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
            'wp_navigation', 'elementor_library', 'elementor_font', 'elementor_icons', 'e-landing-page',
            'e-floating-buttons', 'elementor_component', 'elementor_snippet',
            'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy',
            'jet-engine', 'jet-engine-booking', 'jet-smart-filters', 'jet-menu', 'jet-popup', 'jet-theme-core',
            'angie_snippet', 'code_snippets'
        ];

        // 2. Obtener todos los Post Types activos
        $allTypes = get_post_types([], 'names');
        $targetTypes = array_diff($allTypes, $excludedTypes);

        // 3. Registrar el metabox en cada tipo de contenido
        foreach ($targetTypes as $postType) {
            add_meta_box(
                'atlas_assigned_actions_metabox',
                '🚀 Acciones Comerciales Atlas',
                [self::class, 'renderMetaboxView'],
                $postType,
                'side',
                'high'
            );
        }
    }

    public static function renderMetaboxView(\WP_Post $post): void
    {
        wp_nonce_field('atlas_actions_metabox_nonce_action', 'atlas_actions_metabox_nonce');

        $globalActions = get_option('atlas_global_actions', []);
        $assignedActions = get_post_meta($post->ID, '_atlas_assigned_actions', true) ?: [];

        if (!is_array($assignedActions)) {
            $assignedActions = [];
        }

        if (empty($globalActions)) {
            echo '<p style="font-size: 12px; color: #666; margin: 0;">No has creado ningún botón commercial global aún. Ve a <strong>Atlas KOS > Personalización e Indexación</strong> para crearlos.</p>';
            return;
        }

        echo '<p style="font-size: 12px; color: #555; margin-bottom: 12px; line-height: 1.4;">Selecciona los botones que deseas que el chatbot muestre cuando responda sobre este contenido:</p>';

        foreach ($globalActions as $actionId => $action) {
            $isChecked = in_array($actionId, $assignedActions, true) ? 'checked' : '';
            $bgColor = esc_attr($action['color'] ?? '#007cba');
            $textColor = esc_attr($action['text_color'] ?? '#ffffff');
            $label = esc_html($action['label'] ?? $action['name']);

            echo '<div style="margin-bottom: 8px;">';
            echo '<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; cursor: pointer;">';
            echo '<input type="checkbox" name="atlas_assigned_actions[]" value="' . esc_attr($actionId) . '" ' . $isChecked . ' style="margin:0;">';
            echo '<span style="padding: 3px 8px; background:' . $bgColor . '; color:' . $textColor . '; border-radius: 4px; font-size: 11px; font-weight: bold;">' . $label . '</span>';
            echo '</label>';
            echo '</div>';
        }
    }

    public static function saveMetaboxData(int $postId, \WP_Post $post): void
    {
        // Validar Nonce
        if (!isset($_POST['atlas_actions_metabox_nonce']) || !wp_verify_nonce($_POST['atlas_actions_metabox_nonce'], 'atlas_actions_metabox_nonce_action')) {
            return;
        }

        // Evitar autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Permisos de usuario
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Guardar o borrar selección
        if (isset($_POST['atlas_assigned_actions']) && is_array($_POST['atlas_assigned_actions'])) {
            $sanitized = array_map('sanitize_text_field', $_POST['atlas_assigned_actions']);
            update_post_meta($postId, '_atlas_assigned_actions', $sanitized);
        } else {
            delete_post_meta($postId, '_atlas_assigned_actions');
        }
    }
}