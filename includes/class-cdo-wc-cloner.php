<?php
/**
 * OrderCloner Class File
 *
 * Handles the cloning and duplicating of WooCommerce orders.
 *
 * @package CDO_WC
 */

namespace CDO_WC;

use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Order_Item_Coupon;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OrderCloner
 *
 * Handles the cloning and duplicating of WooCommerce orders.
 */
class CDO_WC_Cloner {

	/**
	 * Constructor.
	 *
	 * Initializes the plugin by setting up actions and filters.
	 */
	public function __construct() {
		// Enqueue custom CSS and JS.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add the clone button to the orders index page.
		add_filter( 'woocommerce_admin_order_actions_end', array( $this, 'add_clone_order_action_button' ) );

		// Handle the clone order action.
		add_action( 'wp_ajax_clone_order', array( $this, 'handle_clone_order_action' ) );

		// Add Clone Order to woocommerce order action.
		add_action( 'woocommerce_order_action_clone_order', array( $this, 'handle_woocommerce_clone_order_action' ) );
		add_action( 'woocommerce_order_actions', array( $this, 'add_clone_to_order_actions_dropdown' ), 10, 1 ); // @phpstan-ignore return.void

		// Add meta box to the order edit and create pages.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box_to_order_screen' ) );

		// Add an action to display the admin notice.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Add site health check.
		add_filter( 'debug_information', array( $this, 'add_to_site_health' ) );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'cdo-wc-admin-css',
			esc_url( CDO_WC_ASSETS_URL . 'css/cdo-wc-main.css' ),
			array(),
			'1.0.0'
		);
	}

	/**
	 * Add meta box to single order screen.
	 */
	public function add_meta_box_to_order_screen() {
		$screen          = get_current_screen();
		$order_screen_id = CDO_WC_Checks::hpos_status() ? 'woocommerce_page_wc-orders' : 'shop_order';

		if ( CDO_WC_Checks::check_permission() && $screen && $screen->id === $order_screen_id ) {
				$order = wc_get_order( get_the_ID() );
			if ( $order->get_status() === 'auto-draft' ) {
				return;
			}

				add_meta_box(
					'order_meta_box',
					'Clone Order',
					array( $this, 'add_clone_button_to_meta_box' ),
					$order_screen_id,
					'side',
					'core'
				);
		}
	}

	/**
	 * Add the clone order button on the single order page inside metabox.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function add_clone_button_to_meta_box( $order ) {
		$order_id = CDO_WC_Checks::hpos_status() ? $order->get_id() : $order->ID;  // @phpstan-ignore property.notFound
		$order    = wc_get_order( $order_id );

		if ( $order ) {
			$nonce = wp_create_nonce( 'clone_order_nonce' );
			$url   = admin_url( 'admin-ajax.php?action=clone_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
			echo '<a href="' . esc_url( $url ) . '" class="button clone_single_order_button cdo-wc-order-clone-prevent-duplicate-js" title="' . esc_attr__( 'Clone Order', 'clone-duplicate-orders-for-woocommerce' ) . '">' . esc_html__( 'Clone Order', 'clone-duplicate-orders-for-woocommerce' ) . '</a>';
			$this->prevent_multiple_submissions_js();
			do_action( 'cdo_wc_add_custom_clone_button', $order_id );
		} else {
			echo '<p>' . esc_html__( 'Clone is not available for this order.', 'clone-duplicate-orders-for-woocommerce' ) . '</p>';
		}
	}

	/**
	 * Adds a 'Clone order' action to the WooCommerce order actions dropdown.
	 *
	 * @param array $actions The existing order actions.
	 * @return array Modified order actions.
	 */
	public function add_clone_to_order_actions_dropdown( $actions ) {

		$order = wc_get_order( get_the_ID() );
		if ( $order->get_status() === 'auto-draft' ) {
			return $actions;
		}

		if ( CDO_WC_Checks::check_permission() ) {
			if ( is_array( $actions ) ) {
				$actions['clone_order'] = esc_html__( 'Clone order', 'clone-duplicate-orders-for-woocommerce' );
			}
		}
		return $actions;
	}

	/**
	 * Add the clone order action button on the orders index page.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function add_clone_order_action_button( $order ) {
		$order_id = $order->get_id();
		$nonce    = wp_create_nonce( 'clone_order_nonce' );
		echo '<a class="button wc-action-button wc-action-button-clone tips cdo-wc-order-clone-prevent-duplicate-js" href="' . esc_url( admin_url( 'admin-ajax.php?action=clone_order&order_id=' . $order_id . '&_wpnonce=' . $nonce ) ) . '" data-tip="' . esc_attr__( 'Clone Order', 'clone-duplicate-orders-for-woocommerce' ) . '">' . esc_html__( 'Clone Order', 'clone-duplicate-orders-for-woocommerce' ) . '</a>';
		$this->prevent_multiple_submissions_js();
	}

	/**
	 * Handle the clone_order action.
	 */
	public function handle_clone_order_action() {
		if ( isset( $_GET['order_id'] ) && isset( $_GET['_wpnonce'] ) ) {
			$order_id = intval( wp_unslash( $_GET['order_id'] ) );
			$nonce    = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( wp_verify_nonce( $nonce, 'clone_order_nonce' ) ) {
				if ( get_transient( 'cdo_wc_cloning_order_' . $order_id ) ) {
					$redirect_url  = admin_url( 'edit.php?post_type=shop_order' );
					$delay_seconds = 5;
					echo '<div style="text-align:center;margin-top:20px;">';
					echo '<p>' . esc_html__( 'This order is already being cloned, and we have detected multiple clicks. You will be redirected to the orders page shortly.', 'clone-duplicate-orders-for-woocommerce' ) . '</p>';
					/* translators: %1$ is number of seconds. %2$ is the word here that links to orders index. */
					echo '<p>' . sprintf( esc_html__( 'If you are not redirected within %1$d seconds, click %2$s.', 'clone-duplicate-orders-for-woocommerce' ), esc_html( (string) $delay_seconds ), '<a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'here', 'clone-duplicate-orders-for-woocommerce' ) . '</a>' ) . '</p>';
					echo '</div>';

					add_action(
						'admin_footer',
						function () use ( $redirect_url, $delay_seconds ) {
							wp_register_script( 'cdo_wc_clonning_redirect', '', array(), '1.0.0', true );
							wp_enqueue_script( 'cdo_wc_clonning_redirect' );

							$inline_script = '
								setTimeout(function(){
									window.location.href = "' . esc_url( $redirect_url ) . '";
								}, ' . (int) ( $delay_seconds * 1000 ) . ');
							';
							wp_add_inline_script( 'cdo_wc_clonning_redirect', $inline_script );
						}
					);

					wp_die();
				}
				set_transient( 'cdo_wc_cloning_order_' . $order_id, true, 30 );

				$new_order_id  = $this->duplicate_order( $order_id );
				$new_order_url = admin_url( 'post.php?post=' . $new_order_id . '&action=edit' );
				$redirect_url  = wp_get_referer() ? wp_get_referer() : admin_url();

				$message = sprintf(
					wp_kses(
						/* translators: %s: New order URL, %d: New order ID */
						__( 'Order successfully duplicated. New order ID: <a href="%1$s">#%2$d</a>', 'clone-duplicate-orders-for-woocommerce' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( $new_order_url ),
					intval( $new_order_id )
				);

				set_transient( 'cdo_wc_clone_order_admin_notice', $message, 30 );
				delete_transient( 'cdo_wc_cloning_order_' . $order_id );
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		} else {
			wp_die( esc_html__( 'Nonce verification failed or order ID not set.', 'clone-duplicate-orders-for-woocommerce' ) );
		}
	}

	/**
	 * Handle the clone order action.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function handle_woocommerce_clone_order_action( $order ) {
		$order_id      = $order->get_id();
		$new_order_id  = $this->duplicate_order( $order_id );
		$new_order_url = admin_url( 'post.php?post=' . $new_order_id . '&action=edit' );
		$redirect_url  = wp_get_referer() ? wp_get_referer() : admin_url();

		$message = sprintf(
			wp_kses(
				/* translators: %s: New order URL, %d: New order ID */
				__( 'Order successfully duplicated. New order ID: <a href="%1$s">#%2$d</a>', 'clone-duplicate-orders-for-woocommerce' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $new_order_url ),
			intval( $new_order_id )
		);

		set_transient( 'cdo_wc_clone_order_admin_notice', $message, 30 );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Duplicate an order.
	 *
	 * @param int $original_order_id Original order ID.
	 * @return int|false New order ID on success, false on failure.
	 */
	public function duplicate_order( $original_order_id ) {
		$original_order = wc_get_order( $original_order_id );
		if ( ! $original_order ) {
			return false;
		}

		// Disable all WooCommerce order status emails.
		add_filter( 'woocommerce_email_enabled_new_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_cancelled_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_failed_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_refunded_order', '__return_false' );
		add_filter( 'woocommerce_email_enabled_customer_invoice', '__return_false' );

		$option_fields       = get_option( 'cdo_wc_acf_option_fields', array() );
		$option_field_values = array();

		if ( $option_fields ) {
			foreach ( $option_fields as $name => $label ) {
				$option_field_values[ $name ] = get_option( $name );
			}
		}

		// Temporarily disable stock management.
		add_filter( 'woocommerce_can_reduce_order_stock', '__return_false' );

		$order_data = array(
			'customer_id' => $original_order->get_user_id(),
			'status'      => 'pending',
			'currency'    => $original_order->get_currency(),
			'billing'     => $original_order->get_address( 'billing' ),
			'shipping'    => $original_order->get_address( 'shipping' ),
		);

		$order = wc_create_order( $order_data );

		foreach ( $original_order->get_meta_data() as $meta ) {
			// Check if this meta key should be cloned.
			if ( array_key_exists( $meta->key, $option_field_values ) && 'no' === $option_field_values[ $meta->key ] ) {
				$order->delete_meta_data( $meta->key );
				continue;
			}

			$order->update_meta_data( $meta->key, $meta->value );
		}

		foreach ( $original_order->get_items() as $item ) {
			if ( $item->get_type() === 'line_item' ) {
				$product = $item->get_product();
				if ( $product ) {
					$new_item = new WC_Order_Item_Product();
					$new_item->set_product_id( $item->get_product_id() );
					$new_item->set_variation_id( $item->get_variation_id() );
					$new_item->set_quantity( $item->get_quantity() );

					// Handle pricing option.
					$new_item->set_subtotal( (string) $item->get_subtotal() );
					$new_item->set_total( (string) $item->get_total() );

					// Copy item meta.
					foreach ( $item->get_meta_data() as $meta ) {
						$new_item->add_meta_data( $meta->key, $meta->value, true );
					}

					$order->add_item( $new_item );
				}
			} else {
				$order->add_item( clone $item );
			}
		}

		// Clone or set new shipping method based on the setting.
		foreach ( $original_order->get_items( 'shipping' ) as $shipping_item ) {
			$new_shipping_item = new WC_Order_Item_Shipping();
			$new_shipping_item->set_method_title( $shipping_item->get_method_title() );
			$new_shipping_item->set_method_id( $shipping_item->get_method_id() );
			$new_shipping_item->set_total( $shipping_item->get_total() );
			$new_shipping_item->set_taxes( $shipping_item->get_taxes() );

			foreach ( $shipping_item->get_meta_data() as $meta ) {
				$new_shipping_item->add_meta_data( $meta->key, $meta->value, true );
			}

			$order->add_item( $new_shipping_item );
		}

		// Coupon items.
		foreach ( $original_order->get_items( 'coupon' ) as $coupon_item ) {
			$new_coupon_item = new WC_Order_Item_Coupon();
			$new_coupon_item->set_code( $coupon_item->get_code() );
			$new_coupon_item->set_discount( $coupon_item->get_discount() );
			$order->add_item( $new_coupon_item );
		}

		$order->calculate_totals();
		$order->update_status( 'pending' );
		$order->set_address( $order_data['billing'], 'billing' );
		$order->set_address( $order_data['shipping'], 'shipping' );

		// Set the order's date created.
		$current_date = current_time( 'mysql' );
		$order->set_date_created( $current_date );
		$order->add_order_note( sprintf( 'This order was duplicated from order %d.', $original_order_id ) );
		$order->save();

		// Re-enable stock management and adjust stock levels manually.
		remove_filter( 'woocommerce_can_reduce_order_stock', '__return_false' );

		$this->adjust_stock_levels( $order );

		// Enable all WooCommerce order status emails.
		remove_filter( 'woocommerce_email_enabled_new_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_cancelled_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_failed_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_customer_refunded_order', '__return_false' );
		remove_filter( 'woocommerce_email_enabled_customer_invoice', '__return_false' );

		return $order->get_id();
	}

	/**
	 * Add sites options and information to Site Health
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function add_to_site_health( array $args ): array {
		$fields = array(
			'is_hpos_enabled' => array(
				'label' => esc_html__( 'HPOS Status', 'clone-duplicate-orders-for-woocommerce' ),
				'value' => CDO_WC_Checks::hpos_status() ? 'enabled' : 'disabled',
			),
		);

		$option_fields = get_option( 'cdo_wc_acf_option_fields', array() );

		if ( $option_fields ) {
			foreach ( $option_fields as $key => $value ) {
				$label          = ucwords( str_replace( '_', ' ', $key ) );
				$fields[ $key ] = array(
					'label' => esc_html( $label ),
					'value' => esc_html( get_option( $key ) ),
				);
			}
		}

		$args['cdo_wc_clone_orders'] = array(
			'label'  => esc_html__( 'Clone / Duplicate Orders for WooCommerce', 'clone-duplicate-orders-for-woocommerce' ), // phpcs:ignore WPOrgSubmissionRules.Naming.UniqueName.MissingPrefix
			'fields' => $fields,
		);

		return $args;
	}

	/**
	 * Prevent multiple form submissions. This code is inlined to make sure it always works.
	 *
	 * @return void
	 */
	private function prevent_multiple_submissions_js() {
		wp_register_script( 'cdo_wc_prevent_duplicate_submissions', '', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_script( 'cdo_wc_prevent_duplicate_submissions' );

		$inline_script = "
			jQuery(document).ready(function($) {
				$('.cdo-wc-order-clone-prevent-duplicate-js').on('click', function(e) {
					var \$this = $(this);
					\$this.css('opacity', '0.4').addClass('disabled');
					e.preventDefault();
					if (\$this.data('clicked')) {
						return false;
					}
					\$this.data('clicked', true);
					window.location.href = \$this.attr('href');
				});
			});
		";

		wp_add_inline_script( 'cdo_wc_prevent_duplicate_submissions', $inline_script );
	}

	/**
	 * Handle the order stock level.
	 *
	 * @param WC_Order $order The order object.
	 */
	private function adjust_stock_levels( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product  = $item->get_product();
			$quantity = $item->get_quantity();
			if ( $product && $product->managing_stock() && $product->get_stock_quantity() > 0 ) {
				wc_update_product_stock( $product, $quantity, 'decrease' );
			}
		}
	}

	/**
	 * Handle the admin notice after duplicating.
	 */
	public function admin_notices() {
		$notice = get_transient( 'cdo_wc_clone_order_admin_notice' );

		if ( $notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo wp_kses_post( $notice ); ?></p>
			</div>
			<?php
			delete_transient( 'cdo_wc_clone_order_admin_notice' );
		}
	}
}
