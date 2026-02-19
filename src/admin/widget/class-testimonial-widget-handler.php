<?php
/**
 * Widget handler for Elementor testimonial widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor testimonial widget.
 */
class Testimonial_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor testimonial widget.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

		$content   = isset( $settings['testimonial_content'] ) ? (string) $settings['testimonial_content'] : '';
		$name      = isset( $settings['testimonial_name'] ) ? trim( (string) $settings['testimonial_name'] ) : '';
		$job       = isset( $settings['testimonial_job'] ) ? trim( (string) $settings['testimonial_job'] ) : '';
		$alignment = isset( $settings['testimonial_alignment'] ) ? (string) $settings['testimonial_alignment'] : 'left';
		$alignment = in_array( $alignment, array( 'left', 'center', 'right' ), true ) ? $alignment : 'left';

		// Image data.
		$image_data = isset( $settings['testimonial_image'] ) && is_array( $settings['testimonial_image'] ) ? $settings['testimonial_image'] : array();
		$image_url  = isset( $image_data['url'] ) ? (string) $image_data['url'] : '';

		// Image dimensions.
		$img_size = $this->resolve_slider_size( $settings['image_size'] ?? null, 63 );

		// Image border-radius (e.g. 50px on all sides = circle).
		$border_radius_css = $this->resolve_trbl_css( $settings['image_border_radius'] ?? null );

		// Image border-width (border-color inherits from theme).
		$border_width_css = $this->resolve_trbl_css( $settings['image_border_width'] ?? null );

		// Custom id / classes.
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' );

		$segments = array();

		// ── 1. Content / quote ─────────────────────────────────────────────────
		if ( '' !== trim( $content ) ) {
			$safe = wp_kses_post( $content );
			if ( ! preg_match( '/<(p|div|blockquote|ul|ol|h[1-6])\b/i', $safe ) ) {
				$safe = '<p>' . $safe . '</p>';
			}
			$segments[] = '<div class="testimonial-content">' . $safe . '</div>';
		}

		// ── 2. Author row: image beside name/job ───────────────────────────────
		$author_parts = array();

		if ( '' !== $image_url ) {
			$img_styles = array(
				'width:' . $img_size . 'px',
				'height:' . $img_size . 'px',
				'object-fit:cover',
				'display:block',
				'flex-shrink:0',
			);

			if ( '' !== $border_radius_css ) {
				$img_styles[] = 'border-radius:' . $border_radius_css;
			}

			$has_border = '' !== $border_width_css && '0px' !== $border_width_css && '0' !== $border_width_css;
			if ( $has_border ) {
				$img_styles[] = 'border-width:' . $border_width_css;
				$img_styles[] = 'border-style:solid';
			}

			$author_parts[] = sprintf(
				'<img src="%1$s" alt="%2$s" class="testimonial-image" style="%3$s"/>',
				esc_url( $image_url ),
				esc_attr( $name ),
				esc_attr( implode( ';', $img_styles ) )
			);
		}

		$meta_parts = array();
		if ( '' !== $name ) {
			$meta_parts[] = '<strong class="testimonial-name">' . esc_html( $name ) . '</strong>';
		}
		if ( '' !== $job ) {
			$meta_parts[] = '<span class="testimonial-job">' . esc_html( $job ) . '</span>';
		}
		if ( ! empty( $meta_parts ) ) {
			$author_parts[] = '<div class="testimonial-meta" style="display:flex;flex-direction:column;justify-content:center;gap:2px;">'
				. implode( '', $meta_parts )
				. '</div>';
		}

		if ( ! empty( $author_parts ) ) {
			$segments[] = '<div class="testimonial-author" style="display:flex;flex-direction:row;align-items:center;gap:12px;margin-top:16px;">'
				. implode( '', $author_parts )
				. '</div>';
		}

		if ( empty( $segments ) ) {
			return '';
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$wrapper_classes = array_merge(
			array( 'testimonial-widget', 'has-text-align-' . $alignment ),
			$custom_classes
		);

		$wrapper_attrs  = 'class="' . esc_attr( implode( ' ', array_unique( $wrapper_classes ) ) ) . '"';
		$wrapper_attrs .= ' style="text-align:' . esc_attr( $alignment ) . '"';

		if ( '' !== $custom_id ) {
			$wrapper_attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		$html = '<div ' . $wrapper_attrs . '>' . "\n" . implode( "\n", $segments ) . "\n" . '</div>';

		return Block_Builder::build( 'html', array(), $html );
	}

	/**
	 * Resolve a numeric slider size from an Elementor size object or raw number.
	 *
	 * @param mixed $value   Elementor size value.
	 * @param int   $default Fallback size.
	 *
	 * @return int Resolved integer size.
	 */
	private function resolve_slider_size( $value, int $default ): int {
		if ( is_array( $value ) && isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
			return (int) round( (float) $value['size'] );
		}
		if ( is_numeric( $value ) ) {
			return (int) round( (float) $value );
		}
		return $default;
	}

	/**
	 * Resolve an Elementor TRBL dimension object to a CSS shorthand string.
	 *
	 * Returns empty string when all sides are absent or zero.
	 *
	 * @param mixed $value Elementor TRBL object (assoc array with top/right/bottom/left keys).
	 *
	 * @return string CSS shorthand value, e.g. "50px" or "4px 8px".
	 */
	private function resolve_trbl_css( $value ): string {
		if ( ! is_array( $value ) ) {
			return '';
		}

		$unit = isset( $value['unit'] ) && '' !== (string) $value['unit'] ? (string) $value['unit'] : 'px';

		$sides = array(
			isset( $value['top'] )    ? (string) $value['top']    : '',
			isset( $value['right'] )  ? (string) $value['right']  : '',
			isset( $value['bottom'] ) ? (string) $value['bottom'] : '',
			isset( $value['left'] )   ? (string) $value['left']   : '',
		);

		// If any side is missing, bail.
		foreach ( $sides as $side ) {
			if ( '' === $side ) {
				return '';
			}
		}

		$vals = array_map(
			static function ( string $v ) use ( $unit ): string {
				return $v . $unit;
			},
			$sides
		);

		// Simplify: all-equal → single value; top=bottom & right=left → two values.
		if ( 1 === count( array_unique( $vals ) ) ) {
			return $vals[0];
		}
		if ( $vals[0] === $vals[2] && $vals[1] === $vals[3] ) {
			return $vals[0] . ' ' . $vals[1];
		}

		return implode( ' ', $vals );
	}

	/**
	 * Sanitize custom class strings.
	 *
	 * @param string $class_string Space-separated class string.
	 *
	 * @return array Array of sanitized class names.
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
}
