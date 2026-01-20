<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function sanitize_key;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

class Woo_Shortcode_Widget_Handler implements Widget_Handler_Interface {
	public function handle( array $element ): string {
		$widget_type = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';

		$map = array(
			'woocommerce-cart'       => '[woocommerce_cart]',
			'woocommerce_cart'       => '[woocommerce_cart]',
			'woocommerce-checkout'   => '[woocommerce_checkout]',
			'woocommerce-my-account' => '[woocommerce_my_account]',
		);

		$shortcode = $map[ $widget_type ] ?? '';
		if ( '' === $shortcode ) {
			return '';
		}

		return $this->serialize_block( 'core/shortcode', array(), $shortcode );
	}

	private function serialize_block( string $block_name, array $attrs, string $inner_html ): string {
		$parsed = array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);

		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block( $parsed ) . "\n";
		}

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return sprintf(
			"<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->\n",
			sanitize_key( $block_name ),
			$attr_json,
			$inner_html,
			sanitize_key( $block_name )
		);
	}
}
