<?php

namespace Kerbcycle\QrCode\Public;

use Kerbcycle\QrCode\Services\OsrmService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Public
 */
class FrontAssets
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue the frontend scripts.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        // Always register OSRM assets so they are available to the shortcode.
        wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], null);
        wp_register_style('lrm', 'https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css', [], null);
        wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
        wp_register_script('lrm', 'https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js', ['leaflet'], null, true);
        wp_register_script(
            'kc-osrm',
            KERBCYCLE_QR_URL . 'assets/js/kc-osrm.js',
            ['leaflet', 'lrm'],
            KERBCYCLE_QR_VERSION,
            true
        );

        // Localize the main script with data.
        $options = OsrmService::get_options();
        $base_url = rtrim(OsrmService::current_endpoint($options), '/');
        wp_localize_script('kc-osrm', 'KC_OSRM', [
            'base'       => $base_url . '/route/v1',
            'profile'    => $options['profile'],
            'tileUrl'    => $options['tile_url'],
            'tileAttrib' => $options['tile_attrib'],
        ]);

        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_scanner = has_shortcode($post->post_content, 'kerbcycle_scanner');
        $has_table = has_shortcode($post->post_content, 'kerbcycle_qr_table');

        // Conditionally load assets for the other shortcodes.
        if ($has_scanner || $has_table) {
            $deps = [];
            if ($has_scanner) {
                wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
                $deps[] = 'html5-qrcode';

                wp_enqueue_script(
                    'zxing-browser',
                    'https://unpkg.com/@zxing/browser@latest',
                    [],
                    null,
                    true
                );
                $deps[] = 'zxing-browser';

                wp_enqueue_script(
                    'jsqr',
                    'https://unpkg.com/jsqr/dist/jsQR.js',
                    [],
                    null,
                    true
                );
                $deps[] = 'jsqr';
            }

            wp_enqueue_style(
                'kerbcycle-qr-frontend-css',
                KERBCYCLE_QR_URL . 'assets/css/public.css',
                [],
                filemtime(KERBCYCLE_QR_PATH . 'assets/css/public.css')
            );

            wp_enqueue_script(
                'kerbcycle-qr-frontend-js',
                KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js',
                $deps,
                filemtime(KERBCYCLE_QR_PATH . 'assets/js/qr-scanner.js'),
                true
            );

            wp_localize_script('kerbcycle-qr-frontend-js', 'kerbcycle_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kerbcycle_qr_nonce'),
                'scanner_enabled' => $has_scanner,
            ]);
        }
    }

}
