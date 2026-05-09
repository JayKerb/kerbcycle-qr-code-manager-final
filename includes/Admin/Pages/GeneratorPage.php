<?php

namespace Kerbcycle\QrCode\Admin\Pages;

use Kerbcycle\QrCode\Data\Repositories\QrRepoRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page for generating QR codes.
 */
class GeneratorPage {
    /** @var self */
    private static $instance;

    /**
     * Singleton instance.
     */
    public static function instance(): self {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'wp_ajax_kerbcycle_generate_qr', array( $this, 'ajax_generate_qr' ) );
        add_action( 'admin_post_kerbcycle_export_qr_csv', array( $this, 'handle_export_csv' ) );
    }

    /**
     * Register assets for the generator page.
     */
    public function assets( $hook ) {
        if ( strpos( $hook, 'kerbcycle-qr-generator' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'kerbcycle-qrcode',
            KERBCYCLE_QR_URL . 'assets/js/qrcode.min.js',
            array(),
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'kerbcycle-qr-generator',
            KERBCYCLE_QR_URL . 'assets/js/qr-generator.js',
            array( 'jquery', 'kerbcycle-qrcode' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'kerbcycle-qr-generator',
            'KerbcycleQRGen',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'kerbcycle_generate_qr' ),
            )
        );

        wp_enqueue_style(
            'kerbcycle-qr-generator',
            KERBCYCLE_QR_URL . 'assets/css/qr-generator.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Render the admin page.
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kerbcycle-qr-code-manager' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'QR Code Generator', 'kerbcycle-qr-code-manager' ); ?></h1>

            <div class="kc-card">
                <h2><?php esc_html_e( 'Generate Codes', 'kerbcycle-qr-code-manager' ); ?></h2>
                <form id="kc-generate-form" onsubmit="return false;">
                    <div class="kc-row">
                        <label><?php esc_html_e( 'Generate Type', 'kerbcycle-qr-code-manager' ); ?></label>
                        <select id="kc-gen-type">
                            <option value="single"><?php esc_html_e( 'Single (enter exact code)', 'kerbcycle-qr-code-manager' ); ?></option>
                            <option value="batch"><?php esc_html_e( 'Batch (random codes)', 'kerbcycle-qr-code-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="kc-row kc-if-single">
                        <label><?php esc_html_e( 'Code', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="text" id="kc-code" placeholder="e.g. KC-2025-0001" />
                    </div>

                    <div class="kc-row kc-if-batch" style="display:none;">
                        <label><?php esc_html_e( 'How many?', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="number" id="kc-count" min="1" max="5000" value="20" />
                    </div>

                    <div class="kc-row kc-if-batch" style="display:none;">
                        <label><?php esc_html_e( 'Prefix (optional)', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="text" id="kc-prefix" placeholder="e.g. KC-2025-" />
                    </div>

                    <div class="kc-row kc-if-batch" style="display:none;">
                        <label><?php esc_html_e( 'Length (random part)', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="number" id="kc-length" min="4" max="16" value="8" />
                    </div>

                    <div class="kc-row">
                        <button class="button button-primary" id="kc-generate-btn"><?php esc_html_e( 'Generate & Save', 'kerbcycle-qr-code-manager' ); ?></button>
                    </div>

                    <p class="description"><?php esc_html_e( 'Codes are saved to the repository only if unique. Duplicates are skipped.', 'kerbcycle-qr-code-manager' ); ?></p>
                </form>

                <div id="kc-generate-result" class="kc-grid"></div>
            </div>

            <div class="kc-card">
                <h2><?php esc_html_e( 'Export Repository (Date Range)', 'kerbcycle-qr-code-manager' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'kerbcycle_export_qr_csv', 'kc_export_nonce' ); ?>
                    <input type="hidden" name="action" value="kerbcycle_export_qr_csv" />
                    <div class="kc-row">
                        <label><?php esc_html_e( 'From', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="date" name="from" required />
                    </div>
                    <div class="kc-row">
                        <label><?php esc_html_e( 'To', 'kerbcycle-qr-code-manager' ); ?></label>
                        <input type="date" name="to" required />
                    </div>
                    <div class="kc-row">
                        <label><?php esc_html_e( 'Export Type', 'kerbcycle-qr-code-manager' ); ?></label>
                        <select name="format">
                            <option value="print"><?php esc_html_e( 'Printable Sheet (browser print)', 'kerbcycle-qr-code-manager' ); ?></option>
                            <option value="csv"><?php esc_html_e( 'CSV file', 'kerbcycle-qr-code-manager' ); ?></option>
                        </select>
                    </div>
                    <div class="kc-row">
                        <button class="button"><?php esc_html_e( 'Export', 'kerbcycle-qr-code-manager' ); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e( '“Printable Sheet” opens a formatted page you can print to paper or PDF. “CSV” downloads code data.', 'kerbcycle-qr-code-manager' ); ?></p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: generate and save unique codes.
     */
    public function ajax_generate_qr() {
        check_ajax_referer( 'kerbcycle_generate_qr', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No permission', 'kerbcycle-qr-code-manager' ) ), 403 );
        }

        $repo   = new QrRepoRepository();
        $user   = get_current_user_id();
        $type   = sanitize_text_field( wp_unslash( $_POST['genType'] ?? 'single' ) );
        $result = array(
            'saved'   => array(),
            'skipped' => array(),
        );

        if ( 'single' === $type ) {
            $code = trim( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) );
            if ( '' === $code ) {
                wp_send_json_error( array( 'message' => __( 'Code required.', 'kerbcycle-qr-code-manager' ) ), 400 );
            }

            if ( $repo->exists( $code ) ) {
                $result['skipped'][] = $code;
            } else {
                $repo->insert( $code, $user );
                $result['saved'][] = $code;
            }
            wp_send_json_success( $result );
        }

        $count  = max( 1, min( 5000, intval( wp_unslash( $_POST['count'] ?? 20 ) ) ) );
        $prefix = sanitize_text_field( wp_unslash( $_POST['prefix'] ?? '' ) );
        $len    = max( 4, min( 16, intval( wp_unslash( $_POST['length'] ?? 8 ) ) ) );

        if ( '' !== $prefix && ! preg_match( '/^[A-Za-z0-9-]+$/', $prefix ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid prefix.', 'kerbcycle-qr-code-manager' ) ), 400 );
        }

        $attempts    = 0;
        $saved_count = count( $result['saved'] );
        while ( $saved_count < $count ) {
            $rand = wp_generate_password( $len, false, false );
            $code = $prefix . strtoupper( $rand );
            if ( $repo->exists( $code ) ) {
                $result['skipped'][] = $code;
            } else {
                $repo->insert( $code, $user );
                $result['saved'][] = $code;
                ++$saved_count;
            }
            ++$attempts;
            if ( $attempts > $count * 5 ) {
                break; // prevent infinite loops on extreme collisions
            }
        }

        wp_send_json_success( $result );
    }

    /**
     * Handle CSV or printable export.
     */
    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No permission.', 'kerbcycle-qr-code-manager' ) );
        }

        $export_nonce = isset( $_POST['kc_export_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['kc_export_nonce'] ) )
            : '';

        if ( '' === $export_nonce || ! wp_verify_nonce( $export_nonce, 'kerbcycle_export_qr_csv' ) ) {
            wp_die( esc_html__( 'Bad nonce.', 'kerbcycle-qr-code-manager' ) );
        }

        $from   = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
        $to     = sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) );
        $format = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'csv' ) );

        if ( ! $from || ! $to ) {
            wp_die( esc_html__( 'Date range required.', 'kerbcycle-qr-code-manager' ) );
        }

        $repo = new QrRepoRepository();
        $rows = $repo->list_between( $from, $to );

        if ( 'print' === $format ) {
            $this->render_printable( $rows, $from, $to );
            exit;
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename=kerbcycle-qr-codes-' . $from . '_to_' . $to . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Code', 'Status', 'Created At' ) );
        foreach ( $rows as $r ) {
            fputcsv( $out, array( $r['id'], $r['code'], $r['status'], $r['created_at'] ) );
        }
        fclose( $out );
        exit;
    }

    private function render_printable( array $rows, string $from, string $to ) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8"/>
            <title>KerbCycle QR Codes: <?php echo esc_html( $from ); ?> to <?php echo esc_html( $to ); ?></title>
            <style>
                body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
                .header { display:flex; justify-content:space-between; align-items:center; margin:16px 0; }
                .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; }
                .card { border:1px solid #ddd; padding:12px; border-radius:12px; text-align:center; }
                .code-text { margin-top:8px; font-weight:600; font-size:14px; word-break:break-all; }
                @media print {
                    .no-print { display: none; }
                    .grid { gap: 8px; }
                    .card { padding: 8px; }
                }
            </style>
        </head>
        <body>
            <div class="header no-print">
                <h1>QR Codes (<?php echo esc_html( $from ); ?> → <?php echo esc_html( $to ); ?>)</h1>
                <button onclick="window.print()">Print</button>
            </div>
            <div class="grid" id="print-grid">
                <?php foreach ( $rows as $r ) : ?>
                    <div class="card">
                        <div class="qrc" data-code="<?php echo esc_attr( $r['code'] ); ?>"></div>
                        <div class="code-text"><?php echo esc_html( $r['code'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
            <?php readfile( KERBCYCLE_QR_PATH . 'assets/js/qrcode.min.js' ); ?>
            document.querySelectorAll('.qrc').forEach(function(el){
                const code = el.getAttribute('data-code');
                new QRCode(el, { text: code, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
            });
            </script>
        </body>
        </html>
        <?php
    }
}
