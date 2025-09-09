<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for QR code history.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Data\Repositories
 */
class QrCodeHistoryRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_qr_code_history';
    }

    public function log($qr_code, $user_id, $status)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->table,
            [
                'qr_code'   => $qr_code,
                'user_id'   => $user_id,
                'status'    => $status,
                'changed_at'=> current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s']
        );
    }

    public function recent($limit)
    {
        global $wpdb;
        $limit = absint($limit);
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $this->table ORDER BY changed_at DESC LIMIT %d", $limit)
        );
    }
}
