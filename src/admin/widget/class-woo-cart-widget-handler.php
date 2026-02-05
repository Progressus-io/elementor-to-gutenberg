<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\WooCommerce_Style_Builder;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Cart_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the cart widget from patterns or a fallback shortcode.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$classes = $this->build_widget_wrapper_classes( $element, 'wc-cart' );

		WooCommerce_Style_Builder::register_cart_styles(
			$element,
			$classes['widget_class'],
			Admin_Settings::get_page_wrapper_class_name()
		);

		$shortcode = $this->serialize_block( 'core/shortcode', array(), '[woocommerce_cart]' );
		if ( '' === $classes['className'] ) {
			return $shortcode;
		}

		return Block_Builder::build(
			'group',
			array( 'className' => $classes['className'] ),
			$shortcode
		);
	}


	/**
	 * Get the default WooCommerce cart block template markup.
	 *
	 * @return string
	 */
	private function get_cart_template(): string {
		return
			"<!-- wp:woocommerce/filled-cart-block -->\n" .
			"<!-- wp:woocommerce/cart-items-block -->\n" .
			"<!-- wp:woocommerce/cart-line-items-block /-->\n" .
			"<!-- /wp:woocommerce/cart-items-block -->\n" .
			"<!-- wp:woocommerce/cart-totals-block -->\n" .
			"<!-- wp:woocommerce/cart-order-summary-block -->\n" .
			"<!-- wp:woocommerce/cart-order-summary-heading-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-coupon-form-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-totals-block -->\n" .
			"<!-- wp:woocommerce/cart-order-summary-subtotal-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-fee-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-discount-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-shipping-block /-->\n" .
			"<!-- wp:woocommerce/cart-order-summary-taxes-block /-->\n" .
			"<!-- /wp:woocommerce/cart-order-summary-totals-block -->\n" .
			"<!-- /wp:woocommerce/cart-order-summary-block -->\n" .
			"<!-- wp:woocommerce/cart-express-payment-block /-->\n" .
			"<!-- wp:woocommerce/proceed-to-checkout-block /-->\n" .
			"<!-- wp:woocommerce/cart-accepted-payment-methods-block /-->\n" .
			"<!-- /wp:woocommerce/cart-totals-block -->\n" .
			"<!-- /wp:woocommerce/filled-cart-block -->\n" .
			"<!-- wp:woocommerce/empty-cart-block -->\n" .
			"<!-- wp:woocommerce/cart-empty-block /-->\n" .
			"<!-- /wp:woocommerce/empty-cart-block -->\n";
	}
}
