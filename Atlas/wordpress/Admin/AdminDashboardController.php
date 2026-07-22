<?php

namespace Atlas\WordPress\Admin;

use Atlas\Analytics\AnalyticsService;
use Atlas\Content\ContentService;
use Atlas\WordPress\Hooks\ContentSyncHandler;
use Atlas\WordPress\Hooks\MigrationRunner;
use Atlas\WordPress\Providers\WordPressProvider;
use Atlas\WordPress\Repositories\WordPressDocumentRepository;

/**
 * Controlador para el panel de administración de Atlas KOS en WordPress.
 * Gestiona la visualización de preguntas huérfanas, ajustes visuales e indexación masiva.
 */
class AdminDashboardController
{
    private AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function registerMenu(): void
    {
        $dashboardPage = add_menu_page(
            'Atlas KOS',
            'Atlas KOS',
            'manage_options',
            'atlas-kos',
            [$this, 'renderDashboard'],
            'dashicons-brain',
            25
        );

        add_submenu_page(
            'atlas-kos',
            'Preguntas Huérfanas',
            'Preguntas Huérfanas',
            'manage_options',
            'atlas-kos',
            [$this, 'renderDashboard']
        );

        $settingsPage = add_submenu_page(
            'atlas-kos',
            'Personalización e Indexación',
            'Personalización e Indexación',
            'manage_options',
            'atlas-settings',
            [$this, 'renderSettings']
        );

        add_action("admin_print_scripts-{$settingsPage}", [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets(): void
    {
        wp_enqueue_media();
        wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@latest', [], null, true);
    }

    public function renderDashboard(): void
    {
        $gaps = $this->analyticsService->getCriticalGaps(20);
        ?>
        <div class="wrap">
            <h1 style="font-weight: 800; margin-bottom: 5px;">🧠 Analíticas Atlas KOS</h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">Monitorea las consultas de tus clientes que requieren de tu atención.</p>
            <hr class="wp-header-end">
            <h2 style="margin-top:20px; font-weight: 700;">⚠️ Brechas de Conocimiento Críticas (Preguntas Huérfanas)</h2>
            <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <thead>
                    <tr>
                        <th style="font-weight: bold; width: 40%; padding: 12px;">Pregunta Detectada</th>
                        <th style="font-weight: bold; width: 25%; padding: 12px;">Página de Origen</th>
                        <th style="font-weight: bold; width: 15%; text-align: center; padding: 12px;">Frecuencia (Hits)</th>
                        <th style="font-weight: bold; width: 20%; padding: 12px;">Última vez visto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gaps)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #666; font-size: 14px;">
                                🎉 ¡Felicitaciones! No hay preguntas huérfanas registradas aún.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gaps as $gap): ?>
                            <tr>
                                <td style="font-weight: 600; color: #d63638; padding: 12px; font-size: 14px;"><?php echo esc_html($gap['question']); ?></td>
                                <td style="padding: 12px;"><a href="<?php echo esc_url($gap['url']); ?>" target="_blank" style="text-decoration: none; color: #007cba;"><?php echo esc_html(parse_url($gap['url'], PHP_URL_PATH) ?: '/'); ?></a></td>
                                <td style="text-align: center; font-weight: bold; color: #007cba; padding: 12px; font-size: 14px;"><?php echo (int)$gap['hit_count']; ?></td>
                                <td style="padding: 12px; color: #555;"><?php echo esc_html($gap['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        global $wpdb;
        $chatColor = get_option('atlas_chat_color', '#007cba');
        $chatIconColor = get_option('atlas_chat_icon_color', '#ffffff');
        $chatIcon = get_option('atlas_chat_icon', 'message-square');
        $chatTitleText = get_option('atlas_chat_title_text', 'Asistente Atlas');
        $chatHeaderLogo = get_option('atlas_chat_header_logo', '');
        $chatShowTitle = get_option('atlas_chat_show_title', 'yes');
        $chatHeaderBg = get_option('atlas_chat_header_bg', '#007cba');
        $chatHeaderTextColor = get_option('atlas_chat_header_text_color', '#ffffff');
        $globalActions = get_option('atlas_global_actions', []);

        $tableDocuments = $wpdb->prefix . 'atlas_documents';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('atlas_settings_nonce_action', 'atlas_settings_nonce')) {
            
            if (isset($_POST['atlas_trigger_bulk_index'])) {
                
                // 1. Forzar migración de tablas si fuera necesario
                if (class_exists(MigrationRunner::class)) {
                    MigrationRunner::run();
                }

                $documentRepo   = new WordPressDocumentRepository();
                $contentService = new ContentService($documentRepo);
                $wpProvider     = new WordPressProvider();
                $syncHandler    = new ContentSyncHandler($contentService, $wpProvider);

                // 2. Limpiar tabla previa
                $wpdb->query("TRUNCATE TABLE {$tableDocuments}");

                // 3. Excluir únicamente componentes de diseño y maquetas internas
                $excludedTypes = [
                    'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
                    'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
                    'wp_navigation', 'elementor_library', 'elementor_font', 'elementor_icons', 'e-landing-page',
                    'e-floating-buttons', 'elementor_component', 'elementor_snippet',
                    'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy',
                    'jet-engine', 'jet-engine-booking', 'jet-smart-filters', 'jet-menu', 'jet-popup', 'jet-theme-core',
                    'angie_snippet', 'code_snippets'
                ];

                // 4. Obtener todos los Post Types con publicaciones activas directamente desde la BD
                $placeholders = implode(',', array_fill(0, count($excludedTypes), '%s'));
                $sqlTypes = $wpdb->prepare(
                    "SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ($placeholders)",
                    ...$excludedTypes
                );
                $targetPostTypes = $wpdb->get_col($sqlTypes);

                $indexedCount = 0;

                // 5. Escaneo e Inserción Directa desde MySQL
                foreach ($targetPostTypes as $singleType) {
                    $sqlPosts = $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                        $singleType
                    );
                    $typePostIds = $wpdb->get_col($sqlPosts);

                    foreach ($typePostIds as $postId) {
                        // Convertir a objeto WP_Post válido
                        $post = get_post($postId);
                        if (!$post) {
                            continue;
                        }

                        $titleLower = mb_strtolower($post->post_title, 'UTF-8');
                        if (str_contains($titleLower, 'elementor') || str_contains($titleLower, 'pie de página') || str_contains($titleLower, 'cabecera')) {
                            continue;
                        }

                        // EXTRACCIÓN AVANZADA DE ACF: Si post_content está vacío, extraemos el contenido de los campos de ACF
                        if (empty(trim(strip_tags($post->post_content)))) {
                            $acfTextParts = [];
                            
                            // Extraer campos de ACF mediante get_fields()
                            if (function_exists('get_fields')) {
                                $acfFields = get_fields($post->ID);
                                if (is_array($acfFields)) {
                                    foreach ($acfFields as $fKey => $fVal) {
                                        if (is_string($fVal) && !empty(trim($fVal))) {
                                            $acfTextParts[] = strip_tags($fVal);
                                        }
                                    }
                                }
                            }

                            // Si no existe ACF o estuvo vacío, buscar en la tabla postmeta
                            if (empty($acfTextParts)) {
                                $allMeta = get_post_meta($post->ID);
                                foreach ($allMeta as $mKey => $mVals) {
                                    if (str_starts_with($mKey, '_')) continue;
                                    foreach ((array)$mVals as $mVal) {
                                        if (is_string($mVal) && mb_strlen($mVal) > 5 && !is_serialized($mVal)) {
                                            $acfTextParts[] = strip_tags($mVal);
                                        }
                                    }
                                }
                            }

                            if (!empty($acfTextParts)) {
                                $post->post_content = implode("\n\n", $acfTextParts);
                            }
                        }

                        $syncHandler->handleSavePost($post->ID, $post, true);
                        $indexedCount++;
                    }
                }

                $typeListStr = implode(', ', $targetPostTypes);

                echo '<div class="notice notice-success is-dismissible"><p>🚀 <strong>Indexación Directa de Alta Precisión completada:</strong> Se han procesado e indexado ' . $indexedCount . ' elementos reales en la memoria de Atlas (incluyendo campos personalizados de ACF).<br><strong>Post Types escaneados:</strong> <code>' . esc_html($typeListStr) . '</code></p></div>';
            }

            if (isset($_POST['atlas_save_general_settings'])) {
                update_option('atlas_chat_color', sanitize_hex_color($_POST['atlas_chat_color']));
                update_option('atlas_chat_icon_color', sanitize_hex_color($_POST['atlas_chat_icon_color']));
                update_option('atlas_chat_icon', sanitize_text_field($_POST['atlas_chat_icon']));
                update_option('atlas_chat_title_text', sanitize_text_field($_POST['atlas_chat_title_text']));
                update_option('atlas_chat_header_logo', esc_url_raw($_POST['atlas_chat_header_logo']));
                update_option('atlas_chat_show_title', sanitize_text_field($_POST['atlas_chat_show_title']));
                update_option('atlas_chat_header_bg', sanitize_hex_color($_POST['atlas_chat_header_bg']));
                update_option('atlas_chat_header_text_color', sanitize_hex_color($_POST['atlas_chat_header_text_color']));

                $chatColor = sanitize_hex_color($_POST['atlas_chat_color']);
                $chatIconColor = sanitize_hex_color($_POST['atlas_chat_icon_color']);
                $chatIcon = sanitize_text_field($_POST['atlas_chat_icon']);
                $chatTitleText = sanitize_text_field($_POST['atlas_chat_title_text']);
                $chatHeaderLogo = esc_url_raw($_POST['atlas_chat_header_logo']);
                $chatShowTitle = sanitize_text_field($_POST['atlas_chat_show_title']);
                $chatHeaderBg = sanitize_hex_color($_POST['atlas_chat_header_bg']);
                $chatHeaderTextColor = sanitize_hex_color($_POST['atlas_chat_header_text_color']);

                echo '<div class="notice notice-success is-dismissible"><p>Aspecto visual e identidad de Atlas actualizados con éxito.</p></div>';
            }

            if (isset($_POST['atlas_create_action'])) {
                $id = 'action_' . uniqid();
                $newAction = [
                    'id' => $id,
                    'name' => sanitize_text_field($_POST['action_name']),
                    'type' => sanitize_text_field($_POST['action_type']),
                    'label' => sanitize_text_field($_POST['action_label']),
                    'url' => sanitize_text_field($_POST['action_url']),
                    'color' => sanitize_hex_color($_POST['action_color']),
                    'text_color' => sanitize_hex_color($_POST['action_text_color']),
                    'icon' => sanitize_text_field($_POST['action_icon']),
                ];
                $globalActions[$id] = $newAction;
                update_option('atlas_global_actions', $globalActions);
                echo '<div class="notice notice-success is-dismissible"><p>Nueva acción comercial creada de forma global.</p></div>';
            }

            if (isset($_POST['atlas_update_action'])) {
                $editId = sanitize_text_field($_POST['edit_action_id']);
                if (isset($globalActions[$editId])) {
                    $globalActions[$editId] = [
                        'id' => $editId,
                        'name' => sanitize_text_field($_POST['edit_action_name']),
                        'type' => sanitize_text_field($_POST['edit_action_type']),
                        'label' => sanitize_text_field($_POST['edit_action_label']),
                        'url' => sanitize_text_field($_POST['edit_action_url']),
                        'color' => sanitize_hex_color($_POST['edit_action_color']),
                        'text_color' => sanitize_hex_color($_POST['edit_action_text_color']),
                        'icon' => sanitize_text_field($_POST['edit_action_icon']),
                    ];
                    update_option('atlas_global_actions', $globalActions);
                    echo '<div class="notice notice-success is-dismissible"><p>Acción comercial actualizada correctamente.</p></div>';
                }
            }

            if (isset($_POST['atlas_delete_action'])) {
                $deleteId = sanitize_text_field($_POST['delete_action_id']);
                if (isset($globalActions[$deleteId])) {
                    unset($globalActions[$deleteId]);
                    update_option('atlas_global_actions', $globalActions);
                    echo '<div class="notice notice-warning is-dismissible"><p>Acción comercial eliminada de forma global.</p></div>';
                }
            }
        }
        ?>
        <style>
            .atlas-modal {
                display: none; 
                position: fixed; 
                z-index: 999999; 
                left: 0; 
                top: 0; 
                width: 100%; 
                height: 100%; 
                overflow: auto; 
                background-color: rgba(0,0,0,0.5);
                backdrop-filter: blur(4px);
            }
            .atlas-modal-content {
                background-color: #fefefe;
                margin: 7% auto; 
                padding: 30px; 
                border: 1px solid #ccd0d4;
                width: 50%;
                min-width: 480px;
                border-radius: 8px;
                position: relative;
                box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            }
            .atlas-close-modal {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                line-height: 20px;
            }
            .atlas-close-modal:hover { color: #d63638; }
        </style>

        <div class="wrap">
            <h1 style="font-weight: 800; margin-bottom: 5px;">⚙️ Personalización e Indexación de Atlas</h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">Sincroniza el contenido de tu web (WooCommerce, ACF, JetEngine) y personaliza la experiencia del chat.</p>
            
            <hr class="wp-header-end">

            <!-- MOTOR DE INDEXACIÓN MASIVA DIRECTA DESDE MYSQL -->
            <div class="card" style="margin-top: 25px; padding: 20px; background: #f0f6fc; border: 1px solid #c5d9ed; border-radius: 6px;">
                <h3 style="margin-top:0; color: #007cba; font-weight: 800;">⚡ Sincronización Directa de Memoria Cognitiva</h3>
                <p style="font-size: 13px; color: #444; margin-bottom: 15px;">
                    Haz clic en el siguiente botón para realizar un escaneo directo sobre la base de datos MySQL e indexar tus páginas, entradas, <strong>productos de WooCommerce</strong> y guías de <strong>ACF y JetEngine</strong> (incluso si están construidas con campos personalizados).
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                    <input type="submit" name="atlas_trigger_bulk_index" class="button button-primary" style="background: #007cba; font-weight: bold; padding: 8px 22px; height: auto; font-size: 14px;" value="⚡ Indexar Todo el Contenido del Sitio Ahora">
                </form>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 25px;">
                
                <!-- SECCIÓN 1: BURBUJA FLOTANTE -->
                <div class="card" style="flex: 1.1; min-width: 340px; padding: 25px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
                    <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700;">🎨 Burbuja de Chat</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Color de Identidad:</label>
                                <input type="color" name="atlas_chat_color" value="<?php echo esc_attr($chatColor); ?>" style="width: 100%; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Fondo Cabecera:</label>
                                <input type="color" name="atlas_chat_header_bg" value="<?php echo esc_attr($chatHeaderBg); ?>" style="width: 100%; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Texto Cabecera:</label>
                                <input type="color" name="atlas_chat_header_text_color" value="<?php echo esc_attr($chatHeaderTextColor); ?>" style="width: 100%; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                        </div>

                        <p style="margin-bottom: 15px;">
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Título del Asistente:</label>
                            <input type="text" name="atlas_chat_title_text" value="<?php echo esc_attr($chatTitleText); ?>" style="width:100%; padding: 6px;" placeholder="Ej: Soporte Atlas">
                        </p>

                        <div style="display: flex; gap: 15px; margin-bottom: 15px; align-items: center;">
                            <p style="margin: 0; flex: 1.5;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Logo Corporativo Cabecera:</label>
                                <div style="display: flex; gap: 5px;">
                                    <input type="text" id="atlas_chat_header_logo" name="atlas_chat_header_logo" value="<?php echo esc_attr($chatHeaderLogo); ?>" style="flex:1; padding: 6px;" placeholder="URL de imagen">
                                    <button type="button" class="button atlas-upload-button" data-target="atlas_chat_header_logo">Subir</button>
                                </div>
                            </p>
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Mostrar Título:</label>
                                <select name="atlas_chat_show_title" style="width:100%; height: 30px;">
                                    <option value="yes" <?php selected($chatShowTitle, 'yes'); ?>>Mostrar</option>
                                    <option value="no" <?php selected($chatShowTitle, 'no'); ?>>Ocultar (Solo Logo)</option>
                                </select>
                            </p>
                        </div>

                        <p style="margin-bottom: 15px;">
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Icono de la Burbuja:</label>
                            <div style="display: flex; gap: 5px; margin-bottom: 5px;">
                                <input type="text" id="atlas_chat_icon" name="atlas_chat_icon" value="<?php echo esc_attr($chatIcon); ?>" style="flex:1; padding: 6px;" placeholder="Ej: message-square o URL de imagen">
                                <button type="button" class="button atlas-upload-button" data-target="atlas_chat_icon">Subir</button>
                            </div>
                        </p>

                        <!-- BRANDING REMOVAL CHECKBOX -->
                        <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 20px; background: #fffcf6; padding: 12px; border-radius: 4px; border: 1px solid #f9f2e8;">
                            <label style="font-weight:bold; display:flex; align-items:center; gap: 8px; margin-bottom:5px; color: #333; cursor: not-allowed;">
                                <input type="checkbox" name="atlas_branding_remove" disabled style="margin:0; cursor: not-allowed;">
                                <span>Remover marca de desarrollador</span>
                            </label>
                            <span class="description" style="font-size:11px; display:block; color: #c0392b; font-weight: bold; line-height: 1.4;">
                                🔒 Característica Premium: Mantener desactivada la atribución de "creactivaweb.cl" requiere una clave de licencia válida.
                            </span>
                        </div>

                        <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                            <input type="submit" name="atlas_save_general_settings" class="button button-secondary" style="width:100%;" value="Guardar Ajustes de Burbuja">
                        </p>
                    </form>
                </div>

                <!-- SECCIÓN 2: GESTOR DE BOTONES COMERCIALES GLOBALES -->
                <div class="card" style="flex: 2; min-width: 450px; padding: 25px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
                    <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700;">🚀 Crear Nueva Acción Comercial</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <p>
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Nombre Interno (Para ti):</label>
                                <input type="text" name="action_name" required style="width:100%;" placeholder="Ej: Añadir Curso al Carro">
                            </p>
                            <p>
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Tipo de Acción:</label>
                                <select id="atlas_action_type" name="action_type" style="width:100%; height: 30px;">
                                    <option value="whatsapp">WhatsApp Directo</option>
                                    <option value="link">Enlace Personalizado</option>
                                    <option value="cart">Carrito WooCommerce (Compra Directa)</option>
                                </select>
                            </p>
                            <p>
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Texto del Botón (Público):</label>
                                <input type="text" name="action_label" required style="width:100%;" placeholder="Ej: Comprar Oferta Ahora">
                            </p>
                            <p>
                                <label id="atlas_url_label" style="font-weight:bold; display:block; margin-bottom:5px;">Enlace / Destino:</label>
                                <input type="text" id="atlas_action_url" name="action_url" required style="width:100%;" placeholder="Ej: https://...">
                            </p>
                            <p>
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Color de Fondo del Botón:</label>
                                <input type="color" name="action_color" value="#007cba" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                            <p>
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Color del Texto e Icono:</label>
                                <input type="color" name="action_text_color" value="#ffffff" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                            
                            <p style="grid-column: span 2; margin-bottom: 0;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Icono Vectorial o URL Imagen:</label>
                                <div style="display: flex; gap: 5px;">
                                    <input type="text" id="action_icon" name="action_icon" style="flex:1;" placeholder="Ej: shopping-cart o URL de imagen">
                                    <button type="button" class="button atlas-upload-button" data-target="action_icon">Subir Icono</button>
                                </div>
                            </p>
                        </div>
                        <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                            <input type="submit" name="atlas_create_action" class="button button-primary" value="Crear Acción Global">
                        </p>
                    </form>
                </div>
            </div>

            <!-- TABLA DE BOTONES REGISTRADOS -->
            <h2 style="margin-top: 40px; font-weight: 700;">📋 Listado de Acciones Comerciales Registradas</h2>
            <table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <thead>
                    <tr>
                        <th style="font-weight:bold; padding: 12px; width: 20%;">Nombre Interno</th>
                        <th style="font-weight:bold; padding: 12px; width: 12%;">Tipo</th>
                        <th style="font-weight:bold; padding: 12px; width: 25%;">Vista Botón</th>
                        <th style="font-weight:bold; padding: 12px; width: 25%;">Icono / Imagen Configurada</th>
                        <th style="font-weight:bold; padding: 12px; width: 18%; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($globalActions)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 25px; color:#666;">No has creado ningún botón global aún.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($globalActions as $action): ?>
                            <?php 
                                $iconValue = $action['icon'] ?? '';
                                $textColor = $action['text_color'] ?? '#ffffff';
                                
                                $iconHtml = '';
                                if (!empty($iconValue)) {
                                    if (str_starts_with($iconValue, 'http') || str_starts_with($iconValue, '/') || str_contains($iconValue, '.')) {
                                        $iconHtml = '<img src="' . esc_url($iconValue) . '" style="width:14px; height:14px; object-fit:contain; display:inline-block; vertical-align:middle; margin-right:5px;" />';
                                    } else {
                                        $iconHtml = '<i data-lucide="' . esc_attr($iconValue) . '" style="width:14px; height:14px; color:' . esc_attr($textColor) . '; display:inline-block; vertical-align:middle; margin-right:5px;"></i>';
                                    }
                                }
                            ?>
                            <tr>
                                <td style="font-weight:600; padding:12px;"><?php echo esc_html($action['name']); ?></td>
                                <td style="padding:12px;"><span class="badge" style="background:#f1f1f1; padding:3px 8px; border-radius:10px; font-size:11px;"><?php echo esc_html($action['type']); ?></span></td>
                                <td style="padding:12px;">
                                    <span style="display:inline-flex; align-items:center; justify-content:center; gap:5px; padding: 6px 14px; background: <?php echo esc_attr($action['color']); ?>; color: <?php echo esc_attr($textColor); ?>; border-radius: 4px; font-size: 11px; font-weight: bold; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <?php echo $iconHtml; ?>
                                        <?php echo esc_html($action['label']); ?>
                                    </span>
                                </td>
                                <td style="padding:12px; color: #666; font-size: 12px; word-break: break-all;">
                                    <code><?php echo esc_html($action['icon'] ?: 'Ninguno'); ?></code>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <button type="button" class="button button-secondary atlas-edit-trigger" 
                                            data-action="<?php echo esc_attr(json_encode($action)); ?>" 
                                            style="margin-right: 5px;">Editar</button>

                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar esta acción comercial?');">
                                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                                        <input type="hidden" name="delete_action_id" value="<?php echo esc_attr($action['id']); ?>">
                                        <input type="submit" name="atlas_delete_action" class="button button-link-delete" value="Eliminar" style="color:#a00; font-weight:bold;">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="atlasEditModal" class="atlas-modal">
            <div class="atlas-modal-content">
                <span class="atlas-close-modal">&times;</span>
                <h2 style="margin-top:0; font-weight: 800; border-bottom: 1px solid #eee; padding-bottom: 12px;">✏️ Editar Acción Comercial</h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                    <input type="hidden" id="edit_action_id" name="edit_action_id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Nombre Interno (Para ti):</label>
                            <input type="text" id="edit_action_name" name="edit_action_name" required style="width:100%;">
                        </p>
                        <p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Tipo de Acción:</label>
                            <select id="edit_action_type" name="edit_action_type" style="width:100%; height: 30px;">
                                <option value="whatsapp">WhatsApp Directo</option>
                                <option value="link">Enlace Personalizado</option>
                                <option value="cart">Carrito WooCommerce (Compra Directa)</option>
                            </select>
                        </p>
                        <p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Texto del Botón (Público):</label>
                            <input type="text" id="edit_action_label" name="edit_action_label" required style="width:100%;">
                        </p>
                        <p>
                            <label id="edit_url_label" style="font-weight:bold; display:block; margin-bottom:5px;">Enlace / Destino:</label>
                            <input type="text" id="edit_action_url" name="edit_action_url" required style="width:100%;">
                        </p>
                        <p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Color de Fondo del Botón:</label>
                            <input type="color" id="edit_action_color" name="edit_action_color" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                        </p>
                        <p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Color del Texto e Icono:</label>
                            <input type="color" id="edit_action_text_color" name="edit_action_text_color" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                        </p>
                        
                        <p style="grid-column: span 2; margin-bottom: 0;">
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Icono Vectorial o URL Imagen:</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" id="edit_action_icon" name="edit_action_icon" style="flex:1;" placeholder="Ej: shopping-cart o URL de imagen">
                                <button type="button" class="button atlas-upload-button" data-target="edit_action_icon">Subir Icono</button>
                            </div>
                        </p>
                    </div>
                    
                    <p style="margin-top: 25px; text-align: right; border-top: 1px solid #eee; padding-top: 15px; margin-bottom: 0;">
                        <button type="button" class="button button-link atlas-close-modal-btn" style="margin-right:10px;">Cancelar</button>
                        <input type="submit" name="atlas_update_action" class="button button-primary" value="Guardar Cambios">
                    </p>
                </form>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                $('#atlas_action_type').change(function() {
                    var type = $(this).val();
                    if (type === 'cart') {
                        $('#atlas_url_label').text('ID del Producto WooCommerce:');
                        $('#atlas_action_url').attr('placeholder', 'Ej: 342').attr('type', 'number');
                    } else if (type === 'whatsapp') {
                        $('#atlas_url_label').text('Número de Teléfono WhatsApp:');
                        $('#atlas_action_url').attr('placeholder', 'Ej: +56912345678').attr('type', 'text');
                    } else {
                        $('#atlas_url_label').text('Enlace / Destino:');
                        $('#atlas_action_url').attr('placeholder', 'Ej: https://...').attr('type', 'text');
                    }
                }).trigger('change');

                $('#edit_action_type').change(function() {
                    var type = $(this).val();
                    if (type === 'cart') {
                        $('#edit_url_label').text('ID del Producto WooCommerce:');
                        $('#edit_action_url').attr('placeholder', 'Ej: 342').attr('type', 'number');
                    } else if (type === 'whatsapp') {
                        $('#edit_url_label').text('Número de Teléfono WhatsApp:');
                        $('#edit_action_url').attr('placeholder', 'Ej: +56912345678').attr('type', 'text');
                    } else {
                        $('#edit_url_label').text('Enlace / Destino:');
                        $('#edit_action_url').attr('placeholder', 'Ej: https://...').attr('type', 'text');
                    }
                });

                $(document).on('click', '.atlas-upload-button', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetId = button.data('target');
                    var targetInput = $('#' + targetId);

                    var mediaUploader = wp.media({
                        title: 'Selecciona o Sube el Icono Corporativo',
                        button: { text: 'Usar este icono' },
                        multiple: false
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        targetInput.val(attachment.url);
                    });

                    mediaUploader.open();
                });

                var modal = $('#atlasEditModal');

                $('.atlas-edit-trigger').click(function() {
                    var rawData = $(this).data('action');
                    
                    $('#edit_action_id').val(rawData.id);
                    $('#edit_action_name').val(rawData.name);
                    $('#edit_action_type').val(rawData.type).trigger('change');
                    $('#edit_action_label').val(rawData.label);
                    $('#edit_action_url').val(rawData.url);
                    $('#edit_action_color').val(rawData.color);
                    $('#edit_action_text_color').val(rawData.text_color ? rawData.text_color : '#ffffff');
                    $('#edit_action_icon').val(rawData.icon ? rawData.icon : '');

                    modal.fadeIn(200);
                });

                $('.atlas-close-modal, .atlas-close-modal-btn').click(function() {
                    modal.fadeOut(200);
                });

                $(window).click(function(event) {
                    if (event.target == modal[0]) {
                        modal.fadeOut(200);
                    }
                });
            });
        </script>
        <?php
    }
}