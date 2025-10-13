<?php
/**
 * Widget handler for Elementor heading widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor heading widget.
 */
class Heading_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor heading to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$title    = isset( $settings['title'] ) ? (string) $settings['title'] : '';
		$level    = $this->resolve_heading_level( $settings['header_size'] ?? '' );

		if ( '' === trim( $title ) ) {
			return '';
		}

		$attributes   = array( 'level' => $level );
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$text_color   = isset( $settings['title_color'] ) ? strtolower( (string) $settings['title_color'] ) : '';

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$classes = array( 'wp-block-heading' );
		$style   = '';

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$attributes['textColor'] = $text_color;
				$classes[]               = 'has-text-color';
				$classes[]               = 'has-' . Style_Parser::clean_class( $text_color ) . '-color';
			} elseif ( $this->is_hex_color( $text_color ) ) {
				$attributes['style']['color']['text'] = $text_color;
				$classes[]                            = 'has-text-color';
				$style                                = 'color:' . $text_color . ';';
			}
		}

		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$classes[] = $clean;
			}
		}

		if ( ! empty( $classes ) ) {
			$attributes['className'] = implode( ' ', array_unique( $classes ) );
		}

		$class_attr = '';
		if ( ! empty( $classes ) ) {
			$class_attr = ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		}

		$id_attr    = '' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '';
		$style_attr = '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '';

		$heading_markup = sprintf(
			'<h%d%s%s%s>%s</h%d>',
			$level,
			$id_attr,
			$class_attr,
			$style_attr,
			wp_kses_post( $title ),
			$level
		);

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'heading', $attributes, $heading_markup );
	}

	/**
	 * Check if a given color value is a Gutenberg preset slug.
	 *
	 * @param string $color Color value.
	 *
	 * @return bool
	 */
	private function is_preset_color_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' );
	}

	/**
	 * Determine if a string is a hex color.
	 */
	private function is_hex_color( string $color ): bool {
		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
	}

	/**
	 * Resolve heading level from Elementor header size setting.
	 *
	 * @param mixed $header_size Elementor header size.
	 */
	private function resolve_heading_level( $header_size ): int {
		if ( is_string( $header_size ) && preg_match( '/h([1-6])/', strtolower( $header_size ), $matches ) ) {
			return (int) $matches[1];
		}

		if ( is_numeric( $header_size ) ) {
			return max( 1, min( 6, (int) $header_size ) );
		}

		return 2;
	}
}
