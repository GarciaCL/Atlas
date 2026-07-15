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

// 1. Cargar el Autoloader de Composer (Agnóstico)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 2. Inicializar el Kernel de Atlas de forma síncrona, limpia y sin Singletons
try {
    $kernel = new \Atlas\Core\Kernel();
    $kernel->boot();
} catch (\Throwable $e) {
    // Evitar pantallas en blanco en WP, capturar errores catastróficos de inicialización
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_die(esc_html__('Atlas KOS failed to boot: ', 'atlas-kos') . esc_html($e->getMessage()));
    }
}