<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Ajax\AdminAjax;
use WP_REST_Request;

final class SecurityBoundarySmokeTest extends TestCase
{
    public function test_low_privilege_user_cannot_call_pickup_exception_test_ajax_handler(): void
    {
        $subscriberId = $this->create_subscriber_user();

        $response = $this->call_admin_ajax(new AdminAjax(), 'test_pickup_exception', $subscriberId, [
            'action' => 'kerbcycle_test_pickup_exception',
            'qr_code' => 'SMOKE-PICKUP-001',
            'customer_id' => '1',
            'issue' => 'Missed pickup',
            'notes' => 'Smoke test',
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('Unauthorized', $response['data']['message'] ?? null);
    }

    public function test_qr_status_rest_route_denies_low_privilege_user(): void
    {
        $subscriberId = $this->create_subscriber_user();
        wp_set_current_user($subscriberId);

        $request = new WP_REST_Request('GET', '/kerbcycle/v1/qr-status/SMOKE-REST-001');
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_qr_status_rest_route_allows_administrator(): void
    {
        global $wpdb;

        $adminId = $this->create_admin_user();
        wp_set_current_user($adminId);

        $qrCode = 'SMOKE-REST-ADMIN-001';

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
        $this->assertSame($qrCode, $data['qr_code'] ?? null);
    }
}
