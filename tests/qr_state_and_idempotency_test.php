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

    function __($text, $domain = null)
    {
        return $text;
    }

    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // no-op
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

    function sanitize_text_field($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    function sanitize_textarea_field($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    function sanitize_key($value)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
    }

    function wp_unslash($value)
    {
        return $value;
    }

    function absint($value)
    {
        return abs((int) $value);
    }

    function get_userdata($user_id)
    {
        $users = $GLOBALS['kc_mock_users'] ?? [];
        if (!isset($users[$user_id])) {
            return false;
        }
        return (object) $users[$user_id];
    }

    function current_user_can($cap)
    {
        return $cap === 'manage_options' && (($GLOBALS['kc_current_role'] ?? 'anonymous') === 'administrator');
    }

    function get_option($key, $default = false)
    {
        $runtime = $GLOBALS['kc_runtime_options'] ?? [];
        if (array_key_exists($key, $runtime)) {
            return $runtime[$key];
        }
        $options = $GLOBALS['kc_mock_options'] ?? [];
        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    function update_option($key, $value, $autoload = null)
    {
        if (!isset($GLOBALS['kc_runtime_options']) || !is_array($GLOBALS['kc_runtime_options'])) {
            $GLOBALS['kc_runtime_options'] = [];
        }
        $GLOBALS['kc_runtime_options'][$key] = $value;
        return true;
    }

    function delete_option($key)
    {
        if (isset($GLOBALS['kc_runtime_options'][$key])) {
            unset($GLOBALS['kc_runtime_options'][$key]);
        }
        return true;
    }

    function wp_create_nonce($action)
    {
        return 'nonce:' . $action;
    }

    function wp_verify_nonce($nonce, $action)
    {
        return is_string($nonce) && $nonce === 'nonce:' . $action;
    }

    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }

    function current_time($type, $gmt = false)
    {
        if ($type === 'mysql') {
            return '2026-04-09 12:00:00';
        }
        return '2026-04-09T12:00:00Z';
    }

    function wp_json_encode($value)
    {
        return json_encode($value);
    }

    function wp_parse_args($args, $defaults = [])
    {
        if (!is_array($args)) {
            $args = [];
        }
        return array_merge($defaults, $args);
    }

    function wp_kses_post($text)
    {
        return (string) $text;
    }

    function apply_filters($hook, $value)
    {
        return $value;
    }

    function wp_remote_post($url, $args = [])
    {
        $GLOBALS['kc_webhook_call_count'] = (int) ($GLOBALS['kc_webhook_call_count'] ?? 0) + 1;
        return [
            'response' => ['code' => 200],
            'body' => '{"summary":"ok","category":"ops","severity":"low","recommended_action":"none"}',
        ];
    }

    function wp_remote_retrieve_response_code($response)
    {
        return isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
    }

    function wp_remote_retrieve_body($response)
    {
        return isset($response['body']) ? (string) $response['body'] : '';
    }

    class FakeWpdb
    {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $tables = [
            'wp_kerbcycle_qr_codes' => [],
            'wp_kerbcycle_qr_code_history' => [],
            'wp_kerbcycle_pickup_exceptions' => [],
            'wp_kerbcycle_error_logs' => [],
        ];
        private $ids = [
            'wp_kerbcycle_qr_codes' => 0,
            'wp_kerbcycle_qr_code_history' => 0,
            'wp_kerbcycle_pickup_exceptions' => 0,
            'wp_kerbcycle_error_logs' => 0,
        ];

        public function prepare($query, ...$args)
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            foreach ($args as $arg) {
                $posS = strpos($query, '%s');
                $posD = strpos($query, '%d');
                if ($posS !== false && ($posD === false || $posS < $posD)) {
                    $query = substr_replace($query, "'" . addslashes((string) $arg) . "'", $posS, 2);
                } else {
                    $query = substr_replace($query, (string) (int) $arg, $posD, 2);
                }
            }
            return $query;
        }

        public function insert($table, $data, $format = [])
        {
            if (!isset($this->tables[$table])) {
                $this->tables[$table] = [];
                $this->ids[$table] = 0;
            }
            $this->ids[$table]++;
            $row = $data;
            $row['id'] = $this->ids[$table];
            $this->tables[$table][] = $row;
            $this->insert_id = $row['id'];
            return 1;
        }

        public function update($table, $data, $where, $format = [], $where_format = [])
        {
            if (!isset($this->tables[$table])) {
                return 0;
            }
            $updated = 0;
            foreach ($this->tables[$table] as &$row) {
                $match = true;
                foreach ($where as $k => $v) {
                    if (!array_key_exists($k, $row) || (string) $row[$k] !== (string) $v) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
                foreach ($data as $k => $v) {
                    $row[$k] = $v;
                }
                $updated++;
            }
            unset($row);
            return $updated;
        }

        public function get_row($query)
        {
            if (preg_match("/FROM\s+(\w+)\s+WHERE\s+qr_code\s*=\s*'([^']+)'\s+AND\s+status\s*=\s*'([^']+)'\s+ORDER BY id DESC LIMIT 1/i", $query, $m)) {
                $table = $m[1];
                $code = stripslashes($m[2]);
                $status = stripslashes($m[3]);
                $rows = array_values(array_filter($this->tables[$table] ?? [], static function ($r) use ($code, $status) {
                    return ($r['qr_code'] ?? '') === $code && ($r['status'] ?? '') === $status;
                }));
                if (!$rows) {
                    return null;
                }
                usort($rows, static function ($a, $b) { return (int) $b['id'] <=> (int) $a['id']; });
                return (object) $rows[0];
            }

            if (preg_match("/FROM\s+(\w+)\s+WHERE\s+qr_code\s*=\s*'([^']+)'\s+ORDER BY id DESC LIMIT 1/i", $query, $m)) {
                $table = $m[1];
                $code = stripslashes($m[2]);
                $rows = array_values(array_filter($this->tables[$table] ?? [], static function ($r) use ($code) {
                    return ($r['qr_code'] ?? '') === $code;
                }));
                if (!$rows) {
                    return null;
                }
                usort($rows, static function ($a, $b) { return (int) $b['id'] <=> (int) $a['id']; });
                return (object) $rows[0];
            }

            if (preg_match('/FROM\s+(\w+)\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $m)) {
                $table = $m[1];
                $id = (int) $m[2];
                foreach ($this->tables[$table] ?? [] as $row) {
                    if ((int) ($row['id'] ?? 0) === $id) {
                        return (object) $row;
                    }
                }
                return null;
            }

            return null;
        }

        public function get_var($query)
        {
            if (preg_match("/SELECT\s+COUNT\(\*\)\s+FROM\s+(\w+)\s+WHERE\s+qr_code\s*=\s*'([^']+)'\s+AND\s+status\s*=\s*'([^']+)'/i", $query, $m)) {
                $table = $m[1];
                $code = stripslashes($m[2]);
                $status = stripslashes($m[3]);
                $count = 0;
                foreach ($this->tables[$table] ?? [] as $row) {
                    if (($row['qr_code'] ?? '') === $code && ($row['status'] ?? '') === $status) {
                        $count++;
                    }
                }
                return $count;
            }
            return 0;
        }

        public function query($query)
        {
            return 0;
        }
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

    // install fake db and autoloader
    $GLOBALS['wpdb'] = new FakeWpdb();
    $GLOBALS['kc_mock_users'] = [
        101 => ['ID' => 101, 'display_name' => 'User One', 'user_login' => 'user1', 'user_email' => 'u1@example.com'],
        202 => ['ID' => 202, 'display_name' => 'User Two', 'user_login' => 'user2', 'user_email' => 'u2@example.com'],
    ];
    $GLOBALS['kc_mock_options'] = [
        'kerbcycle_ai_webhook_options' => [
            'env' => 'dev',
            'webhook_url_dev' => 'https://example.test/webhook',
            'webhook_url_stage' => '',
            'webhook_url_prod' => '',
            'timeout' => 20,
        ],
    ];
    $GLOBALS['kc_runtime_options'] = [];

    require_once __DIR__ . '/../includes/Autoloader.php';
    \Kerbcycle\QrCode\Autoloader::run();

    // ---- Test 1: available -> assigned succeeds ----
    $repo = new \Kerbcycle\QrCode\Data\Repositories\QrCodeRepository();
    $repo->insert_available('QR-STATE-1');

    $service = new \Kerbcycle\QrCode\Services\QrService();
    $assign_result = $service->assign('QR-STATE-1', 101, false, false, false);

    assert_true(!is_wp_error($assign_result), 'Assign from available should succeed');
    $latest_qr_state_1 = $repo->find_by_qr_code('QR-STATE-1');
    assert_equals('assigned', $latest_qr_state_1->status, 'QR status should be assigned');
    assert_equals('101', (string) $latest_qr_state_1->user_id, 'Assigned user_id should match');

    // ---- Test 2: assigned -> assigned different user fails ----
    $assign_again = $service->assign('QR-STATE-1', 202, false, false, false);
    assert_true(is_wp_error($assign_again), 'Reassigning assigned QR to different user should fail');
    assert_equals('qr_code_already_assigned', $assign_again->get_error_code(), 'Expected qr_code_already_assigned code');

    // ---- Test 3: release from non-assigned fails ----
    $repo->insert_available('QR-STATE-2');
    $release_non_assigned = $service->release('QR-STATE-2', false, false);
    assert_true(is_wp_error($release_non_assigned), 'Releasing available QR should fail');
    assert_equals('invalid_state', $release_non_assigned->get_error_code(), 'Expected invalid_state code');

    // ---- Test 4: duplicate/near-duplicate assignment attempts keep canonical state ----
    $repo->insert_available('QR-RACE-1');
    $first_assign = $service->assign('QR-RACE-1', 101, false, false, false);
    $second_assign = $service->assign('QR-RACE-1', 202, false, false, false);

    assert_true(!is_wp_error($first_assign), 'First sequential assign should succeed in race characterization');
    assert_true(is_wp_error($second_assign), 'Second sequential assign should fail in current observed behavior');
    $race_rows = array_values(array_filter($GLOBALS['wpdb']->tables['wp_kerbcycle_qr_codes'], static function ($row) {
        return ($row['qr_code'] ?? '') === 'QR-RACE-1';
    }));
    assert_equals(1, count($race_rows), 'Duplicate assignment attempts should keep one canonical QR row');

    // ---- Test 5: unknown QR assignment is explicitly rejected ----
    $unknown_assign = $service->assign('QR-UNKNOWN-1', 101, false, false, false);
    assert_true(is_wp_error($unknown_assign), 'Unknown QR assignment should fail safely');
    assert_equals('qr_code_not_available', $unknown_assign->get_error_code(), 'Unknown QR assignment should return qr_code_not_available');

    // ---- Test 6: release affects current assigned record only ----
    $repo->insert_available('QR-REL-1');
    $assign_for_release = $service->assign('QR-REL-1', 101, false, false, false);
    assert_true(!is_wp_error($assign_for_release), 'Release setup assignment should succeed');
    $release_result = $service->release('QR-REL-1', false, false);
    assert_true(!is_wp_error($release_result), 'Release from assigned state should succeed');
    $released_row = $repo->find_by_qr_code('QR-REL-1');
    assert_equals('available', $released_row->status, 'Released QR should return to available');
    assert_true(!isset($released_row->user_id) || $released_row->user_id === null, 'Released QR should clear user_id');

    // ---- Test 7: pickup exception duplicate submission characterization ----
    $GLOBALS['kc_current_role'] = 'administrator';
    $GLOBALS['kc_webhook_call_count'] = 0;

    $ajax = new \Kerbcycle\QrCode\Admin\Ajax\AdminAjax();

    $payload = [
        'security' => wp_create_nonce('kerbcycle_qr_nonce'),
        'qr_code' => 'QR-EXC-1',
        'customer_id' => '101',
        'issue' => 'Missed pickup',
        'notes' => 'Same payload duplicate characterization',
    ];

    $_POST = $payload;
    $_REQUEST = $payload;
    try {
        $ajax->test_pickup_exception();
    } catch (TestAjaxResponse $response_one) {
        assert_true($response_one->success, 'First duplicate submission should complete with JSON success envelope');
    }

    $_POST = $payload;
    $_REQUEST = $payload;
    try {
        $ajax->test_pickup_exception();
    } catch (TestAjaxResponse $response_two) {
        assert_true($response_two->success, 'Second duplicate submission should complete with JSON success envelope');
        assert_equals('duplicate_suppressed', $response_two->data['status'], 'Second duplicate submission should be suppressed');
    }

    $exception_rows = $GLOBALS['wpdb']->tables['wp_kerbcycle_pickup_exceptions'];
    assert_equals(1, count($exception_rows), 'Duplicate submissions should create a single effective row');
    assert_equals(1, (int) $GLOBALS['kc_webhook_call_count'], 'Duplicate submissions should trigger a single webhook attempt');

    echo "qr_state_and_idempotency tests passed\n";
}

namespace Kerbcycle\QrCode\Install {
    // Minimal no-op seam to avoid activation DDL side effects in unit-style execution.
    class Activator
    {
        public static function activate()
        {
            return;
        }
    }
}
