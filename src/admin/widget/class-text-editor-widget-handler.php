<?php
/**
 * Widget handler for Elementor text-editor widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor text-editor widget.
 */
class Text_Editor_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor text-editor to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$content      = isset( $settings['editor'] ) ? (string) $settings['editor'] : '';
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$computed     = Style_Parser::get_computed_styles( $element );

		if ( '' === trim( $content ) ) {
			return '';
		}

		$custom_classes = array();
		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		$base_attributes = array();
		if ( ! empty( $custom_classes ) ) {
			$base_attributes['className'] = implode( ' ', array_unique( $custom_classes ) );
		}

		$markup_classes     = $custom_classes;
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
			$color_value = Style_Parser::normalize_color_value( $settings['text_color'] ?? '' );
		}

		if ( '' === $color_value ) {
			$raw_setting_color = isset( $settings['text_color'] ) ? (string) $settings['text_color'] : '';
			if ( $this->is_preset_color_slug( $raw_setting_color ) ) {
				$color_slug  = Style_Parser::clean_class( $raw_setting_color );
				$color_value = Style_Parser::resolve_theme_color_value( $color_slug );
			}
		}

		if ( '' === $color_slug && '' === $color_value ) {
			$matched_slug = Style_Parser::match_theme_color_slug( $computed['color'] ?? ( $settings['text_color'] ?? '' ) );
			if ( null !== $matched_slug ) {
				$color_slug  = Style_Parser::clean_class( $matched_slug );
				$color_value = Style_Parser::resolve_theme_color_value( $color_slug );
			}
		}

		if ( '' !== $color_value ) {
			$base_attributes['style']['color']['text'] = $color_value;
			$inline_styles['color']                    = $color_value;
			$markup_classes[]                          = 'has-text-color';
		}

		if ( '' !== $color_slug ) {
			$base_attributes['textColor'] = $color_slug;
			$markup_classes[]             = 'has-text-color';
			$markup_classes[]             = 'has-' . $color_slug . '-color';
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
					$base_attributes['fontSize'] = $font_size_slug;
					$markup_classes[]            = 'has-' . $font_size_slug . '-font-size';
					$resolved_font_size          = Style_Parser::resolve_font_size_value( $font_size_slug );
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

		if ( ! empty( $typography ) ) {
			$base_attributes['style']['typography'] = $typography;
		}

		if ( ! empty( $spacing ) ) {
			$base_attributes['style']['spacing'] = $spacing;
		}

		$align_payload = Alignment_Helper::build_text_alignment_payload(
			Alignment_Helper::detect_alignment(
				$settings,
				array( 'align', 'alignment', 'text_align' ),
				array( $computed['text-align'] ?? '' )
			)
		);
		if ( ! empty( $align_payload['attributes'] ) ) {
			$base_attributes = array_merge( $base_attributes, $align_payload['attributes'] );
		}
		if ( ! empty( $align_payload['classes'] ) ) {
			$markup_classes = array_merge( $markup_classes, $align_payload['classes'] );
		}
		if ( '' !== $align_payload['style'] && isset( $align_payload['attributes']['textAlign'] ) ) {
			$inline_styles['text-align'] = $align_payload['attributes']['textAlign'];
		}

		$segments = $this->extract_structured_segments( $content );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		if ( null === $segments ) {
			return $this->build_html_block( $content );
		}

		$style_string = $this->build_style_string( $inline_styles );

		$output   = array();
		$is_first = true;

		foreach ( $segments as $segment ) {
			$attributes = $base_attributes;

			if ( $is_first && '' !== $custom_id ) {
				$attributes['anchor'] = $custom_id;
			}

			$element_classes = $markup_classes;

			if ( 'paragraph' === $segment['type'] ) {
				$paragraph_html = $this->build_paragraph_html(
					$segment,
					$element_classes,
					$style_string,
					$is_first ? $custom_id : ''
				);

				$output[] = Block_Builder::build( 'paragraph', $attributes, $paragraph_html );
			} elseif ( 'list' === $segment['type'] ) {
				if ( 'ol' === $segment['tag'] ) {
					$attributes['ordered'] = true;
				}

				$list_html = $this->build_list_html(
					$segment,
					$element_classes,
					$style_string,
					$is_first ? $custom_id : ''
				);

				$output[] = Block_Builder::build( 'list', $attributes, $list_html );
			}

			$is_first = false;
		}

		if ( empty( $output ) ) {
			return '';
		}

		return implode( '', $output );
	}

	/**
	 * Fallback renderer for complex HTML content that cannot be mapped cleanly to core blocks.
	 *
	 * @param string $content Raw HTML content.
	 *
	 * @return string
	 */
	private function build_html_block( string $content ): string {
		return Block_Builder::build( 'html', array(), wp_kses_post( $content ) );
	}

	/**
	 * Build the markup for a paragraph segment.
	 *
	 * @param array $segment Segment data containing paragraph content.
	 * @param array $classes Classes to apply to the paragraph element.
	 * @param string $style Inline style declaration.
	 * @param string $custom_id Optional anchor to apply.
	 */
	private function build_paragraph_html( array $segment, array $classes, string $style, string $custom_id ): string {
		$attrs = '';

		if ( '' !== $custom_id ) {
			$attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $classes ) ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		}

		if ( '' !== $style ) {
			$attrs .= ' style="' . esc_attr( $style ) . '"';
		}

		$inner = $segment['plain'] ? esc_html( $segment['content'] ) : wp_kses_post( $this->strip_wrapping_p( $segment['content'] ) );
		return sprintf( '<p%s>%s</p>', $attrs, $inner );
	}

	/**
	 * Build the markup for a list segment.
	 *
	 * @param array $segment Segment data containing list information.
	 * @param array $classes Classes to apply to the list element.
	 * @param string $style Inline style declaration.
	 * @param string $custom_id Optional anchor for the list.
	 */
	private function build_list_html( array $segment, array $classes, string $style, string $custom_id ): string {
		$tag   = 'ol' === $segment['tag'] ? 'ol' : 'ul';
		$attrs = '';

		if ( '' !== $custom_id ) {
			$attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $classes ) ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		}

		if ( '' !== $style ) {
			$attrs .= ' style="' . esc_attr( $style ) . '"';
		}

		$items_html = array();
		foreach ( $segment['items'] as $item ) {
			$items_html[] = sprintf( '<li>%s</li>', wp_kses_post( $item ) );
		}

		return sprintf( '<%1$s%2$s>%3$s</%1$s>', $tag, $attrs, implode( '', $items_html ) );
	}

	/**
	 * Attempt to convert the raw editor content into structured segments (paragraphs/lists).
	 *
	 * @param string $content Raw editor HTML/content.
	 *
	 * @return array<int, array>|null
	 */
	private function extract_structured_segments( string $content ): ?array {
		$trimmed = trim( $content );
		if ( '' === $trimmed ) {
			return array();
		}

		if ( false === strpos( $trimmed, '<' ) ) {
			return array(
				array(
					'type'    => 'paragraph',
					'content' => $trimmed,
					'plain'   => true,
				),
			);
		}

		$libxml_previous = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();

		$loaded = $document->loadHTML( '<div>' . $trimmed . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );

		if ( ! $loaded ) {
			return null;
		}

		$wrapper = $document->getElementsByTagName( 'div' )->item( 0 );
		if ( ! $wrapper ) {
			return null;
		}

		$segments = array();

		foreach ( $wrapper->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$text = trim( $child->nodeValue );
				if ( '' !== $text ) {
					$segments[] = array(
						'type'    => 'paragraph',
						'content' => $text,
						'plain'   => true,
					);
				}
				continue;
			}

			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}

			$tag = strtolower( $child->nodeName );

			if ( 'p' === $tag ) {
				$segments[] = array(
					'type'    => 'paragraph',
					'content' => $this->get_inner_html( $child ),
					'plain'   => false,
				);
				continue;
			}

			if ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
				$items = array();
				foreach ( $child->childNodes as $item_node ) {
					if ( XML_ELEMENT_NODE !== $item_node->nodeType || 'li' !== strtolower( $item_node->nodeName ) ) {
						continue;
					}

					$items[] = $this->get_inner_html( $item_node );
				}

				if ( ! empty( $items ) ) {
					$segments[] = array(
						'type'  => 'list',
						'tag'   => $tag,
						'items' => $items,
					);
					continue;
				}
			}

			return null;
		}

		if ( empty( $segments ) ) {
			return null;
		}

		return $segments;
	}

	/**
	 * Retrieve the inner HTML of a DOM node.
	 *
	 * @param \DOMNode $node Node to extract HTML from.
	 */
	private function get_inner_html( \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Determine if a color value refers to a preset slug.
	 *
	 * @param string $color Color value.
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
	 * Ensure only supported alignment values are returned.
	 *
	 * @param mixed $value Raw alignment value.
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
	 * Convert style map to an inline declaration string.
	 *
	 * @param array<string, string> $styles Styles to render.
	 */
	private function build_style_string( array $styles ): string {
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

		$style = implode( ';', $rules );
		if ( '' !== $style && ';' !== substr( $style, - 1 ) ) {
			$style .= ';';
		}

		return $style;
	}
	private function strip_wrapping_p( string $html ): string {
		$trimmed = trim( $html );
		if ( 1 === preg_match( '#^<p\b[^>]*>(.*)</p>$#is', $trimmed, $m ) ) {
			return (string) $m[1];
		}
		return $html;
	}

}
