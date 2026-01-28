<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

class Woo_Mini_Cart_Widget_Handler implements Widget_Handler_Interface {
	use Woo_Block_Serializer_Trait;

	public function handle( array $element ): string {
		return $this->serialize_block_or_shortcode( 'woocommerce/mini-cart', '[woocommerce_mini_cart]' );
	}
}
