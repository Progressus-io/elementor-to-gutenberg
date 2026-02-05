<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\WooCommerce_Style_Builder;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_My_Account_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the my account widget using WooCommerce blocks or shortcode.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$classes = $this->build_widget_wrapper_classes( $element, 'wc-my-account' );
		WooCommerce_Style_Builder::register_my_account_styles(
			$element,
			$classes['widget_class'],
			Admin_Settings::get_page_wrapper_class_name()
		);

		$shortcode = $this->serialize_block( 'core/shortcode', array(), '[woocommerce_my_account]' );
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
