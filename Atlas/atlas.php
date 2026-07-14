<?php
/**
 * Plugin Name:       Atlas KOS
 * Plugin URI:        https://atlasai.network
 * Description:       Turn your WordPress into a Knowledge Platform.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Atlas Core Team
 * License:           GPL-3.0-or-later
 * Text Domain:       atlas-kos
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Cargar el Autoloader de Composer (Agnóstico y Seguro en Local)
$autoload_path = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // Alerta amigable en el panel de WP en caso de que falte el autoloader en local
    add_action('admin_notices', function() use ($autoload_path) {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo sprintf(
            __('<strong>Atlas KOS Error:</strong> No se encuentra el autoloader de Composer. Por favor, ejecuta <code>composer install</code> o <code>composer dump-autoload</code> en la carpeta del plugin (Ruta analizada: %s).', 'atlas-kos'),
            esc_html($autoload_path)
        );
        echo '</p></div>';
    });
    return;
}

// 2. Inicializar el Kernel de Atlas de forma síncrona, limpia y sin Singletons
try {
    if (class_exists('\\Atlas\\Core\\Kernel')) {
        $kernel = new \Atlas\Core\Kernel();
        $kernel->boot();
    } else {
        throw new \Exception('La clase \Atlas\Core\Kernel no se encuentra registrada en el autoloader de Composer. Asegúrate de regenerar el mapa de clases con "composer dump-autoload".');
    }
} catch (\Throwable $e) {
    // Capturar errores catastróficos de inicialización de forma segura
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Atlas KOS falló al arrancar: ', 'atlas-kos') . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}