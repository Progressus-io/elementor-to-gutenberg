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

		$markup_classes = $custom_classes;
		$style_color    = '';

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$base_attributes['textColor'] = $text_color;
				$markup_classes[]             = 'has-text-color';
				$markup_classes[]             = 'has-' . Style_Parser::clean_class( $text_color ) . '-color';
			} elseif ( $this->is_hex_color( $text_color ) ) {
				$base_attributes['style']['color']['text'] = $text_color;
				$markup_classes[]                          = 'has-text-color';
				$style_color                               = 'color:' . $text_color . ';';
			}
		}

		$segments = $this->extract_structured_segments( $content );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		if ( null === $segments ) {
			return $this->build_html_block( $content );
		}

		$output   = array();
		$is_first = true;

		foreach ( $segments as $segment ) {
			$attributes = $base_attributes;

			if ( $is_first && '' !== $custom_id ) {
				$attributes['anchor'] = $custom_id;
			}

			$element_classes = $markup_classes;
			$style_attr      = $style_color;

			if ( 'paragraph' === $segment['type'] ) {
				$paragraph_html = $this->build_paragraph_html(
					$segment,
					$element_classes,
					$style_attr,
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
					$style_attr,
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

		$inner = $segment['plain'] ? esc_html( $segment['content'] ) : wp_kses_post( $segment['content'] );

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
		return '' !== $color && false === strpos( $color, '#' );
	}

	/**
	 * Determine if a color string is hexadecimal.
	 */
	private function is_hex_color( string $color ): bool {
		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
	}
}
