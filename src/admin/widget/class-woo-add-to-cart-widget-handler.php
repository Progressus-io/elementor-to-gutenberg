<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Helper\WooCommerce_Style_Builder;
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
		$classes = $this->build_widget_wrapper_classes( $element, 'wc-add-to-cart' );
		WooCommerce_Style_Builder::register_add_to_cart_styles(
			$element,
			$classes['widget_class'],
			Admin_Settings::get_page_wrapper_class_name()
		);

		$block = $this->serialize_first_registered_block(
			array(
				'woocommerce/add-to-cart-form',
				'woocommerce/product-add-to-cart',
				'woocommerce/add-to-cart',
			),
			array(
				'className' => $classes['className'],
			),
			''
		);

		if ( '' !== $block ) {
			return $block;
		}

		return '';
	}
}
