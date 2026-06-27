<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Services\MessagesService;
use Kerbcycle\QrCode\Services\SmsService;

final class SmsServiceSmokeTest extends TestCase
{
    private const LOCAL_PHONE = '(201) 555-0123';
    private const NORMALIZED_PHONE = '+12015550123';
    private const NORMALIZED_PHONE_DIGITS = '12015550123';
    private const E164_PHONE = '+15551234567';
    private const FROM_NUMBER = '+15555559999';

    private const PROVIDER_WEBHOOK = 'webhook';
    private const HEADER_JSON_LINE = 'Content-Type: application/json';

    private const SMS_ENDPOINT = 'https://kerbcycle-sms-dev.test/v1/messages';
    private const SMS_ERROR_ENDPOINT = 'https://sms-error.test/v1/messages';
    private const SMS_GET_ENDPOINT = 'https://sms-get.test/send';
    private const NOTIFY_SMS_ENDPOINT = 'https://notify-sms.test/send';

    private function messageLogTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_message_logs';
    }

    private function updateSmsOptions(array $overrides): void
    {
        update_option(
            SmsService::OPT,
            array_merge(
                [
                    'debug' => '0',
                ],
                $overrides
            ),
            false
        );
    }

    private function httpResponse(int $code, string $message, string $body): array
    {
        return [
            'headers' => [],
            'body' => $body,
            'response' => [
                'code' => $code,
                'message' => $message,
            ],
            'cookies' => [],
            'filename' => null,
        ];
    }

    private function assertSuccessfulSmsResult($result, int $statusCode, string $body): void
    {
        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame($statusCode, $result['http']);
        $this->assertSame($body, $result['body']);
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
        $this->updateSmsOptions(
            [
                'provider' => self::PROVIDER_WEBHOOK,
                'auth_method' => 'none',
                'gateway_url' => self::SMS_ENDPOINT,
                'method' => 'POST',
            ]
        );

        $opts = SmsService::get_opts();

        $this->assertSame(self::PROVIDER_WEBHOOK, $opts['provider']);
        $this->assertSame('none', $opts['auth_method']);
        $this->assertSame(self::SMS_ENDPOINT, $opts['gateway_url']);
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

        $this->assertSame(self::NORMALIZED_PHONE, SmsService::normalize_phone(self::LOCAL_PHONE, $opts));
        $this->assertSame(self::E164_PHONE, SmsService::normalize_phone(self::E164_PHONE, $opts));
    }

    public function test_email_to_sms_requires_gateway_domain(): void
    {
        $this->updateSmsOptions(
            [
                'provider' => 'email2sms',
                'email_gateway' => '',
            ]
        );

        $result = SmsService::send(self::E164_PHONE, 'Test message');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kc_sms_email_gateway', $result->get_error_code());
    }

    public function test_email_to_sms_success_uses_wp_mail_gateway_address(): void
    {
        $this->updateSmsOptions(
            [
                'provider' => 'email2sms',
                'email_gateway' => 'vtext.example',
            ]
        );

        $captured = [];

        add_filter(
            'pre_wp_mail',
            function ($preempt, $atts) use (&$captured) {
                $captured = $atts;

                if (null !== $preempt) {
                    return $preempt;
                }

                return true;
            },
            10,
            2
        );

        $result = SmsService::send(self::LOCAL_PHONE, '<strong>Hello</strong> SMS');

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame('2015550123@vtext.example', $result['to']);
        $this->assertSame('2015550123@vtext.example', $captured['to']);
        $this->assertSame('Hello SMS', $captured['message']);
    }

    public function test_webhook_post_success_sends_json_body_and_headers(): void
    {
        $this->updateSmsOptions(
            [
                'provider' => self::PROVIDER_WEBHOOK,
                'api_key' => 'test-api-key',
                'api_secret' => '',
                'auth_method' => 'bearer',
                'from_number' => self::FROM_NUMBER,
                'country_code' => '+1',
                'gateway_url' => self::SMS_ENDPOINT,
                'method' => 'POST',
                'body_template' => '{"from":"{from}","to":"{to}","body":"{message}"}',
                'headers' => self::HEADER_JSON_LINE . "\nX-Test-Key: {api_key}",
            ]
        );

        add_filter(
            'pre_http_request',
            function ($preempt, $parsedArgs, $url) {
                if ($url !== self::SMS_ENDPOINT) {
                    return $preempt;
                }

                $headers = $parsedArgs['headers'];
                $body = json_decode((string) $parsedArgs['body'], true);

                $this->assertSame('Bearer test-api-key', $headers['Authorization'] ?? '');
                $this->assertSame('test-api-key', $headers['X-Test-Key'] ?? '');
                $this->assertSame(self::FROM_NUMBER, $body['from'] ?? '');
                $this->assertSame(self::NORMALIZED_PHONE, $body['to'] ?? '');
                $this->assertSame('Pickup reminder', $body['body'] ?? '');

                return $this->httpResponse(202, 'Accepted', '{"queued":true}');
            },
            10,
            3
        );

        $result = SmsService::send(self::LOCAL_PHONE, '<em>Pickup reminder</em>');

        $this->assertSuccessfulSmsResult($result, 202, '{"queued":true}');
    }

    public function test_webhook_post_http_error_returns_wp_error_with_status_data(): void
    {
        $this->updateSmsOptions(
            [
                'provider' => self::PROVIDER_WEBHOOK,
                'auth_method' => 'none',
                'gateway_url' => self::SMS_ERROR_ENDPOINT,
                'method' => 'POST',
                'body_template' => '{"to":"{to}","body":"{message}"}',
                'headers' => self::HEADER_JSON_LINE,
            ]
        );

        add_filter(
            'pre_http_request',
            function ($preempt, $parsedArgs, $url) {
                if ($url !== self::SMS_ERROR_ENDPOINT) {
                    return $preempt;
                }

                $this->assertIsArray($parsedArgs);

                return $this->httpResponse(403, 'Forbidden', 'Forbidden');
            },
            10,
            3
        );

        $result = SmsService::send(self::E164_PHONE, 'Blocked message');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kc_sms_http', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['http'] ?? null);
        $this->assertSame('Forbidden', $result->get_error_data()['body'] ?? null);
    }

    public function test_webhook_get_success_appends_template_as_query_args(): void
    {
        $this->updateSmsOptions(
            [
                'provider' => self::PROVIDER_WEBHOOK,
                'auth_method' => 'key_header',
                'api_key' => 'query-api-key',
                'gateway_url' => self::SMS_GET_ENDPOINT,
                'method' => 'GET',
                'country_code' => '+1',
                'body_template' => '{"to":"{to}","message":"{message}"}',
                'headers' => '',
            ]
        );

        add_filter(
            'pre_http_request',
            function ($preempt, $parsedArgs, $url) {
                if (strpos($url, self::SMS_GET_ENDPOINT) !== 0) {
                    return $preempt;
                }

                $query = [];
                wp_parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

                $queryToDigits = preg_replace('/\D+/', '', (string) ($query['to'] ?? ''));

                $this->assertSame('query-api-key', $parsedArgs['headers']['key'] ?? '');
                $this->assertSame(self::NORMALIZED_PHONE_DIGITS, $queryToDigits);
                $this->assertSame('GET message', $query['message'] ?? '');

                return $this->httpResponse(200, 'OK', 'OK');
            },
            10,
            3
        );

        $result = SmsService::send(self::LOCAL_PHONE, 'GET message');

        $this->assertSuccessfulSmsResult($result, 200, 'OK');
    }

    public function test_send_notification_returns_error_when_user_has_no_phone(): void
    {
        $userId = self::factory()->user->create(
            [
                'role' => 'subscriber',
                'display_name' => 'No Phone User',
            ]
        );

        $service = new SmsService();
        $result = $service->send_notification($userId, 'QR-NO-PHONE', 'assigned');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sms_config', $result->get_error_code());
    }

    public function test_send_notification_success_logs_message_result(): void
    {
        global $wpdb;

        $this->updateSmsOptions(
            [
                'provider' => self::PROVIDER_WEBHOOK,
                'auth_method' => 'none',
                'from_number' => self::FROM_NUMBER,
                'country_code' => '+1',
                'gateway_url' => self::NOTIFY_SMS_ENDPOINT,
                'method' => 'POST',
                'body_template' => '{"to":"{to}","message":"{message}"}',
                'headers' => self::HEADER_JSON_LINE,
            ]
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
            function ($preempt, $parsedArgs, $url) {
                if ($url !== self::NOTIFY_SMS_ENDPOINT) {
                    return $preempt;
                }

                $body = json_decode((string) $parsedArgs['body'], true);

                $this->assertSame(self::NORMALIZED_PHONE, $body['to'] ?? '');
                $this->assertSame('Assigned QR-NOTIFY-001 to Notify User', $body['message'] ?? '');

                return $this->httpResponse(200, 'OK', '{"sent":true}');
            },
            10,
            3
        );

        $userId = self::factory()->user->create(
            [
                'role' => 'subscriber',
                'display_name' => 'Notify User',
            ]
        );

        update_user_meta($userId, 'billing_phone', self::LOCAL_PHONE);

        $service = new SmsService();
        $result = $service->send_notification($userId, 'QR-NOTIFY-001', 'assigned');

        $this->assertTrue($result);

        $log = $wpdb->get_row(
            'SELECT * FROM ' . $this->messageLogTableName() . ' ORDER BY id DESC LIMIT 1'
        );

        $this->assertNotNull($log);
        $this->assertSame('sms', $log->type);
        $this->assertSame(self::LOCAL_PHONE, $log->recipient);
        $this->assertSame('Assigned QR-NOTIFY-001 to Notify User', $log->body);
        $this->assertSame('sent', $log->status);
        $this->assertSame(self::PROVIDER_WEBHOOK, $log->provider);
        $this->assertStringContainsString('"ok":true', $log->response);
    }
}