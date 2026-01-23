<?php
/**
 * Widget handler for Elementor icon widget.
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
use function esc_url;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Elementor Icon widget into Gutenberg `gutenberg/icon` block.
 */
class Icon_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor icon to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 */
	public function handle( array $element ): string {
		$settings       = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' );

		$icon_data  = $this->resolve_icon_data( $settings );
		$icon_value = trim( $icon_data['class_name'] );
		$icon       = $icon_data['slug'];

		$size             = $this->sanitize_slider_value( $settings['size'] ?? null, 24 );
		$color            = $this->resolve_color_value( $settings['primary_color'] ?? '#333333', '#333333' );
		$background_color = $this->resolve_color_value( $settings['background_color'] ?? '', '' );
		$border_radius    = $this->sanitize_slider_value( $settings['border_radius'] ?? null, 0 );
		$padding          = $this->sanitize_slider_value( $settings['padding'] ?? null, 0 );
		$hover_color      = $this->resolve_color_value( $settings['hover_color'] ?? '', '' );
		$hover_effect     = isset( $settings['hover_effect'] ) ? (string) $settings['hover_effect'] : 'scale-up';
		$link_settings    = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
		$link             = isset( $link_settings['url'] ) ? (string) $link_settings['url'] : '';
		$link_target = ! empty( $link_settings['is_external'] ) ? '_blank' : '';

		$icon_style_class = $icon_data['style_class'];
		$attributes       = array(
			'icon'            => $icon,
			'iconStyle'       => $icon_style_class,
			'size'            => $size,
			'color'           => $color,
			'backgroundColor' => $background_color,
			'borderRadius'    => $border_radius,
			'padding'         => $padding,
			'hoverColor'      => $hover_color,
			'hoverEffect'     => $hover_effect,
			'link'            => $link,
			'linkTarget'      => $link_target,
		);

		if ( ! empty( $custom_classes ) ) {
			$attributes['className'] = implode( ' ', $custom_classes );
		}

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$attributes = array_filter(
			$attributes,
			static function ( $value ) {
				return ! ( null === $value || '' === $value );
			}
		);

		$icon_styles = array(
			'font-size'                      => $size . 'px',
			'color'                          => $color,
			'background-color'               => '' !== $background_color ? $background_color : 'transparent',
			'border-radius'                  => $border_radius . 'px',
			'padding'                        => $padding . 'px',
			'display'                        => 'inline-block',
			'line-height'                    => '1',
			'transition'                     => 'all 0.3s ease',
			'width'                          => 'auto',
			'height'                         => 'auto',
			'--fontawesome-icon-hover-color' => $hover_color,
		);

		if ( '' === $hover_color ) {
			unset( $icon_styles['--fontawesome-icon-hover-color'] );
		}

		if ( 'svg' === $icon_data['type'] && '' !== $icon_data['url'] ) {
			$svg_styles = array(
				'width'            => $size . 'px',
				'height'           => 'auto',
				'display'          => 'inline-block',
				'background-color' => '' !== $background_color ? $background_color : '',
				'padding'          => $padding > 0 ? $padding . 'px' : '',
			);

			$icon_html = sprintf(
				'<img src="%1$s" alt="" style="%2$s" class="svg-icon" />',
				esc_url( $icon_data['url'] ),
				esc_attr( $this->build_style_string( $svg_styles ) )
			);
		} elseif ( '' !== $icon_value ) {
			$icon_html = sprintf(
				'<i class="%1$s fontawesome-icon-hover-%2$s" style="%3$s" aria-label="" aria-hidden="true" data-hover-effect="%2$s" data-icon="%4$s" data-icon-style="%5$s"></i>',
				esc_attr( $icon_value ),
				esc_attr( $hover_effect ),
				esc_attr( $this->build_style_string( $icon_styles ) ),
				esc_attr( $icon ),
				esc_attr( $icon_style_class )
			);
		} else {
			$icon_html = '';
		}

		if ( '' !== $link ) {
			$rel_attr = $link_target ? ' rel="noopener noreferrer"' : '';
			$target   = $link_target ? ' target="_blank"' : '';

			$icon_html = sprintf(
				'<a href="%1$s"%2$s%3$s aria-label="">%4$s</a>',
				esc_url( $link ),
				$target,
				$rel_attr,
				$icon_html
			);
		}

		$alignment       = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment', 'icon_align' ) );
		$alignment_value = '' === $alignment ? 'left' : $alignment;
		$align_class     = Style_Parser::clean_class( 'fontawesome-icon-align-' . $alignment_value );
		$wrapper_style   = 'text-align:' . $alignment_value;

		$wrapper_classes = array_merge(
			array(
				'wp-block-gutenberg-icon',
				$align_class,
			),
			$custom_classes
		);
		$wrapper_attrs   = array(
			'class="' . esc_attr( implode( ' ', array_unique( array_filter( $wrapper_classes ) ) ) ) . '"',
			'style="' . esc_attr( $wrapper_style ) . '"',
		);


		if ( '' !== $custom_id ) {
			$wrapper_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		$inner_html = sprintf( '<div %1$s>%2$s</div>', implode( ' ', $wrapper_attrs ), $icon_html );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build(
			'gutenberg/icon',
			$attributes,
			$inner_html,
			array( 'no_kses' => true )
		);
	}

	/**
	 * Resolve icon data from Elementor settings.
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
	 * Sanitize custom classes string from Elementor.
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
	 * Resolve a color value ensuring hex output where possible.
	 */
	private function resolve_color_value( $raw_value, string $fallback ): string {
		$normalized = Style_Parser::normalize_color_value( $raw_value );
		if ( '' !== $normalized ) {
			return $normalized;
		}

		if ( is_string( $raw_value ) ) {
			$trimmed = trim( strtolower( $raw_value ) );
			if ( '' !== $trimmed ) {
				return $trimmed;
			}
		}

		return $fallback;
	}

	/**
	 * Sanitize slider/dimension values coming from Elementor controls.
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

	/**
	 * Convert associative array of styles into a CSS string.
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

	/**
	 * Map Elementor library names to Font Awesome style classes.
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
}
