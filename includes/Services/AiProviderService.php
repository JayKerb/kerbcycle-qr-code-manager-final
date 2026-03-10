<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;

/**
 * Lightweight LLM provider adapter for Option B AI endpoint.
 */
class AiProviderService
{
    /**
     * Generate a structured response for an AI action.
     *
     * @param string $action
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function generate($action, array $payload)
    {
        $provider = $this->get_provider();

        if ($provider === 'render') {
            return $this->call_render_endpoint($action, $payload);
        }

        if ($provider !== 'ollama') {
            return new \WP_Error('kerbcycle_ai_provider_unsupported', __('Unsupported AI provider configured.', 'kerbcycle'), ['status' => 500]);
        }

        return $this->call_ollama($action, $payload);
    }

    /**
     * @return string
     */
    private function get_provider()
    {
        $provider = defined('KERBCYCLE_AI_PROVIDER') ? KERBCYCLE_AI_PROVIDER : get_option('kerbcycle_ai_provider', 'ollama');
        $provider = is_string($provider) ? strtolower(trim($provider)) : 'ollama';

        return $provider !== '' ? $provider : 'ollama';
    }

    /**
     * @param string $action
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>|\WP_Error
     */
    private function call_ollama($action, array $payload)
    {
        $base_url = defined('KERBCYCLE_AI_OLLAMA_BASE_URL') ? KERBCYCLE_AI_OLLAMA_BASE_URL : get_option('kerbcycle_ai_ollama_base_url', 'http://127.0.0.1:11434');
        $model    = defined('KERBCYCLE_AI_OLLAMA_MODEL') ? KERBCYCLE_AI_OLLAMA_MODEL : get_option('kerbcycle_ai_ollama_model', 'llama3.1:8b');

        $base_url = is_string($base_url) ? rtrim(trim($base_url), '/') : 'http://127.0.0.1:11434';
        $model    = is_string($model) ? trim($model) : 'llama3.1:8b';

        if ($base_url === '' || $model === '') {
            return new \WP_Error('kerbcycle_ai_provider_misconfigured', __('AI provider configuration is incomplete.', 'kerbcycle'), ['status' => 500]);
        }

        $request_payload = [
            'model'   => $model,
            'stream'  => false,
            'format'  => 'json',
            'options' => [
                'temperature' => 0.2,
                'top_p'       => 0.9,
            ],
            'prompt'  => $this->build_prompt($action, $payload),
        ];

        $started = microtime(true);
        $response = wp_remote_post($base_url . '/api/generate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($request_payload),
            'timeout' => 20,
        ]);
        $elapsed_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            ErrorLogRepository::log([
                'type'    => 'ai_provider',
                'message' => sprintf('AI request failed (%s): %s', $action, $response->get_error_message()),
                'page'    => 'api-ai',
                'status'  => 'failure',
            ]);

            return new \WP_Error('kerbcycle_ai_provider_unreachable', __('AI provider is unreachable.', 'kerbcycle'), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            ErrorLogRepository::log([
                'type'    => 'ai_provider',
                'message' => sprintf('AI provider HTTP %d (%s).', $status_code, $action),
                'page'    => 'api-ai',
                'status'  => 'failure',
            ]);

            return new \WP_Error('kerbcycle_ai_provider_http_error', __('AI provider returned an unexpected response.', 'kerbcycle'), ['status' => 502]);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['response']) || !is_string($decoded['response'])) {
            return new \WP_Error('kerbcycle_ai_provider_invalid_response', __('AI provider response could not be parsed.', 'kerbcycle'), ['status' => 502]);
        }

        $parsed_output = json_decode(trim($decoded['response']), true);
        if (!is_array($parsed_output)) {
            ErrorLogRepository::log([
                'type'    => 'ai_provider',
                'message' => sprintf('AI returned non-JSON output (%s).', $action),
                'page'    => 'api-ai',
                'status'  => 'failure',
            ]);

            return new \WP_Error('kerbcycle_ai_output_invalid_json', __('AI output was not valid JSON.', 'kerbcycle'), ['status' => 422]);
        }

        return [
            'provider' => 'ollama',
            'model'    => $model,
            'latency_ms' => $elapsed_ms,
            'output'   => $parsed_output,
        ];
    }

    /**
     * @param string $action
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>|\WP_Error
     */
    private function call_render_endpoint($action, array $payload)
    {
        $endpoint = defined('KERBCYCLE_AI_RENDER_ENDPOINT') ? KERBCYCLE_AI_RENDER_ENDPOINT : get_option('kerbcycle_ai_render_endpoint', '');
        $api_key  = defined('KERBCYCLE_AI_RENDER_API_KEY') ? KERBCYCLE_AI_RENDER_API_KEY : get_option('kerbcycle_ai_render_api_key', '');

        $endpoint = is_string($endpoint) ? trim($endpoint) : '';
        $api_key  = is_string($api_key) ? trim($api_key) : '';

        if ($endpoint === '' || $api_key === '') {
            return new \WP_Error('kerbcycle_ai_provider_misconfigured', __('AI provider configuration is incomplete.', 'kerbcycle'), ['status' => 500]);
        }

        $started = microtime(true);
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $api_key,
            ],
            'body'    => wp_json_encode([
                'task' => $action,
                'data' => $payload,
            ]),
            'timeout' => 20,
        ]);
        $elapsed_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            ErrorLogRepository::log([
                'type'    => 'ai_provider',
                'message' => sprintf('AI request failed (%s): %s', $action, $response->get_error_message()),
                'page'    => 'api-ai',
                'status'  => 'failure',
            ]);

            return new \WP_Error('kerbcycle_ai_provider_unreachable', __('AI provider is unreachable.', 'kerbcycle'), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            ErrorLogRepository::log([
                'type'    => 'ai_provider',
                'message' => sprintf('AI provider HTTP %d (%s).', $status_code, $action),
                'page'    => 'api-ai',
                'status'  => 'failure',
            ]);

            return new \WP_Error('kerbcycle_ai_provider_http_error', __('AI provider returned an unexpected response.', 'kerbcycle'), ['status' => 502]);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['result']) || !is_array($decoded['result'])) {
            return new \WP_Error('kerbcycle_ai_provider_invalid_response', __('AI provider response could not be parsed.', 'kerbcycle'), ['status' => 502]);
        }

        return [
            'provider'   => 'render',
            'latency_ms' => $elapsed_ms,
            'output'     => $decoded['result'],
        ];
    }

    /**
     * @param string $action
     * @param array<string,mixed> $payload
     *
     * @return string
     */
    private function build_prompt($action, array $payload)
    {
        $schemas = [
            'pickup_summary' => [
                'summary' => 'string',
                'highlights' => ['string'],
                'risk_level' => 'low|medium|high',
            ],
            'qr_exceptions' => [
                'overview' => 'string',
                'priority_exceptions' => [['type' => 'string', 'count' => 'number', 'reason' => 'string']],
                'recommended_actions' => ['string'],
            ],
            'draft_template' => [
                'title' => 'string',
                'message' => 'string',
                'audience' => 'string',
            ],
        ];

        $schema = isset($schemas[$action]) ? $schemas[$action] : ['result' => 'string'];

        return "You are a municipal operations assistant.\n"
            . "Return ONLY valid JSON with no markdown, no prose outside JSON, and no trailing text.\n"
            . "Action: {$action}\n"
            . 'Required JSON schema: ' . wp_json_encode($schema) . "\n"
            . 'Input payload: ' . wp_json_encode($payload) . "\n";
    }
}
