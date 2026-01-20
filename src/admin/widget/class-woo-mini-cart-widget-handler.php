<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function sanitize_key;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

class Woo_Mini_Cart_Widget_Handler implements Widget_Handler_Interface {
	public function handle( array $element ): string {
		if ( $this->is_block_registered( 'woocommerce/mini-cart' ) ) {
			return $this->serialize_block( 'woocommerce/mini-cart', array(), '' );
		}

		return $this->serialize_block( 'core/shortcode', array(), '[woocommerce_mini_cart]' );
	}

	private function is_block_registered( string $block_name ): bool {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return false;
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		return is_object( $block_type );
	}

	private function serialize_block( string $block_name, array $attrs, string $inner_html ): string {
		$parsed = array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => '' === $inner_html ? array() : array( $inner_html ),
		);

		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block( $parsed ) . "\n";
		}

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( '' === $inner_html ) {
			return sprintf( '<!-- wp:%s%s /-->%s', sanitize_key( $block_name ), $attr_json, "\n" );
		}

		return sprintf(
			"<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->\n",
			sanitize_key( $block_name ),
			$attr_json,
			$inner_html,
			sanitize_key( $block_name )
		);
	}
}
