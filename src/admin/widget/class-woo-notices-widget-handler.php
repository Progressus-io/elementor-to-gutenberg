<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Notices_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	/**
	 * Render the WooCommerce notices widget using blocks or shortcode.
	 *
	 * @param array<string, mixed> $element Elementor widget data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$block = $this->serialize_first_registered_block(
			array(
				'woocommerce/store-notices',
				'woocommerce/store-notice',
				'woocommerce/notices',
			),
			array(),
			''
		);

		if ( '' !== $block ) {
			return $block;
		}

		return $this->serialize_block( 'core/shortcode', array(), '[woocommerce_notices]' );
	}
}
