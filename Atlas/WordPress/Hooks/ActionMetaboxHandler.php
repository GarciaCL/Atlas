<?php

namespace Atlas\WordPress\Hooks;

class ActionMetaboxHandler
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetabox']);
        add_action('save_post', [self::class, 'saveMetaboxData']);
    }

    public static function addMetabox(): void
    {
        add_meta_box(
            'atlas_action_meta',
            '🚀 Acciones Comerciales Atlas',
            [self::class, 'renderMetabox'],
            ['post', 'page'],
            'side',
            'high'
        );
    }

    public static function renderMetabox(\WP_Post $post): void
    {
        wp_nonce_field('atlas_action_nonce_action', 'atlas_action_nonce');

        $globalActions = get_option('atlas_global_actions', []);
        $selectedActions = get_post_meta($post->ID, '_atlas_selected_actions', true) ?: [];

        ?>
        <div style="margin-bottom: 10px;">
            <p class="description" style="margin-bottom:15px;">Selecciona los botones comerciales que quieres activar cuando Atlas responda con este contenido en el chat.</p>
            
            <?php if (empty($globalActions)): ?>
                <div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 10px; border-radius: 2px;">
                    No tienes acciones configuradas. <a href="<?php echo esc_url(admin_url('admin.php?page=atlas-settings')); ?>" target="_blank">Créalas aquí primero</a>.
                </div>
            <?php else: ?>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background: #fff; border-radius: 4px;">
                    <?php foreach ($globalActions as $action): 
                        $checked = in_array($action['id'], $selectedActions) ? 'checked' : '';
                        ?>
                        <p style="margin: 8px 0; display:flex; align-items:center;">
                            <input type="checkbox" name="atlas_selected_actions[]" value="<?php echo esc_attr($action['id']); ?>" <?php echo $checked; ?> style="margin-right:8px;">
                            <span style="font-weight: 500;"><?php echo esc_html($action['name']); ?></span>
                            <span style="display:inline-block; width: 12px; height: 12px; border-radius:50%; margin-left: auto; background: <?php echo esc_attr($action['color']); ?>;"></span>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function saveMetaboxData(int $postId): void
    {
        if (!isset($_POST['atlas_action_nonce']) || !wp_verify_nonce($_POST['atlas_action_nonce'], 'atlas_action_nonce_action')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $postId)) return;

        if (isset($_POST['atlas_selected_actions']) && is_array($_POST['atlas_selected_actions'])) {
            $sanitized = array_map('sanitize_text_field', $_POST['atlas_selected_actions']);
            update_post_meta($postId, '_atlas_selected_actions', $sanitized);
        } else {
            update_post_meta($postId, '_atlas_selected_actions', []);
        }
    }
}