<?php

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function preg_match;
use function trim;

defined( 'ABSPATH' ) || exit;

class Shortcode_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render a shortcode widget into block markup.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$settings  = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$shortcode = isset( $settings['shortcode'] ) ? $this->sanitize_shortcode_text( (string) $settings['shortcode'] ) : '';

		if ( '' === $shortcode ) {
			return '';
		}

		if ( preg_match( '/^\[woocommerce_cart\b/i', $shortcode ) ) {
			return $this->serialize_by_patterns_or_shortcode(
				array( 'woocommerce/cart', 'woocommerce/cart-page', 'woocommerce/cart-template' ),
				$shortcode
			);
		}

		if ( preg_match( '/^\[woocommerce_checkout\b/i', $shortcode ) ) {
			return $this->serialize_by_patterns_or_shortcode(
				array( 'woocommerce/checkout', 'woocommerce/checkout-page', 'woocommerce/checkout-template' ),
				$shortcode
			);
		}

		if ( preg_match( '/^\[woocommerce_my_account\b/i', $shortcode ) ) {
			return $this->serialize_by_patterns_or_shortcode(
				array( 'woocommerce/my-account', 'woocommerce/my-account-page', 'woocommerce/my-account-template' ),
				'[woocommerce_my_account]'
			);
		}

		if ( preg_match( '/^\[woocommerce_mini_cart\b/i', $shortcode ) ) {
			return $this->serialize_by_patterns_or_shortcode(
				array( 'woocommerce/mini-cart', 'woocommerce/mini-cart-template' ),
				'[woocommerce_mini_cart]'
			);
		}

		return $this->serialize_block( 'core/shortcode', array(), $shortcode );
	}

	/**
	 * Keep the shortcode tokens of the widget text and escape everything else.
	 *
	 * The core/shortcode block prints its text as raw HTML, so markup around a
	 * token, or inside an unregistered one, would otherwise run on the front end.
	 *
	 * @param string $text The widget's shortcode text.
	 *
	 * @return string The sanitized shortcode text.
	 */
	private function sanitize_shortcode_text( string $text ): string {
		$parts = preg_split( '/(\[\[?\/?[A-Za-z0-9_-]+[^\[\]]*\]\]?)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$output = '';
		foreach ( $parts as $index => $part ) {
			// Odd indexes are the captured shortcode tokens.
			// Text between tokens can be the body of an enclosing shortcode, so its safe markup is kept.
			$output .= 1 === $index % 2 ? wp_strip_all_tags( $part ) : wp_kses_post( $part );
		}

		return trim( $output );
	}

	/**
	 * Serialize a registered pattern or fallback shortcode.
	 *
	 * @param array<int, string> $pattern_names Pattern slugs to try.
	 * @param string $fallback_shortcode Shortcode to use if no pattern found.
	 *
	 * @return string
	 */
	private function serialize_by_patterns_or_shortcode( array $pattern_names, string $fallback_shortcode ): string {
		foreach ( $pattern_names as $pattern_name ) {
			$content = $this->get_block_pattern_content( (string) $pattern_name );
			if ( '' !== $content ) {
				return $content . "\n";
			}
		}

		return $this->serialize_block( 'core/shortcode', array(), $fallback_shortcode );
	}

	/**
	 * Serialize a checkout block when available or fallback shortcode.
	 *
	 * @param string $fallback_shortcode Shortcode to use if block not available.
	 *
	 * @return string
	 */
	private function serialize_checkout_block_or_shortcode( string $fallback_shortcode ): string {
		if ( $this->is_block_registered( 'woocommerce/checkout' ) ) {
			return $this->serialize_block( 'woocommerce/checkout', array(), $this->get_checkout_template() );
		}

		return $this->serialize_block( 'core/shortcode', array(), $fallback_shortcode );
	}

	/**
	 * Serialize a cart block when available or fallback shortcode.
	 *
	 * @param string $fallback_shortcode Shortcode to use if block not available.
	 *
	 * @return string
	 */
	private function serialize_cart_block_or_shortcode( string $fallback_shortcode ): string {
		if ( $this->is_block_registered( 'woocommerce/cart' ) ) {
			return $this->serialize_block( 'woocommerce/cart', array(), $this->get_cart_template() );
		}

		return $this->serialize_block( 'core/shortcode', array(), $fallback_shortcode );
	}

	/**
	 * Get the default WooCommerce checkout block template markup.
	 *
	 * @return string
	 */
	private function get_checkout_template(): string {
		return "<!-- wp:woocommerce/checkout-totals-block -->\n" .
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

	/**
	 * Get the default WooCommerce cart block template markup.
	 *
	 * @return string
	 */
	private function get_cart_template(): string {
		return "<!-- wp:woocommerce/filled-cart-block -->\n" .
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
