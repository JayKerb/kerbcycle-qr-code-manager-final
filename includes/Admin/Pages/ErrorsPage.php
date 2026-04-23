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
        $status   = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $page_f   = isset($_GET['log_page']) ? sanitize_text_field(wp_unslash($_GET['log_page'])) : '';
        $paged    = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page = 20;

        $table_ok = $this->repository->table_is_valid();
        if (!$table_ok) {
            \Kerbcycle\QrCode\Install\Activator::activate();
        }

        $available_pages = $table_ok ? $this->repository->get_pages() : [];
        $logs  = $table_ok ? $this->repository->get_logs($search, $status, $page_f, $paged, $per_page) : [];
        $total = $table_ok ? $this->repository->count_logs($search, $status, $page_f) : 0;
        $pages = max(1, (int) ceil($total / $per_page));
        $base_url = remove_query_arg(['paged'], admin_url('admin.php?page=' . $this->page_slug));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Errors', 'kerbcycle'); ?></h1>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                <p class="search-box" style="display:flex; gap:8px; align-items:center;">
                    <label class="screen-reader-text" for="search-input"><?php esc_html_e('Search Logs', 'kerbcycle'); ?></label>
                    <input type="search" id="search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search'); ?>" />
                    <select name="status">
                        <option value="" <?php selected($status, ''); ?>><?php esc_html_e('All Statuses', 'kerbcycle'); ?></option>
                        <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Success', 'kerbcycle'); ?></option>
                        <option value="failure" <?php selected($status, 'failure'); ?>><?php esc_html_e('Failure', 'kerbcycle'); ?></option>
                    </select>
                    <select name="log_page">
                        <option value="" <?php selected($page_f, ''); ?>><?php esc_html_e('All Pages', 'kerbcycle'); ?></option>
                        <?php foreach ($available_pages as $p) : ?>
                            <option value="<?php echo esc_attr($p); ?>" <?php selected($page_f, $p); ?>><?php echo esc_html($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" class="button" value="<?php esc_attr_e('Filter'); ?>" />
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug)); ?>" class="button"><?php esc_html_e('Reset', 'kerbcycle'); ?></a>
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
                    <?php $structured = $this->parse_structured_message($log->message); ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->type); ?></td>
                        <td>
                            <?php if (!empty($structured)) : ?>
                                <?php
                                $summary_parts = [];
                                if (!empty($structured['action'])) {
                                    $summary_parts[] = sprintf(__('Action: %s', 'kerbcycle'), $structured['action']);
                                }
                                if (!empty($structured['status'])) {
                                    $summary_parts[] = sprintf(__('Status: %s', 'kerbcycle'), $structured['status']);
                                }
                                if (!empty($structured['qr_code'])) {
                                    $summary_parts[] = sprintf(__('QR: %s', 'kerbcycle'), $structured['qr_code']);
                                }
                                if (!empty($structured['exception_id'])) {
                                    $summary_parts[] = sprintf(__('Exception: %s', 'kerbcycle'), $structured['exception_id']);
                                }
                                if (!empty($structured['actor_user_id'])) {
                                    $summary_parts[] = sprintf(__('Actor: #%s', 'kerbcycle'), $structured['actor_user_id']);
                                }
                                $summary = implode(' | ', $summary_parts);
                                ?>
                                <div><?php echo esc_html($summary); ?></div>
                                <?php if (!empty($structured['reason'])) : ?>
                                    <div><strong><?php esc_html_e('Reason:', 'kerbcycle'); ?></strong> <?php echo esc_html($structured['reason']); ?></div>
                                <?php endif; ?>
                                <details>
                                    <summary><?php esc_html_e('Raw payload', 'kerbcycle'); ?></summary>
                                    <code style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html($log->message); ?></code>
                                </details>
                            <?php else : ?>
                                <?php echo esc_html(wp_trim_words(wp_strip_all_tags($log->message), 20, '…')); ?>
                            <?php endif; ?>
                        </td>
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

    /**
     * Parse structured log payloads while preserving legacy raw message behavior.
     *
     * @param string $message
     * @return array<string, string>
     */
    private function parse_structured_message($message)
    {
        if (!is_string($message) || $message === '') {
            return [];
        }

        $decoded = json_decode($message, true);
        if (!is_array($decoded)) {
            return [];
        }

        $action = isset($decoded['action']) ? sanitize_text_field((string) $decoded['action']) : '';
        if ($action === '') {
            return [];
        }

        $context = (isset($decoded['context']) && is_array($decoded['context'])) ? $decoded['context'] : [];

        $reason = '';
        foreach (['reason', 'message'] as $key) {
            if (isset($decoded[$key]) && (string) $decoded[$key] !== '') {
                $reason = sanitize_text_field((string) $decoded[$key]);
                break;
            }
        }

        $qr_code = '';
        if (isset($decoded['qr_code']) && (string) $decoded['qr_code'] !== '') {
            $qr_code = sanitize_text_field((string) $decoded['qr_code']);
        } elseif (isset($context['qr_code']) && (string) $context['qr_code'] !== '') {
            $qr_code = sanitize_text_field((string) $context['qr_code']);
        }

        $exception_id = '';
        foreach (['exception_id', 'pickup_exception_id'] as $key) {
            if (isset($decoded[$key]) && (string) $decoded[$key] !== '') {
                $exception_id = sanitize_text_field((string) $decoded[$key]);
                break;
            }
            if (isset($context[$key]) && (string) $context[$key] !== '') {
                $exception_id = sanitize_text_field((string) $context[$key]);
                break;
            }
        }

        return [
            'action'        => $action,
            'status'        => isset($decoded['status']) ? sanitize_text_field((string) $decoded['status']) : '',
            'actor_user_id' => isset($decoded['actor_user_id']) ? sanitize_text_field((string) $decoded['actor_user_id']) : '',
            'qr_code'       => $qr_code,
            'exception_id'  => $exception_id,
            'reason'        => $reason,
        ];
    }
}
