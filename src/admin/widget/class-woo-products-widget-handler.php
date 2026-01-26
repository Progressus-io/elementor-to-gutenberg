<?php

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function absint;
use function esc_attr;
use function sanitize_key;
use function sanitize_title;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

class Woo_Products_Widget_Handler implements Widget_Handler_Interface {
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		$columns = absint( $settings['columns'] ?? 0 );
		$rows    = absint( $settings['rows'] ?? 0 );
		$order   = $this->normalize_order( (string) ( $settings['order'] ?? '' ) );
		$orderby = $this->normalize_orderby( (string) ( $settings['orderby'] ?? '' ) );

		$category = $this->normalize_terms_csv( $settings['categories'] ?? null );
		$tag      = $this->normalize_terms_csv( $settings['tags'] ?? null );

		$attrs = array();

		if ( $columns > 0 ) {
			$attrs['columns'] = (string) $columns;
		}

		if ( $columns > 0 && $rows > 0 ) {
			$attrs['limit'] = (string) ( $columns * $rows );
		}

		if ( '' !== $order ) {
			$attrs['order'] = $order;
		}

		if ( '' !== $orderby ) {
			$attrs['orderby'] = $orderby;
		}

		if ( '' !== $category ) {
			$attrs['category'] = $category;
		}

		if ( '' !== $tag ) {
			$attrs['tag'] = $tag;
		}

		$shortcode = $this->build_shortcode( 'products', $attrs );

		return $this->serialize_block( 'core/shortcode', array(), $shortcode );
	}

	private function normalize_order( string $value ): string {
		$value = strtolower( trim( $value ) );

		return in_array( $value, array( 'asc', 'desc' ), true ) ? $value : '';
	}

	private function normalize_orderby( string $value ): string {
		$value   = sanitize_key( $value );
		$allowed = array( 'date', 'title', 'id', 'menu_order', 'rand', 'price', 'popularity', 'rating' );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	private function normalize_terms_csv( $value ): string {
		$terms = array();

		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					if ( isset( $item['slug'] ) ) {
						$item = $item['slug'];
					} elseif ( isset( $item['value'] ) ) {
						$item = $item['value'];
					}
				}

				$slug = sanitize_title( (string) $item );
				if ( '' !== $slug ) {
					$terms[] = $slug;
				}
			}
		}

		$terms = array_values( array_unique( $terms ) );

		return empty( $terms ) ? '' : implode( ',', $terms );
	}

	private function build_shortcode( string $tag, array $attrs ): string {
		$shortcode = '[' . sanitize_key( $tag );

		foreach ( $attrs as $key => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$shortcode .= sprintf( ' %s="%s"', sanitize_key( (string) $key ), esc_attr( (string) $value ) );
		}

		$shortcode .= ']';

		return $shortcode;
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
