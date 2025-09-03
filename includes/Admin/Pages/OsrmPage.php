<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OSRM settings and map page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class OsrmPage
{
    const OPT = 'kc_osrm_options';

    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_kc_osrm_test', [$this, 'ajax_test']);
        add_shortcode('kerbcycle_osrm_map', [$this, 'shortcode_map']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    /* ---------- Settings ---------- */

    public static function defaults()
    {
        return [
            'env'           => 'dev', // dev|stage|prod
            'endpoint_dev'  => 'https://router.project-osrm.org', // demo (dev only)
            'endpoint_stage'=> '',
            'endpoint_prod' => '',
            'profile'       => 'driving', // driving|cycling|walking
            'tile_url'      => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attrib'   => '© OpenStreetMap',
            'deny_demo_in_prod' => 1,
            'timeout'       => 10,
        ];
    }

    public static function get_options()
    {
        return wp_parse_args(get_option(self::OPT, []), self::defaults());
    }

    public static function current_endpoint($opts = null)
    {
        $o = $opts ?: self::get_options();
        $env = $o['env'];
        $map = [
            'dev'   => $o['endpoint_dev'],
            'stage' => $o['endpoint_stage'],
            'prod'  => $o['endpoint_prod'],
        ];
        $url = isset($map[$env]) ? rtrim($map[$env], '/') : '';
        /**
         * Filter to override programmatically if needed.
         */
        return apply_filters('kerbcycle/osrm/endpoint', $url, $o);
    }

    public function register_settings()
    {
        register_setting(self::OPT, self::OPT, function ($in) {
            $d = self::defaults();
            $out = [];
            $out['env'] = in_array($in['env'] ?? 'dev', ['dev', 'stage', 'prod'], true) ? $in['env'] : 'dev';
            foreach (['endpoint_dev', 'endpoint_stage', 'endpoint_prod'] as $k) {
                $out[$k] = esc_url_raw(trim($in[$k] ?? ''));
            }
            $out['profile'] = in_array($in['profile'] ?? 'driving', ['driving', 'cycling', 'walking'], true) ? $in['profile'] : 'driving';
            $out['tile_url'] = sanitize_text_field($in['tile_url'] ?? $d['tile_url']);
            $out['tile_attrib'] = sanitize_text_field($in['tile_attrib'] ?? $d['tile_attrib']);
            $out['deny_demo_in_prod'] = empty($in['deny_demo_in_prod']) ? 0 : 1;
            $out['timeout'] = max(1, intval($in['timeout'] ?? 10));
            return $out;
        });

        add_settings_section('kc_osrm_main', 'OSRM Configuration', '__return_false', self::OPT);

        add_settings_field('env', 'Environment', function () {
            $o = self::get_options();
            ?>
            <select name="<?php echo esc_attr(self::OPT); ?>[env]">
                <option value="dev"   <?php selected($o['env'], 'dev'); ?>>Development</option>
                <option value="stage" <?php selected($o['env'], 'stage'); ?>>Staging</option>
                <option value="prod"  <?php selected($o['env'], 'prod'); ?>>Production</option>
            </select>
            <?php
        }, self::OPT, 'kc_osrm_main');

        $field = function ($key, $label) {
            $o = self::get_options();
            printf(
                '<input type="url" size="60" name="%1$s[%2$s]" value="%3$s" placeholder="https://your-osrm.example.com" />',
                esc_attr(self::OPT), esc_attr($key), esc_attr($o[$key])
            );
            if ($key === 'endpoint_dev') {
                echo '<p class="description">Demo server is OK for dev, not for prod.</p>';
            }
        };

        add_settings_field('endpoint_dev', 'Dev endpoint', function () use ($field) {
            $field('endpoint_dev', 'Dev endpoint');
        }, self::OPT, 'kc_osrm_main');
        add_settings_field('endpoint_stage', 'Staging endpoint', function () use ($field) {
            $field('endpoint_stage', 'Staging endpoint');
        }, self::OPT, 'kc_osrm_main');
        add_settings_field('endpoint_prod', 'Production endpoint', function () use ($field) {
            $field('endpoint_prod', 'Production endpoint');
        }, self::OPT, 'kc_osrm_main');

        add_settings_field('profile', 'Default profile', function () {
            $o = self::get_options(); ?>
            <select name="<?php echo esc_attr(self::OPT); ?>[profile]">
                <option value="driving" <?php selected($o['profile'], 'driving'); ?>>driving</option>
                <option value="cycling" <?php selected($o['profile'], 'cycling'); ?>>cycling</option>
                <option value="walking" <?php selected($o['profile'], 'walking'); ?>>walking</option>
            </select>
        <?php }, self::OPT, 'kc_osrm_main');

        add_settings_field('tile_url', 'Tile URL', function () {
            $o = self::get_options();
            printf('<input type="text" size="60" name="%1$s[tile_url]" value="%2$s" />', esc_attr(self::OPT), esc_attr($o['tile_url']));
        }, self::OPT, 'kc_osrm_main');

        add_settings_field('tile_attrib', 'Tile attribution', function () {
            $o = self::get_options();
            printf('<input type="text" size="60" name="%1$s[tile_attrib]" value="%2$s" />', esc_attr(self::OPT), esc_attr($o['tile_attrib']));
        }, self::OPT, 'kc_osrm_main');

        add_settings_field('timeout', 'HTTP timeout (s)', function () {
            $o = self::get_options();
            printf('<input type="number" min="1" max="60" name="%1$s[timeout]" value="%2$s" />', esc_attr(self::OPT), esc_attr($o['timeout']));
        }, self::OPT, 'kc_osrm_main');

        add_settings_field('deny_demo_in_prod', 'Block demo in prod', function () {
            $o = self::get_options();
            printf('<label><input type="checkbox" name="%1$s[deny_demo_in_prod]" value="1" %2$s /> Prevent saving router.project-osrm.org while env=prod</label>',
                esc_attr(self::OPT), checked($o['deny_demo_in_prod'], 1, false)
            );
        }, self::OPT, 'kc_osrm_main');
    }

    public function render()
    {
        $o = self::get_options();
        $endpoint = self::current_endpoint($o);
        $warn = ($o['env'] === 'prod' && $o['deny_demo_in_prod'] && strpos($endpoint, 'router.project-osrm.org') !== false);
        ?>
        <div class="wrap">
            <h1>OSRM Settings</h1>
            <?php if ($warn) : ?>
                <div class="notice notice-error"><p><strong>Production cannot use the public demo endpoint.</strong></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT);
                do_settings_sections(self::OPT);
                submit_button('Save OSRM Settings');
                ?>
            </form>

            <h2>Quick Test</h2>
            <p>Current endpoint: <code><?php echo esc_html($endpoint); ?></code> (profile: <code><?php echo esc_html($o['profile']); ?></code>)</p>
            <p>
                <button class="button" id="kc-osrm-test">Ping /route</button>
                <span id="kc-osrm-test-out" style="margin-left:8px;"></span>
            </p>
            <script>
            (function(){
                const btn = document.getElementById('kc-osrm-test');
                const out = document.getElementById('kc-osrm-test-out');
                if (!btn) return;
                btn.addEventListener('click', function(){
                    out.textContent = 'Testing...';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'action=kc_osrm_test&_wpnonce=<?php echo wp_create_nonce('kc_osrm_test'); ?>'
                    }).then(r=>r.json()).then(j=>{
                        out.textContent = j.ok ? 'OK ('+j.ms+' ms)' : ('Error: '+(j.error||'unknown'));
                    }).catch(e=>{
                        out.textContent = 'Error: ' + e;
                    });
                });
            })();
            </script>
        </div>
        <?php
    }

    public function ajax_test()
    {
        check_ajax_referer('kc_osrm_test');
        $o = self::get_options();
        $endpoint = self::current_endpoint($o);
        if (!$endpoint) {
            wp_send_json(['ok' => false, 'error' => 'No endpoint configured']);
        }
        if ($o['env'] === 'prod' && $o['deny_demo_in_prod'] && strpos($endpoint, 'router.project-osrm.org') !== false) {
            wp_send_json(['ok' => false, 'error' => 'Demo endpoint blocked in prod']);
        }
        $profile = $o['profile'];
        $url = $endpoint . "/route/v1/$profile/-73.990,40.730;-73.970,40.780?overview=false";
        $t0 = microtime(true);
        $res = wp_remote_get($url, ['timeout' => $o['timeout']]);
        $ms = round(1000 * (microtime(true) - $t0));
        if (is_wp_error($res)) {
            wp_send_json(['ok' => false, 'error' => $res->get_error_message()]);
        }
        $code = wp_remote_retrieve_response_code($res);
        if ($code === 200) {
            wp_send_json(['ok' => true, 'ms' => $ms]);
        }
        wp_send_json(['ok' => false, 'error' => 'HTTP ' + String(code)]);
    }

    /* ---------- Front-end map ---------- */

    public function register_assets()
    {
        wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], null);
        wp_register_style('lrm', 'https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css', [], null);
        wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
        wp_register_script('lrm', 'https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js', ['leaflet'], null, true);
        wp_register_script('kc-osrm', KERBCYCLE_QR_URL . 'assets/js/kc-osrm.js', ['leaflet', 'lrm'], '1.0', true);

        $o = self::get_options();
        wp_localize_script('kc-osrm', 'KC_OSRM', [
            'endpoint' => self::current_endpoint($o) . '/route/v1/' . $o['profile'],
            'tileUrl'  => $o['tile_url'],
            'tileAttrib' => $o['tile_attrib'],
        ]);
    }

    public function shortcode_map($atts)
    {
        $atts = shortcode_atts([
            'start'   => '40.730,-73.990',
            'end'     => '40.780,-73.970',
            'height'  => '420px',
            'zoom'    => 12,
        ], $atts, 'kerbcycle_osrm_map');

        wp_enqueue_style('leaflet');
        wp_enqueue_style('lrm');
        wp_enqueue_script('leaflet');
        wp_enqueue_script('lrm');
        wp_enqueue_script('kc-osrm');

        $id = 'kc-osrm-' . wp_generate_uuid4();
        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>" style="height:<?php echo esc_attr($atts['height']); ?>;"></div>
        <script>
        (function(){
            if (!window.L || !window.L.Routing) return;
            var map = L.map('<?php echo esc_js($id); ?>').setView([<?php echo esc_js($atts['start']); ?>].reverse(), <?php echo intval($atts['zoom']); ?>);
            L.tileLayer(KC_OSRM.tileUrl, { attribution: KC_OSRM.tileAttrib }).addTo(map);
            var wp1 = L.latLng.apply(null, [<?php echo esc_js($atts['start']); ?>]);
            var wp2 = L.latLng.apply(null, [<?php echo esc_js($atts['end']); ?>]);
            L.Routing.control({
                waypoints: [ wp1, wp2 ],
                router: L.Routing.osrmv1({ serviceUrl: KC_OSRM.endpoint.replace(/\/route\/v1\/.*$/, '/route/v1') })
            }).addTo(map);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

