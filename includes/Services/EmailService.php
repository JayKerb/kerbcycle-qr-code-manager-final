<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\MessagesService;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;

/**
 * The email service.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Services
 */
class EmailService
{
    /**
     * Send a notification email.
     *
     * @param int    $user_id The user ID.
     * @param string $qr_code The QR code.
     * @param string $type    The type of notification (e.g., 'assigned', 'released').
     *
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function send_notification($user_id, $qr_code, $type = 'assigned')
    {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return new \WP_Error('email_config', __('Missing user email', 'kerbcycle'));
        }

        $rendered = MessagesService::render($type, [
            'user' => $user->display_name ?: $user->user_login,
            'code' => $qr_code,
        ]);

        $subject = 'KerbCycle: ' . ucfirst($type);
        $sent    = wp_mail($user->user_email, $subject, $rendered['email']);

        if (!$sent) {
            return new \WP_Error('email_send', __('Failed to send email', 'kerbcycle'));
        }

        // Log the email
        MessageLogRepository::log_message([
            'type'     => 'email',
            'to'       => $user->user_email,
            'subject'  => $subject,
            'body'     => $rendered['email'],
            'status'   => $sent ? 'sent' : 'failed',
            'provider' => 'wp_mail',
            'response' => $sent ? 'OK' : 'wp_mail returned false',
        ]);

        return true;
    }
}
