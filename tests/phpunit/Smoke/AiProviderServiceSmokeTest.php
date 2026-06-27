<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Services\AiProviderService;

final class AiProviderServiceSmokeTest extends TestCase
{
    public function tear_down(): void
    {
        remove_all_filters('pre_http_request');

        delete_option('kerbcycle_ai_provider');
        delete_option('kerbcycle_ai_ollama_base_url');
        delete_option('kerbcycle_ai_ollama_model');
        delete_option('kerbcycle_ai_render_endpoint');
        delete_option('kerbcycle_ai_render_api_key');
        delete_option('kerbcycle_ai_render_model');
        
    }

    public function test_generate_rejects_unsupported_provider(): void
    {
        update_option('kerbcycle_ai_provider', 'unsupported-provider', false);

        $service = new AiProviderService();
        $result = $service->generate('pickup_summary', ['count' => 3]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_unsupported', $result->get_error_code());
        $this->assertSame(500, $result->get_error_data()['status'] ?? null);
    }

    public function test_ollama_generate_returns_parsed_json_output(): void
    {
        update_option('kerbcycle_ai_provider', 'ollama', false);
        update_option('kerbcycle_ai_ollama_base_url', 'http://ollama.test', false);
        update_option('kerbcycle_ai_ollama_model', 'llama-test', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://ollama.test/api/generate') {
                    return $preempt;
                }

                $body = json_decode((string) ($parsed_args['body'] ?? ''), true);

                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'response' => wp_json_encode([
                            'summary' => 'Pickup summary generated.',
                            'risk_level' => 'low',
                            'prompt_included_action' => isset($body['prompt']) && strpos((string) $body['prompt'], 'pickup_summary') !== false,
                        ]),
                    ]),
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

        $service = new AiProviderService();
        $result = $service->generate('pickup_summary', ['count' => 3]);

        $this->assertIsArray($result);
        $this->assertSame('ollama', $result['provider']);
        $this->assertSame('llama-test', $result['model']);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertSame('Pickup summary generated.', $result['output']['summary']);
        $this->assertSame('low', $result['output']['risk_level']);
        $this->assertTrue($result['output']['prompt_included_action']);
    }

    public function test_ollama_generate_handles_http_error(): void
    {
        update_option('kerbcycle_ai_provider', 'ollama', false);
        update_option('kerbcycle_ai_ollama_base_url', 'http://ollama-http-error.test', false);
        update_option('kerbcycle_ai_ollama_model', 'llama-test', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://ollama-http-error.test/api/generate') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => 'Service unavailable',
                    'response' => [
                        'code' => 503,
                        'message' => 'Service Unavailable',
                    ],
                    'cookies' => [],
                    'filename' => null,
                ];
            },
            10,
            3
        );

        $service = new AiProviderService();
        $result = $service->generate('pickup_summary', ['count' => 3]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_http_error', $result->get_error_code());
        $this->assertSame(502, $result->get_error_data()['status'] ?? null);
    }

    public function test_ollama_generate_handles_invalid_provider_response_shape(): void
    {
        update_option('kerbcycle_ai_provider', 'ollama', false);
        update_option('kerbcycle_ai_ollama_base_url', 'http://ollama-invalid-shape.test', false);
        update_option('kerbcycle_ai_ollama_model', 'llama-test', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://ollama-invalid-shape.test/api/generate') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => wp_json_encode(['unexpected' => 'value']),
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

        $service = new AiProviderService();
        $result = $service->generate('pickup_summary', ['count' => 3]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_invalid_response', $result->get_error_code());
        $this->assertSame(502, $result->get_error_data()['status'] ?? null);
    }

    public function test_ollama_generate_handles_invalid_ai_json_output(): void
    {
        update_option('kerbcycle_ai_provider', 'ollama', false);
        update_option('kerbcycle_ai_ollama_base_url', 'http://ollama-invalid-output.test', false);
        update_option('kerbcycle_ai_ollama_model', 'llama-test', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'http://ollama-invalid-output.test/api/generate') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => wp_json_encode(['response' => 'not json']),
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

        $service = new AiProviderService();
        $result = $service->generate('pickup_summary', ['count' => 3]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_output_invalid_json', $result->get_error_code());
        $this->assertSame(422, $result->get_error_data()['status'] ?? null);
    }

    public function test_render_generate_rejects_missing_endpoint_or_api_key(): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', '', false);
        update_option('kerbcycle_ai_render_api_key', '', false);

        $service = new AiProviderService();
        $result = $service->generate('draft_template', ['audience' => 'customers']);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_misconfigured', $result->get_error_code());
        $this->assertSame(500, $result->get_error_data()['status'] ?? null);
    }

    public function test_render_generate_returns_array_output_from_result_key(): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', 'https://render.test/ask', false);
        update_option('kerbcycle_ai_render_api_key', 'test-key', false);
        update_option('kerbcycle_ai_render_model', 'render-model-test', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'https://render.test/ask') {
                    return $preempt;
                }

                $body = json_decode((string) ($parsed_args['body'] ?? ''), true);

                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'result' => [
                            'title' => 'Customer reminder',
                            'message' => 'Remember to place bags outside.',
                            'task_was_sent' => ($body['task'] ?? '') === 'draft_template',
                        ],
                    ]),
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

        $service = new AiProviderService();
        $result = $service->generate('draft_template', ['audience' => 'customers']);

        $this->assertIsArray($result);
        $this->assertSame('render', $result['provider']);
        $this->assertSame('render-model-test', $result['model']);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertSame('Customer reminder', $result['output']['title']);
        $this->assertSame('Remember to place bags outside.', $result['output']['message']);
        $this->assertTrue($result['output']['task_was_sent']);
    }

    public function test_render_generate_parses_string_output_key(): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', 'https://render-string.test/ask', false);
        update_option('kerbcycle_ai_render_api_key', 'test-key', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'https://render-string.test/ask') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'output' => wp_json_encode([
                            'title' => 'String output title',
                            'message' => 'String output body',
                        ]),
                    ]),
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

        $service = new AiProviderService();
        $result = $service->generate('draft_template', ['audience' => 'operators']);

        $this->assertIsArray($result);
        $this->assertSame('render', $result['provider']);
        $this->assertNull($result['model']);
        $this->assertSame('String output title', $result['output']['title']);
        $this->assertSame('String output body', $result['output']['message']);
    }

    public function test_render_generate_handles_invalid_json_body(): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', 'https://render-invalid-body.test/ask', false);
        update_option('kerbcycle_ai_render_api_key', 'test-key', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'https://render-invalid-body.test/ask') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => 'not json',
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

        $service = new AiProviderService();
        $result = $service->generate('draft_template', ['audience' => 'operators']);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_provider_invalid_response', $result->get_error_code());
        $this->assertSame(502, $result->get_error_data()['status'] ?? null);
    }

    public function test_render_generate_handles_non_array_output(): void
    {
        update_option('kerbcycle_ai_provider', 'render', false);
        update_option('kerbcycle_ai_render_endpoint', 'https://render-invalid-output.test/ask', false);
        update_option('kerbcycle_ai_render_api_key', 'test-key', false);

        add_filter(
            'pre_http_request',
            static function ($preempt, $parsed_args, $url) {
                if ($url !== 'https://render-invalid-output.test/ask') {
                    return $preempt;
                }

                return [
                    'headers' => [],
                    'body' => wp_json_encode(['output' => 'not json']),
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

        $service = new AiProviderService();
        $result = $service->generate('draft_template', ['audience' => 'operators']);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('kerbcycle_ai_output_invalid_json', $result->get_error_code());
        $this->assertSame(422, $result->get_error_data()['status'] ?? null);
    }
}
