<?php
/**
 * Widget handler for Elementor icon box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Icon_Parser;
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
		$alignment      = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment', 'text_align' ) );
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( trim( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' ) );

		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();

		$icon_data   = $this->resolve_icon_data( $settings );
		$icon_value  = trim( $icon_data['class_name'] );
		$size        = $this->sanitize_slider_value( $settings['size'] ?? null, 24 );
		$title       = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
		$description = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : '';

		// Normalize Elementor "start/end" to CSS text-align values.
		$alignment_value = is_string( $alignment ) ? trim( strtolower( $alignment ) ) : '';
		if ( 'start' === $alignment_value ) {
			$alignment_value = 'left';
		} elseif ( 'end' === $alignment_value ) {
			$alignment_value = 'right';
		}
		if ( '' === $alignment_value ) {
			$alignment_value = 'left';
		}

		$align_payload = Alignment_Helper::build_text_alignment_payload( $alignment_value );

		$icon_html = '';

		if ( 'svg' === $icon_data['type'] && '' !== $icon_data['url'] ) {
			$icon_html = sprintf(
				'<img src="%1$s" alt="" style="width:%2$dpx;height:auto;" class="svg-icon" />',
				esc_url( $icon_data['url'] ),
				$size
			);
		} elseif ( '' !== $icon_value ) {
			$icon_html = sprintf(
				'<i class="%1$s" style="font-size:%2$dpx;"></i>',
				esc_attr( $icon_value ),
				$size
			);
		} else {
			$icon_html = sprintf(
				'<i class="fas fa-star" style="font-size:%2$dpx;"></i>',
				esc_attr( $icon_value ),
				$size
			);
		}

		$segments   = array();
		$segments[] = '<div class="icon-box-icon">' . $icon_html . '</div>';

		// Determine title/description typographic defaults (fall back to sensible values).
		$title_size        = isset( $typography_attr['fontSize'] ) ? (int) $typography_attr['fontSize'] : 20;
		$title_color       = isset( $typography_attr['color'] ) ? $typography_attr['color'] : '#000000';
		$description_size  = isset( $typography_attr['descriptionSize'] ) ? (int) $typography_attr['descriptionSize'] : 14;
		$description_color = isset( $typography_attr['descriptionColor'] ) ? $typography_attr['descriptionColor'] : '#666666';

		if ( '' !== trim( $title ) ) {
			$segments[] = '<h3 class="icon-box-title" style="font-size:' . esc_attr( $title_size ) . 'px;color:' . esc_attr( $title_color ) . '">' . esc_html( $title ) . '</h3>';
		}
		if ( '' !== trim( $description ) ) {
			$segments[] = '<div class="icon-box-description" style="font-size:' . esc_attr( $description_size ) . 'px;color:' . esc_attr( $description_color ) . '">' . wp_kses_post( $description ) . '</div>';
		}

		$wrapper_classes = array_merge( array( 'wp-block-icon-box' ), $align_payload['classes'], $custom_classes );
		$wrapper_attrs   = array( 'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"' );
		if ( '' !== $custom_id ) {
			$wrapper_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		$alignment_value = '' !== $alignment ? $alignment : 'left';
		$wrapper_attrs[] = 'style="text-align:' . esc_attr( $alignment_value ) . '"';

		$content = '<div ' . implode( ' ', $wrapper_attrs ) . '>' . implode( '', $segments ) . '</div>';

		// Build block attributes for the new `gutenberg/icon-box` block.
		$block_attributes = array(
			'icon'             => isset( $icon_data['slug'] ) ? (string) $icon_data['slug'] : '',
			'iconStyle'        => isset( $icon_data['style_class'] ) ? (string) $icon_data['style_class'] : 'fas',
			'svgUrl'           => isset( $icon_data['url'] ) ? (string) $icon_data['url'] : '',
			'svgStyle'         => ( 'svg' === $icon_data['type'] && '' !== $icon_data['url'] )
				? ( 'width:' . $size . 'px;height:auto;' )
				: '',
			'size'             => $size,
			'title'            => $title,
			'description'      => $description,
			'titleSize'        => $title_size,
			'titleColor'       => $title_color,
			'descriptionSize'  => $description_size,
			'descriptionColor' => $description_color,
			'alignment'        => $alignment_value,

		);
		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'gutenberg/icon-box', $block_attributes, $content );
	}

	/**
	 * Resolve the icon data from Elementor settings.
	 */
	private function resolve_icon_data( array $settings ): array {
		$icon_setting = isset( $settings['selected_icon'] ) && is_array( $settings['selected_icon'] ) ? $settings['selected_icon'] : null;
		$icon_data    = Icon_Parser::parse_selected_icon( $icon_setting );

		if ( '' === $icon_data['class_name'] && '' === $icon_data['url'] && isset( $settings['icon'] ) ) {
			$icon_data = Icon_Parser::parse_selected_icon(
				array(
					'value'   => $settings['icon'],
					'library' => 'fa-solid',
				)
			);
		}

		return $icon_data;
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