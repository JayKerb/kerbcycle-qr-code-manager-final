<?php

namespace Kerbcycle\QrCode\Public;

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
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_scanner = has_shortcode($post->post_content, 'kerbcycle_scanner');
        $has_table = has_shortcode($post->post_content, 'kerbcycle_qr_table');
        $has_map = has_shortcode($post->post_content, 'kerbcycle_osrm_map');

        if (!$has_scanner && !$has_table && !$has_map) {
            return;
        }

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

        if ($has_map) {
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

            $options = self::get_options();
            wp_localize_script('kc-osrm', 'KC_OSRM', [
                'endpoint'   => trailingslashit(self::current_endpoint($options)) . 'route/v1/' . $options['profile'],
                'tileUrl'    => $options['tile_url'],
                'tileAttrib' => $options['tile_attrib'],
            ]);
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

    /**
     * Retrieve stored options merged with defaults.
     */
    private static function get_options()
    {
        return wp_parse_args(get_option('kerbcycle_osrm_options', []), self::defaults());
    }

    /**
     * Provide default option values.
     */
    private static function defaults()
    {
        return [
            'env'              => 'dev',
            'endpoint_dev'     => 'https://router.project-osrm.org',
            'endpoint_stage'   => '',
            'endpoint_prod'    => '',
            'profile'          => 'driving',
            'tile_url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attrib'      => '© OpenStreetMap',
            'deny_demo_in_prod' => 1,
            'timeout'          => 10,
        ];
    }

    /**
     * Determine the current endpoint based on selected environment.
     */
    private static function current_endpoint($options = null)
    {
        $options = $options ?: self::get_options();
        $environment = $options['env'];
        $map = [
            'dev'   => $options['endpoint_dev'],
            'stage' => $options['endpoint_stage'],
            'prod'  => $options['endpoint_prod'],
        ];
        $url = isset($map[$environment]) ? rtrim((string) $map[$environment], '/') : '';

        /**
         * Filter the resolved endpoint URL.
         */
        return apply_filters('kerbcycle/osrm/endpoint', $url, $options);
    }
}
