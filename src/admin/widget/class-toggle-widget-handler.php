<?php
/**
 * Widget handler for Elementor toggle widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function wp_json_encode;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor toggle widget.
 */
class Toggle_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor toggle widget to Gutenberg details blocks.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$tabs     = is_array( $settings['tabs'] ?? null ) ? $settings['tabs'] : array();

		if ( empty( $tabs ) ) {
			return '';
		}

		$custom_css     = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id      = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_classes = $this->sanitize_custom_classes( isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '' );

		$items_html = array();

		foreach ( $tabs as $index => $tab ) {
			$title   = isset( $tab['tab_title'] ) ? trim( (string) $tab['tab_title'] ) : '';
			$content = isset( $tab['tab_content'] ) ? (string) $tab['tab_content'] : '';

			if ( '' === $title && '' === trim( $content ) ) {
				continue;
			}

			// Block attrs: only className and anchor — summary is NOT stored as an attr.
			$attrs = array();
			if ( ! empty( $custom_classes ) ) {
				$attrs['className'] = implode( ' ', $custom_classes );
			}

			$id_attribute = '';
			if ( '' !== $custom_id ) {
				$tab_suffix      = isset( $tab['_id'] ) ? trim( (string) $tab['_id'] ) : (string) $index;
				$details_id      = '' !== $tab_suffix ? $custom_id . '-' . $tab_suffix : $custom_id;
				$attrs['anchor'] = $details_id;
				$id_attribute    = ' id="' . esc_attr( $details_id ) . '"';
			}

			// HTML class attribute: always include wp-block-details plus any custom classes.
			$classes    = array_filter( array_unique( array_merge( array( 'wp-block-details' ), $custom_classes ) ) );
			$class_attr = ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';

			$attrs_comment = ! empty( $attrs ) ? ' ' . (string) wp_json_encode( $attrs ) : '';
			$summary_html  = wp_kses_post( $title );

			$content_trimmed = trim( $content );
			if ( '' !== $content_trimmed ) {
				// Inner blocks go directly inside <details> after </summary> — no wrapper div.
				$inner_block  = $this->build_inner_block( wp_kses_post( $content_trimmed ) );
				$items_html[] = sprintf(
					"<!-- wp:details%s -->\n<details%s%s><summary>%s</summary>%s</details>\n<!-- /wp:details -->\n",
					$attrs_comment,
					$class_attr,
					$id_attribute,
					$summary_html,
					$inner_block
				);
			} else {
				// No content: no inner blocks — matches save() output exactly.
				$items_html[] = sprintf(
					"<!-- wp:details%s -->\n<details%s%s><summary>%s</summary></details>\n<!-- /wp:details -->\n",
					$attrs_comment,
					$class_attr,
					$id_attribute,
					$summary_html
				);
			}
		}

		if ( empty( $items_html ) ) {
			return '';
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return implode( '', $items_html );
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
	 * Convert raw HTML content to a serialized Gutenberg inner block.
	 *
	 * Inner blocks are placed directly inside <details> after </summary>,
	 * with no wrapper div. Uses core/paragraph for text/simple content and
	 * core/html for complex HTML.
	 *
	 * @param string $content Sanitized HTML content.
	 *
	 * @return string Serialized inner block markup.
	 */
	private function build_inner_block( string $content ): string {
		$content = trim( $content );

		if ( '' === $content ) {
			return '';
		}

		// Plain text with no HTML tags — wrap in <p> for a paragraph block.
		if ( ! preg_match( '/<[a-z][^>]*>/i', $content ) ) {
			return "<!-- wp:paragraph -->\n<p>" . $content . "</p>\n<!-- /wp:paragraph -->";
		}

		// Single <p>...</p> — use as-is for a paragraph block.
		if ( preg_match( '/^<p[^>]*>.*<\/p>$/si', $content ) && 1 === substr_count( strtolower( $content ), '<p' ) ) {
			return "<!-- wp:paragraph -->\n" . $content . "\n<!-- /wp:paragraph -->";
		}

		// Complex or multi-paragraph HTML — use a raw HTML block.
		return "<!-- wp:html -->\n" . $content . "\n<!-- /wp:html -->";
	}
}
