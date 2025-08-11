<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$text          = $settings['text'] ?? '';
		$url           = $settings['link']['url'] ?? '';
		$attrs_array   = array();
		$inline_style  = '';

		// Button URL
		if ( $url ) {
			$attrs_array['url'] = esc_url( $url );
		}

		// Alignment
		if ( isset( $settings['align'] ) ) {
			$attrs_array['align'] = $settings['align'];
		}

		// Size
		if ( isset( $settings['size'] ) ) {
			$attrs_array['size'] = $settings['size'];
		}

		// Text shadow
		if ( isset( $settings['text_shadow_text_shadow_type'] ) && $settings['text_shadow_text_shadow_type'] === 'yes' ) {
			$attrs_array['style']['textShadow'] = '1px 1px 2px #000';
			$inline_style .= 'text-shadow:1px 1px 2px #000;';
		}

		// Background color
		if ( isset( $settings['background_color'] ) ) {
			$attrs_array['style']['color']['background'] = $settings['background_color'];
			$inline_style .= 'background-color:' . esc_attr( $settings['background_color'] ) . ';';
		}

		// Button text color
		if ( isset( $settings['button_text_color'] ) ) {
			$inline_style .= 'color:' . esc_attr( $settings['button_text_color'] ) . ';';
		}

		// Border
		if ( isset( $settings['border_border'] ) ) {
			$attrs_array['style']['border']['style'] = $settings['border_border'];
			$inline_style .= 'border-style:' . esc_attr( $settings['border_border'] ) . ';';
		}

		if ( isset( $settings['border_width'] ) && is_array( $settings['border_width'] ) ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['border_width'][ $side ] ) ) {
					$unit = isset( $settings['border_width']['unit'] ) ? $settings['border_width']['unit'] : 'px';
					$attrs_array['style']['border']['width'][ $side ] = $settings['border_width'][ $side ] . $unit;
					$inline_style .= 'border-' . $side . '-width:' . esc_attr( $settings['border_width'][ $side ] ) . $unit . ';';
				}
			}
		}

		if ( isset( $settings['border_radius'] ) && is_array( $settings['border_radius'] ) ) {
			$radius = '';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['border_radius'][ $side ] ) ) {
					$unit = isset( $settings['border_radius']['unit'] ) ? $settings['border_radius']['unit'] : 'px';
					$attrs_array['style']['border']['radius'][ $side ] = $settings['border_radius'][ $side ] . $unit;
					$radius .= esc_attr( $settings['border_radius'][ $side ] ) . $unit . ' ';
				} else {
					$radius .= '0px ';
				}
			}
			$inline_style .= 'border-radius:' . trim( $radius ) . ';';
		}

		// Margin & Padding
		$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );

		// Typography
		$typography  = Style_Parser::parse_typography( $settings );
		$inline_style .= $typography['style'];
		$attrs_array['style']['typography'] = $typography['attributes'];

		$attrs = wp_json_encode( $attrs_array );

		// Build block content
		$block_content = sprintf(
			'<!-- wp:button %s --><p><a class="wp-block-button__link"%s%s>%s</a></p><!-- /wp:button -->' . "\n",
			$attrs,
			$inline_style ? ' style="' . $inline_style . '"' : '',
			$url ? ' href="' . esc_url( $url ) . '"' : '',
			esc_html( $text )
		);

		return $block_content;
	}
}