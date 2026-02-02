<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\WooCommerce_Style_Builder;
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
		$classes = $this->build_widget_wrapper_classes( $element, 'wc-categories' );
		WooCommerce_Style_Builder::register_categories_styles(
			$element,
			$classes['widget_class'],
			Admin_Settings::get_page_wrapper_class_name()
		);

		$block = $this->serialize_first_registered_block(
			array(
				'woocommerce/product-categories',
				'woocommerce/product-categories-list',
				'woocommerce/product-category-list',
			),
			array(
				'className' => $classes['className'],
			),
			''
		);

		if ( '' !== $block ) {
			return $block;
		}

		$shortcode = $this->serialize_block( 'core/shortcode', array(), '[product_categories]' );
		if ( '' === $classes['className'] ) {
			return $shortcode;
		}

		return Block_Builder::build(
			'group',
			array( 'className' => $classes['className'] ),
			$shortcode
		);
	}
}
