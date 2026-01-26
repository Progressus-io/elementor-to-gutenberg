<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Cart_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	public function handle( array $element ): string {
		$pattern_names = array(
			'woocommerce/cart',
			'woocommerce/cart-page',
			'woocommerce/cart-template',
		);

		foreach ( $pattern_names as $pattern_name ) {
			$content = $this->get_block_pattern_content( $pattern_name );
			if ( '' !== $content ) {
				return $content . "\n";
			}
		}

		return $this->serialize_block( 'core/shortcode', array(), '[woocommerce_cart]' );
	}


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
