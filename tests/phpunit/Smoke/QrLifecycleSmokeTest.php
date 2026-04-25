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
}
