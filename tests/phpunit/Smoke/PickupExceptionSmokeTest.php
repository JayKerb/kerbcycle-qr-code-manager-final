<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Data\Repositories\PickupExceptionRepository;
use Kerbcycle\QrCode\Services\PickupExceptionRetryService;
use Kerbcycle\QrCode\Services\QrService;

final class PickupExceptionSmokeTest extends TestCase
{
    private function pickup_exception_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_pickup_exceptions';
    }

    private function create_pickup_exception(array $overrides = []): int
    {
        $now = current_time('mysql', true);

        $defaults = [
            'qr_code' => 'SMOKE-PICKUP-EXCEPTION-001',
            'customer_id' => 123,
            'issue' => 'Bag was not found',
            'notes' => 'Customer reported a missed pickup.',
            'submitted_at' => $now,
            'webhook_sent' => 0,
            'status' => 'failed',
            'source' => 'test',
            'retry_count' => 0,
            'last_retry_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return PickupExceptionRepository::create(array_merge($defaults, $overrides));
    }

    private function get_pickup_exception(int $id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->pickup_exception_table_name() . ' WHERE id = %d',
                $id
            )
        );
    }

    public function test_repository_create_inserts_pickup_exception_record(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-CREATE-001',
            'customer_id' => 456,
            'issue' => 'Damaged bag',
            'notes' => 'Lid was cracked.',
            'status' => 'pending',
        ]);

        $this->assertGreaterThan(0, $id);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame('SMOKE-PICKUP-CREATE-001', $row->qr_code);
        $this->assertSame(456, (int) $row->customer_id);
        $this->assertSame('Damaged bag', $row->issue);
        $this->assertSame('Lid was cracked.', $row->notes);
        $this->assertSame('pending', $row->status);
        $this->assertSame(0, (int) $row->webhook_sent);
    }

    public function test_repository_update_result_updates_retry_and_webhook_fields(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-UPDATE-001',
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $updatedAt = current_time('mysql', true);

        $updated = PickupExceptionRepository::update_result($id, [
            'webhook_sent' => 1,
            'webhook_status_code' => 200,
            'status' => 'sent',
            'webhook_response_body' => '{"ok":true}',
            'ai_severity' => 'low',
            'ai_category' => 'missed_pickup',
            'ai_summary' => 'Pickup exception handled.',
            'ai_recommended_action' => 'Notify customer.',
            'retry_count' => 1,
            'last_retry_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        $this->assertSame(1, $updated);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->webhook_sent);
        $this->assertSame(200, (int) $row->webhook_status_code);
        $this->assertSame('sent', $row->status);
        $this->assertSame('{"ok":true}', $row->webhook_response_body);
        $this->assertSame('low', $row->ai_severity);
        $this->assertSame('missed_pickup', $row->ai_category);
        $this->assertSame('Pickup exception handled.', $row->ai_summary);
        $this->assertSame('Notify customer.', $row->ai_recommended_action);
        $this->assertSame(1, (int) $row->retry_count);
        $this->assertNotEmpty($row->last_retry_at);
    }

    public function test_retry_rejects_invalid_and_missing_exception_ids(): void
    {
        $service = new PickupExceptionRetryService();

        $invalid = $service->retry(0);
        $this->assertSame('invalid_id', $invalid['state']);
        $this->assertSame(400, $invalid['status_code']);

        $missing = $service->retry(999999);
        $this->assertSame('not_found', $missing['state']);
        $this->assertSame(404, $missing['status_code']);
    }

    public function test_retry_rejects_exception_that_was_already_sent(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-INELIGIBLE-001',
            'webhook_sent' => 1,
            'status' => 'sent',
        ]);

        $service = new PickupExceptionRetryService();
        $result = $service->retry($id);

        $this->assertSame('ineligible', $result['state']);
        $this->assertSame(400, $result['status_code']);
    }

    public function test_retry_success_updates_record_with_ai_fields(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-RETRY-SUCCESS-001',
            'customer_id' => 789,
            'issue' => 'Missed bag',
            'notes' => 'Retry success path.',
            'webhook_sent' => 0,
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $qrService = new SuccessfulPickupExceptionQrService();
        $service = new PickupExceptionRetryService();

        $result = $service->retry($id, $qrService);

        $this->assertSame('success', $result['state']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame(202, $result['webhook_status_code']);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->webhook_sent);
        $this->assertSame(202, (int) $row->webhook_status_code);
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->retry_count);
        $this->assertSame('medium', $row->ai_severity);
        $this->assertSame('missed_pickup', $row->ai_category);
        $this->assertSame('Retry accepted.', $row->ai_summary);
        $this->assertSame('Follow up with customer.', $row->ai_recommended_action);
        $this->assertNotEmpty($row->last_retry_at);
    }

    public function test_retry_webhook_error_keeps_record_failed(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-RETRY-ERROR-001',
            'webhook_sent' => 0,
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $qrService = new ErrorPickupExceptionQrService();
        $service = new PickupExceptionRetryService();

        $result = $service->retry($id, $qrService);

        $this->assertSame('webhook_error', $result['state']);
        $this->assertSame(500, $result['status_code']);
        $this->assertSame('simulated_webhook_error', $result['error_code']);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->webhook_sent);
        $this->assertSame(0, (int) $row->webhook_status_code);
        $this->assertSame('failed', $row->status);
        $this->assertSame('Simulated webhook failure.', $row->webhook_response_body);
        $this->assertSame(1, (int) $row->retry_count);
    }

    public function test_retry_non_success_response_keeps_record_failed(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-RETRY-NON-SUCCESS-001',
            'webhook_sent' => 0,
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $qrService = new NonSuccessPickupExceptionQrService();
        $service = new PickupExceptionRetryService();

        $result = $service->retry($id, $qrService);

        $this->assertSame('webhook_non_success', $result['state']);
        $this->assertSame(500, $result['status_code']);
        $this->assertSame(502, $result['webhook_status_code']);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->webhook_sent);
        $this->assertSame(502, (int) $row->webhook_status_code);
        $this->assertSame('failed', $row->status);
        $this->assertSame('Bad gateway', $row->webhook_response_body);
        $this->assertSame(1, (int) $row->retry_count);
    }

    public function test_retry_lock_conflict_returns_conflict_without_mutating_record(): void
    {
        $id = $this->create_pickup_exception([
            'qr_code' => 'SMOKE-PICKUP-RETRY-LOCK-001',
            'webhook_sent' => 0,
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        add_option('kerbcycle_pickup_retry_lock_' . $id, (string) (time() + 120), '', 'no');

        $service = new PickupExceptionRetryService();
        $result = $service->retry($id, new SuccessfulPickupExceptionQrService());

        $this->assertSame('lock_conflict', $result['state']);
        $this->assertSame(409, $result['status_code']);

        $row = $this->get_pickup_exception($id);

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->retry_count);

        delete_option('kerbcycle_pickup_retry_lock_' . $id);
    }
}

final class SuccessfulPickupExceptionQrService extends QrService
{
    public function send_pickup_exception_to_n8n(array $data)
    {
        return [
            'success' => true,
            'status_code' => 202,
            'body' => wp_json_encode([
                'summary' => 'Retry accepted.',
                'category' => 'missed_pickup',
                'severity' => 'medium',
                'recommended_action' => 'Follow up with customer.',
            ]),
        ];
    }
}

final class ErrorPickupExceptionQrService extends QrService
{
    public function send_pickup_exception_to_n8n(array $data)
    {
        return new \WP_Error('simulated_webhook_error', 'Simulated webhook failure.');
    }
}

final class NonSuccessPickupExceptionQrService extends QrService
{
    public function send_pickup_exception_to_n8n(array $data)
    {
        return [
            'success' => false,
            'status_code' => 502,
            'body' => 'Bad gateway',
        ];
    }
}
