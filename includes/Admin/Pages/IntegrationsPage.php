<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Kerbcycle\QrCode\Services\IntegrationsService;

/**
 * The integrations page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class IntegrationsPage {
    /**
     * Render the integrations page.
     *
     * @since    1.0.0
     */
    public function render() {
        $summaries = IntegrationsService::get_summaries();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Plugin Integrations', 'kerbcycle-qr-code-manager' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Plugin', 'kerbcycle-qr-code-manager' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'kerbcycle-qr-code-manager' ); ?></th>
                        <th><?php esc_html_e( 'Summary', 'kerbcycle-qr-code-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaries as $summary) : ?>
                        <tr>
                            <td><?php echo esc_html( $summary['name'] ); ?></td>
                            <td><?php echo $summary['active'] ? esc_html__( 'Active', 'kerbcycle-qr-code-manager' ) : esc_html__( 'Inactive', 'kerbcycle-qr-code-manager' ); ?></td>
                            <td><?php echo esc_html( $summary['summary'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
