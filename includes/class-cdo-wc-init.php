<?php
/**
 * Init
 *
 * @package CDO_WC
 */

namespace CDO_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init.
 */
class CDO_WC_Init {

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'cdo_wc_load_textdomain' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		if ( ! self::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_inactive_notice' ) );
			return;
		}

		add_action( 'admin_notices', array( $this, 'welcome_notice' ) );
		$this->load_classes();
	}

	/**
	 * Load textdomain.
	 */
	public function cdo_wc_load_textdomain() {
		load_plugin_textdomain( 'clone-duplicate-orders-for-woocommerce', false, CDO_WC_LANGUAGES_PATH );
	}

	/**
	 * Declare HPOS compatibility.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CDO_WC_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Activation hook callback.
	 */
	public static function activate() {
		set_transient( 'cdo_wc_activated_plugin', true, 5 );
	}

	/**
	 * Load all necessary classes.
	 */
	public function load_classes() {
		require_once CDO_WC_INCLUDES_PATH . 'class-cdo-wc-checks.php';
		require_once CDO_WC_INCLUDES_PATH . 'class-cdo-wc-cloner.php';
		require_once CDO_WC_INCLUDES_PATH . 'class-cdo-wc-clone-settings.php';
		new \CDO_WC\CDO_WC_Cloner();
		new \CDO_WC\CDO_WC_Clone_Settings();
	}

	/**
	 * Admin notice for activation.
	 */
	public function welcome_notice() {
		if ( get_transient( 'cdo_wc_activated_plugin' ) ) {
			$message = sprintf(
				/* translators: %1$ %2$ wraps a link to the settings page */
				esc_html__( 'Thanks for activating Clone / Duplicate Orders for WooCommerce.', 'clone-duplicate-orders-for-woocommerce' ),
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
			delete_transient( 'cdo_wc_activated_plugin' );
		}
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		$plugin                 = 'woocommerce/woocommerce.php';
		$single_site_activation = is_plugin_active( $plugin );
		$network_activation     = is_multisite() && is_plugin_active_for_network( $plugin );

		return $single_site_activation || $network_activation;
	}

	/**
	 * Admin notice if WooCommerce is not active.
	 */
	public function woocommerce_inactive_notice() {
		if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
			// WooCommerce is installed but not activated.
			$woocommerce_activate_url = wp_nonce_url(
				admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ),
				'activate-plugin_woocommerce/woocommerce.php'
			);
			echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
				/* translators: %1$s is the opening <a> tag with a link to activate WooCommerce, and %2$s is the closing </a> tag. */
				esc_html__( 'Clone / Duplicate Orders for WooCommerce requires WooCommerce to be active. Please %1$s Activate WooCommerce %2$s.', 'clone-duplicate-orders-for-woocommerce' ),
				'<a href="' . esc_url( $woocommerce_activate_url ) . '">',
				'</a>'
			) . '</p></div>';
		} else {
			// WooCommerce is not installed.
			$woocommerce_install_url = wp_nonce_url(
				self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
				'install-plugin_woocommerce'
			);
			echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
				/* translators: %1$s is the opening <a> tag with a link to install WooCommerce, and %2$s is the closing </a> tag. */
				esc_html__( 'Clone / Duplicate Orders for WooCommerce requires WooCommerce to be installed and active. Please %1$s Install WooCommerce%2$s.', 'clone-duplicate-orders-for-woocommerce' ),
				'<a href="' . esc_url( $woocommerce_install_url ) . '">',
				'</a>'
			) . '</p></div>';
		}
	}
}
