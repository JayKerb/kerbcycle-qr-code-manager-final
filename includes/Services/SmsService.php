<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\MessagesService;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;
use Kerbcycle\QrCode\Helpers\Nonces;

class SmsService
{

    const OPT = 'kerbcycle_sms_options';

    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /* ---------------- Admin ---------------- */

    public static function defaults()
    {
        return [
            'provider'       => 'webex', // webex|twilio|textbelt|messagebird|webhook|email2sms
            'api_key'        => '',
            'api_secret'     => '',
            'auth_method'    => 'key_header', // none|basic|bearer|key_header|custom
            'from_number'    => '',
            'country_code'   => '+1',
            'gateway_url'    => 'https://api.us.webexconnect.io/v1/sms/messages',
            'method'         => 'POST', // POST|GET
            // JSON with placeholders {to},{from},{message},{api_key},{api_secret}
            'body_template'  => "{\n  \"from\": \"{from}\",\n  \"to\": \"{to}\",\n  \"content\": \"{message}\",\n  \"contentType\": \"text\"\n}",
            // One per line: Header-Name: value (placeholders allowed)
            'headers'        => "Content-Type: application/json\nkey: {api_key}",
            'email_gateway'  => '', // for email-to-sms (e.g., vtext.com)
            'debug'          => '0',
        ];
    }

    public static function get_opts()
    {
        $opts = get_option(self::OPT, []);
        return wp_parse_args(is_array($opts) ? $opts : [], self::defaults());
    }

    public function register_settings()
    {
        register_setting(self::OPT, self::OPT, [$this, 'sanitize']);

        add_settings_section('kc_sms_main', 'Provider & Authentication', function () {
            echo '<p>Configure a generic SMS gateway. Works with Webex Connect, Twilio-compatible, Textbelt, MessageBird, or any custom webhook.</p>';
        }, self::OPT);

        $this->add_field('provider', 'Provider', function ($o) {
            $map = [
                'webex'       => 'Webex Connect (Sandbox/Prod)',
                'twilio'      => 'Twilio-compatible',
                'messagebird' => 'MessageBird',
                'textbelt'    => 'Textbelt / Self-hosted',
                'webhook'     => 'Generic Webhook',
                'email2sms'   => 'Email-to-SMS (via wp_mail)',
            ];
            echo '<select name="' . esc_attr(self::OPT) . '[provider]">';
            foreach ($map as $k => $v) {
                printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($o['provider'], $k, false), esc_html($v));
            }
            echo '</select>';
        });

        $this->add_field('api_key', 'API Key / Service Key', function ($o) {
            printf(
                '<input type="text" name="%s[api_key]" value="%s" style="width:380px" />',
                esc_attr(self::OPT),
                esc_attr($o['api_key'])
            );
        });

        $this->add_field('api_secret', 'API Secret / Token (if needed)', function ($o) {
            printf(
                '<input type="password" name="%s[api_secret]" value="%s" style="width:380px" />',
                esc_attr(self::OPT),
                esc_attr($o['api_secret'])
            );
        });

        $this->add_field('auth_method', 'Auth Method', function ($o) {
            $methods = [
                'none'       => 'None',
                'basic'      => 'HTTP Basic (user=API Key, pass=Secret)',
                'bearer'     => 'Bearer <API Key>',
                'key_header' => 'Header: key: {api_key}',
                'custom'     => 'Custom (use headers box)',
            ];
            echo '<select name="' . esc_attr(self::OPT) . '[auth_method]">';
            foreach ($methods as $k => $v) {
                printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($o['auth_method'], $k, false), esc_html($v));
            }
            echo '</select>';
        });

        add_settings_section('kc_sms_routing', 'Routing & Templates', '__return_null', self::OPT);

        $this->add_field('from_number', 'Default From (Sender ID/Number)', function ($o) {
            printf(
                '<input type="text" name="%s[from_number]" value="%s" placeholder="+15551234567 or ALPHASENDER" style="width:280px" />',
                esc_attr(self::OPT),
                esc_attr($o['from_number'])
            );
            echo '<p class="description">Webex sandbox often uses a pre-provisioned number; Twilio requires a purchased number; MessageBird can accept up to 11-char alphanumeric.</p>';
        });

        $this->add_field('country_code', 'Default Country Code', function ($o) {
            printf(
                '<input type="text" name="%s[country_code]" value="%s" placeholder="+1" style="width:120px" />',
                esc_attr(self::OPT),
                esc_attr($o['country_code'])
            );
        });

        $this->add_field('gateway_url', 'Gateway URL', function ($o) {
            printf(
                '<input type="url" name="%s[gateway_url]" value="%s" style="width:520px" />',
                esc_attr(self::OPT),
                esc_attr($o['gateway_url'])
            );
        });

        $this->add_field('method', 'HTTP Method', function ($o) {
            echo '<select name="' . esc_attr(self::OPT) . '[method]">';
            foreach (['POST', 'GET'] as $m) {
                printf('<option %s>%s</option>', selected($o['method'], $m, false), $m);
            }
            echo '</select>';
        });

        $this->add_field('body_template', 'Request Body Template (JSON or form-encoded)', function ($o) {
            printf(
                '<textarea name="%s[body_template]" rows="8" style="width:520px">%s</textarea>',
                esc_attr(self::OPT),
                esc_textarea($o['body_template'])
            );
            echo '<p class="description">Placeholders: {to} {from} {message} {api_key} {api_secret}</p>';
        });

        $this->add_field('headers', 'Custom Headers (one per line)', function ($o) {
            printf(
                '<textarea name="%s[headers]" rows="5" style="width:520px">%s</textarea>',
                esc_attr(self::OPT),
                esc_textarea($o['headers'])
            );
            echo '<p class="description">Example:<br>Content-Type: application/json<br>key: {api_key}<br>Authorization: Bearer {api_key}</p>';
        });

        $this->add_field('email_gateway', 'Email-to-SMS Gateway (if used)', function ($o) {
            printf(
                '<input type="text" name="%s[email_gateway]" value="%s" placeholder="vtext.com" style="width:240px" />',
                esc_attr(self::OPT),
                esc_attr($o['email_gateway'])
            );
        });

        $this->add_field('debug', 'Debug Logging', function ($o) {
            printf(
                '<label><input type="checkbox" name="%s[debug]" value="1" %s /> Log requests/responses to error_log</label>',
                esc_attr(self::OPT),
                checked('1', $o['debug'], false)
            );
        });

        // Quick test sender (same screen)
        add_settings_section('kc_sms_test', 'Send Test', '__return_null', self::OPT);
        add_settings_field('kc_sms_test_btn', '', function () {
            wp_nonce_field('kc_sms_test', 'kc_sms_test_nonce');
            echo '<input type="text" name="kc_sms_test_to" placeholder="+15551234567" style="width:220px" /> ';
            echo '<input type="text" name="kc_sms_test_msg" placeholder="Hello from KerbCycle" style="width:320px" /> ';
            submit_button('Send Test SMS', 'secondary', 'kc_sms_do_test', false);
        }, self::OPT, 'kc_sms_test');
    }

    private function add_field($key, $label, $cb)
    {
        add_settings_field($key, $label, function () use ($cb) {
            $cb($this->get_opts());
        }, self::OPT, 'kc_sms_main' === $key || 'kc_sms_routing' === $key ? $key : (strpos($key, 'kc_sms_') === 0 ? 'kc_sms_test' : 'kc_sms_routing'));
    }

    public function sanitize($in)
    {
        $out = self::defaults();
        foreach ($out as $k => $v) {
            if (!isset($in[$k])) {
                continue;
            }
            $val = is_string($in[$k]) ? trim($in[$k]) : $in[$k];
            $out[$k] = is_string($val) ? wp_kses_post($val) : $val;
        }
        // Handle in-page test send
        if (!empty($_POST['kc_sms_do_test'])) {
            Nonces::verify('kc_sms_test', 'kc_sms_test_nonce');
            $to  = isset($_POST['kc_sms_test_to']) ? sanitize_text_field($_POST['kc_sms_test_to']) : '';
            $msg = isset($_POST['kc_sms_test_msg']) ? sanitize_text_field($_POST['kc_sms_test_msg']) : '';
            if ($to && $msg) {
                $res = kerbcycle_sms_send($to, $msg);
                if (is_wp_error($res)) {
                    add_settings_error(self::OPT, 'kc_sms_test_fail', 'Test failed: ' . $res->get_error_message(), 'error');
                } else {
                    add_settings_error(self::OPT, 'kc_sms_test_ok', 'Test sent: ' . esc_html(json_encode($res)), 'updated');
                }
            } else {
                add_settings_error(self::OPT, 'kc_sms_test_missing', 'Please provide both a phone number and a message.', 'error');
            }
        }
        return $out;
    }

    public static function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>KerbCycle SMS Settings</h1>';
        settings_errors(self::OPT);
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPT);
        do_settings_sections(self::OPT);
        submit_button('Save Settings');
        echo '</form></div>';
    }

    /* ---------------- Sender ---------------- */

    public static function normalize_phone($to, $opts)
    {
        $to = trim($to);
        if (strpos($to, '+') !== 0 && !empty($opts['country_code'])) {
            $cc = preg_replace('/\s+/', '', $opts['country_code']);
            if ($cc && $cc[0] !== '+') {
                $cc = '+' . $cc;
            }
            $to = $cc . preg_replace('/\D+/', '', $to);
        }
        return $to;
    }

    // Build headers array from textarea lines + auth_method
    private static function build_headers($opts, $map)
    {
        $headers = [];
        // 1) Auth method helpers
        switch ($opts['auth_method']) {
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode($opts['api_key'] . ':' . $opts['api_secret']);
                break;
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $opts['api_key'];
                break;
            case 'key_header':
                $headers['key'] = $opts['api_key'];
                break;
            case 'custom':
            case 'none':
            default:
                // use custom headers box only
                break;
        }
        // 2) Custom headers textarea (supports placeholders)
        $lines = preg_split('/\r\n|\n|\r/', (string) $opts['headers']);
        foreach ($lines as $line) {
            if (!trim($line) || strpos($line, ':') === false) {
                continue;
            }
            list($k, $v) = array_map('trim', explode(':', $line, 2));
            $v = strtr($v, $map);
            $headers[$k] = $v;
        }
        return $headers;
    }

    public static function send($to, $message, $args = [])
    {
        $opts = self::get_opts();

        // Email-to-SMS pathway (optional)
        if ($opts['provider'] === 'email2sms') {
            if (empty($opts['email_gateway'])) {
                return new \WP_Error('kc_sms_email_gateway', 'Email-to-SMS gateway domain is missing.');
            }
            $digits = preg_replace('/\D+/', '', $to);
            $addr   = $digits . '@' . $opts['email_gateway'];
            $sent = wp_mail($addr, '', wp_strip_all_tags($message));
            return $sent ? ['ok' => true, 'to' => $addr] : new \WP_Error('kc_sms_email_fail', 'wp_mail failed.');
        }

        $to_norm = self::normalize_phone($to, $opts);
        $from    = isset($args['from']) && $args['from'] !== '' ? $args['from'] : $opts['from_number'];

        $map = [
            '{to}'         => $to_norm,
            '{from}'       => $from,
            '{message}'    => wp_strip_all_tags($message),
            '{api_key}'    => $opts['api_key'],
            '{api_secret}' => $opts['api_secret'],
        ];

        $url     = trim($opts['gateway_url']);
        $method  = strtoupper($opts['method']);
        $bodyTpl = (string) $opts['body_template'];
        $body    = strtr($bodyTpl, $map);

        $headers = self::build_headers($opts, $map);
        if (empty($headers['Content-Type'])) {
            // Default to JSON
            $headers['Content-Type'] = 'application/json';
        }

        $args_http = [
            'timeout' => 20,
            'headers' => $headers,
        ];

        if ($method === 'GET') {
            // Append query
            $query = [];
            // Try to interpret JSON; if fails, treat as URL-encoded template “key=value&…”
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $query = $decoded;
            } else {
                wp_parse_str($body, $query);
            }
            $url = add_query_arg($query, $url);
            $response = wp_remote_get($url, $args_http);
        } else {
            // POST
            // If JSON header, send JSON; else send as form data
            if (stripos($headers['Content-Type'], 'json') !== false) {
                $args_http['body'] = $body;
            } else {
                $decoded = json_decode($body, true);
                $args_http['body'] = is_array($decoded) ? $decoded : $body;
            }
            $response = wp_remote_post($url, $args_http);
        }

        if (is_wp_error($response)) {
            if ($opts['debug'] === '1') {
                error_log('KerbCycle SMS WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $resp_body = wp_remote_retrieve_body($response);

        if ($opts['debug'] === '1') {
            error_log('KerbCycle SMS Response: HTTP ' . $code . ' Body: ' . $resp_body);
        }

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'http' => $code, 'body' => $resp_body];
        }
        return new \WP_Error('kc_sms_http', 'SMS gateway error', ['http' => $code, 'body' => $resp_body]);
    }

    /**
     * Send a notification SMS.
     *
     * @param int    $user_id The user ID.
     * @param string $qr_code The QR code.
     * @param string $type    The type of notification (e.g., 'assigned', 'released').
     *
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function send_notification($user_id, $qr_code, $type = 'assigned')
    {
        $to = get_user_meta($user_id, 'phone_number', true);
        if (empty($to)) {
            $to = get_user_meta($user_id, 'billing_phone', true);
        }

        if (empty($to)) {
            return new \WP_Error('sms_config', __('Missing phone number', 'kerbcycle'));
        }

        $user     = get_userdata($user_id);
        $rendered = MessagesService::render($type, [
            'user' => $user ? ($user->display_name ?: $user->user_login) : '',
            'code' => $qr_code,
        ]);

        $body   = $rendered['sms'];
        $result = self::send($to, $body);

        // Log the SMS
        MessageLogRepository::log_message([
            'type'     => 'sms',
            'to'       => $to,
            'body'     => $body,
            'status'   => is_wp_error($result) ? 'failed' : 'sent',
            'provider' => self::get_opts()['provider'] ?? 'unknown',
            'response' => is_wp_error($result) ? $result->get_error_message() : json_encode($result),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}

/**
 * Public helper your plugin can call anywhere.
 */
function kerbcycle_sms_send($to, $message, $args = [])
{
    return SmsService::send($to, $message, $args);
}
