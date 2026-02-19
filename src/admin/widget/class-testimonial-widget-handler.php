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
	 * Handle conversion of Elementor testimonial widget to progressus/testimonial block.
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
		$image_id   = isset( $image_data['id'] ) ? (int) $image_data['id'] : 0;

		// Image dimensions.
		$img_size = $this->resolve_slider_size( $settings['image_size'] ?? null, 63 );

		// Image border-radius TRBL object (passed as-is to match block attribute shape).
		$border_radius_raw = isset( $settings['image_border_radius'] ) && is_array( $settings['image_border_radius'] )
			? $settings['image_border_radius']
			: array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' );

		// Image border-width TRBL object.
		$border_width_raw = isset( $settings['image_border_width'] ) && is_array( $settings['image_border_width'] )
			? $settings['image_border_width']
			: array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' );

		// Normalise TRBL objects to only the keys the block expects.
		$border_radius_attr = $this->normalise_trbl_attr( $border_radius_raw );
		$border_width_attr  = $this->normalise_trbl_attr( $border_width_raw );

		// Image border color (may come from __globals__ reference, stored as string or empty).
		$image_border_color = '';
		if ( isset( $settings['image_border_color'] ) && is_string( $settings['image_border_color'] ) ) {
			$image_border_color = $settings['image_border_color'];
		}

		// Custom id / classes.
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' );
		$custom_class   = implode( ' ', $custom_classes );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		// ── Block attributes (must match block.json attribute names) ───────────
		$block_attrs = array(
			'content'           => $content,
			'name'              => $name,
			'job'               => $job,
			'alignment'         => $alignment,
			'imageUrl'          => $image_url,
			'imageId'           => $image_id,
			'imageSize'         => $img_size,
			'imageBorderRadius' => $border_radius_attr,
			'imageBorderWidth'  => $border_width_attr,
			'imageBorderColor'  => $image_border_color,
			'customId'          => $custom_id,
			'customClass'       => $custom_class,
		);

		// Strip attributes that equal their defaults to keep markup lean.
		$block_attrs = $this->strip_default_attrs( $block_attrs );

		// ── Inner HTML — must mirror the JSX output of save.js exactly ─────────
		$border_radius_css = $this->resolve_trbl_css( $border_radius_raw );
		$border_width_css  = $this->resolve_trbl_css( $border_width_raw );

		$wrapper_classes = array_filter(
			array_merge(
				array( 'wp-block-progressus-testimonial', 'testimonial-widget', 'has-text-align-' . $alignment ),
				$custom_classes
			)
		);
		$wrapper_style = 'text-align:' . esc_attr( $alignment );

		$wrapper_open = '<div class="' . esc_attr( implode( ' ', array_unique( $wrapper_classes ) ) ) . '"';
		if ( '' !== $custom_id ) {
			$wrapper_open .= ' id="' . esc_attr( $custom_id ) . '"';
		}
		$wrapper_open .= ' style="' . $wrapper_style . '">';

		$inner = '';

		// 1. Quote content.
		if ( '' !== trim( $content ) ) {
			$safe = wp_kses_post( $content );
			if ( ! preg_match( '/<(p|div|blockquote|ul|ol|h[1-6])\b/i', $safe ) ) {
				$safe = '<p>' . $safe . '</p>';
			}
			$inner .= '<div class="testimonial-content">' . $safe . '</div>';
		}

		// 2. Author row.
		$has_author = '' !== $image_url || '' !== $name || '' !== $job;
		if ( $has_author ) {
			$author_inner = '';

			if ( '' !== $image_url ) {
				$img_styles = array(
					'width:' . $img_size . 'px',
					'height:' . $img_size . 'px',
					'object-fit:cover',
					'display:block',
					'flex-shrink:0',
				);

				if ( '' !== $border_radius_css && '0px' !== $border_radius_css ) {
					$img_styles[] = 'border-radius:' . $border_radius_css;
				}

				$has_border = '' !== $border_width_css && '0px' !== $border_width_css && '0' !== $border_width_css;
				if ( $has_border ) {
					$img_styles[] = 'border-width:' . $border_width_css;
					$img_styles[] = 'border-style:solid';
					if ( '' !== $image_border_color ) {
						$img_styles[] = 'border-color:' . esc_attr( $image_border_color );
					}
				}

				$author_inner .= sprintf(
					'<img src="%1$s" alt="%2$s" class="testimonial-image" style="%3$s"/>',
					esc_url( $image_url ),
					esc_attr( $name ),
					esc_attr( implode( ';', $img_styles ) )
				);
			}

			$has_meta = '' !== $name || '' !== $job;
			if ( $has_meta ) {
				$meta_inner = '';
				if ( '' !== $name ) {
					$meta_inner .= '<strong class="testimonial-name">' . esc_html( $name ) . '</strong>';
				}
				if ( '' !== $job ) {
					$meta_inner .= '<span class="testimonial-job">' . esc_html( $job ) . '</span>';
				}
				$author_inner .= '<div class="testimonial-meta" style="display:flex;flex-direction:column;justify-content:center;gap:2px;">'
					. $meta_inner
					. '</div>';
			}

			$inner .= '<div class="testimonial-author" style="display:flex;flex-direction:row;align-items:center;gap:12px;margin-top:16px;">'
				. $author_inner
				. '</div>';
		}

		if ( '' === $inner ) {
			return '';
		}

		$html = $wrapper_open . $inner . '</div>';

		return Block_Builder::build( 'progressus/testimonial', $block_attrs, $html );
	}

	/**
	 * Normalise a raw Elementor TRBL array to the shape expected by the block attribute
	 * (keys: top, right, bottom, left, unit).
	 *
	 * @param array $raw Raw Elementor dimension array.
	 *
	 * @return array Normalised TRBL array.
	 */
	private function normalise_trbl_attr( array $raw ): array {
		return array(
			'top'    => isset( $raw['top'] )    ? (string) $raw['top']    : '0',
			'right'  => isset( $raw['right'] )  ? (string) $raw['right']  : '0',
			'bottom' => isset( $raw['bottom'] ) ? (string) $raw['bottom'] : '0',
			'left'   => isset( $raw['left'] )   ? (string) $raw['left']   : '0',
			'unit'   => isset( $raw['unit'] ) && '' !== (string) $raw['unit'] ? (string) $raw['unit'] : 'px',
		);
	}

	/**
	 * Remove block attributes whose values equal the block defaults so the
	 * serialised comment is kept lean (Gutenberg omits defaults by convention).
	 *
	 * @param array $attrs Full attribute map.
	 *
	 * @return array Pruned attribute map.
	 */
	private function strip_default_attrs( array $attrs ): array {
		$defaults = array(
			'content'           => '',
			'name'              => '',
			'job'               => '',
			'alignment'         => 'left',
			'imageUrl'          => '',
			'imageId'           => 0,
			'imageSize'         => 63,
			'imageBorderColor'  => '',
			'customId'          => '',
			'customClass'       => '',
		);

		$trbl_zero = array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' );
		$defaults['imageBorderRadius'] = $trbl_zero;
		$defaults['imageBorderWidth']  = $trbl_zero;

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $attrs ) ) {
				continue;
			}
			if ( $attrs[ $key ] === $default ) {
				unset( $attrs[ $key ] );
			}
		}

		return $attrs;
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
