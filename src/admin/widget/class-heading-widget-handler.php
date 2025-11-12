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
		$computed     = Style_Parser::get_computed_styles( $element );

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$classes            = array( 'wp-block-heading' );
		$inline_styles      = array();
		$typography         = array();
		$spacing            = array();
		$color_slug         = '';
		$typography_map     = Style_Parser::parse_typography( $settings );
		$setting_typography = isset( $typography_map['attributes'] ) && is_array( $typography_map['attributes'] )
			? $typography_map['attributes']
			: array();

		$color_value = Style_Parser::normalize_color_value( $computed['color'] ?? '' );
		if ( '' === $color_value ) {
			$color_value = Style_Parser::normalize_color_value( $settings['title_color'] ?? '' );
		}

		if ( '' === $color_value ) {
			$raw_setting_color = isset( $settings['title_color'] ) ? (string) $settings['title_color'] : '';
			if ( $this->is_preset_color_slug( $raw_setting_color ) ) {
				$color_slug  = Style_Parser::clean_class( $raw_setting_color );
				$color_value = Style_Parser::resolve_theme_color_value( $color_slug );
			}
		}

		if ( '' === $color_slug && '' === $color_value ) {
			$matched_slug = Style_Parser::match_theme_color_slug( $computed['color'] ?? ( $settings['title_color'] ?? '' ) );
			if ( null !== $matched_slug ) {
				$color_slug  = Style_Parser::clean_class( $matched_slug );
				$color_value = Style_Parser::resolve_theme_color_value( $color_slug );
			}
		}

		if ( '' !== $color_value ) {
			$attributes['style']['color']['text'] = $color_value;
			$inline_styles['color']               = $color_value;
			$classes[]                            = 'has-text-color';
		}

		if ( '' !== $color_slug ) {
			$attributes['textColor'] = $color_slug;
			$classes[]               = 'has-text-color';
			$classes[]               = 'has-' . $color_slug . '-color';
			if ( '' === ( $inline_styles['color'] ?? '' ) ) {
				$resolved = Style_Parser::resolve_theme_color_value( $color_slug );
				if ( '' !== $resolved ) {
					$inline_styles['color'] = $resolved;
				}
			}
		}

		$font_family = Style_Parser::sanitize_font_family_value( $computed['font-family'] ?? '' );
		if ( '' === $font_family && isset( $setting_typography['fontFamily'] ) ) {
			$font_family = Style_Parser::sanitize_font_family_value( $setting_typography['fontFamily'] );
		}
		if ( '' !== $font_family ) {
			$typography['fontFamily']     = $font_family;
			$inline_styles['font-family'] = $font_family;
		}

		$font_size = Style_Parser::sanitize_css_dimension_value( $computed['font-size'] ?? '' );
		if ( '' === $font_size && isset( $setting_typography['fontSize'] ) ) {
			$font_size = Style_Parser::sanitize_css_dimension_value( $setting_typography['fontSize'] );
		}
		if ( '' !== $font_size ) {
			$typography['fontSize']     = $font_size;
			$inline_styles['font-size'] = $font_size;
		} else {
			$raw_font_size_slug = isset( $settings['typography_font_size'] ) ? (string) $settings['typography_font_size'] : '';
			if ( '' !== $raw_font_size_slug && preg_match( '/[a-zA-Z]/', $raw_font_size_slug ) ) {
				$font_size_slug = Style_Parser::clean_class( $raw_font_size_slug );
				if ( '' !== $font_size_slug ) {
					$attributes['fontSize'] = $font_size_slug;
					$classes[]              = 'has-' . $font_size_slug . '-font-size';
					$resolved_font_size     = Style_Parser::resolve_font_size_value( $font_size_slug );
					if ( '' !== $resolved_font_size ) {
						$inline_styles['font-size'] = $resolved_font_size;
					}
				}
			}
		}

		$font_weight = Style_Parser::sanitize_font_weight_value( $computed['font-weight'] ?? '' );
		if ( '' === $font_weight && isset( $setting_typography['fontWeight'] ) ) {
			$font_weight = Style_Parser::sanitize_font_weight_value( $setting_typography['fontWeight'] );
		}
		if ( '' !== $font_weight ) {
			$typography['fontWeight']     = $font_weight;
			$inline_styles['font-weight'] = $font_weight;
		}

		$font_style = Style_Parser::sanitize_font_style_value( $computed['font-style'] ?? '' );
		if ( '' === $font_style && isset( $setting_typography['fontStyle'] ) ) {
			$font_style = Style_Parser::sanitize_font_style_value( $setting_typography['fontStyle'] );
		}
		if ( '' !== $font_style ) {
			$typography['fontStyle']     = $font_style;
			$inline_styles['font-style'] = $font_style;
		}

		$text_decoration = Style_Parser::sanitize_text_decoration_value( $computed['text-decoration'] ?? '' );
		if ( '' === $text_decoration && isset( $setting_typography['textDecoration'] ) ) {
			$text_decoration = Style_Parser::sanitize_text_decoration_value( $setting_typography['textDecoration'] );
		}
		if ( '' !== $text_decoration ) {
			$typography['textDecoration']     = $text_decoration;
			$inline_styles['text-decoration'] = $text_decoration;
		}

		$line_height = Style_Parser::sanitize_line_height_value( $computed['line-height'] ?? '' );
		if ( '' === $line_height && isset( $setting_typography['lineHeight'] ) ) {
			$line_height = Style_Parser::sanitize_line_height_value( $setting_typography['lineHeight'] );
		}
		if ( '' !== $line_height ) {
			$typography['lineHeight']     = $line_height;
			$inline_styles['line-height'] = $line_height;
		}

		$letter_spacing = Style_Parser::sanitize_letter_spacing_value( $computed['letter-spacing'] ?? '' );
		if ( '' === $letter_spacing && isset( $setting_typography['letterSpacing'] ) ) {
			$letter_spacing = Style_Parser::sanitize_letter_spacing_value( $setting_typography['letterSpacing'] );
		}
		if ( '' !== $letter_spacing ) {
			$typography['letterSpacing']     = $letter_spacing;
			$inline_styles['letter-spacing'] = $letter_spacing;
		}

		$word_spacing = Style_Parser::sanitize_word_spacing_value( $computed['word-spacing'] ?? '' );
		if ( '' === $word_spacing && isset( $setting_typography['wordSpacing'] ) ) {
			$word_spacing = Style_Parser::sanitize_word_spacing_value( $setting_typography['wordSpacing'] );
		}
		if ( '' !== $word_spacing ) {
			$typography['wordSpacing']     = $word_spacing;
			$inline_styles['word-spacing'] = $word_spacing;
		}

		$text_transform = Style_Parser::sanitize_text_transform_value( $computed['text-transform'] ?? '' );
		if ( '' === $text_transform && isset( $setting_typography['textTransform'] ) ) {
			$text_transform = Style_Parser::sanitize_text_transform_value( $setting_typography['textTransform'] );
		}
		if ( '' !== $text_transform ) {
			$typography['textTransform']     = $text_transform;
			$inline_styles['text-transform'] = $text_transform;
		}

		$margin_top = Style_Parser::sanitize_css_dimension_value( $computed['margin-top'] ?? '' );
		if ( '' !== $margin_top ) {
			$spacing['margin']['top']    = $margin_top;
			$inline_styles['margin-top'] = $margin_top;
		}

		$margin_bottom = Style_Parser::sanitize_css_dimension_value( $computed['margin-bottom'] ?? '' );
		if ( '' !== $margin_bottom ) {
			$spacing['margin']['bottom']    = $margin_bottom;
			$inline_styles['margin-bottom'] = $margin_bottom;
		}

		$margin_left = Style_Parser::sanitize_css_dimension_value( $computed['margin-left'] ?? '' );
		if ( '' !== $margin_left ) {
			$spacing['margin']['left']    = $margin_left;
			$inline_styles['margin-left'] = $margin_left;
		}

		$margin_right = Style_Parser::sanitize_css_dimension_value( $computed['margin-right'] ?? '' );
		if ( '' !== $margin_right ) {
			$spacing['margin']['right']    = $margin_right;
			$inline_styles['margin-right'] = $margin_right;
		}

		$padding_top = Style_Parser::sanitize_css_dimension_value( $computed['padding-top'] ?? '' );
		if ( '' !== $padding_top ) {
			$spacing['padding']['top']    = $padding_top;
			$inline_styles['padding-top'] = $padding_top;
		}

		$padding_bottom = Style_Parser::sanitize_css_dimension_value( $computed['padding-bottom'] ?? '' );
		if ( '' !== $padding_bottom ) {
			$spacing['padding']['bottom']    = $padding_bottom;
			$inline_styles['padding-bottom'] = $padding_bottom;
		}

		$padding_left = Style_Parser::sanitize_css_dimension_value( $computed['padding-left'] ?? '' );
		if ( '' !== $padding_left ) {
			$spacing['padding']['left']    = $padding_left;
			$inline_styles['padding-left'] = $padding_left;
		}

		$padding_right = Style_Parser::sanitize_css_dimension_value( $computed['padding-right'] ?? '' );
		if ( '' !== $padding_right ) {
			$spacing['padding']['right']    = $padding_right;
			$inline_styles['padding-right'] = $padding_right;
		}

		if ( empty( $spacing['margin']['top'] ?? '' ) && empty( $spacing['margin']['bottom'] ?? '' ) ) {
			$spacing['margin']['top']       = '0';
			$spacing['margin']['bottom']    = '0';
			$inline_styles['margin-top']    = '0';
			$inline_styles['margin-bottom'] = '0';
		}

		if ( ! empty( $typography ) ) {
			$attributes['style']['typography'] = $typography;
		}

		if ( ! empty( $spacing ) ) {
			$attributes['style']['spacing'] = $spacing;
		}

		$text_align = $this->sanitize_text_align( $computed['text-align'] ?? ( $settings['align'] ?? '' ) );
		if ( '' !== $text_align ) {
			$attributes['textAlign']     = $text_align;
			$classes[]                   = 'has-text-align-' . $text_align;
			$inline_styles['text-align'] = $text_align;
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
		$style_attr = $this->build_style_attribute( $inline_styles );

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
		$color = strtolower( trim( $color ) );

		if ( '' === $color ) {
			return false;
		}

		if ( false !== strpos( $color, '#' ) || false !== strpos( $color, '(' ) || false !== strpos( $color, ')' ) ) {
			return false;
		}

		if ( false !== strpos( $color, 'rgb' ) || false !== strpos( $color, 'hsl' ) || false !== strpos( $color, 'var-' ) ) {
			return false;
		}

		return 1 === preg_match( '/^[a-z0-9\-]+$/', $color );
	}

	/**
	 * Ensure only valid text alignment values are returned.
	 *
	 * @param mixed $value Raw align value.
	 */
	private function sanitize_text_align( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value   = strtolower( trim( $value ) );
		$allowed = array( 'left', 'right', 'center', 'justify' );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Build inline style attribute for the heading markup.
	 *
	 * @param array<string, string> $styles Property/value pairs.
	 */
	private function build_style_attribute( array $styles ): string {
		if ( empty( $styles ) ) {
			return '';
		}

		$rules = array();

		foreach ( $styles as $property => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$rules[] = $property . ':' . $value;
		}

		if ( empty( $rules ) ) {
			return '';
		}

		$style_string = implode( ';', $rules );
		if ( '' !== $style_string && ';' !== substr( $style_string, - 1 ) ) {
			$style_string .= ';';
		}

		return ' style="' . esc_attr( $style_string ) . '"';
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