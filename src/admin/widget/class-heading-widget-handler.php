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
use function sanitize_html_class;
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

		$attributes         = array( 'level' => $level );
		$inline_style_parts = array();
		$class_names        = array();
		$element_classes    = array( 'wp-block-heading' );
		$custom_id          = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css         = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_class       = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$text_color         = isset( $settings['title_color'] ) ? strtolower( (string) $settings['title_color'] ) : '';
		$text_transform     = isset( $settings['typography_text_transform'] ) ? trim( (string) $settings['typography_text_transform'] ) : '';

		if ( '' !== $text_transform ) {
			$class_names[]     = 'has-text-transform-' . sanitize_html_class( $text_transform );
			$element_classes[] = 'has-text-transform-' . sanitize_html_class( $text_transform );
		}

		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$class = trim( $class );
				if ( '' !== $class ) {
					$class_names[]     = sanitize_html_class( $class );
					$element_classes[] = sanitize_html_class( $class );
				}
			}
		}

		$typography = Style_Parser::parse_typography( $settings );
		$spacing    = Style_Parser::parse_spacing( $settings );
		$border     = Style_Parser::parse_border( $settings );

		if ( ! empty( $typography['attributes'] ) ) {
			$attributes['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$attributes['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$attributes['style']['border'] = $border['attributes'];
		}

		$inline_style_parts[] = $typography['style'];
		$inline_style_parts[] = $spacing['style'];
		$inline_style_parts[] = $border['style'];

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$attributes['textColor']                                  = $text_color;
				$attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
			} else {
				$attributes['style']['color']['text']                     = $text_color;
				$attributes['style']['elements']['link']['color']['text'] = $text_color;
				$inline_style_parts[]                                     = 'color:' . $text_color . ';';
			}
			$class_names[]     = 'has-text-color';
			$class_names[]     = 'has-link-color';
			$element_classes[] = 'has-text-color';
			$element_classes[] = 'has-link-color';
		}

		if ( ! empty( $attributes['style']['color']['text'] ) && ! in_array( 'has-text-color', $class_names, true ) ) {
			$class_names[]     = 'has-text-color';
			$element_classes[] = 'has-text-color';
		}

		if ( ! empty( $attributes['style']['elements']['link']['color']['text'] ) && ! in_array( 'has-link-color', $class_names, true ) ) {
			$class_names[]     = 'has-link-color';
			$element_classes[] = 'has-link-color';
		}

		if ( ! empty( $class_names ) ) {
			$attributes['className'] = implode( ' ', array_unique( $class_names ) );
		}

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$inline_style    = implode( '', array_filter( $inline_style_parts ) );
		$element_classes = array_unique( $element_classes );

		$heading_markup = sprintf(
			'<h%d%s%s%s>%s</h%d>',
			$level,
			'' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '',
			! empty( $element_classes ) ? ' class="' . esc_attr( implode( ' ', $element_classes ) ) . '"' : '',
			'' !== $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
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
