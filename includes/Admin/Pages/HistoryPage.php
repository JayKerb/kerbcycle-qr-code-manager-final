<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The history page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class HistoryPage {
    /**
     * Render the history page.
     *
     * @since    1.0.0
     */
    public function render() {
        global $wpdb;
        $table_name   = $wpdb->prefix . 'kerbcycle_qr_code_history';
        $per_page     = (int) get_option( 'kerbcycle_history_per_page', 20 );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history filter/pagination state; no server-side state is changed here.
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
        $offset       = ($current_page - 1) * $per_page;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history filter/pagination state; no server-side state is changed here.
        $status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history filter/pagination state; no server-side state is changed here.
        $start_date    = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history filter/pagination state; no server-side state is changed here.
        $end_date      = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history filter/pagination state; no server-side state is changed here.
        $search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        $where  = '1=1';
        $params = [];

        if ($status_filter) {
            $where   .= ' AND status = %s';
            $params[] = $status_filter;
        }

        if ($start_date) {
            $where   .= ' AND DATE(changed_at) >= %s';
            $params[] = $start_date;
        }

        if ($end_date) {
            $where   .= ' AND DATE(changed_at) <= %s';
            $params[] = $end_date;
        }

        if ($search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where   .= ' AND (CAST(id AS CHAR) LIKE %s OR qr_code LIKE %s OR CAST(user_id AS CHAR) LIKE %s OR CAST(changed_at AS CHAR) LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $count_sql   = "SELECT COUNT(*) FROM $table_name WHERE $where";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed fragments; dynamic values are prepared before execution.
        $total_items = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
        $total_pages  = (int) ceil($total_items / $per_page);

        $pagination_links = $total_pages > 1 ? paginate_links(
            [
            'base'      => add_query_arg( 'paged', '%#%' ),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current_page,
            ]
        ) : '';

        $select_sql = "SELECT * FROM $table_name WHERE $where ORDER BY changed_at DESC LIMIT %d OFFSET %d";
        $query_args = array_merge( $params, [ $per_page, $offset ] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed fragments; dynamic values are prepared before execution.
        $qr_codes   = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_args ) );
        ?>
        <div class="wrap">
            <h1>QR Code History</h1>
            <form method="get" class="qr-filters" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="kerbcycle-qr-history" />
                <select name="status_filter">
                    <option value=""><?php esc_html_e( 'All Statuses', 'kerbcycle-qr-code-manager' ); ?></option>
                    <option value="assigned" <?php selected( $status_filter, 'assigned' ); ?>><?php esc_html_e( 'Assigned', 'kerbcycle-qr-code-manager' ); ?></option>
                    <option value="released" <?php selected( $status_filter, 'released' ); ?>><?php esc_html_e( 'Released', 'kerbcycle-qr-code-manager' ); ?></option>
                    <option value="deleted" <?php selected( $status_filter, 'deleted' ); ?>><?php esc_html_e( 'Deleted', 'kerbcycle-qr-code-manager' ); ?></option>
                    <option value="added" <?php selected( $status_filter, 'added' ); ?>><?php esc_html_e( 'Added', 'kerbcycle-qr-code-manager' ); ?></option>
                </select>
                <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search', 'kerbcycle-qr-code-manager' ); ?>" />
                <button class="button"><?php esc_html_e( 'Filter', 'kerbcycle-qr-code-manager' ); ?></button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kerbcycle-qr-history' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'kerbcycle-qr-code-manager' ); ?></a>
            </form>
            <p class="description">Recent QR code activity</p>
            <?php if ($pagination_links) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post( $pagination_links ); ?>
                    </div>
                </div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>QR Code</th>
                        <th>User ID</th>
                        <th>Status</th>
                        <th>Changed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $qr_codes ) ) : ?>
                        <?php foreach ($qr_codes as $qr) : ?>
                            <tr>
                                <td><?php echo esc_html( $qr->id ); ?></td>
                                <td><?php echo esc_html( $qr->qr_code ); ?></td>
                                <td><?php echo $qr->user_id ? esc_html( $qr->user_id ) : '—'; ?></td>
                                <td><?php echo esc_html( ucfirst( $qr->status ) ); ?></td>
                                <td><?php echo $qr->changed_at ? esc_html( $qr->changed_at ) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5" class="description">No QR codes found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($pagination_links) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post( $pagination_links ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
