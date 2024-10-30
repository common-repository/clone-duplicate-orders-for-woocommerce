<?php
/**
 * Clone / Duplicate Orders for WooCommerce
 *
 * @package           clone-duplicate-orders-for-woocommerce
 * @author            YMMV LLC
 * @copyright         2024 YMMV LLC
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:             Clone / Duplicate Orders for WooCommerce
 * Description:             Clone, duplicate or copy WooCommerce orders in one-click. HPOS compatible.
 * Author:                  YMMV LLC
 * Contributors:            ymmvplugins
 * Author URI:              https://www.ymmv.co
 * Text Domain:             clone-duplicate-orders-for-woocommerce
 * Domain Path:             /languages
 * Version:                 1.0.0
 * Requires PHP:            7.4
 * Requires at least:       6.0
 * Tested up to:            6.6.2
 * WC requires at least:    8.2.0
 * WC tested up to:         9.3.3
 * Requires Plugins:        woocommerce
 * License:                 GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CDO_WC_PLUGIN_FILE', __FILE__ );
define( 'CDO_WC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CDO_WC_PLUGIN_URL', plugin_dir_url( CDO_WC_PLUGIN_FILE ) );
define( 'CDO_WC_ASSETS_URL', CDO_WC_PLUGIN_URL . 'assets/' );
define( 'CDO_WC_INCLUDES_PATH', CDO_WC_PLUGIN_PATH . 'includes/' );
define( 'CDO_WC_LANGUAGES_PATH', CDO_WC_PLUGIN_PATH . 'languages/' );
define( 'CDO_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CDO_WC_INCLUDES_PATH . '/class-cdo-wc-init.php';

register_activation_hook( __FILE__, array( 'CDO_WC\CDO_WC_Init', 'activate' ) );

new \CDO_WC\CDO_WC_Init();
