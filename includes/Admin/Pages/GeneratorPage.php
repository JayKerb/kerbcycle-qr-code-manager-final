<?php

namespace Kerbcycle\QrCode\Admin\Pages;

use Kerbcycle\QrCode\Data\Repositories\QrRepoRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for generating QR codes.
 */
class GeneratorPage
{
    /** @var self */
    private static $instance;

    /**
     * Singleton instance.
     */
    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_kerbcycle_generate_qr', [$this, 'ajax_generate_qr']);
        add_action('admin_post_kerbcycle_export_qr_csv', [$this, 'handle_export_csv']);
    }

    /**
     * Register assets for the generator page.
     */
    public function assets($hook)
    {
        if (strpos($hook, 'kerbcycle-qr-generator') === false) {
            return;
        }

        wp_enqueue_script(
            'kerbcycle-qrcode',
            KERBCYCLE_QR_URL . 'assets/js/qrcode.min.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'kerbcycle-qr-generator',
            KERBCYCLE_QR_URL . 'assets/js/qr-generator.js',
            ['jquery', 'kerbcycle-qrcode'],
            '1.0.0',
            true
        );

        wp_localize_script('kerbcycle-qr-generator', 'KerbcycleQRGen', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('kerbcycle_generate_qr'),
        ]);

        wp_enqueue_style(
            'kerbcycle-qr-generator',
            KERBCYCLE_QR_URL . 'assets/css/qr-generator.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Render the admin page.
     */
    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('QR Code Generator', 'kerbcycle'); ?></h1>

            <div
                id="kc-qrgen-data"
                data-ajax-url="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('kerbcycle_generate_qr')); ?>"
            ></div>

            <div class="kc-card">
                <h2><?php esc_html_e('Generate Codes', 'kerbcycle'); ?></h2>
                <form id="kc-generate-form" onsubmit="return false;">
  <div class="kc-row">
    <label><?php esc_html_e('Mode', 'kerbcycle'); ?></label>
    <select id="kc-gen-mode">
      <option value="random"><?php esc_html_e('Random', 'kerbcycle'); ?></option>
      <option value="sequential"><?php esc_html_e('Sequential', 'kerbcycle'); ?></option>
      <option value="single"><?php esc_html_e('Single', 'kerbcycle'); ?></option>
    </select>
  </div>

  <!-- Single -->
  <div class="kc-row kc-if-single" style="display:none;">
    <label><?php esc_html_e('Code', 'kerbcycle'); ?></label>
    <input type="text" id="kc-code" placeholder="e.g. KC-2025-0001" />
  </div>

  <!-- Random -->
  <div class="kc-row kc-if-random">
    <label><?php esc_html_e('How many?', 'kerbcycle'); ?></label>
    <input type="number" id="kc-count" min="1" max="5000" value="20" />
  </div>
  <div class="kc-row kc-if-random">
    <label><?php esc_html_e('Prefix (optional)', 'kerbcycle'); ?></label>
    <input type="text" id="kc-prefix" placeholder="e.g. KC-2025-" />
  </div>
  <div class="kc-row kc-if-random">
    <label><?php esc_html_e('Length (random part)', 'kerbcycle'); ?></label>
    <input type="number" id="kc-length" min="4" max="16" value="8" />
  </div>

  <!-- Sequential -->
  <div class="kc-row kc-if-seq" style="display:none;">
    <label><?php esc_html_e('Prefix', 'kerbcycle'); ?></label>
    <input type="text" id="kc-seq-prefix" placeholder="e.g. KC-2025-" />
  </div>
  <div class="kc-row kc-if-seq" style="display:none;">
    <label><?php esc_html_e('Start #', 'kerbcycle'); ?></label>
    <input type="number" id="kc-seq-start" min="0" value="1" />
  </div>
  <div class="kc-row kc-if-seq" style="display:none;">
    <label><?php esc_html_e('Count', 'kerbcycle'); ?></label>
    <input type="number" id="kc-seq-count" min="1" max="5000" value="100" />
  </div>
  <div class="kc-row kc-if-seq" style="display:none;">
    <label><?php esc_html_e('Pad Length', 'kerbcycle'); ?></label>
    <input type="number" id="kc-seq-pad" min="1" max="10" value="4" />
  </div>

  <div class="kc-row">
    <button class="button button-primary" id="kc-generate-btn"><?php esc_html_e('Generate & Save', 'kerbcycle'); ?></button>
  </div>
  <p class="description"><?php esc_html_e('Only unique codes are saved; duplicates are skipped.', 'kerbcycle'); ?></p>
</form>
<div id="kc-generate-result" class="kc-grid"></div>
            </div>

            <div class="kc-card">
                <h2><?php esc_html_e('Export Repository (Date Range)', 'kerbcycle'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('kerbcycle_export_qr_csv', 'kc_export_nonce'); ?>
                    <input type="hidden" name="action" value="kerbcycle_export_qr_csv" />
                    <div class="kc-row">
                        <label><?php esc_html_e('From', 'kerbcycle'); ?></label>
                        <input type="date" name="from" required />
                    </div>
                    <div class="kc-row">
                        <label><?php esc_html_e('To', 'kerbcycle'); ?></label>
                        <input type="date" name="to" required />
                    </div>
                    <div class="kc-row">
                        <label><?php esc_html_e('Export Type', 'kerbcycle'); ?></label>
                        <select name="format">
  <option value="print"><?php esc_html_e('Printable Sheet (grid)', 'kerbcycle'); ?></option>
  <option value="print_labels_5160"><?php esc_html_e('Avery 5160 Labels (3×10)', 'kerbcycle'); ?></option>
  <option value="csv"><?php esc_html_e('CSV file', 'kerbcycle'); ?></option>
  <option value="zip_png"><?php esc_html_e('ZIP of PNG images', 'kerbcycle'); ?></option>
  <option value="zip_svg"><?php esc_html_e('ZIP of SVG images', 'kerbcycle'); ?></option>
</select>
                    </div>
                    <div class="kc-row">
                        <button class="button"><?php esc_html_e('Export', 'kerbcycle'); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e('“Printable Sheet” opens a formatted page you can print to paper or PDF. “CSV” downloads code data.', 'kerbcycle'); ?></p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: generate and save unique codes.
     */
    public function ajax_generate_qr()
    {
        check_ajax_referer('kerbcycle_generate_qr', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission'], 403);
        }

        $repo = new QrRepoRepository();
        $user = get_current_user_id();
        $raw_mode = isset($_POST['mode']) ? $_POST['mode'] : ($_POST['genType'] ?? 'single');
        $mode = sanitize_text_field($raw_mode);
        $allowed = ['single', 'random', 'sequential'];
        if (!in_array($mode, $allowed, true)) {
            $mode = 'single';
        }
        $result = ['saved' => [], 'skipped' => []];

        if ($mode === 'single') {
            $code = trim(sanitize_text_field($_POST['code'] ?? ''));
            if ($code === '') {
                wp_send_json_error(['message' => 'Code required.'], 400);
            }

            if ($repo->exists($code)) {
                $result['skipped'][] = $code;
            } else {
                $repo->insert($code, $user);
                $result['saved'][] = $code;
            }
            wp_send_json_success($result);
        }

        if ($mode === 'sequential') {
            $prefix = sanitize_text_field($_POST['seqPrefix'] ?? '');
            $start  = max(0, intval($_POST['seqStart'] ?? 1));
            $count  = max(1, min(5000, intval($_POST['seqCount'] ?? 100)));
            $pad    = max(1, min(10, intval($_POST['seqPad'] ?? 4)));

            for ($i = 0; $i < $count; $i++) {
                $num  = $start + $i;
                $code = $prefix . str_pad((string) $num, $pad, '0', STR_PAD_LEFT);

                if ($repo->exists($code)) {
                    $result['skipped'][] = $code;
                    continue;
                }

                $repo->insert($code, $user);
                $result['saved'][] = $code;
            }

            wp_send_json_success($result);
        }

        // Random mode
        $count  = max(1, min(5000, intval($_POST['count'] ?? 20)));
        $prefix = sanitize_text_field($_POST['prefix'] ?? '');
        $len    = max(4, min(16, intval($_POST['length'] ?? 8)));

        for ($i = 0; $i < $count; $i++) {
            $rand = wp_generate_password($len, false, false);
            $code = $prefix . strtoupper($rand);
            $tries = 0;
            while ($tries < 3 && $repo->exists($code)) {
                $rand = wp_generate_password($len, false, false);
                $code = $prefix . strtoupper($rand);
                $tries++;
            }
            if ($repo->exists($code)) {
                $result['skipped'][] = $code;
                continue;
            }
            $repo->insert($code, $user);
            $result['saved'][] = $code;
        }

        wp_send_json_success($result);
    }

    /**
     * Handle CSV or printable export.
     */
    public function handle_export_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('No permission.');
        }
        if (!isset($_POST['kc_export_nonce']) || !wp_verify_nonce($_POST['kc_export_nonce'], 'kerbcycle_export_qr_csv')) {
            wp_die('Bad nonce.');
        }

        $from   = sanitize_text_field($_POST['from'] ?? '');
        $to     = sanitize_text_field($_POST['to'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        if (!$from || !$to) {
            wp_die('Date range required.');
        }

        $repo = new QrRepoRepository();
        $rows = $repo->list_between($from, $to);

        switch ($format) {
            case 'print':
                $this->render_printable($rows, $from, $to);
                exit;
            case 'print_labels_5160':
                $this->render_printable_avery_5160($rows, $from, $to);
                exit;
            case 'zip_png':
                $this->render_zip_png($rows, $from, $to);
                exit;
            case 'zip_svg':
                $this->render_zip_svg($rows, $from, $to);
                exit;
            case 'csv':
            default:
                nocache_headers();
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename=kerbcycle-qr-codes-' . $from . '_to_' . $to . '.csv');

                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Code', 'Status', 'Created At']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['id'], $r['code'], $r['status'], $r['created_at']]);
                }
                fclose($out);
                exit;
        }
    }

    private function render_printable(array $rows, string $from, string $to)
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8"/>
            <title>KerbCycle QR Codes: <?php echo esc_html($from); ?> to <?php echo esc_html($to); ?></title>
            <style>
                body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
                .header { display:flex; justify-content:space-between; align-items:center; margin:16px 0; }
                .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; }
                .card { border:1px solid #ddd; padding:12px; border-radius:12px; text-align:center; }
                .code-text { margin-top:8px; font-weight:600; font-size:14px; word-break:break-all; }
                @media print {
                  .no-print { display:none; }
                  .grid { gap:8px; }
                  .card { padding:8px; }
                }
            </style>
        </head>
        <body>
            <div class="header no-print">
                <h1>QR Codes (<?php echo esc_html($from); ?> → <?php echo esc_html($to); ?>)</h1>
                <button onclick="window.print()">Print</button>
            </div>
            <div class="grid" id="print-grid">
                <?php foreach ($rows as $r): ?>
                    <div class="card">
                        <div class="qrc" data-code="<?php echo esc_attr($r['code']); ?>"></div>
                        <div class="code-text"><?php echo esc_html($r['code']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/qrcode.min.js'); ?>"></script>
            <script>
            document.querySelectorAll('.qrc').forEach(function(el){
                const code = el.getAttribute('data-code');
                new QRCode(el, { text: code, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
            });
            </script>
        </body>
        </html>
        <?php
    }

    private static function render_printable_avery_5160(array $rows, string $from, string $to)
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8"/>
          <title>Avery 5160 — <?php echo esc_html("$from to $to"); ?></title>
          <style>
            @page { size: 8.5in 11in; margin: 0.5in; }
            body { margin: 0.25in; font-family: system-ui, Arial, sans-serif; }
            .sheet {
              width: 8.5in;
              display: grid;
              grid-template-columns: repeat(3, 2.625in);
              grid-auto-rows: 1in;
              gap: 0in 0.125in;
            }
            .label {
              width: 2.625in; height: 1in;
              display:flex; align-items:center; justify-content:center;
              flex-direction:row; gap:0.15in; overflow:hidden; background:#fff;
            }
            .label .qrc { width:0.8in; height:0.8in; }
            .label .txt { font-size:10pt; max-width:1.5in; word-wrap:break-word; }
            @media print { .no-print { display:none; } }
          </style>
        </head>
        <body>
          <div class="no-print" style="padding:8px">
            <button onclick="window.print()">Print</button>
          </div>
          <div class="sheet">
            <?php foreach ($rows as $r): ?>
              <div class="label">
                <div class="qrc" data-code="<?php echo esc_attr($r['code']); ?>"></div>
                <div class="txt"><?php echo esc_html($r['code']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/qrcode.min.js'); ?>"></script>
          <script>
            document.querySelectorAll('.qrc').forEach(function(el){
              const code = el.getAttribute('data-code');
              new QRCode(el, { text: code, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
            });
          </script>
        </body>
        </html>
        <?php
    }

    private static function render_zip_png(array $rows, string $from, string $to)
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8"/>
          <title>PNG ZIP — <?php echo esc_html("$from to $to"); ?></title>
          <style>
            body { font-family: system-ui, Arial, sans-serif; padding:16px; }
            .grid { display:grid; grid-template-columns: repeat(5,1fr); gap:12px; }
            .card { border:1px solid #ddd; border-radius:12px; padding:12px; text-align:center; }
            .qrc { margin:auto; }
            button { margin:12px 0; }
          </style>
        </head>
        <body>
          <h1>Build ZIP of PNGs</h1>
          <button id="buildZip">Generate ZIP</button>
          <div class="grid">
            <?php foreach ($rows as $r): ?>
              <div class="card">
                <div class="qrc" data-code="<?php echo esc_attr($r['code']); ?>"></div>
                <div class="txt"><?php echo esc_html($r['code']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/qrcode.min.js'); ?>"></script>
          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/jszip.min.js'); ?>"></script>
          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/FileSaver.min.js'); ?>"></script>
          <script>
            const cards = document.querySelectorAll('.qrc');
            cards.forEach(function(el){
              const code = el.getAttribute('data-code');
              new QRCode(el, { text: code, width: 256, height: 256, correctLevel: QRCode.CorrectLevel.M });
            });

            document.getElementById('buildZip').addEventListener('click', async () => {
              const zip = new JSZip();
              let i = 0;
              for (const card of cards) {
                const code = card.getAttribute('data-code');
                const canvas = card.querySelector('canvas');
                if (!canvas) continue;
                const dataUrl = canvas.toDataURL('image/png');
                const base64 = dataUrl.split(',')[1];
                zip.file(code + '.png', base64, {base64:true});
                i++;
              }
              const blob = await zip.generateAsync({type:'blob'});
              saveAs(blob, 'kerbcycle-qr-png-<?php echo esc_js($from . "_to_" . $to); ?>.zip');
            });
          </script>
        </body>
        </html>
        <?php
    }

    private static function render_zip_svg(array $rows, string $from, string $to)
    {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8"/>
          <title>SVG ZIP — <?php echo esc_html("$from to $to"); ?></title>
          <style> body { font-family: system-ui, Arial, sans-serif; padding:16px; } button { margin:12px 0; } </style>
        </head>
        <body>
          <h1>Build ZIP of SVGs</h1>
          <button id="buildZip">Generate ZIP</button>
          <div id="status"></div>

          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/jszip.min.js'); ?>"></script>
          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/FileSaver.min.js'); ?>"></script>
          <script src="<?php echo esc_url(KERBCYCLE_QR_URL . 'assets/js/qrcodegen.min.js'); ?>"></script>
          <script>
            function makeSvgString(text, size = 256, margin = 4) {
              const ecl = qrcodegen.QrCode.Ecc.M;
              const qr = qrcodegen.QrCode.encodeText(text, ecl);
              const scale = Math.floor(size / (qr.size + margin * 2));
              const dim = (qr.size + margin * 2) * scale;
              let path = "";
              for (let y = 0; y < qr.size; y++) {
                for (let x = 0; x < qr.size; x++) {
                  if (qr.getModule(x, y)) {
                    path += `M${(x+margin)*scale},${(y+margin)*scale}h${scale}v${scale}h-${scale}z`;
                  }
                }
              }
              return `<svg xmlns="http://www.w3.org/2000/svg" width="${dim}" height="${dim}" viewBox="0 0 ${dim} ${dim}"><rect width="100%" height="100%" fill="#FFFFFF"/><path d="${path}" fill="#000000"/></svg>`;
            }

            document.getElementById('buildZip').addEventListener('click', async () => {
              const zip = new JSZip();
              const codes = <?php echo wp_json_encode(array_map(fn($r) => $r['code'], $rows)); ?>;
              let i = 0;
              for (const code of codes) {
                const svg = makeSvgString(code, 256, 4);
                zip.file(code + '.svg', svg);
                i++;
              }
              const blob = await zip.generateAsync({type:'blob'});
              saveAs(blob, 'kerbcycle-qr-svg-<?php echo esc_js($from . "_to_" . $to); ?>.zip');
              document.getElementById('status').textContent = 'Done: ' + i + ' SVG files.';
            });
          </script>
        </body>
        </html>
        <?php
    }
}
