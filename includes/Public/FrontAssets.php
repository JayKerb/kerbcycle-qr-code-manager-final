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

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kerbcycle_scanner')) {
            wp_enqueue_script(
                'html5-qrcode',
                'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
                [],
                '2.3.8',
                true
            );
            wp_enqueue_script(
                'kerbcycle-qr-frontend-js',
                KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js',
                ['html5-qrcode'],
                '1.0',
                true
            );

            wp_localize_script('kerbcycle-qr-frontend-js', 'kerbcycle_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kerbcycle_qr_nonce')
            ]);
        }
    }
}
