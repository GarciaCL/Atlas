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
        // Cargamos la librería de Lucide Icons para renderizar los iconos en la tabla de administración
        wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@latest', [], null, true);
    }

    public function renderDashboard(): void
    {
        // CORRECCIÓN: Llamamos al método expuesto en el AnalyticsService de Dominio
        $gaps = $this->analyticsService->getCriticalGaps(15);
        ?>
        <div class="wrap">
            <h1 style="font-weight: 800; margin-bottom: 5px;">🧠 Analíticas Atlas KOS</h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">Monitorea las consultas de tus clientes que requieren de tu atención.</p>
            <hr class="wp-header-end">
            <h2 style="margin-top:20px; font-weight: 700;">⚠️ Brechas de Conocimiento Críticas</h2>
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
                                🎉 ¡Felicitaciones! No hay preguntas huérfanas registradas.
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
        $chatColor = get_option('atlas_chat_color', '#007cba');
        $chatIconColor = get_option('atlas_chat_icon_color', '#ffffff');
        $chatIcon = get_option('atlas_chat_icon', 'message-square');
        $globalActions = get_option('atlas_global_actions', []);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('atlas_settings_nonce_action', 'atlas_settings_nonce')) {
            
            // Guardar Burbuja de Chat
            if (isset($_POST['atlas_save_general_settings'])) {
                update_option('atlas_chat_color', sanitize_hex_color($_POST['atlas_chat_color']));
                update_option('atlas_chat_icon_color', sanitize_hex_color($_POST['atlas_chat_icon_color']));
                update_option('atlas_chat_icon', sanitize_text_field($_POST['atlas_chat_icon']));
                $chatColor = sanitize_hex_color($_POST['atlas_chat_color']);
                $chatIconColor = sanitize_hex_color($_POST['atlas_chat_icon_color']);
                $chatIcon = sanitize_text_field($_POST['atlas_chat_icon']);
                echo '<div class="notice notice-success is-dismissible"><p>Estilo general del chat actualizado correctamente.</p></div>';
            }

            // Crear Acción Comercial
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

            // Editar Acción Comercial existente
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

            // Eliminar Acción Comercial
            if (isset($_POST['atlas_delete_action'])) {
                $deleteId = sanitize_text_field($_POST['delete_action_id']);
                if (isset($globalActions[$deleteId])) {
                    unset($globalActions[$deleteId]);
                    update_option('atlas_global_actions', $globalActions);
                    echo '<div class="notice notice-warning is-dismissible"><p>Acción comercial eliminada globalmente.</p></div>';
                }
            }
        }
        ?>
        <style>
            .atlas-modal {
                display: none; 
                position: fixed; 
                z-index: 99999; 
                left: 0; 
                top: 0; 
                width: 100%; 
                height: 100%; 
                overflow: auto; 
                background-color: rgba(0,0,0,0.5);
            }
            .atlas-modal-content {
                background-color: #fefefe;
                margin: 7% auto; 
                padding: 25px; 
                border: 1px solid #888;
                width: 50%;
                min-width: 450px;
                border-radius: 6px;
                position: relative;
                box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            }
            .atlas-close-modal {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                line-height: 20px;
            }
            .atlas-close-modal:hover {
                color: #000;
            }
        </style>

        <div class="wrap">
            <h1 style="font-weight: 800; margin-bottom: 5px;">⚙️ Ajustes y Personalización de Atlas</h1>
            <p class="description" style="font-size: 14px; margin-bottom: 25px;">Configura la apariencia y crea la botonera comercial para tus páginas y flujos transaccionales.</p>
            
            <hr class="wp-header-end">

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 25px;">
                
                <!-- SECCIÓN 1: BURBUJA FLOTANTE -->
                <div class="card" style="flex: 1; min-width: 320px; padding: 25px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
                    <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 700;">🎨 Burbuja de Chat</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('atlas_settings_nonce_action', 'atlas_settings_nonce'); ?>
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Color de Identidad:</label>
                                <input type="color" name="atlas_chat_color" value="<?php echo esc_attr($chatColor); ?>" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                            <p style="margin: 0; flex: 1;">
                                <label style="font-weight:bold; display:block; margin-bottom:5px;">Color del Icono:</label>
                                <input type="color" name="atlas_chat_icon_color" value="<?php echo esc_attr($chatIconColor); ?>" style="width: 50px; height: 35px; border-radius: 4px; cursor: pointer; border: 1px solid #ccc;">
                            </p>
                        </div>
                        <p style="margin-bottom: 15px;">
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Icono de la Burbuja:</label>
                            <div style="display: flex; gap: 5px; margin-bottom: 5px;">
                                <input type="text" id="atlas_chat_icon" name="atlas_chat_icon" value="<?php echo esc_attr($chatIcon); ?>" style="flex:1; padding: 6px;" placeholder="Ej: message-square o URL de imagen">
                                <button type="button" class="button atlas-upload-button" data-target="atlas_chat_icon">Subir Icono</button>
                            </div>
                            <span class="description" style="font-size:11px; display:block; line-height: 1.4;">
                                💡 Escribe el nombre de un icono de <a href="https://lucide.dev/icons" target="_blank" style="font-weight: bold; color: #007cba;">Lucide Icons</a> o sube tu logo.
                            </span>
                        </p>
                        <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                            <input type="submit" name="atlas_save_general_settings" class="button button-secondary" value="Guardar Aspecto Chat">
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
                                <input type="text" name="action_name" required style="width:100%;" placeholder="Ej: Añadir Curso de PHP al Carro">
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
                            
                            <p style="grid-column: span 2; margin-top: 0;">
                                <span class="description" style="font-size:11px; display:block; line-height: 1.4;">
                                    💡 Visita la web <a href="https://lucide.dev/icons" target="_blank" style="font-weight: bold; color: #007cba;">Lucide Icons</a>, busca un icono, copia el nombre (ej. <code>shopping-cart</code>) y pégalo aquí. O haz clic en "Subir Icono" para cargar tu logo corporativo.
                                </span>
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
                                    <span style="display:inline-flex; align-items:center; justify-content:center; gap:5px; padding: 6px 14px; background: <?php echo esc_attr($action['color']); ?>; color: <?php echo esc_attr($textColor); ?>; border-radius: 4px; font-size: 11px; font-weight: bold; box-shadow: 0 1px 3px rgba(0,0,0,0.1); ">
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

        <!-- MODAL DE EDICIÓN -->
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
                        <p style="grid-column: span 2; margin-top: 0;">
                            <span class="description" style="font-size:11px; display:block; line-height: 1.4;">
                                💡 Visita la web <a href="https://lucide.dev/icons" target="_blank" style="font-weight: bold; color: #007cba;">Lucide Icons</a>, busca un icono, copia el nombre y pégalo aquí. O sube tu logotipo corporativo.
                            </span>
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
                // Forzar inicialización de iconos de Lucide en la tabla administrativa
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                // Lógica de cambio dinámico de placeholders en "Crear Acción"
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

                // Lógica de cambio dinámico de placeholders en "Editar Acción" (Modal)
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

                // Control del cargador de medios multimedia de WordPress
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

                // --- LÓGICA DEL MODAL DE EDICIÓN ---
                var modal = $('#atlasEditModal');

                // Abrir el modal y cargar la información correspondiente
                $('.atlas-edit-trigger').click(function() {
                    var rawData = $(this).data('action');
                    
                    // Cargar datos en los inputs del modal
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

                // Funciones para cerrar el modal
                $('.atlas-close-modal, .atlas-close-modal-btn').click(function() {
                    modal.fadeOut(200);
                });

                // Cerrar modal al hacer clic fuera del contenedor blanco
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