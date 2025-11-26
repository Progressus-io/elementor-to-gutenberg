<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$text       = isset( $settings['text'] ) ? trim( (string) $settings['text'] ) : '';
		$link_data  = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
		$url        = isset( $link_data['url'] ) ? esc_url( (string) $link_data['url'] ) : '';
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_raw = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$color_map  = Style_Parser::parse_button_styles( $settings );

		if ( '' === $text ) {
			$text = isset( $link_data['custom_text'] ) ? trim( (string) $link_data['custom_text'] ) : '';
		}

		if ( '' === $text && '' === $url ) {
			return '';
		}

		$custom_classes = array();
		if ( '' !== $custom_raw ) {
			foreach ( preg_split( '/\s+/', $custom_raw ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		$button_attributes = $color_map['attributes'] ?? array();
		if ( ! empty( $custom_classes ) ) {
			$existing_classnames = array();
			if ( ! empty( $button_attributes['className'] ) ) {
				$existing_classnames = preg_split( '/\s+/', (string) $button_attributes['className'] );
			}

			$combined_classes = array_filter( array_unique( array_merge( $existing_classnames, $custom_classes ) ) );
			if ( ! empty( $combined_classes ) ) {
				$button_attributes['className'] = implode( ' ', $combined_classes );
			}
		}

		$anchor_classes = array_merge(
			array( 'wp-block-button__link', 'wp-element-button' ),
			$color_map['anchor_classes'] ?? array()
		);
		$anchor_style   = $color_map['anchor_styles'] ?? array();

		if ( '' !== $url ) {
			$button_attributes['url'] = $url;
		}

		$rel_tokens = array();
		if ( ! empty( $link_data['is_external'] ) ) {
			$button_attributes['linkTarget'] = '_blank';
			$rel_tokens[]                    = 'noopener';
		}

		if ( ! empty( $link_data['nofollow'] ) ) {
			$rel_tokens[] = 'nofollow';
		}

		if ( ! empty( $rel_tokens ) ) {
			$button_attributes['rel'] = implode( ' ', array_unique( $rel_tokens ) );
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$anchor_attrs   = array();
		$anchor_attrs[] = 'class="' . esc_attr( implode( ' ', array_unique( $anchor_classes ) ) ) . '"';

		if ( '' !== $url ) {
			$anchor_attrs[] = 'href="' . $url . '"';
		}

		if ( ! empty( $link_data['is_external'] ) ) {
			$anchor_attrs[] = 'target="_blank"';
		}

		if ( ! empty( $rel_tokens ) ) {
			$anchor_attrs[] = 'rel="' . esc_attr( implode( ' ', array_unique( $rel_tokens ) ) ) . '"';
		}

		if ( ! empty( $anchor_style ) ) {
			$anchor_attrs[] = 'style="' . esc_attr( implode( ';', $anchor_style ) ) . '"';
		}

		$anchor_html = sprintf(
			'<a %s>%s</a>',
			implode( ' ', $anchor_attrs ),
			wp_strip_all_tags( $text )
		);

		$button_block = Block_Builder::build( 'button', $button_attributes, $anchor_html );

		return Block_Builder::build( 'buttons', array(), $button_block );
	}

}
