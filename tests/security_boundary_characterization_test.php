<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    class TestAjaxResponse extends \RuntimeException
    {
        public $success;
        public $data;
        public $status;

        public function __construct(bool $success, $data, int $status)
        {
            parent::__construct('ajax response');
            $this->success = $success;
            $this->data = $data;
            $this->status = $status;
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error
        {
            private $code;
            private $message;
            private $data;

            public function __construct($code = '', $message = '', $data = null)
            {
                $this->code = $code;
                $this->message = $message;
                $this->data = $data;
            }

            public function get_error_code()
            {
                return $this->code;
            }

            public function get_error_message()
            {
                return $this->message;
            }

            public function get_error_data()
            {
                return $this->data;
            }
        }
    }

    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request
        {
            private $params = [];
            private $headers = [];

            public function __construct(array $params = [], array $headers = [])
            {
                $this->params = $params;
                $this->headers = $headers;
            }

            public function get_param($key)
            {
                return $this->params[$key] ?? null;
            }

            public function get_header($key)
            {
                return $this->headers[$key] ?? '';
            }

            public function offsetGet($offset)
            {
                return $this->params[$offset] ?? null;
            }
        }
    }

    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            private $data;
            private $status;

            public function __construct($data, int $status = 200)
            {
                $this->data = $data;
                $this->status = $status;
            }

            public function get_data()
            {
                return $this->data;
            }

            public function get_status()
            {
                return $this->status;
            }
        }
    }

    // ---- WordPress function stubs used by code paths under test ----
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // no-op
    }

    function add_shortcode($tag, $callback)
    {
        // no-op
    }

    function __($text, $domain = null)
    {
        return $text;
    }

    function esc_html_e($text, $domain = null)
    {
        echo $text;
    }

    function esc_attr_e($text, $domain = null)
    {
        echo $text;
    }

    function esc_html($text)
    {
        return (string) $text;
    }

    function esc_attr($text)
    {
        return (string) $text;
    }

    function esc_js($text)
    {
        return (string) $text;
    }

    function sanitize_text_field($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    function wp_unslash($value)
    {
        return $value;
    }

    function wp_verify_nonce($nonce, $action)
    {
        return is_string($nonce) && $nonce === 'nonce:' . $action;
    }

    function wp_create_nonce($action)
    {
        return 'nonce:' . $action;
    }

    function wp_doing_ajax()
    {
        return true;
    }

    function wp_send_json_error($data = null, $status_code = null)
    {
        throw new TestAjaxResponse(false, $data, (int) ($status_code ?? 200));
    }

    function wp_send_json_success($data = null, $status_code = null)
    {
        throw new TestAjaxResponse(true, $data, (int) ($status_code ?? 200));
    }

    function wp_die($message = '', $title = '', $status = 0)
    {
        throw new \RuntimeException('wp_die: ' . $message . '|' . $status);
    }

    function current_user_can($capability)
    {
        $role = $GLOBALS['kc_current_role'] ?? 'anonymous';
        return $capability === 'manage_options' && $role === 'administrator';
    }

    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }

    function rest_ensure_response($value)
    {
        return $value;
    }

    function wp_dropdown_users($args = [])
    {
        $users = $GLOBALS['kc_mock_users'] ?? [];
        $id = $args['id'] ?? 'customer-id';
        $name = $args['name'] ?? 'customer_id';
        echo '<select id="' . $id . '" name="' . $name . '">';
        foreach ($users as $user) {
            echo '<option value="' . (int) $user['ID'] . '">' . $user['display_name'] . '</option>';
        }
        echo '</select>';
    }

    function selected($selected, $current, $echo = true)
    {
        return ((string) $selected === (string) $current) ? ' selected="selected"' : '';
    }

    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        return array_merge($pairs, is_array($atts) ? $atts : []);
    }

    function wp_generate_uuid4()
    {
        return 'uuid-test';
    }

    function wp_enqueue_style($handle)
    {
    }
    function wp_enqueue_script($handle)
    {
    }
    function wp_add_inline_script($handle, $data, $position = 'after')
    {
    }
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
    function get_current_user_id()
    {
        return $GLOBALS['kc_current_role'] === 'anonymous' ? 0 : 1;
    }

    function assert_true($condition, string $message)
    {
        if (!$condition) {
            throw new \RuntimeException('Assertion failed: ' . $message);
        }
    }

    function assert_equals($expected, $actual, string $message)
    {
        if ($expected !== $actual) {
            throw new \RuntimeException('Assertion failed: ' . $message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
        }
    }

    function assert_contains(string $needle, string $haystack, string $message)
    {
        if (strpos($haystack, $needle) === false) {
            throw new \RuntimeException('Assertion failed: ' . $message . ' missing=' . $needle);
        }
    }

    // Minimal $wpdb double used by constructors and QR table renderer.
    $GLOBALS['wpdb'] = new class () {
        public $prefix = 'wp_';

        public function get_results($sql)
        {
            return $GLOBALS['kc_mock_qr_rows'] ?? [];
        }
    };

    require_once __DIR__ . '/../includes/Autoloader.php';
    \Kerbcycle\QrCode\Autoloader::run();

    // ---- Test 1: AJAX assign denied for low privilege ----
    $GLOBALS['kc_current_role'] = 'subscriber';
    $_POST = [
        'qr_code' => 'QR-LOW-1',
        'customer_id' => '123',
        'security' => wp_create_nonce('kerbcycle_qr_nonce'),
    ];
    $_REQUEST = $_POST;

    try {
        $ajax = new \Kerbcycle\QrCode\Admin\Ajax\AdminAjax();
        $ajax->assign_qr_code();
        throw new \RuntimeException('Expected unauthorized AJAX response was not thrown.');
    } catch (TestAjaxResponse $response) {
        assert_equals(false, $response->success, 'Low-priv assign must fail');
        assert_equals(403, $response->status, 'Low-priv assign must return 403');
        assert_true(isset($response->data['message']), 'Low-priv error must include message');
    }

    // ---- Test 2: AJAX assign missing/invalid nonce ----
    $GLOBALS['kc_current_role'] = 'administrator';
    $_POST = [
        'qr_code' => 'QR-ADMIN-1',
        'customer_id' => '123',
        'security' => 'bad-nonce',
    ];
    $_REQUEST = $_POST;

    try {
        $ajax = new \Kerbcycle\QrCode\Admin\Ajax\AdminAjax();
        $ajax->assign_qr_code();
        throw new \RuntimeException('Expected nonce failure AJAX response was not thrown.');
    } catch (TestAjaxResponse $response) {
        assert_equals(false, $response->success, 'Invalid nonce assign must fail');
        assert_equals(403, $response->status, 'Invalid nonce assign must return 403');
        assert_true(isset($response->data['message']), 'Nonce error must include message');
    }

    // ---- Test 3: REST AI endpoint rejects missing X-WP-Nonce ----
    $GLOBALS['kc_current_role'] = 'administrator';
    $ai_controller = new \Kerbcycle\QrCode\Api\Controllers\AiController();
    $ai_request = new WP_REST_Request(['action' => 'draft_template'], []); // no X-WP-Nonce
    $ai_permission_result = $ai_controller->permissions($ai_request);

    assert_true(is_wp_error($ai_permission_result), 'AI permission without X-WP-Nonce must return WP_Error');
    assert_equals('rest_nonce_invalid', $ai_permission_result->get_error_code(), 'AI missing nonce error code mismatch');

    // ---- Test 4: REST QR status route denies non-admin ----
    $GLOBALS['kc_current_role'] = 'subscriber';
    $qr_controller = new \Kerbcycle\QrCode\Api\Controllers\QrController();
    $qr_request = new WP_REST_Request(['qr_code' => 'QR-LOW-1'], []);
    $qr_status_result = $qr_controller->get_qr_status($qr_request);

    assert_true(is_wp_error($qr_status_result), 'QR status for non-admin must return WP_Error');
    assert_equals('rest_forbidden', $qr_status_result->get_error_code(), 'QR status non-admin error code mismatch');

    // ---- Test 5: Public shortcode anonymous exposure characterization ----
    $GLOBALS['kc_current_role'] = 'anonymous';
    $GLOBALS['kc_mock_users'] = [
        ['ID' => 77, 'display_name' => 'Alice Customer'],
    ];
    $GLOBALS['kc_mock_qr_rows'] = [
        (object) [
            'id' => 1,
            'qr_code' => 'QR-EXPOSED-1',
            'user_id' => 77,
            'display_name' => 'Alice Customer',
            'status' => 'assigned',
            'assigned_at' => '2026-04-01 10:00:00',
        ],
    ];

    $shortcodes = new \Kerbcycle\QrCode\Public\Shortcodes();
    $scanner_html = $shortcodes->generate_frontend_scanner();
    $table_html = $shortcodes->generate_qr_table();

    // Characterization assertions: capture current anonymous-visible output.
    assert_contains('Select Customer', $scanner_html, 'Scanner shortcode should render customer selector text for anonymous users');
    assert_contains('Alice Customer', $scanner_html, 'Scanner shortcode currently exposes user display name to anonymous users');

    assert_contains('QR-EXPOSED-1', $table_html, 'QR table shortcode currently exposes QR code row to anonymous users');
    assert_contains('Alice Customer', $table_html, 'QR table shortcode currently exposes assigned display name to anonymous users');
    assert_contains('Assigned', $table_html, 'QR table shortcode currently exposes assignment status to anonymous users');

    echo "security_boundary_characterization tests passed\n";
}
