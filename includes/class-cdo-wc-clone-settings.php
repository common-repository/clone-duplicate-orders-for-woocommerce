<?php
/**
 * Order_Clone_Settings Class File
 *
 * Handles the settings for the Order Cloner plugin.
 *
 * @package CDO_WC
 */

namespace CDO_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  Clone Settings
 *
 * Handles the settings for the Order Cloner plugin.
 */
class CDO_WC_Clone_Settings {
	/**
	 * Constructor.
	 *
	 * Initializes the settings by adding filters and actions.
	 */
	public function __construct() {
		if ( defined( 'CDO_WC_PLUGIN_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . CDO_WC_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Add Action Links
	 *
	 * @param array $links Links.
	 * @return array Links.
	 */
	public static function add_action_links( $links ) {
		$custom_links = array(
			'<a href="https://www.ymmv.co" target="_blank">Support</a>',
		);
		return array_merge( $custom_links, $links );
	}
}
