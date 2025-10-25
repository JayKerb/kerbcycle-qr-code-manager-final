<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for QR code generator table.
 */
class QrRepoRepository
{
    /** @var string */
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_qr_repo';
    }

    /**
     * Check if a code already exists in the repository.
     */
    public function exists(string $code): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->table WHERE code = %s", $code));
    }

    /**
     * Insert a new code.
     */
    public function insert(string $code, ?int $user_id = null)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->table,
            [
                'code'       => $code,
                'status'     => 'available',
                'created_by' => $user_id,
            ],
            ['%s', '%s', '%d']
        );
    }

    /**
     * Get codes created within a date range (inclusive).
     *
     * @return array[]
     */
    public function list_between(string $from, string $to): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code, status, created_at FROM $this->table WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY created_at ASC",
                $from,
                $to
            ),
            ARRAY_A
        );
    }
}
