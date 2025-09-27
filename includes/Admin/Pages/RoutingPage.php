<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page and helpers for configuring KerbCycle OSRM routing.
 */
class RoutingPage
{
    private const OPTION_KEY = 'kerbcycle_osrm_options';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor hooks.
     */
    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_kc_osrm_test', [$this, 'ajax_test']);
    }

    /**
     * Render the admin page.
     */
    public function render()
    {
        $options = self::get_options();
        $endpoint = self::current_endpoint($options);
        $demo_in_prod = ($options['env'] === 'prod'
            && $options['deny_demo_in_prod']
            && false !== strpos($endpoint, 'router.project-osrm.org'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('OSRM Settings', 'kerbcycle'); ?></h1>
            <?php if ($demo_in_prod) : ?>
                <div class="notice notice-error">
                    <p><strong><?php esc_html_e('Production cannot use the public demo endpoint.', 'kerbcycle'); ?></strong></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_KEY);
                do_settings_sections(self::OPTION_KEY);
                submit_button(__('Save OSRM Settings', 'kerbcycle'));
                ?>
            </form>

            <h2><?php esc_html_e('Quick Test', 'kerbcycle'); ?></h2>
            <p>
                <?php esc_html_e('Current endpoint:', 'kerbcycle'); ?>
                <code><?php echo esc_html($endpoint); ?></code>
                (<?php esc_html_e('profile:', 'kerbcycle'); ?>
                <code><?php echo esc_html($options['profile']); ?></code>)
            </p>
            <p>
                <button class="button" id="kc-osrm-test"><?php esc_html_e('Ping /route', 'kerbcycle'); ?></button>
                <span id="kc-osrm-test-out" style="margin-left:8px;"></span>
            </p>
            <script>
            (function(){
                const btn = document.getElementById('kc-osrm-test');
                const out = document.getElementById('kc-osrm-test-out');
                if (!btn || !window.fetch) {
                    return;
                }
                btn.addEventListener('click', function(){
                    out.textContent = '<?php echo esc_js(__('Testing...', 'kerbcycle')); ?>';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=kc_osrm_test&_wpnonce=<?php echo wp_create_nonce('kc_osrm_test'); ?>'
                    })
                        .then(function(response){ return response.json(); })
                        .then(function(payload){
                            if (payload.ok) {
                                out.textContent = 'OK (' + payload.ms + ' ms)';
                            } else {
                                out.textContent = 'Error: ' + (payload.error || 'unknown');
                            }
                        })
                        .catch(function(error){
                            out.textContent = 'Error: ' + error;
                        });
                });
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Register OSRM settings and fields.
     */
    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize_options']);

        add_settings_section(
            'kc_osrm_main',
            __('OSRM Configuration', 'kerbcycle'),
            '__return_false',
            self::OPTION_KEY
        );

        add_settings_field(
            'env',
            __('Environment', 'kerbcycle'),
            [$this, 'render_environment_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'endpoint_dev',
            __('Dev endpoint', 'kerbcycle'),
            function () {
                $this->render_endpoint_field('endpoint_dev');
            },
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'endpoint_stage',
            __('Staging endpoint', 'kerbcycle'),
            function () {
                $this->render_endpoint_field('endpoint_stage');
            },
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'endpoint_prod',
            __('Production endpoint', 'kerbcycle'),
            function () {
                $this->render_endpoint_field('endpoint_prod');
            },
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'profile',
            __('Default profile', 'kerbcycle'),
            [$this, 'render_profile_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'tile_url',
            __('Tile URL', 'kerbcycle'),
            [$this, 'render_tile_url_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'tile_attrib',
            __('Tile attribution', 'kerbcycle'),
            [$this, 'render_tile_attribution_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'timeout',
            __('HTTP timeout (s)', 'kerbcycle'),
            [$this, 'render_timeout_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );

        add_settings_field(
            'deny_demo_in_prod',
            __('Block demo in prod', 'kerbcycle'),
            [$this, 'render_deny_demo_field'],
            self::OPTION_KEY,
            'kc_osrm_main'
        );
    }

    /**
     * Sanitize saved options.
     *
     * @param array $input Raw options.
     */
    public function sanitize_options($input)
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();

        $output = [];
        $env = isset($input['env']) ? $input['env'] : 'dev';
        $output['env'] = in_array($env, ['dev', 'stage', 'prod'], true) ? $env : 'dev';

        foreach (['endpoint_dev', 'endpoint_stage', 'endpoint_prod'] as $key) {
            $output[$key] = esc_url_raw(trim($input[$key] ?? ''));
        }

        $profile = isset($input['profile']) ? $input['profile'] : $defaults['profile'];
        $output['profile'] = in_array($profile, ['driving', 'cycling', 'walking'], true)
            ? $profile
            : $defaults['profile'];

        $output['tile_url'] = sanitize_text_field($input['tile_url'] ?? $defaults['tile_url']);
        $output['tile_attrib'] = sanitize_text_field($input['tile_attrib'] ?? $defaults['tile_attrib']);
        $output['deny_demo_in_prod'] = empty($input['deny_demo_in_prod']) ? 0 : 1;
        $timeout = isset($input['timeout']) ? (int) $input['timeout'] : $defaults['timeout'];
        $output['timeout'] = max(1, min(60, $timeout));

        return $output;
    }

    /**
     * Render the environment selector.
     */
    public function render_environment_field()
    {
        $options = self::get_options();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[env]">
            <option value="dev" <?php selected($options['env'], 'dev'); ?>><?php esc_html_e('Development', 'kerbcycle'); ?></option>
            <option value="stage" <?php selected($options['env'], 'stage'); ?>><?php esc_html_e('Staging', 'kerbcycle'); ?></option>
            <option value="prod" <?php selected($options['env'], 'prod'); ?>><?php esc_html_e('Production', 'kerbcycle'); ?></option>
        </select>
        <?php
    }

    /**
     * Render an endpoint input.
     */
    private function render_endpoint_field($key)
    {
        $options = self::get_options();
        printf(
            '<input type="url" size="60" name="%1$s[%2$s]" value="%3$s" placeholder="https://your-osrm.example.com" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($options[$key])
        );

        if ('endpoint_dev' === $key) {
            echo '<p class="description">' . esc_html__('Demo server is OK for development, not for production.', 'kerbcycle') . '</p>';
        }
    }

    /**
     * Render the default profile select.
     */
    public function render_profile_field()
    {
        $options = self::get_options();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[profile]">
            <option value="driving" <?php selected($options['profile'], 'driving'); ?>><?php esc_html_e('driving', 'kerbcycle'); ?></option>
            <option value="cycling" <?php selected($options['profile'], 'cycling'); ?>><?php esc_html_e('cycling', 'kerbcycle'); ?></option>
            <option value="walking" <?php selected($options['profile'], 'walking'); ?>><?php esc_html_e('walking', 'kerbcycle'); ?></option>
        </select>
        <?php
    }

    /**
     * Render tile URL field.
     */
    public function render_tile_url_field()
    {
        $options = self::get_options();
        printf(
            '<input type="text" size="60" name="%1$s[tile_url]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($options['tile_url'])
        );
    }

    /**
     * Render tile attribution field.
     */
    public function render_tile_attribution_field()
    {
        $options = self::get_options();
        printf(
            '<input type="text" size="60" name="%1$s[tile_attrib]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($options['tile_attrib'])
        );
    }

    /**
     * Render timeout field.
     */
    public function render_timeout_field()
    {
        $options = self::get_options();
        printf(
            '<input type="number" min="1" max="60" name="%1$s[timeout]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($options['timeout'])
        );
    }

    /**
     * Render demo checkbox field.
     */
    public function render_deny_demo_field()
    {
        $options = self::get_options();
        printf(
            '<label><input type="checkbox" name="%1$s[deny_demo_in_prod]" value="1" %2$s /> %3$s</label>',
            esc_attr(self::OPTION_KEY),
            checked($options['deny_demo_in_prod'], 1, false),
            esc_html__('Prevent saving router.project-osrm.org while env = Production', 'kerbcycle')
        );
    }

    /**
     * AJAX handler used by the admin test button.
     */
    public function ajax_test()
    {
        check_ajax_referer('kc_osrm_test');

        $options = self::get_options();
        $endpoint = self::current_endpoint($options);

        if (empty($endpoint)) {
            wp_send_json(['ok' => false, 'error' => __('No endpoint configured', 'kerbcycle')]);
        }

        if (
            'prod' === $options['env']
            && $options['deny_demo_in_prod']
            && false !== strpos($endpoint, 'router.project-osrm.org')
        ) {
            wp_send_json(['ok' => false, 'error' => __('Demo endpoint blocked in production', 'kerbcycle')]);
        }

        $profile = $options['profile'];
        $url = trailingslashit($endpoint) . 'route/v1/' . rawurlencode($profile) . '/-73.990,40.730;-73.970,40.780?overview=false';

        $start = microtime(true);
        $response = wp_remote_get($url, ['timeout' => (int) $options['timeout']]);
        $elapsed = (int) round(1000 * (microtime(true) - $start));

        if (is_wp_error($response)) {
            wp_send_json(['ok' => false, 'error' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 === $code) {
            wp_send_json(['ok' => true, 'ms' => $elapsed]);
        }

        wp_send_json(['ok' => false, 'error' => sprintf('HTTP %d', $code)]);
    }

}
