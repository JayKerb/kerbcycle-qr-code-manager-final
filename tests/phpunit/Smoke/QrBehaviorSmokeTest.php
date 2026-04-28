<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Ajax\AdminAjax;
use WP_REST_Request;

final class QrBehaviorSmokeTest extends TestCase
{
    private function create_operator_user(): int
    {
        return self::factory()->user->create(['role' => 'kerbcycle_operator']);
    }

    private function insert_assigned_qr(string $qrCode, int $userId, string $displayName = 'Assigned User'): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->qr_table_name(),
            [
                'qr_code' => $qrCode,
                'user_id' => $userId,
                'display_name' => $displayName,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );
    }

    private function history_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'kerbcycle_qr_code_history';
    }

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

    public function test_operator_can_release_qr_via_ajax(): void
    {
        $operatorId = $this->create_operator_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Release Customer']);
        $qrCode = 'SMOKE-OP-RELEASE-001';

        $this->insert_assigned_qr($qrCode, $customerId, 'Release Customer');

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $operatorId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
        ]);

        $this->assertTrue($response['success']);
        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('available', $row->status);
        $this->assertTrue(empty($row->user_id) || (int) $row->user_id === 0);
    }

    public function test_operator_can_assign_qr_via_ajax(): void
    {
        $operatorId = $this->create_operator_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Assign Customer']);
        $qrCode = 'SMOKE-OP-ASSIGN-001';

        $this->insert_available_qr($qrCode);

        $response = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $operatorId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $customerId,
        ]);

        $this->assertTrue($response['success']);
        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($customerId, (int) $row->user_id);
    }

    public function test_subscriber_cannot_release_qr_and_state_is_unchanged(): void
    {
        $subscriberId = $this->create_subscriber_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Blocked Customer']);
        $qrCode = 'SMOKE-SUB-RELEASE-001';

        $this->insert_assigned_qr($qrCode, $customerId, 'Blocked Customer');

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $subscriberId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('Unauthorized', $response['data']['message'] ?? null);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($customerId, (int) $row->user_id);
    }

    public function test_invalid_nonce_blocks_operator_release_and_state_is_unchanged(): void
    {
        $operatorId = $this->create_operator_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Nonce Customer']);
        $qrCode = 'SMOKE-OP-NONCE-001';

        $this->insert_assigned_qr($qrCode, $customerId, 'Nonce Customer');

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $operatorId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
            'security' => 'invalid-nonce',
        ]);

        $this->assertFalse($response['success']);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($customerId, (int) $row->user_id);
    }

    public function test_bulk_release_is_restricted_for_operator_and_does_not_mutate_rows(): void
    {
        global $wpdb;

        $operatorId = $this->create_operator_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Bulk Customer']);
        $qrA = 'SMOKE-OP-BULK-001';
        $qrB = 'SMOKE-OP-BULK-002';

        $this->insert_assigned_qr($qrA, $customerId, 'Bulk Customer');
        $this->insert_assigned_qr($qrB, $customerId, 'Bulk Customer');

        $response = $this->call_admin_ajax(new AdminAjax(), 'bulk_release_qr_codes', $operatorId, [
            'action' => 'bulk_release_qr_codes',
            'qr_codes' => $qrA . ',' . $qrB,
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('Unauthorized', $response['data']['message'] ?? null);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT qr_code, status, user_id FROM ' . $this->qr_table_name() . ' WHERE qr_code IN (%s, %s) ORDER BY qr_code ASC',
                $qrA,
                $qrB
            )
        );
        $this->assertCount(2, $rows);
        $this->assertSame('assigned', $rows[0]->status);
        $this->assertSame('assigned', $rows[1]->status);
        $this->assertSame($customerId, (int) $rows[0]->user_id);
        $this->assertSame($customerId, (int) $rows[1]->user_id);
    }

    public function test_assignment_writes_history_row(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'History Assign']);
        $qrCode = 'SMOKE-HISTORY-ASSIGN-001';

        $this->insert_available_qr($qrCode);

        $response = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $customerId,
        ]);

        $this->assertTrue($response['success']);

        $historyRow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT qr_code, user_id, status FROM ' . $this->history_table_name() . ' WHERE qr_code = %s ORDER BY id DESC LIMIT 1',
                $qrCode
            )
        );

        $this->assertNotNull($historyRow);
        $this->assertSame($qrCode, $historyRow->qr_code);
        $this->assertSame($customerId, (int) $historyRow->user_id);
        $this->assertSame('assigned', $historyRow->status);
    }

    public function test_release_writes_history_row(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'History Release']);
        $qrCode = 'SMOKE-HISTORY-RELEASE-001';

        $this->insert_assigned_qr($qrCode, $customerId, 'History Release');

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $adminId, [
            'action' => 'release_qr_code',
            'qr_code' => $qrCode,
        ]);

        $this->assertTrue($response['success']);

        $historyRow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT qr_code, user_id, status FROM ' . $this->history_table_name() . ' WHERE qr_code = %s ORDER BY id DESC LIMIT 1',
                $qrCode
            )
        );

        $this->assertNotNull($historyRow);
        $this->assertSame($qrCode, $historyRow->qr_code);
        $this->assertSame($customerId, (int) $historyRow->user_id);
        $this->assertSame('released', $historyRow->status);
    }

    public function test_failed_duplicate_assignment_does_not_add_history_rows(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $firstCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'History First']);
        $secondCustomerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'History Second']);
        $qrCode = 'SMOKE-HISTORY-DUP-001';

        $this->insert_available_qr($qrCode);

        $firstAssign = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $firstCustomerId,
        ]);
        $this->assertTrue($firstAssign['success']);

        $historyCountBefore = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->history_table_name() . ' WHERE qr_code = %s',
                $qrCode
            )
        );

        $secondAssign = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => (string) $secondCustomerId,
        ]);

        $this->assertFalse($secondAssign['success']);

        $historyCountAfter = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->history_table_name() . ' WHERE qr_code = %s',
                $qrCode
            )
        );

        $this->assertSame($historyCountBefore, $historyCountAfter);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($firstCustomerId, (int) $row->user_id);
    }

    public function test_admin_bulk_release_success_only_mutates_selected_rows(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Bulk Success Customer']);

        $selectedA = 'SMOKE-BULK-SUCCESS-001';
        $selectedB = 'SMOKE-BULK-SUCCESS-002';
        $otherAssigned = 'SMOKE-BULK-SUCCESS-OTHER-ASSIGNED';
        $otherAvailable = 'SMOKE-BULK-SUCCESS-OTHER-AVAILABLE';

        $this->insert_assigned_qr($selectedA, $customerId, 'Bulk Success Customer');
        $this->insert_assigned_qr($selectedB, $customerId, 'Bulk Success Customer');
        $this->insert_assigned_qr($otherAssigned, $customerId, 'Bulk Success Customer');
        $this->insert_available_qr($otherAvailable);

        $response = $this->call_admin_ajax(new AdminAjax(), 'bulk_release_qr_codes', $adminId, [
            'action' => 'bulk_release_qr_codes',
            'qr_codes' => $selectedA . ',' . $selectedB,
        ]);

        $this->assertTrue($response['success']);

        $selectedARow = $this->get_qr_row($selectedA);
        $selectedBRow = $this->get_qr_row($selectedB);
        $otherAssignedRow = $this->get_qr_row($otherAssigned);
        $otherAvailableRow = $this->get_qr_row($otherAvailable);

        $this->assertNotNull($selectedARow);
        $this->assertNotNull($selectedBRow);
        $this->assertNotNull($otherAssignedRow);
        $this->assertNotNull($otherAvailableRow);

        $this->assertSame('available', $selectedARow->status);
        $this->assertSame('available', $selectedBRow->status);
        $this->assertTrue(empty($selectedARow->user_id) || (int) $selectedARow->user_id === 0);
        $this->assertTrue(empty($selectedBRow->user_id) || (int) $selectedBRow->user_id === 0);

        $this->assertSame('assigned', $otherAssignedRow->status);
        $this->assertSame($customerId, (int) $otherAssignedRow->user_id);
        $this->assertSame('available', $otherAvailableRow->status);
    }

    public function test_release_fails_with_missing_qr_code_and_state_unchanged(): void
    {
        $adminId = $this->create_admin_user();
        $customerId = self::factory()->user->create(['role' => 'subscriber', 'display_name' => 'Missing QR']);
        $existingQr = 'SMOKE-MALFORMED-MISSING-QR-001';

        $this->insert_assigned_qr($existingQr, $customerId, 'Missing QR');

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $adminId, [
            'action' => 'release_qr_code',
            'qr_code' => '',
        ]);

        $this->assertFalse($response['success']);

        $row = $this->get_qr_row($existingQr);
        $this->assertNotNull($row);
        $this->assertSame('assigned', $row->status);
        $this->assertSame($customerId, (int) $row->user_id);
    }

    public function test_release_fails_for_unknown_qr_code_without_creating_row(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        $unknownQr = 'SMOKE-MALFORMED-UNKNOWN-QR-001';

        $countBefore = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->qr_table_name() . ' WHERE qr_code = %s',
                $unknownQr
            )
        );

        $response = $this->call_admin_ajax(new AdminAjax(), 'release_qr_code', $adminId, [
            'action' => 'release_qr_code',
            'qr_code' => $unknownQr,
        ]);

        $this->assertFalse($response['success']);

        $countAfter = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->qr_table_name() . ' WHERE qr_code = %s',
                $unknownQr
            )
        );

        $this->assertSame($countBefore, $countAfter);
    }

    public function test_assignment_fails_with_missing_customer_id_and_qr_remains_available(): void
    {
        $adminId = $this->create_admin_user();
        $qrCode = 'SMOKE-MALFORMED-MISSING-CUSTOMER-001';

        $this->insert_available_qr($qrCode);

        $response = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => '',
        ]);

        $this->assertFalse($response['success']);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('available', $row->status);
        $this->assertTrue(empty($row->user_id) || (int) $row->user_id === 0);
    }

    public function test_assignment_fails_with_invalid_customer_id_and_qr_remains_available(): void
    {
        $adminId = $this->create_admin_user();
        $qrCode = 'SMOKE-MALFORMED-INVALID-CUSTOMER-001';

        $this->insert_available_qr($qrCode);

        $response = $this->call_admin_ajax(new AdminAjax(), 'assign_qr_code', $adminId, [
            'action' => 'assign_qr_code',
            'qr_code' => $qrCode,
            'customer_id' => '99999999',
        ]);

        $this->assertFalse($response['success']);

        $row = $this->get_qr_row($qrCode);
        $this->assertNotNull($row);
        $this->assertSame('available', $row->status);
        $this->assertTrue(empty($row->user_id) || (int) $row->user_id === 0);
    }
}
