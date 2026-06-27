<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Api\Controllers\AiController;
use Kerbcycle\QrCode\Services\AiProviderService;
use WP_REST_Request;
use WP_REST_Response;

final class AiControllerSmokeTest extends TestCase
{
    private const ADMIN_USER = 'AI Controller Admin';
    private const SUBSCRIBER_USER = 'AI Controller Subscriber';
    private const AI_ENDPOINT = 'https://ai-controller.test/ask';
    private const API_KEY = 'test-ai-key';

    public function tear_down(): void
    {
        remove_all_filters('pre_http_request');

        delete_option('kerbcycle_ai_provider');
        delete_option('kerbcycle_ai_render_endpoint');
        delete_option('kerbcycle_ai_render_api_key');
        delete_option('kerbcycle_ai_render_model');
       
    }

    private function request(array $params = [], string $nonce = ''): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/kerbcycle/v1/ai');

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        if ($nonce !== '') {
            $request->set_header('X-WP-Nonce', $nonce);
        }

        return $request;
    }

    private function configureRenderProvider(string $body): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', self::AI_ENDPOINT, false);
        update_option('kerbcycle_ai_render_api_key', self::API_KEY, false);
        update_option('kerbcycle_ai_render_model', 'controller-model', false);

        add_filter(
            'pre_http_request',
            function ($preempt, $parsedArgs, $url) use ($body) {
                if ($url !== self::AI_ENDPOINT) {
                    return $preempt;
                }

                $headers = $parsedArgs['headers'];
                $payload = json_decode((string) $parsedArgs['body'], true);

                $this->assertSame(self::API_KEY, $headers['x-api-key'] ?? '');
                $this->assertArrayHasKey('task', $payload);
                $this->assertArrayHasKey('data', $payload);

                return [
                    'headers' => [],
                    'body' => $body,
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
    }

    private function assertRestSuccessResponse($response, string $action): array
    {
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame($action, $data['action']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);

        return $data;
    }

    public function test_permissions_rejects_user_without_admin_capability(): void
    {
        $subscriberId = self::factory()->user->create(
            [
                'role' => 'subscriber',
                'display_name' => self::SUBSCRIBER_USER,
            ]
        );

        wp_set_current_user($subscriberId);

        $controller = new AiController();
        $result = $controller->permissions($this->request([], wp_create_nonce('wp_rest')));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status'] ?? null);
    }

    public function test_permissions_rejects_invalid_nonce_for_admin(): void
    {
        $adminId = self::factory()->user->create(
            [
                'role' => 'administrator',
                'display_name' => self::ADMIN_USER,
            ]
        );

        wp_set_current_user($adminId);

        $controller = new AiController();
        $result = $controller->permissions($this->request([], 'invalid-nonce'));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rest_nonce_invalid', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status'] ?? null);
    }

    public function test_permissions_accepts_admin_with_valid_nonce(): void
    {
        $adminId = self::factory()->user->create(
            [
                'role' => 'administrator',
                'display_name' => self::ADMIN_USER,
            ]
        );

        wp_set_current_user($adminId);

        $controller = new AiController();
        $result = $controller->permissions($this->request([], wp_create_nonce('wp_rest')));

        $this->assertTrue($result);
    }

    public function test_dispatch_rejects_missing_action(): void
    {
        $controller = new AiController();
        $result = $controller->dispatch_action($this->request());

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_action_missing', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status'] ?? null);
    }

    public function test_dispatch_rejects_invalid_action(): void
    {
        $controller = new AiController();
        $result = $controller->dispatch_action(
            $this->request(
                [
                    'action' => 'not_supported',
                ]
            )
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_action_invalid', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status'] ?? null);
    }

    public function test_dispatch_pickup_summary_returns_ai_output_and_meta(): void
    {
        $this->configureRenderProvider(
            wp_json_encode(
                [
                    'result' => [
                        'summary' => 'Three pickups completed.',
                        'risk_level' => 'low',
                    ],
                ]
            )
        );

        $controller = new AiController();
        $response = $controller->dispatch_action(
            $this->request(
                [
                    'action' => 'pickup_summary',
                    'from_date' => '2026-06-01<script>',
                    'to_date' => '2026-06-27',
                    'context' => "Completed pickups\n<script>alert(1)</script>",
                ]
            )
        );

        $data = $this->assertRestSuccessResponse($response, 'pickup_summary');

        $this->assertSame('Three pickups completed.', $data['data']['summary']);
        $this->assertSame('low', $data['data']['risk_level']);
        $this->assertSame('render', $data['meta']['provider']);
        $this->assertSame('controller-model', $data['meta']['model']);
        $this->assertArrayHasKey('latency_ms', $data['meta']);
    }

    public function test_dispatch_draft_template_returns_ai_output(): void
    {
        $this->configureRenderProvider(
            wp_json_encode(
                [
                    'result' => [
                        'title' => 'Pickup reminder',
                        'message' => 'Please place bags outside by 8 AM.',
                        'audience' => 'customers',
                    ],
                ]
            )
        );

        $controller = new AiController();
        $response = $controller->dispatch_action(
            $this->request(
                [
                    'action' => 'draft_template',
                    'topic' => 'Pickup reminder<script>',
                    'tone' => 'friendly',
                    'details' => "Morning pickup\n<script>alert(1)</script>",
                ]
            )
        );

        $data = $this->assertRestSuccessResponse($response, 'draft_template');

        $this->assertSame('Pickup reminder', $data['data']['title']);
        $this->assertSame('Please place bags outside by 8 AM.', $data['data']['message']);
        $this->assertSame('customers', $data['data']['audience']);
    }

    public function test_dispatch_qr_exceptions_returns_ai_output_and_source_payload(): void
    {
        $this->configureRenderProvider(
            wp_json_encode(
                [
                    'result' => [
                        'overview' => 'No severe QR issues found.',
                        'recommended_actions' => [
                            'Review exception notes.',
                        ],
                    ],
                ]
            )
        );

        $controller = new AiController();
        $response = $controller->dispatch_action(
            $this->request(
                [
                    'action' => 'qr_exceptions',
                    'from_date' => 'not-a-date',
                    'to_date' => 'also-not-a-date',
                ]
            )
        );

        $data = $this->assertRestSuccessResponse($response, 'qr_exceptions');

        $this->assertSame('No severe QR issues found.', $data['data']['overview']);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('window', $data['source']);
        $this->assertArrayHasKey('counts', $data['source']);
        $this->assertArrayHasKey('top_exception_groups', $data['source']);
        $this->assertArrayHasKey('notes', $data['source']);
    }

    public function test_dispatch_returns_provider_error(): void
    {
        update_option('kerbcycle_ai_provider', 'unsupported-provider', false);

        $controller = new AiController();
        $result = $controller->dispatch_action(
            $this->request(
                [
                    'action' => 'pickup_summary',
                    'from_date' => '2026-06-01',
                    'to_date' => '2026-06-27',
                ]
            )
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_unsupported', $result->get_error_code());
    }
}
