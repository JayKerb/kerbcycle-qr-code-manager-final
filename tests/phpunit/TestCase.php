<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit;

use Kerbcycle\QrCode\Admin\Ajax\AdminAjax;
use Kerbcycle\QrCode\Install\Activator;

class AjaxDieException extends \RuntimeException
{
}

abstract class TestCase extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Activator::activate();
    }

    protected function qr_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'kerbcycle_qr_codes';
    }

    protected function create_admin_user(): int
    {
        return self::factory()->user->create(['role' => 'administrator']);
    }

    protected function create_subscriber_user(): int
    {
        return self::factory()->user->create(['role' => 'subscriber']);
    }

    protected function insert_available_qr(string $qrCode): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->qr_table_name(),
            [
                'qr_code' => $qrCode,
                'status' => 'available',
            ],
            ['%s', '%s']
        );
    }

    protected function get_qr_row(string $qrCode)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->qr_table_name() . ' WHERE qr_code = %s ORDER BY id DESC LIMIT 1',
                $qrCode
            )
        );
    }

    protected function call_admin_ajax(AdminAjax $ajax, string $method, int $userId, array $post = []): array
    {
        wp_set_current_user($userId);

        $_POST = array_merge(
            [
                'security' => wp_create_nonce('kerbcycle_qr_nonce'),
            ],
            $post
        );
        $_REQUEST = $_POST;

        add_filter('wp_die_ajax_handler', [$this, 'ajax_die_handler']);

        $json = '';
        ob_start();

        try {
            $ajax->{$method}();
        } catch (AjaxDieException $e) {
            // expected for wp_send_json_* in ajax handlers
        }

        $json = (string) ob_get_clean();

        remove_filter('wp_die_ajax_handler', [$this, 'ajax_die_handler']);

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded, 'Expected a JSON response from AJAX handler.');
        $this->assertArrayHasKey('success', $decoded, 'Expected standard WP AJAX response payload.');

        return $decoded;
    }

    public function ajax_die_handler(): callable
    {
        return static function () : void {
            throw new AjaxDieException('AJAX die captured');
        };
    }
}
