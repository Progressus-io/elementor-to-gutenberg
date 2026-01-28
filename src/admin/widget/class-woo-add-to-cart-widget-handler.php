<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Add_To_Cart_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the add-to-cart widget using the best available WooCommerce block.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$block = $this->serialize_first_registered_block(
			array(
				'woocommerce/add-to-cart-form',
				'woocommerce/product-add-to-cart',
				'woocommerce/add-to-cart',
			),
			array(),
			''
		);

		if ( '' !== $block ) {
			return $block;
		}

		return '';
	}
}
