<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The history page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class HistoryPage
{
    /**
     * Render the history page.
     *
     * @since    1.0.0
     */
    public function render()
    {
        global $wpdb;
        $table_name   = $wpdb->prefix . 'kerbcycle_qr_codes';
        $per_page     = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset       = ($current_page - 1) * $per_page;

        $total_items  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages  = (int) ceil($total_items / $per_page);

        $qr_codes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY assigned_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        ?>
        <div class="wrap">
            <h1>QR Code History</h1>
            <p class="description">Recent QR code assignments</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>QR Code</th>
                        <th>User ID</th>
                        <th>Status</th>
                        <th>Assigned At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($qr_codes)) : ?>
                        <?php foreach ($qr_codes as $qr) : ?>
                            <tr>
                                <td><?= esc_html($qr->id) ?></td>
                                <td><?= esc_html($qr->qr_code) ?></td>
                                <td><?= $qr->user_id ? esc_html($qr->user_id) : '—' ?></td>
                                <td><?= esc_html(ucfirst($qr->status)) ?></td>
                                <td><?= $qr->assigned_at ? esc_html($qr->assigned_at) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5" class="description">No QR codes found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?= paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
