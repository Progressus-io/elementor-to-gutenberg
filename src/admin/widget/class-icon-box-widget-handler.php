<?php
/**
 * Widget handler for Elementor icon box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon box widget.
 */
class Icon_Box_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor icon box to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings       = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( trim( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' ) );

		list( $icon_value, $icon_library ) = $this->resolve_icon_data( $settings );
		$size        = $this->sanitize_slider_value( $settings['size'] ?? null, 24 );
		$title       = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
		$description = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : '';
		$tooltip     = isset( $settings['premium_tooltip_text'] ) ? (string) $settings['premium_tooltip_text'] : '';
		$tooltip_pos = $this->sanitize_tooltip_position( $settings['premium_tooltip_position'] ?? '' );

		$icon_style_class = $this->resolve_icon_style_class( $icon_library );
		$icon_html        = '';

		if ( '' !== $icon_value ) {
			$icon_html = sprintf(
				'<i class="%1$s %2$s" style="font-size:%3$dpx;"></i>',
				esc_attr( $icon_style_class ),
				esc_attr( $icon_value ),
				$size
			);

			if ( '' !== $tooltip ) {
				$icon_html = sprintf(
					'<span class="tooltip-wrapper" data-tooltip="%1$s" data-tooltip-position="%2$s">%3$s</span>',
					esc_attr( $tooltip ),
					esc_attr( $tooltip_pos ),
					$icon_html
				);
			}
		}

		$segments = array();
		if ( '' !== $icon_html ) {
			$segments[] = '<div class="icon-box-icon">' . $icon_html . '</div>';
		}
		if ( '' !== trim( $title ) ) {
			$segments[] = '<h3 class="icon-box-title">' . esc_html( $title ) . '</h3>';
		}
		if ( '' !== trim( $description ) ) {
			$segments[] = '<div class="icon-box-description">' . wp_kses_post( $description ) . '</div>';
		}

		$wrapper_classes = array_merge( array( 'wp-block-icon-box' ), $custom_classes );
		$wrapper_attrs   = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		if ( '' !== $custom_id ) {
			$wrapper_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . implode( '', $segments ) . '</div>';

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'html', array(), $content );
	}

	/**
	 * Resolve the icon data from Elementor settings.
	 */
	private function resolve_icon_data( array $settings ): array {
		$icon_value   = '';
		$icon_library = 'fa-solid';

		if ( isset( $settings['selected_icon'] ) && is_array( $settings['selected_icon'] ) ) {
			$icon_value   = isset( $settings['selected_icon']['value'] ) ? (string) $settings['selected_icon']['value'] : '';
			$icon_library = isset( $settings['selected_icon']['library'] ) ? (string) $settings['selected_icon']['library'] : 'fa-solid';
		} elseif ( isset( $settings['icon'] ) ) {
			$icon_value = (string) $settings['icon'];
		}

		return array( $icon_value, $icon_library );
	}

	/**
	 * Determine Font Awesome style class based on Elementor library value.
	 */
	private function resolve_icon_style_class( string $library ): string {
		switch ( $library ) {
			case 'fa-regular':
				return 'far';
			case 'fa-brands':
				return 'fab';
			default:
				return 'fas';
		}
	}

	/**
	 * Sanitize tooltip position value.
	 */
	private function sanitize_tooltip_position( $value ): string {
		$positions = array( 'top', 'bottom', 'left', 'right' );
		if ( ! is_string( $value ) ) {
			return 'top';
		}

		$parts = explode( ',', $value );
		$first = trim( strtolower( $parts[0] ?? '' ) );

		return in_array( $first, $positions, true ) ? $first : 'top';
	}

	/**
	 * Sanitize custom class string into individual classes.
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
	 * Sanitize slider or numeric values from Elementor settings.
	 */
	private function sanitize_slider_value( $value, int $default ): int {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
				return (int) round( $value['size'] );
			}
			if ( isset( $value['value'] ) && is_numeric( $value['value'] ) ) {
				return (int) round( $value['value'] );
			}
		}
		if ( is_numeric( $value ) ) {
			return (int) round( $value );
		}

		return $default;
	}
}