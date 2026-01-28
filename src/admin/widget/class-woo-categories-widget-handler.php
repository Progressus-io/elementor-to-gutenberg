<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Categories_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the product categories widget using WooCommerce blocks or shortcode.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$block = $this->serialize_first_registered_block(
			array(
				'woocommerce/product-categories',
				'woocommerce/product-categories-list',
				'woocommerce/product-category-list',
			),
			array(),
			''
		);

		if ( '' !== $block ) {
			return $block;
		}

		return $this->serialize_block( 'core/shortcode', array(), '[product_categories]' );
	}
}
