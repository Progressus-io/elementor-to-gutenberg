<?php
/**
 * Widget handler for Elementor text-editor widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function sanitize_html_class;
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
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$content      = isset( $settings['editor'] ) ? (string) $settings['editor'] : '';
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$text_color   = isset( $settings['text_color'] ) ? strtolower( (string) $settings['text_color'] ) : '';

		if ( '' === trim( $content ) ) {
			return '';
		}

		$typography = Style_Parser::parse_typography( $settings );
		$spacing    = Style_Parser::parse_spacing( $settings );
		$border     = Style_Parser::parse_border( $settings );

		$inline_style_parts = array( $typography['style'], $spacing['style'], $border['style'] );
		$attributes         = array();
		$class_names        = array();
		$element_classes    = array();

		if ( ! empty( $typography['attributes'] ) ) {
			$attributes['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$attributes['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$attributes['style']['border'] = $border['attributes'];
		}

		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$class = trim( $class );
				if ( '' === $class ) {
					continue;
				}

				$sanitized         = sanitize_html_class( $class );
				$class_names[]     = $sanitized;
				$element_classes[] = $sanitized;
			}
		}

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$attributes['textColor']                                  = $text_color;
				$attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
				$inline_style_parts[]                                     = 'color:var(--wp--preset--color--' . sanitize_html_class( $text_color ) . ');';
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

		if ( ! empty( $class_names ) ) {
			$attributes['className'] = implode( ' ', array_unique( $class_names ) );
		}

		$inline_style      = implode( '', array_filter( $inline_style_parts ) );
		$element_classes   = array_unique( array_filter( $element_classes ) );
		$structured_blocks = $this->extract_structured_segments( $content );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		if ( null === $structured_blocks ) {
			return $this->build_html_block( $content, $custom_id, $element_classes, $inline_style );
		}

		$output   = array();
		$is_first = true;
		foreach ( $structured_blocks as $segment ) {
			$block_attributes = $attributes;

			if ( $is_first && '' !== $custom_id ) {
				$block_attributes['anchor'] = $custom_id;
			}

			if ( 'paragraph' === $segment['type'] ) {
				$paragraph_html = $this->build_paragraph_html(
					$segment,
					$element_classes,
					$inline_style,
					$is_first ? $custom_id : ''
				);

				$output[] = Block_Builder::build( 'paragraph', $block_attributes, $paragraph_html );
			} elseif ( 'list' === $segment['type'] ) {
				if ( 'ol' === $segment['tag'] ) {
					$block_attributes['ordered'] = true;
				}

				$list_html = $this->build_list_html(
					$segment,
					$element_classes,
					$inline_style,
					$is_first ? $custom_id : ''
				);

				$output[] = Block_Builder::build( 'list', $block_attributes, $list_html );
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
	 * @param string $custom_id Optional element ID.
	 * @param string[] $element_classes Classes to append to the wrapper element.
	 * @param string $inline_style Inline style string.
	 *
	 * @return string
	 */
	private function build_html_block( string $content, string $custom_id, array $element_classes, string $inline_style ): string {
		$wrapper_classes = array_merge( array( 'wp-block-paragraph' ), $element_classes );
		$attributes      = '';

		if ( '' !== $custom_id ) {
			$attributes .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $wrapper_classes ) ) {
			$attributes .= ' class="' . esc_attr( implode( ' ', array_unique( $wrapper_classes ) ) ) . '"';
		}

		if ( '' !== $inline_style ) {
			$attributes .= ' style="' . esc_attr( $inline_style ) . '"';
		}

		$inner_markup = sprintf( '<div%s>%s</div>', $attributes, wp_kses_post( $content ) );

		return Block_Builder::build( 'html', array(), $inner_markup );
	}

	/**
	 * Build the markup for a paragraph segment.
	 *
	 * @param array $segment Segment data containing paragraph content.
	 * @param string[] $element_classes Classes to apply to the paragraph element.
	 * @param string $inline_style Inline style string.
	 * @param string $custom_id Custom ID for the element (only applied to the first segment).
	 */
	private function build_paragraph_html( array $segment, array $element_classes, string $inline_style, string $custom_id ): string {
		$classes = $element_classes;
		$attrs   = '';

		if ( '' !== $custom_id ) {
			$attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $classes ) ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		}

		if ( '' !== $inline_style ) {
			$attrs .= ' style="' . esc_attr( $inline_style ) . '"';
		}

		$inner = $segment['plain'] ? esc_html( $segment['content'] ) : wp_kses_post( $segment['content'] );

		return sprintf( '<p%s>%s</p>', $attrs, $inner );
	}

	/**
	 * Build the markup for a list segment.
	 *
	 * @param array $segment Segment data containing list information.
	 * @param string[] $element_classes Classes to apply to the list element.
	 * @param string $inline_style Inline style string.
	 * @param string $custom_id Custom ID for the list (first segment only).
	 */
	private function build_list_html( array $segment, array $element_classes, string $inline_style, string $custom_id ): string {
		$tag     = 'ol' === $segment['tag'] ? 'ol' : 'ul';
		$classes = array_merge( array( 'wp-block-list' ), $element_classes );
		$attrs   = '';

		if ( '' !== $custom_id ) {
			$attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $classes ) ) {
			$attrs .= ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		}

		if ( '' !== $inline_style ) {
			$attrs .= ' style="' . esc_attr( $inline_style ) . '"';
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
		return '' !== $color && false === strpos( $color, '#' );
	}
}
