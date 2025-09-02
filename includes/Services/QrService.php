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
        if ($this->repository->available_exists($qr_code)) {
            return new \WP_Error('duplicate_qr_code', 'This QR code is already available.');
        }
        return $this->repository->insert_available($qr_code);
    }

    public function assign($qr_code, $user_id, $send_email, $send_sms, $send_reminder)
    {
        $user      = get_userdata($user_id);
        $name      = $user ? $user->display_name : '';

        if ($this->repository->available_exists($qr_code)) {
            $result = $this->repository->update_available_to_assigned($qr_code, $user_id, $name);
        } else {
            $result = $this->repository->insert_assigned($qr_code, $user_id, $name);
        }

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to assign QR code in database.');
        }

        $sms_result = null;
        if ($send_email) {
            (new EmailService())->send_notification($user_id, $qr_code, 'assigned');
        }
        if ($send_sms) {
            $sms_result = (new SmsService())->send_notification($user_id, $qr_code, 'assigned');
        }
        // Reminder logic would go here
        // if ($send_reminder) { ... }

        return ['sms_result' => $sms_result];
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

        $sms_result = null;
        if ($row->user_id) {
            if ($send_email) {
                (new EmailService())->send_notification($row->user_id, $qr_code, 'released');
            }
            if ($send_sms) {
                $sms_result = (new SmsService())->send_notification($row->user_id, $qr_code, 'released');
            }
        }

        return ['sms_result' => $sms_result];
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
        return $this->repository->update_code($old_code, $new_code);
    }
}
