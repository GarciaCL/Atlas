<?php

namespace Atlas\WordPress;

use Atlas\Core\ModuleManager;
use Atlas\Conversation\ConversationService;
use Atlas\Analytics\AnalyticsService;
use Atlas\WordPress\Hooks\MigrationRunner;
use Atlas\WordPress\Hooks\ContentSyncHandler;
use Atlas\WordPress\Providers\WordPressProvider;
use Atlas\WordPress\Repositories\WordPressDocumentRepository;
use Atlas\WordPress\REST\AskController;
use Atlas\Content\ContentService;
use Atlas\WordPress\Admin\AdminDashboardController;
use Atlas\WordPress\Hooks\ActionMetaboxHandler;

class WordPressAdapter
{
    private ModuleManager $moduleManager;
    private ?ConversationService $conversationService = null;
    private ?AnalyticsService $analyticsService = null;

    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Amarra Atlas a los eventos y hooks globales de WordPress.
     */
    public function integrate(ConversationService $conversationService, AnalyticsService $analyticsService): void
    {
        $this->conversationService = $conversationService;
        $this->analyticsService = $analyticsService;

        // 1. Ciclo de Vida de Base de Datos
        register_activation_hook(dirname(__DIR__, 2) . '/atlas.php', [MigrationRunner::class, 'run']);

        // 2. Inicializar el Generador de Acciones Comerciales (Metabox en posts)
        ActionMetaboxHandler::init();

        // 3. Inicialización de componentes cuando WP carga sus plugins
        add_action('plugins_loaded', [$this, 'bootWordPressComponents']);

        // 4. Encolar Assets en el frontend (Widget de Chat)
        add_action('wp_enqueue_scripts', [$this, 'enqueueChatAssets']);

        // 5. Renderizar el contenedor HTML del Chat en el Footer de la Web
        add_action('wp_footer', [$this, 'renderChatWidgetHtml']);
    }

    public function bootWordPressComponents(): void
    {
        // Forzar la creación de tablas si no existen de manera silenciosa y segura
        try {
            global $wpdb;
            $tableName = $wpdb->prefix . 'atlas_unanswered_questions';
            if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
                if (class_exists('Atlas\WordPress\Hooks\MigrationRunner')) {
                    MigrationRunner::run();
                }
            }
            $this->moduleManager->bootModules();
        } catch (\Throwable $e) {
            error_log("Error inicializando módulos en Atlas KOS: " . $e->getMessage());
        }

        // Registramos el controlador de la REST API
        if ($this->conversationService) {
            $askController = new AskController($this->conversationService);
            add_action('rest_api_init', [$askController, 'registerRoutes']);
        }

        // Inicializar el Menú de Administración de forma segura
        if (class_exists('Atlas\WordPress\Admin\AdminDashboardController') && $this->analyticsService) {
            $dashboard = new AdminDashboardController($this->analyticsService);
            add_action('admin_menu', [$dashboard, 'registerMenu']);
        }

        // Inicializamos la sincronización automática de contenido
        try {
            $documentRepo = new WordPressDocumentRepository();
            $contentService = new ContentService($documentRepo);
            $wpProvider = new WordPressProvider();
            
            $syncHandler = new ContentSyncHandler($contentService, $wpProvider);
            add_action('save_post', [$syncHandler, 'handleSavePost'], 10, 3);
        } catch (\Throwable $e) {
            error_log("Error inicializando ContentSync en Atlas KOS: " . $e->getMessage());
        }
    }

    /**
     * Encola los estilos, scripts y pasa las variables de configuración
     * de WordPress hacia el Frontend del Chat (chat.js).
     */
    public function enqueueChatAssets(): void
    {
        // Encolar de manera segura chat.js buscando la raíz relativa del plugin
        $js_url = plugins_url('../Assets/chat.js', dirname(__FILE__, 2));

        wp_enqueue_script(
            'atlas-chat-js', 
            $js_url, 
            ['jquery'], 
            '1.7', // Forzamos actualización de caché
            true
        );

        // Obtener información del usuario logueado actualmente
        $current_user = wp_get_current_user();
        $user_name = '';
        if ($current_user->ID !== 0) {
            $user_name = $current_user->first_name ?: $current_user->display_name;
        }

        // Preparar los botones de fallback (emergencia) dinámicamente con sus URLs
        $fallbackActionIds = get_option('atlas_chat_fallback_actions', []);
        $globalActions = get_option('atlas_global_actions', []);
        $fallbackButtons = [];

        foreach ($fallbackActionIds as $actionId) {
            if (isset($globalActions[$actionId])) {
                $action = $globalActions[$actionId];
                $url = $action['url'];
                
                if ($action['type'] === 'whatsapp' && !empty($url) && !str_starts_with($url, 'http')) {
                    $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $url);
                }
                if ($action['type'] === 'cart' && !empty($url)) {
                    $productId = (int)preg_replace('/[^0-9]/', '', $url);
                    $url = home_url('/?add-to-cart=' . $productId);
                }

                $fallbackButtons[] = [
                    'label' => $action['label'],
                    'url' => $url,
                    'icon' => $action['icon'] ?: null,
                    'styles' => [
                        'backgroundColor' => $action['color'],
                        'color' => $action['text_color'] ?? '#ffffff'
                    ]
                ];
            }
        }

        // Inyectar las configuraciones dinámicas
        wp_localize_script('atlas-chat-js', 'AtlasConfig', [
            'restUrl' => esc_url_raw(rest_url()), // ◄ CLAVE: Pasa la ruta base exacta de la REST API
            'userName' => $user_name,
            'titleText' => get_option('atlas_chat_title_text', 'Asistente Atlas'),
            'headerBg' => get_option('atlas_chat_header_bg', '#10b981'),
            'headerTextColor' => get_option('atlas_chat_header_text_color', '#ffffff'),
            'fallbackButtons' => $fallbackButtons
        ]);
    }

    /**
     * Imprime el contenedor HTML del chat en el pie de página del sitio.
     */
    public function renderChatWidgetHtml(): void
    {
        $chatColor = get_option('atlas_chat_color', '#10b981');
        $chatIcon = get_option('atlas_chat_icon', 'message-square');
        $titleText = get_option('atlas_chat_title_text', 'Asistente Atlas');
        $headerBg = get_option('atlas_chat_header_bg', '#10b981');
        $headerTextColor = get_option('atlas_chat_header_text_color', '#ffffff');
        ?>
        <!-- Cargar Lucide Icons para soportar iconos dinámicos vectoriales -->
        <script src="https://unpkg.com/lucide@latest"></script>

        <style>
            #atlas-chat-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                background-color: <?php echo esc_attr($chatColor); ?>;
                color: <?php echo esc_attr($headerTextColor); ?>;
                border-radius: 50%;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 999999;
                transition: transform 0.2s;
            }
            #atlas-chat-toggle:hover {
                transform: scale(1.05);
            }
            #atlas-chat-widget {
                position: fixed;
                bottom: 90px;
                right: 20px;
                width: 360px;
                height: 500px;
                background: #ffffff;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                border-radius: 12px;
                display: none;
                flex-direction: column;
                overflow: hidden;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .atlas-chat-header {
                padding: 15px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .atlas-chat-messages-container {
                flex: 1;
                padding: 15px;
                overflow-y: auto;
                background: #fdfdfd;
                display: flex;
                flex-direction: column;
            }
            .atlas-chat-input-container {
                display: flex;
                padding: 10px;
                border-top: 1px solid #eee;
                background: #fff;
            }
            .atlas-chat-input-field {
                flex: 1;
                border: 1px solid #ddd;
                border-radius: 20px;
                padding: 8px 14px;
                outline: none;
                font-size: 13px;
            }
            .atlas-chat-send-btn {
                background: none;
                border: none;
                color: <?php echo esc_attr($chatColor); ?>;
                cursor: pointer;
                padding: 5px 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>

        <!-- Botón Flotante para abrir/cerrar -->
        <div id="atlas-chat-toggle">
            <?php if (filter_var($chatIcon, FILTER_VALIDATE_URL) || str_starts_with($chatIcon, '/')): ?>
                <img src="<?php echo esc_url($chatIcon); ?>" style="width: 26px; height: 26px; object-fit: contain;" />
            <?php else: ?>
                <i data-lucide="<?php echo esc_attr($chatIcon); ?>" style="width: 26px; height: 26px;"></i>
            <?php endif; ?>
        </div>

        <!-- Ventana del Chat -->
        <div id="atlas-chat-widget">
            <div class="atlas-chat-header" style="background: <?php echo esc_attr($headerBg); ?>; color: <?php echo esc_attr($headerTextColor); ?>;">
                <h3 class="atlas-chat-title" style="margin: 0; font-size: 15px;"><?php echo esc_html($titleText); ?></h3>
            </div>
            <div class="atlas-chat-messages-container"></div>
            <div class="atlas-chat-input-container">
                <input type="text" class="atlas-chat-input-field" placeholder="Pregúntame algo..." value="" />
                <button class="atlas-chat-send-btn">
                    <i data-lucide="send" style="width: 18px; height: 18px;"></i>
                </button>
            </div>
        </div>

        <script>
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        </script>
        <?php
    }
}