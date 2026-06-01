<?php
/**
 * PHPStan bootstrap for KerbCycle plugin constants.
 *
 * These definitions mirror constants normally defined by the main plugin
 * bootstrap file during WordPress/plugin load.
 */

if ( ! defined( 'KERBCYCLE_QR_PATH' ) ) {
	define( 'KERBCYCLE_QR_PATH', __DIR__ . '/' );
}

if ( ! defined( 'KERBCYCLE_QR_URL' ) ) {
	define( 'KERBCYCLE_QR_URL', 'https://example.com/wp-content/plugins/kerbcycle-qr-code-manager/' );
}
