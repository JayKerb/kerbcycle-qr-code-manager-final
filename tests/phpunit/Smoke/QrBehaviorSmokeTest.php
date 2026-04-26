<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Ajax\AdminAjax;
use WP_REST_Request;

final class QrBehaviorSmokeTest extends TestCase
{
    public function test_duplicate_qr_assignment_is_rejected_without_overwriting_owner(): void
    {
        $adminId = $this->create_admin_user();
        $firstCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'First Owner']);
        $secondCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Second Owner']);
        $qrCode = 'SMOKE-BEHAVIOR-DUP-001';

        $this->insert_available_qr($qrCode);

        $ajax = new AdminAjax();

        $firstAssign = $this->call_admin_ajax($ajax, 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $firstCustomerId,
        ]);
        $this->assertTrue($firstAssign['success']);

        $secondAssign = $this->call_admin_ajax($ajax, 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $secondCustomerId,
        ]);

        $this->assertFalse($secondAssign['success']);
        $this->assertNotEmpty($secondAssign['data']['message'] ?? '');

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($firstCustomerId, (int) $row->user_id);
    }

    public function test_qr_status_rest_route_returns_not_found_for_unknown_qr_code(): void
    {
        $adminId = $this->create_admin_user();
        wp_set_current_user($adminId);

        $request = new WP_REST_Request('GET', '/kerbcycle/v1/qr-status/SMOKE-UNKNOWN-QR-001');
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(404, $response->get_status());
        $data = $response->get_data();
        $normalized = is_array($data) ? $data : (array) $data;
        $this->assertSame('not_found', $normalized['code'] ?? null);
    }

    public function test_qr_status_rest_route_response_contract_includes_qr_code_and_status(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        wp_set_current_user($adminId);

        $qrCode = 'SMOKE-BEHAVIOR-CONTRACT-001';
        $wpdb->insert(
            $this->qr_table_name(),
            [
                'qr_code' => $qrCode,
                'status' => 'available',
            ],
            ['%s', '%s']
        );

        $request = new WP_REST_Request('GET', '/kerbcycle/v1/qr-status/' . $qrCode);
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $normalized = is_array($data) ? $data : (array) $data;
        $this->assertSame($qrCode, $normalized['qr_code'] ?? null);
        $this->assertSame('available', $normalized['status'] ?? null);
        $this->assertArrayHasKey('id', $normalized);
    }
}
