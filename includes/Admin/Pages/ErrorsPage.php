<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;

/**
 * Admin page to display error and failure logs.
 */
class ErrorsPage
{
    protected $page_slug = 'kerbcycle-errors';
    private $repository;

    public function __construct()
    {
        $this->repository = new ErrorLogRepository();
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged    = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page = 20;

        $table_ok = $this->repository->table_is_valid();
        if (!$table_ok) {
            \Kerbcycle\QrCode\Install\Activator::activate();
        }

        $logs  = $table_ok ? $this->repository->get_logs($search, $paged, $per_page) : [];
        $total = $table_ok ? $this->repository->count_logs($search) : 0;
        $pages = max(1, (int) ceil($total / $per_page));
        $base_url = remove_query_arg(['paged'], admin_url('admin.php?page=' . $this->page_slug));
?>
        <div class="wrap">
            <h1><?php esc_html_e('Errors', 'kerbcycle'); ?></h1>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                <p class="search-box">
                    <label class="screen-reader-text" for="search-input"><?php esc_html_e('Search Errors', 'kerbcycle'); ?></label>
                    <input type="search" id="search-input" name="s" value="<?php echo esc_attr($search); ?>" />
                    <input type="submit" class="button" value="<?php esc_attr_e('Search'); ?>" />
                </p>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Type', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Message', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Page', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Date', 'kerbcycle'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No errors found.', 'kerbcycle'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->type); ?></td>
                        <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags($log->message), 20, '…')); ?></td>
                        <td><?php echo esc_html($log->page); ?></td>
                        <td><?php echo esc_html($log->status); ?></td>
                        <td><?php echo esc_html(get_date_from_gmt($log->created_at, 'Y-m-d H:i:s')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($pages > 1) : ?>
                <div class="tablenav"><div class="tablenav-pages">
                <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%', $base_url),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $pages,
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                    ]);
                ?>
                </div></div>
            <?php endif; ?>
        </div>
<?php
    }
}
