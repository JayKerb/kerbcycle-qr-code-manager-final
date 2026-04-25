<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Ajax\AdminAjax;

final class QrLifecycleSmokeTest extends TestCase
{
    public function test_assign_qr_code_via_real_admin_ajax_handler(): void
    {
        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Customer One']);
        $qrCode = 'SMOKE-ASSIGN-001';

        $this->insert_available_qr($qrCode);

        $response = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $customerId,
        ]);

        $this->assertTrue($response['success']);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($customerId, (int) $row->user_id);
        $this->assertNotEmpty($row->assigned_at);
    }

    public function test_release_qr_code_via_real_admin_ajax_handler(): void
    {
        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Customer Two']);
        $qrCode = 'SMOKE-RELEASE-001';

        $this->insert_available_qr($qrCode);

        $ajax = new AdminAjax();
        $assign = $this->call_admin_ajax($ajax, 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $customerId,
        ]);
        $this->assertTrue($assign['success']);

        $release = $this->call_admin_ajax($ajax, 'release_qr_code', $adminId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
        ]);

        $this->assertTrue($release['success']);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('available', $row->status);
        $this->assertNull($row->user_id);
        $this->assertNull($row->assigned_at);
        $this->assertNull($row->display_name);
    }

    public function test_assigning_already_assigned_qr_fails_without_overwriting_original_assignment(): void
    {
        $adminId = $this->create_admin_user();
        $firstCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Customer First']);
        $secondCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Customer Second']);
        $qrCode = 'SMOKE-CONFLICT-001';

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

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($firstCustomerId, (int) $row->user_id, 'Second assignment attempt must not overwrite original owner.');
    }

    public function test_release_qr_code_fails_with_conflicting_assigned_rows_and_does_not_mutate_state(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $firstCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Conflict Customer One']);
        $secondCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Conflict Customer Two']);
        $qrCode = 'SMOKE-RELEASE-CONFLICT-001';

        $wpdb->insert(
            $this->qr_table_name(),
            [
                'qr_code' => $qrCode,
                'user_id' => $firstCustomerId,
                'display_name' => 'Conflict Customer One',
                'status' => 'assigned',
                'assigned_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        $wpdb->insert(
            $this->qr_table_name(),
            [
                'qr_code' => $qrCode,
                'user_id' => $secondCustomerId,
                'display_name' => 'Conflict Customer Two',
                'status' => 'assigned',
                'assigned_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        $release = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $adminId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
        ]);

        $this->assertFalse($release['success']);
        $this->assertIsArray($release['data'] ?? null);
        $this->assertNotEmpty($release['data']['message'] ?? '');
        $this->assertStringContainsString('ambiguous', strtolower((string) ($release['data']['message'] ?? '')));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT user_id, status, assigned_at FROM ' . $this->qr_table_name() . ' WHERE qr_code = %s ORDER BY id ASC',
                $qrCode
            )
        );
        $this->assertCount(2, $rows);
        $this->assertSame('assigned', $rows[0]->status);
        $this->assertSame($firstCustomerId, (int) $rows[0]->user_id);
        $this->assertNotEmpty($rows[0]->assigned_at);
        $this->assertSame('assigned', $rows[1]->status);
        $this->assertSame($secondCustomerId, (int) $rows[1]->user_id);
        $this->assertNotEmpty($rows[1]->assigned_at);
    }

    public function test_assign_qr_code_rejects_invalid_customer_id_without_state_change(): void
    {
        $adminId = $this->create_admin_user();
        $qrCode = 'SMOKE-INVALID-CUSTOMER-001';

        $this->insert_available_qr($qrCode);

        $assign = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => '99999999',
        ]);

        $this->assertFalse($assign['success']);
        $this->assertIsArray($assign['data'] ?? null);
        $this->assertNotEmpty($assign['data']['message'] ?? '');
        $this->assertStringContainsString('invalid customer', strtolower((string) ($assign['data']['message'] ?? '')));

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('available', $row->status);
        $this->assertTrue(empty($row->user_id) || (int) $row->user_id === 0);
        $this->assertTrue(empty($row->assigned_at));
    }

    public function test_assign_qr_code_success_response_includes_admin_js_record_shape(): void
    {
        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Record Shape Customer']);
        $qrCode = 'SMOKE-RECORD-SHAPE-001';

        $this->insert_available_qr($qrCode);

        $assign = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $customerId,
        ]);

        $this->assertTrue($assign['success']);
        $this->assertIsArray($assign['data'] ?? null);
        $this->assertNotEmpty($assign['data']['message'] ?? '');
        $this->assertIsArray($assign['data']['record'] ?? null);

        $record = $assign['data']['record'];
        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('qr_code', $record);
        $this->assertArrayHasKey('user_id', $record);
        $this->assertArrayHasKey('display_name', $record);
        $this->assertArrayHasKey('status', $record);
        $this->assertArrayHasKey('assigned_at', $record);
        $this->assertSame($qrCode, $record['qr_code']);
        $this->assertSame($customerId, (int) $record['user_id']);
        $this->assertSame('assigned', $record['status']);
        $this->assertNotEmpty($record['assigned_at']);
    }
}
