<?php

namespace Kerbcycle\QrCode\Helpers;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Centralized capability names and compatibility checks.
 */
class Capabilities
{
	public const MANAGE_OPERATIONS = 'kerbcycle_manage_operations';
	public const MANAGE_SETTINGS = 'kerbcycle_manage_settings';
	public const VIEW_LOGS = 'kerbcycle_view_logs';

	public static function manage_operations()
	{
		return self::MANAGE_OPERATIONS;
	}

	public static function manage_settings()
	{
		return self::MANAGE_SETTINGS;
	}

	public static function view_logs()
	{
		return self::VIEW_LOGS;
	}

	/**
	 * Backward-compatible capability check.
	 *
	 * @param string $capability Capability to check.
	 *
	 * @return bool
	 */
	public static function can($capability)
	{
		return current_user_can($capability) || current_user_can('manage_options');
	}
}
