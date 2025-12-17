<?php
/**
 * Widget handler for Elementor accordion widget.
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
 * Widget handler for Elementor accordion widget.
 */
class Accordion_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor accordion to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$accordions = is_array( $settings['accordions'] ?? null ) ? $settings['accordions'] : array();

		if ( empty( $accordions ) ) {
			return '';
		}

		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' );

		$title_color           = $this->resolve_color_value( $settings['title_color'] ?? '' );
		$title_bg_color        = $this->resolve_color_value( $settings['title_background_color'] ?? '' );
		$content_color         = $this->resolve_color_value( $settings['content_color'] ?? '' );
		$content_bg_color      = $this->resolve_color_value( $settings['content_background_color'] ?? '' );
		$border_radius         = $this->sanitize_dimension_value( $settings['border_radius'] ?? '', 'px' );
		$border_width          = $this->sanitize_dimension_value( $settings['border_width'] ?? '', 'px' );
		$border_color          = $this->resolve_color_value( $settings['border_color'] ?? '' );
		$spacing_between_items = $this->sanitize_dimension_value( $settings['items_gap'] ?? '16px', 'px' );

		$items_html = array();

		foreach ( $accordions as $item ) {
			$title   = isset( $item['title'] ) ? wp_kses_post( $item['title'] ) : '';
			$content = isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '';

			if ( '' === trim( $title ) || '' === trim( $content ) ) {
				continue;
			}

			$summary_styles = array( 'padding' => '0.5em 0' );
			if ( '' !== $title_color ) {
				$summary_styles['color'] = $title_color;
			}
			if ( '' !== $title_bg_color ) {
				$summary_styles['background-color'] = $title_bg_color;
			}

			$content_styles = array( 'padding' => '0.5em 1em' );
			if ( '' !== $content_color ) {
				$content_styles['color'] = $content_color;
			}
			if ( '' !== $content_bg_color ) {
				$content_styles['background-color'] = $content_bg_color;
			}

			$details_styles = array();
			if ( '' !== $border_radius ) {
				$details_styles['border-radius'] = $border_radius;
			}
			if ( '' !== $border_width && '' !== $border_color ) {
				$details_styles['border'] = $border_width . ' solid ' . $border_color;
			}
			if ( '' !== $spacing_between_items ) {
				$details_styles['margin-bottom'] = $spacing_between_items;
			}

			$class_attribute = '';
			if ( ! empty( $custom_classes ) ) {
				$class_attribute = ' class="' . esc_attr( implode( ' ', $custom_classes ) ) . '"';
			}

			$id_attribute = '';
			if ( '' !== $custom_id ) {
				$id_attribute = ' id="' . esc_attr( $custom_id ) . '"';
			}

			$items_html[] = sprintf(
				'<details%1$s%2$s style="%3$s"><summary style="%4$s">%5$s</summary><div style="%6$s">%7$s</div></details>',
				$class_attribute,
				$id_attribute,
				esc_attr( $this->build_style_string( $details_styles ) ),
				esc_attr( $this->build_style_string( $summary_styles ) ),
				$title,
				esc_attr( $this->build_style_string( $content_styles ) ),
				$content
			);
		}

		if ( empty( $items_html ) ) {
			return '';
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'html', array(), implode( '', $items_html ) );
	}

	/**
	 * Sanitize custom class strings.
	 */
	private function sanitize_custom_classes( string $class_string ): array {
		$classes = array();

		foreach ( preg_split( '/\s+/', $class_string ) as $class ) {
			$clean = Style_Parser::clean_class( $class );
			if ( '' === $clean ) {
				continue;
			}

			$classes[] = $clean;
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Resolve color value, preserving hex output where possible.
	 */
	private function resolve_color_value( $value ): string {
		$normalized = Style_Parser::normalize_color_value( $value );
		if ( '' !== $normalized ) {
			return $normalized;
		}

		return is_string( $value ) ? trim( strtolower( $value ) ) : '';
	}

	/**
	 * Sanitize dimension values allowing numeric input.
	 */
	private function sanitize_dimension_value( $value, string $unit ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
				$value = $value['size'];
			} elseif ( isset( $value['value'] ) && is_numeric( $value['value'] ) ) {
				$value = $value['value'];
			}
		}

		if ( is_numeric( $value ) ) {
			$value = $value . $unit;
		}

		$value = Style_Parser::sanitize_css_dimension_value( $value );

		return $value;
	}

	/**
	 * Build inline style declaration from an associative array.
	 */
	private function build_style_string( array $styles ): string {
		$rules = array();
		foreach ( $styles as $property => $value ) {
			$property = trim( (string) $property );
			$value    = trim( (string) $value );
			if ( '' === $property || '' === $value ) {
				continue;
			}
			$rules[] = $property . ':' . $value;
		}

		return implode( ';', $rules );
	}
}