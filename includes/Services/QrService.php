<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Data\Repositories\QrCodeRepository;
use Kerbcycle\QrCode\Services\EmailService;
use Kerbcycle\QrCode\Services\SmsService;

/**
 * The qr service.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Services
 */
class QrService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new QrCodeRepository();
    }

    public function add($qr_code)
    {
        // Prevent duplicates regardless of current status (available or assigned)
        if ($this->repository->find_by_qr_code($qr_code)) {
            return new \WP_Error('duplicate_qr_code', __('This QR code already exists.', 'kerbcycle'));
        }
        $inserted = $this->repository->insert_available($qr_code);
        if ($inserted === false) {
            return false;
        }
        return $this->repository->find_by_qr_code($qr_code);
    }

    public function assign($qr_code, $user_id, $send_email, $send_sms, $send_reminder)
    {
        $existing = $this->repository->find_by_qr_code($qr_code);
        if ($existing && isset($existing->status) && $existing->status === 'assigned') {
            if ((int) $existing->user_id === (int) $user_id) {
                return new \WP_Error(
                    'qr_code_already_assigned',
                    __('This QR code is already assigned to the selected customer.', 'kerbcycle')
                );
            }

            $message = __('This QR code is already assigned. Release it before assigning it to another customer.', 'kerbcycle');

            if (!empty($existing->display_name)) {
                /* translators: %s is the customer's display name. */
                $message = sprintf(
                    __('This QR code is already assigned to %s. Release it before assigning it to another customer.', 'kerbcycle'),
                    $existing->display_name
                );
            } elseif (!empty($existing->user_id)) {
                /* translators: %d is the customer's ID. */
                $message = sprintf(
                    __('This QR code is already assigned to customer #%d. Release it before assigning it to another customer.', 'kerbcycle'),
                    (int) $existing->user_id
                );
            }

            return new \WP_Error('qr_code_already_assigned', $message);
        }

        $user = get_userdata($user_id);
        $name = $user ? $user->display_name : '';

        if ($existing && isset($existing->status) && $existing->status === 'available') {
            $result = $this->repository->update_available_to_assigned($qr_code, $user_id, $name);
        } elseif ($this->repository->available_exists($qr_code)) {
            $result = $this->repository->update_available_to_assigned($qr_code, $user_id, $name);
        } else {
            $result = $this->repository->insert_assigned($qr_code, $user_id, $name);
        }

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to assign QR code in database.');
        }

        $sms_result   = null;
        $email_result = null;
        if ($send_email) {
            $email_result = (new EmailService())->send_notification($user_id, $qr_code, 'assigned');
        }
        if ($send_sms) {
            $sms_result = (new SmsService())->send_notification($user_id, $qr_code, 'assigned');
        }
        // Reminder logic would go here
        // if ($send_reminder) { ... }

        return [
            'sms_result'   => $sms_result,
            'email_result' => $email_result,
            'record'       => $this->repository->find_by_qr_code($qr_code),
        ];
    }

    public function release($qr_code, $send_email, $send_sms)
    {
        $row = $this->repository->find_by_qr_code($qr_code);
        if (!$row) {
            return new \WP_Error('not_found', 'QR code not found.');
        }

        $result = $this->repository->release_latest_assigned($qr_code);

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to release QR code in database.');
        }

        $sms_result   = null;
        $email_result = null;
        if ($row->user_id) {
            if ($send_email) {
                $email_result = (new EmailService())->send_notification($row->user_id, $qr_code, 'released');
            }
            if ($send_sms) {
                $sms_result = (new SmsService())->send_notification($row->user_id, $qr_code, 'released');
            }
        }

        return [
            'sms_result'   => $sms_result,
            'email_result' => $email_result,
        ];
    }

    public function bulk_release(array $codes)
    {
        return $this->repository->bulk_release($codes);
    }

    public function bulk_delete(array $codes)
    {
        return $this->repository->bulk_delete_available($codes);
    }

    public function update($old_code, $new_code)
    {
        if ($this->repository->find_by_qr_code($new_code)) {
            return new \WP_Error('duplicate_qr_code', __('This QR code already exists.', 'kerbcycle'));
        }
        return $this->repository->update_code($old_code, $new_code);
    }

    public function get_assigned_by_user($user_id)
    {
        return $this->repository->get_assigned_codes_by_user($user_id);
    }
}
