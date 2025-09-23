<?php

namespace Kerbcycle\QrCode\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;

/**
 * Helper for rendering admin notices and logging them to the Kerbcycle log table.
 */
class Notices
{
    /**
     * Render an admin notice and persist it to the error log repository.
     *
     * @param string $notice_type WordPress notice type (success, error, warning, info).
     * @param string $message     The HTML message to display inside the notice.
     * @param array  $args        Optional arguments.
     *
     * @return string Rendered HTML for the notice.
     */
    public static function add($notice_type, $message, array $args = [])
    {
        $defaults = [
            'dismissible'   => false,
            'log_type'      => '',
            'page'          => '',
            'status'        => null,
            'echo'          => true,
            'extra_classes' => [],
        ];

        $args = \wp_parse_args($args, $defaults);

        $notice_type = is_string($notice_type) ? strtolower($notice_type) : 'info';
        $classes = ['notice', 'notice-' . $notice_type];
        if (!empty($args['dismissible'])) {
            $classes[] = 'is-dismissible';
        }

        $extra_classes = $args['extra_classes'];
        if (!is_array($extra_classes)) {
            $extra_classes = $extra_classes ? [$extra_classes] : [];
        }
        foreach ($extra_classes as $extra_class) {
            if (is_string($extra_class) && $extra_class !== '') {
                $classes[] = \sanitize_html_class($extra_class);
            }
        }

        $sanitized_message = \wp_kses_post($message);
        $page = $args['page'];
        if ($page === '' && isset($_GET['page'])) {
            $page = \sanitize_text_field(\wp_unslash($_GET['page']));
        }

        $status = $args['status'];
        if ($status === null) {
            $status = ($notice_type === 'success') ? 'success' : 'failure';
        }

        $log_type = $args['log_type'] !== '' ? $args['log_type'] : $notice_type;

        ErrorLogRepository::log([
            'type'    => $log_type,
            'message' => $sanitized_message,
            'page'    => $page,
            'status'  => $status,
        ]);

        $html = \sprintf(
            '<div class="%1$s"><p>%2$s</p></div>',
            \esc_attr(\implode(' ', \array_filter($classes))),
            $sanitized_message
        );

        if (!empty($args['echo'])) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $html;
    }
}
