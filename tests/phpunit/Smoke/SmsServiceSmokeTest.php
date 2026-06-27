<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;
use Kerbcycle\QrCode\Services\MessagesService;
use Kerbcycle\QrCode\Services\SmsService;

final class SmsServiceSmokeTest extends TestCase
{
    private function message_log_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_message_logs';
    }

    public function tear_down(): void
    {
        remove_all_filters('pre_http_request');
        remove_all_filters('pre_wp_mail');

        delete_option(SmsService::OPT);
        delete_option(MessagesService::OPT);
    }

    public function test_sms_defaults_and_get_opts_merge_saved_values(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'webhook',
                'auth_method' => 'none',
                'gateway_url' => 'http://kerbcycle-sms-dev:4001/v1/messages',
                'method' => 'POST',
            ],
            false
        );

        $opts = SmsService::get_opts();

        $this->assertSame('webhook', $opts['provider']);
        $this->assertSame('none', $opts['auth_method']);
        $this->assertSame('http://kerbcycle-sms-dev:4001/v1/messages', $opts['gateway_url']);
        $this->assertSame('POST', $opts['method']);
        $this->assertArrayHasKey('country_code', $opts);
        $this->assertArrayHasKey('body_template', $opts);
        $this->assertArrayHasKey('headers', $opts);
    }

    public function test_normalize_phone_adds_country_code_to_local_number(): void
    {
        $opts = [
            'country_code' => '+1',
        ];

        $this->assertSame('+12015550123', SmsService::normalize_phone('(201) 555-0123', $opts));
        $this->assertSame('+15551234567', SmsService::normalize_phone('+15551234567', $opts));
    }

    public function test_email_to_sms_requires_gateway_domain(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'email2sms',
                'email_gateway' => '',
            ],
            false
        );

        $result = SmsService::send('+15551234567', 'Test message');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kc_sms_email_gateway', $result->get_error_code());
    }

    public function test_email_to_sms_success_uses_wp_mail_gateway_address(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'email2sms',
                'email_gateway' => 'vtext.example',
            ],
            false
        );

        $captured = [];

        add_filter(
            'pre_wp_mail',
            static function ($preempt, $atts) use (&$captured) {
                $captured = $atts;

                return true;
            },
            10,
            2
        );

        $result = SmsService::send('(201) 555-0123', '<strong>Hello</strong> SMS');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame('2015550123@vtext.example', $result['to']);
        $this->assertSame('2015550123@vtext.example', $captured['to']);
        $this->assertSame('Hello SMS', $captured['message']);
    }

    public function test_webhook_post_success_sends_json_body_and_headers(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'webhook',
                'api_key' => 'test-api-key',
                'api_secret' => '',
                'auth_method' => 'bearer',
                'from_number' => '+15555559999',
                'country_code' => '+1',
                'gateway_url' => 'http://kerbcycle-sms-dev:4001/v1/messages',
                'method' => 'POST',
                'body_template' => '{"from":"{from}","to":"{to}","body":"{message}"}',
                'headers' => "Content-Type: application/json\nX-Test-Key: {api_key}",
                'debug' => '0',
            ],
            false
        );

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://kerbcycle-sms-dev:4001/v1/messages') {
                    return $preempt;
                }

                $headers = $parsed_args['headers'];
                $body = json_decode((string) $parsed_args['body'], true);

                if (($headers['Authorization'] ?? '') !== 'Bearer test-api-key') {
                    return new \WP_Error('unexpected_auth', 'Unexpected auth header.');
                }

                if (($headers['X-Test-Key'] ?? '') !== 'test-api-key') {
                    return new \WP_Error('unexpected_custom_header', 'Unexpected custom header.');
                }

                if (($body['from'] ?? '') !== '+15555559999') {
                    return new \WP_Error('unexpected_from', 'Unexpected from number.');
                }

                if (($body['to'] ?? '') !== '+12015550123') {
                    return new \WP_Error('unexpected_to', 'Unexpected normalized recipient.');
                }

                if (($body['body'] ?? '') !== 'Pickup reminder') {
                    return new \WP_Error('unexpected_body', 'Unexpected message body.');
                }

                return [
                    'headers' => [],
                    'body' => '{"queued":true}',
                    'response' => [
                        'code' => 202,
                        'message' => 'Accepted',
                    ],
                    'cookies' => [],
                    'filename' => null,
                ];
            },
            10,
            3
        );

        $result = SmsService::send('(201) 555-0123', '<em>Pickup reminder</em>');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['http']);
        $this->assertSame('{"queued":true}', $result['body']);
    }

    public function test_webhook_post_http_error_returns_wp_error_with_status_data(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'webhook',
                'auth_method' => 'none',
                'gateway_url' => 'http://sms-error.test/v1/messages',
                'method' => 'POST',
                'body_template' => '{"to":"{to}","body":"{message}"}',
                'headers' => 'Content-Type: application/json',
                'debug' => '0',
            ],
            false
        );

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://sms-error.test/v1/messages') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => 'Forbidden',
                    'response' => [
                        'code' => 403,
                        'message' => 'Forbidden',
                    ],
                    'cookies' => [],
                    'filename' => null,
                ];
            },
            10,
            3
        );

        $result = SmsService::send('+15551234567', 'Blocked message');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kc_sms_http', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['http'] ?? null);
        $this->assertSame('Forbidden', $result->get_error_data()['body'] ?? null);
    }

    public function test_webhook_get_success_appends_template_as_query_args(): void
    {
        update_option(
            SmsService::OPT,
            [
                'provider' => 'webhook',
                'auth_method' => 'key_header',
                'api_key' => 'query-api-key',
                'gateway_url' => 'http://sms-get.test/send',
                'method' => 'GET',
                'country_code' => '+1',
                'body_template' => '{"to":"{to}","message":"{message}"}',
                'headers' => '',
                'debug' => '0',
            ],
            false
        );

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if (strpos($url, 'http://sms-get.test/send') !== 0) {
                    return $preempt;
                }

                $query = [];
                wp_parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

                if (($parsed_args['headers']['key'] ?? '') !== 'query-api-key') {
                    return new \WP_Error('unexpected_key_header', 'Unexpected key header.');
                }

               $queryToDigits = preg_replace('/\D+/', '', (string) ($query['to'] ?? ''));

               if ($queryToDigits !== '12015550123') {
                   return new \WP_Error('unexpected_query_to', 'Unexpected query recipient.');
}

                if (($query['message'] ?? '') !== 'GET message') {
                    return new \WP_Error('unexpected_query_message', 'Unexpected query message.');
                }

                return [
                    'headers' => [],
                    'body' => 'OK',
                    'response' => [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    'cookies' => [],
                    'filename' => null,
                ];
            },
            10,
            3
        );

        $result = SmsService::send('(201) 555-0123', 'GET message');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http']);
        $this->assertSame('OK', $result['body']);
    }

    public function test_send_notification_returns_error_when_user_has_no_phone(): void
    {
        $userId = self::factory()->user->create([
            'role' => 'subscriber',
            'display_name' => 'No Phone User',
        ]);

        $service = new SmsService();
        $result = $service->send_notification($userId, 'QR-NO-PHONE', 'assigned');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sms_config', $result->get_error_code());
    }

    public function test_send_notification_success_logs_message_result(): void
    {
        global $wpdb;

        update_option(
            SmsService::OPT,
            [
                'provider' => 'webhook',
                'auth_method' => 'none',
                'from_number' => '+15555559999',
                'country_code' => '+1',
                'gateway_url' => 'http://notify-sms.test/send',
                'method' => 'POST',
                'body_template' => '{"to":"{to}","message":"{message}"}',
                'headers' => 'Content-Type: application/json',
                'debug' => '0',
            ],
            false
        );

        update_option(
            MessagesService::OPT,
            [
                'assigned' => [
                    'sms' => 'Assigned {code} to {user}',
                    'email' => '',
                ],
            ],
            false
        );

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://notify-sms.test/send') {
                    return $preempt;
                }

                $body = json_decode((string) $parsed_args['body'], true);

                if (($body['to'] ?? '') !== '+12015550123') {
                    return new \WP_Error('unexpected_notify_to', 'Unexpected notification recipient.');
                }

                if (($body['message'] ?? '') !== 'Assigned QR-NOTIFY-001 to Notify User') {
                    return new \WP_Error('unexpected_notify_body', 'Unexpected notification body.');
                }

                return [
                    'headers' => [],
                    'body' => '{"sent":true}',
                    'response' => [
                        'code' => 200,
                        'message' => 'OK',
                    ],
                    'cookies' => [],
                    'filename' => null,
                ];
            },
            10,
            3
        );

        $userId = self::factory()->user->create([
            'role' => 'subscriber',
            'display_name' => 'Notify User',
        ]);

        update_user_meta($userId, 'billing_phone', '(201) 555-0123');

        $service = new SmsService();
        $result = $service->send_notification($userId, 'QR-NOTIFY-001', 'assigned');

        $this->assertTrue($result);

        $log = $wpdb->get_row(
            'SELECT * FROM ' . $this->message_log_table_name() . ' ORDER BY id DESC LIMIT 1'
        );

        $this->assertNotNull($log);
        $this->assertSame('sms', $log->type);
        $this->assertSame('(201) 555-0123', $log->recipient);
        $this->assertSame('Assigned QR-NOTIFY-001 to Notify User', $log->body);
        $this->assertSame('sent', $log->status);
        $this->assertSame('webhook', $log->provider);
        $this->assertStringContainsString('"ok":true', $log->response);
    }
}
