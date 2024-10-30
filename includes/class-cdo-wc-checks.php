<?php
/**
 * Checks
 *
 * @package CDO_WC
 */

namespace CDO_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks
 */
class CDO_WC_Checks {
	/**
	 * Check permission method.
	 *
	 * @return bool True if the user has the 'edit_shop_orders' capability or any additional capabilities, false otherwise.
	 */
	public static function check_permission() {
		// Default capability.
		$capabilities = array( 'edit_shop_orders' );

		// Allow developers to add more capabilities.
		$capabilities = apply_filters( 'cdo_wc_permission_capabilities', $capabilities );

		// Check if the user has any of the capabilities.
		foreach ( $capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check HPOS status
	 */
	public static function hpos_status() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			return ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		}
		return false;
	}
}
