<?php

namespace Atlas\WordPress\Admin;

use Atlas\Analytics\AnalyticsService;

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
            'Personalización',
            'Personalización',
            'manage_options',
            'atlas-settings',
            [$this, 'renderSettings']
        );

        add_action("admin_print_scripts-{$settingsPage}", [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets(): void
    {
        wp_enqueue_media();
        // Cargar Lucide para vista de panel
        wp_enqueue_script('lucide-cdn', 'https://unpkg.com/lucide@latest', [], null, true);
    }

    public function renderDashboard(): void
    {
        $unanswered = $this->analyticsService->getTopUnansweredQuestions(15);
        ?>
        <div class="wrap">
            <h1>📊 Atlas KOS - Panel de Consultas Huérfanas</h1>
            <p class="description">Aquí puedes analizar qué consultas de tus clientes no obtuvieron respuestas para optimizar tu base de conocimientos.</p>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Pregunta del Cliente</th>
                        <th>Visto en (URL)</th>
                        <th>Veces Preguntada</th>
                        <th>Fecha de Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($unanswered)): ?>
                        <tr>
                            <td colspan="4">🎉 ¡Excelente! No tienes preguntas huérfanas registradas. Tu base está respondiendo todo.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($unanswered as $item): ?>
                            <tr>
                                <td style="font-weight: bold;"><?php echo esc_html($item['question']); ?></td>
                                <td><a href="<?php echo esc_url($item['url']); ?>" target="_blank"><?php echo esc_html($item['url']); ?></a></td>
                                <td><span class="badge" style="background: #e1f5fe; color: #0288d1; padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?php echo esc_html($item['hit_count']); ?></span></td>
                                <td><?php echo esc_html($item['created_at']); ?></td>
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
        // 1. Obtener opciones guardadas o valores por defecto
        $chatColor = get_option('atlas_chat_color', '#10b981');
        $chatIcon = get_option('atlas_chat_icon', 'message-square');
        $chatTitleText = get_option('atlas_chat_title_text', 'Asistente Atlas');
        $chatHeaderBg = get_option('atlas_chat_header_bg', '#10b981');
        $chatHeaderTextColor = get_option('atlas_chat_header_text_color', '#ffffff');
        $chatFallbackActions = get_option('atlas_chat_fallback_actions', []);

        $globalActions = get_option('atlas_global_actions', []);

        // 2. Procesar el Guardado de Formularios de la Burbuja y Cabecera (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('atlas_settings_nonce_action', 'atlas_settings_nonce')) {
            
            // Guardar estilo general y cabecera
            if (isset($_POST['atlas_save_general_settings'])) {
                update_option('atlas_chat_color', sanitize_hex_color($_POST['atlas_chat_color']));
                update_option('atlas_chat_icon', sanitize_text_field($_POST['atlas_chat_icon']));
                update_option('atlas_chat_title_text', sanitize_text_field($_POST['atlas_chat_title_text']));
                update_option('atlas_chat_header_bg', sanitize_hex_color($_POST['atlas_chat_header_bg']));
                update_option('atlas_chat_header_text_color', sanitize_hex_color($_POST['atlas_chat_header_text_color']));
                
                $fallbackSelected = isset($_POST['atlas_fallback_actions']) ? array_map('sanitize_text_field', $_POST['atlas_fallback_actions']) : [];
                update_option('atlas_chat_fallback_actions', $fallbackSelected);

                // Recargar variables en tiempo real
                $chatColor = sanitize_hex_color($_POST['atlas_chat_color']);
                $chatIcon = sanitize_text_field($_POST['atlas_chat_icon']);
                $chatTitleText = sanitize_text_field($_POST['atlas_chat_title_text']);
                $chatHeaderBg = sanitize_hex_color($_POST['atlas_chat_header_bg']);
                $chatHeaderTextColor = sanitize_hex_color($_POST['atlas_chat_header_text_color']);
                $chatFallbackActions = $fallbackSelected;

                echo '<div class="notice notice-success is-dismissible"><p>Configuración de marca y comportamiento guardados correctamente.</p></div>';
            }

            // Crear Acción Comercial
            if (isset($_POST['atlas_create_action'])) {
                $actionId = 'action_' . uniqid();
                $actionType = sanitize_text_field($_POST['action_type']);
                
                $actionUrl = '';
                if ($actionType === 'whatsapp') {
                    $actionUrl = sanitize_text_field($_POST['action_whatsapp'] ?? '');
                } elseif ($actionType === 'cart') {
                    $actionUrl = sanitize_text_field($_POST['action_product_id'] ?? '');
                } else {
                    $actionUrl = esc_url_raw($_POST['action_url'] ?? '');
                }

                $newAction = [
                    'id' => $actionId,
                    'name' => sanitize_text_field($_POST['action_name']),
                    'type' => $actionType,
                    'label' => sanitize_text_field($_POST['action_label']),
                    'url' => $actionUrl,
                    'color' => sanitize_hex_color($_POST['action_color']),
                    'text_color' => sanitize_hex_color($_POST['action_text_color'] ?? '#ffffff'),
                    'icon' => sanitize_text_field($_POST['action_icon'] ?? '')
                ];

                $globalActions[$actionId] = $newAction;
                update_option('atlas_global_actions', $globalActions);

                echo '<div class="notice notice-success is-dismissible"><p>Acción comercial guardada correctamente.</p></div>';
            }

            // Eliminar Acción Comercial
            if (isset($_POST['atlas_delete_action'])) {
                $deleteId = sanitize_text_field($_POST['delete_action_id']);
                if (isset($globalActions[$deleteId])) {
                    unset($globalActions[$deleteId]);
                    update_option('atlas_global_actions', $globalActions);

                    if (($key = array_search($deleteId, $chatFallbackActions)) !== false) {
                        unset($chatFallbackActions[$key]);
                        update_option('atlas_chat_fallback_actions', array_values($chatFallbackActions));
                    }

                    echo '<div class="notice notice-warning is-dismissible"><p>Acción comercial eliminada correctamente.</p></div>';
                }
            }
        }
        ?>

        <style>
            .atlas-admin-wrapper { max-width: 1200px; margin-top: 20px; }
            .atlas-grid-row { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
            .atlas-column-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 24px; flex: 1; min-width: 340px; box-sizing: border-box; }
            .atlas-form-field { margin-bottom: 16px; }
            .atlas-form-field label { display: block; font-weight: bold; margin-bottom: 6px; }
            .atlas-input-element { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
            .atlas-color-row { display: flex; gap: 10px; }
            .atlas-color-row .atlas-form-field { flex: 1; }
            .atlas-btn-submit { background-color: #2271b1 !important; color: #fff !important; border: none !important; padding: 10px 18px !important; border-radius: 4px !important; cursor: pointer; font-weight: bold; }
            .atlas-btn-submit:hover { background-color: #135e96 !important; }
            .atlas-badge-preview { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 50px; font-size: 11px; font-weight: bold; }
            .atlas-checklist { max-height: 120px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        </style>

        <div class="wrap atlas-admin-wrapper">
            <h1>⚙️ Personalización y Aspecto de Atlas</h1>
            <p class="description">Personaliza el título del chat, los colores corporativos de tu marca y asigna flujos de contingencia en caso de que el asistente no encuentre respuestas.</p>
            <hr class="wp-header-end">

            <div class="atlas-grid-row">
                
                <!-- SECCIÓN 1: CABECERA Y ESTILO DE BURBUJA -->
                <div class="atlas-column-card">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">🎨 Personalización del Chat</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                        
                        <div class="atlas-form-field">
                            <label>Título en Cabecera:</label>
                            <input type="text" name="atlas_chat_title_text" value="<?php echo esc_attr($chatTitleText); ?>" class="atlas-input-element" placeholder="Ej: Soporte Inteligente">
                        </div>

                        <div class="atlas-color-row">
                            <div class="atlas-form-field">
                                <label>Fondo Cabecera:</label>
                                <input type="color" name="atlas_chat_header_bg" value="<?php echo esc_attr($chatHeaderBg); ?>" style="width:100%; height:38px; cursor:pointer;">
                            </div>
                            <div class="atlas-form-field">
                                <label>Texto Cabecera:</label>
                                <input type="color" name="atlas_chat_header_text_color" value="<?php echo esc_attr($chatHeaderTextColor); ?>" style="width:100%; height:38px; cursor:pointer;">
                            </div>
                        </div>

                        <div class="atlas-form-field">
                            <label>Color General de Burbuja:</label>
                            <input type="color" name="atlas_chat_color" value="<?php echo esc_attr($chatColor); ?>" style="width:100%; height:38px; cursor:pointer;">
                        </div>

                        <div class="atlas-form-field">
                            <label>Icono de Burbuja (Lucide / URL):</label>
                            <div style="display:flex; gap:8px;">
                                <input type="text" id="atlas_chat_icon" name="atlas_chat_icon" value="<?php echo esc_attr($chatIcon); ?>" class="atlas-input-element" placeholder="Ej: message-square">
                                <button type="button" class="button atlas-upload-button" data-target="atlas_chat_icon">Subir</button>
                            </div>
                        </div>

                        <!-- ⚠️ FALLBACK CHECKS -->
                        <div class="atlas-form-field" style="border-top:1px solid #eee; padding-top:15px;">
                            <label>Botones de Fallback Automático:</label>
                            <span class="description" style="font-size:11px; display:block; margin-bottom:8px;">Elige los botones comerciales que se ofrecerán al usuario cuando el asistente no encuentre información.</span>
                            
                            <div class="atlas-checklist">
                                <?php if (empty($globalActions)): ?>
                                    <span style="color:#888; font-size:12px;">Crea primero una acción a la derecha para seleccionarla.</span>
                                <?php else: ?>
                                    <?php foreach ($globalActions as $action): ?>
                                        <label style="display:block; font-weight:normal; margin-bottom:5px; cursor:pointer;">
                                            <input type="checkbox" name="atlas_fallback_actions[]" value="<?php echo esc_attr($action['id']); ?>" <?php checked(in_array($action['id'], $chatFallbackActions)); ?>>
                                            <?php echo esc_html($action['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                            <input type="submit" name="atlas_save_general_settings" class="atlas-btn-submit" style="width:100%;" value="Guardar Cambios de Burbuja">
                        </div>
                    </form>
                </div>

                <!-- SECCIÓN 2: CREACIÓN DE ACCIONES GLOBALES -->
                <div class="atlas-column-card" style="flex:1.3;">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">🚀 Registrar Nueva Acción Comercial</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                        
                        <div class="atlas-color-row">
                            <div class="atlas-form-field">
                                <label>Nombre Interno:</label>
                                <input type="text" name="action_name" class="atlas-input-element" placeholder="Ej: WhatsApp Soporte" required>
                            </div>
                            <div class="atlas-form-field">
                                <label>Tipo de Acción:</label>
                                <select name="action_type" id="action_type" class="atlas-input-element" style="height:38px;" onchange="toggleActionFields(this.value)" required>
                                    <option value="whatsapp">WhatsApp Directo</option>
                                    <option value="cart">Añadir al Carro (WooCommerce)</option>
                                    <option value="link">Enlace de Redirección</option>
                                </select>
                            </div>
                        </div>

                        <div class="atlas-form-field">
                            <label>Texto del Botón:</label>
                            <input type="text" name="action_label" class="atlas-input-element" placeholder="Ej: Hablar por WhatsApp" required>
                        </div>

                        <div class="atlas-form-field dynamic-action-field" id="field-whatsapp">
                            <label>Teléfono (Incluye código de país sin +):</label>
                            <input type="text" name="action_whatsapp" class="atlas-input-element" placeholder="Ej: 56912345678">
                        </div>

                        <div class="atlas-form-field dynamic-action-field" id="field-cart" style="display:none;">
                            <label>ID del Producto WooCommerce:</label>
                            <input type="number" name="action_product_id" class="atlas-input-element" placeholder="Ej: 4321">
                        </div>

                        <div class="atlas-form-field dynamic-action-field" id="field-link" style="display:none;">
                            <label>URL de Destino:</label>
                            <input type="url" name="action_url" class="atlas-input-element" placeholder="Ej: https://tusitio.com/oferta">
                        </div>

                        <div class="atlas-color-row">
                            <div class="atlas-form-field">
                                <label>Fondo Botón:</label>
                                <input type="color" name="action_color" value="#007cba" style="width:100%; height:38px; cursor:pointer;">
                            </div>
                            <div class="atlas-form-field">
                                <label>Texto Botón:</label>
                                <input type="color" name="action_text_color" value="#ffffff" style="width:100%; height:38px; cursor:pointer;">
                            </div>
                        </div>

                        <div class="atlas-form-field">
                            <label>Icono (Lucide / URL):</label>
                            <div style="display:flex; gap:8px;">
                                <input type="text" id="action_icon" name="action_icon" class="atlas-input-element" placeholder="Ej: shopping-cart">
                                <button type="button" class="button atlas-upload-button" data-target="action_icon">Subir</button>
                            </div>
                        </div>

                        <div style="margin-top:15px; border-top:1px solid #eee; padding-top:15px;">
                            <input type="submit" name="atlas_create_action" class="atlas-btn-submit" style="width:100%;" value="Crear Acción Global">
                        </div>
                    </form>
                </div>

            </div>

            <!-- TABLA DE ACCIONES REGISTRADAS -->
            <div class="atlas-column-card" style="margin-top:25px; width:100%;">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">📋 Listado de Acciones Registradas</h3>
                <?php if (empty($globalActions)): ?>
                    <p style="color:#777; font-style:italic;">No hay acciones comerciales creadas todavía.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nombre Interno</th>
                                <th>Tipo</th>
                                <th>Vista Previa Botón</th>
                                <th>Icono</th>
                                <th style="width:100px; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($globalActions as $action): ?>
                                <tr>
                                    <td style="font-weight:bold;"><?php echo esc_html($action['name']); ?></td>
                                    <td>
                                        <span style="background:#e0f2f1; color:#00695c; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:bold; text-transform:uppercase;">
                                            <?php echo esc_html($action['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="atlas-badge-preview" style="background-color:<?php echo esc_attr($action['color']); ?>; color:<?php echo esc_attr($action['text_color'] ?? '#ffffff'); ?>;">
                                            <?php echo esc_html($action['label']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo esc_html($action['icon'] ?: 'Ninguno'); ?></code></td>
                                    <td style="text-align:center;">
                                        <form method="post" action="" onsubmit="return confirm('¿Eliminar esta acción comercial de forma definitiva?');">
                                            <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                                            <input type="hidden" name="delete_action_id" value="<?php echo esc_attr($action['id']); ?>">
                                            <input type="submit" name="atlas_delete_action" class="button button-link-delete" value="Eliminar" style="color:#b32d2e; font-weight:bold;">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>

        <script>
            function toggleActionFields(type) {
                document.querySelectorAll('.dynamic-action-field').forEach(function(el) {
                    el.style.display = 'none';
                });
                const activeField = document.getElementById('field-' + type);
                if (activeField) {
                    activeField.style.display = 'block';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const uploadButtons = document.querySelectorAll('.atlas-upload-button');
                uploadButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('data-target');
                        const targetInput = document.getElementById(targetId);

                        const customUploader = wp.media({
                            title: 'Subir Icono Comercial',
                            button: { text: 'Usar este Archivo' },
                            multiple: false
                        });

                        customUploader.on('select', function() {
                            const attachment = customUploader.state().get('selection').first().toJSON();
                            if (targetInput && attachment.url) {
                                targetInput.value = attachment.url;
                            }
                        });
                        customUploader.open();
                    });
                });
            });
        </script>
        <?php
    }
}