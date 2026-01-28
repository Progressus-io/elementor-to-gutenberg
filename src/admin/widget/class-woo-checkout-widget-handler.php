<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Checkout_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the checkout widget from patterns or a fallback shortcode.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$pattern_names = array(
			'woocommerce/checkout',
			'woocommerce/checkout-page',
			'woocommerce/checkout-template',
		);

		foreach ( $pattern_names as $pattern_name ) {
			$content = $this->get_block_pattern_content( $pattern_name );
			if ( '' !== $content ) {
				return $content . "\n";
			}
		}

		return $this->serialize_block( 'core/shortcode', array(), '[woocommerce_checkout]' );
	}

	/**
	 * Get the default WooCommerce checkout block template markup.
	 *
	 * @return string
	 */
	private function get_checkout_template(): string {
		// Same template content as in Elementor_Shortcode_Widget_Handler::get_checkout_template()
		return
			"<!-- wp:woocommerce/checkout-totals-block -->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-block -->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-cart-items-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-coupon-form-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-totals-block -->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-subtotal-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-fee-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-discount-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-shipping-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-summary-taxes-block /-->\n" .
			"<!-- /wp:woocommerce/checkout-order-summary-totals-block -->\n" .
			"<!-- /wp:woocommerce/checkout-order-summary-block -->\n" .
			"<!-- /wp:woocommerce/checkout-totals-block -->\n" .
			"<!-- wp:woocommerce/checkout-fields-block -->\n" .
			"<!-- wp:woocommerce/checkout-express-payment-block /-->\n" .
			"<!-- wp:woocommerce/checkout-contact-information-block /-->\n" .
			"<!-- wp:woocommerce/checkout-shipping-method-block /-->\n" .
			"<!-- wp:woocommerce/checkout-pickup-options-block /-->\n" .
			"<!-- wp:woocommerce/checkout-shipping-address-block /-->\n" .
			"<!-- wp:woocommerce/checkout-billing-address-block /-->\n" .
			"<!-- wp:woocommerce/checkout-shipping-methods-block /-->\n" .
			"<!-- wp:woocommerce/checkout-payment-block /-->\n" .
			"<!-- wp:woocommerce/checkout-additional-information-block /-->\n" .
			"<!-- wp:woocommerce/checkout-order-note-block /-->\n" .
			"<!-- wp:woocommerce/checkout-terms-block /-->\n" .
			"<!-- wp:woocommerce/checkout-actions-block /-->\n" .
			"<!-- /wp:woocommerce/checkout-fields-block -->\n";
	}
}
