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

    public function assign_code($qr_code, $user_id, $send_email = false, $send_sms = false)
    {
        $result = $this->repository->insert_assigned($qr_code, $user_id);
        if ($result !== false) {
            if ($send_email) {
                (new EmailService())->send_notification($user_id, $qr_code, 'assigned');
            }
            if ($send_sms) {
                (new SmsService())->send_notification($user_id, $qr_code, 'assigned');
            }
        }
        return $result;
    }

    public function release_code($qr_code, $send_email = false, $send_sms = false)
    {
        $row = $this->repository->get_latest_assigned($qr_code);
        if ($row) {
            $this->repository->release_by_id($row->id);
            if ($row->user_id) {
                if ($send_email) {
                    (new EmailService())->send_notification($row->user_id, $qr_code, 'released');
                }
                if ($send_sms) {
                    (new SmsService())->send_notification($row->user_id, $qr_code, 'released');
                }
            }
            return $row;
        }
        return null;
    }

    public function bulk_release_codes(array $codes)
    {
        return $this->repository->bulk_release($codes);
    }

    public function update_code($old_code, $new_code)
    {
        return $this->repository->update_code($old_code, $new_code);
    }
}
