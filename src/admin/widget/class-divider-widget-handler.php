<?php
/**
 * Widget handler for Elementor divider widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor divider widget.
 */
class Divider_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor divider to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$text          = $settings['text'] ?? '';
		$block_content = '';
		$attrs_array   = array();
		$inline_style  = '';

		// Alignment
		if ( isset( $settings['align'] ) ) {
			$attrs_array['align'] = $settings['align'];
			$inline_style .= 'text-align:' . esc_attr( $settings['align'] ) . ';';
		}

		// Width
		if ( isset( $settings['width']['size'] ) ) {
			$attrs_array['style']['dimensions']['width'] = $settings['width']['size'] . ($settings['width']['unit'] ?? 'px');
			$inline_style .= 'width:' . esc_attr( $settings['width']['size'] . ($settings['width']['unit'] ?? 'px')) . ';';
		}

		// Color
		if ( isset( $settings['color'] ) ) {
			$attrs_array['style']['color']['background'] = $settings['color'];
			$inline_style .= 'border-color:' . esc_attr( $settings['color'] ) . ';';
		}

		// Style (e.g., solid, dashed)
        	$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_border( $settings ) );

		// if ( isset( $settings['style'] ) ) {
		// 	$attrs_array['style']['border']['style'] = $settings['style'];
		// 	$inline_style .= 'border-style:' . esc_attr( $settings['style'] ) . ';';
		// }

		// Gap (spacing above/below divider)
		if ( isset( $settings['gap']['size'] ) ) {
			$attrs_array['style']['spacing']['margin']['top'] = $settings['gap']['size'] . ($settings['gap']['unit'] ?? 'px');
			$attrs_array['style']['spacing']['margin']['bottom'] = $settings['gap']['size'] . ($settings['gap']['unit'] ?? 'px');
			$inline_style .= 'margin-top:' . esc_attr( $settings['gap']['size'] . ($settings['gap']['unit'] ?? 'px')) . ';';
			$inline_style .= 'margin-bottom:' . esc_attr( $settings['gap']['size'] . ($settings['gap']['unit'] ?? 'px')) . ';';
		}

		// Margin & Padding (using Style_Parser for consistency)
		$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );

		// Remove empty style arrays
		if ( empty( $attrs_array['style']['spacing'] ) ) {
			unset( $attrs_array['style']['spacing'] );
		}
		if ( empty( $attrs_array['style'] ) ) {
			unset( $attrs_array['style'] );
		}

		$attrs = wp_json_encode( $attrs_array );

		// Build block content
		if ( $text ) {
			// If text is present, use a group block with paragraph and separator
			$block_content .= sprintf(
				'<!-- wp:group %s --><div class="wp-block-group">',
				$attrs ?: ''
			);
			$block_content .= sprintf(
				'<!-- wp:paragraph --><p%s>%s</p><!-- /wp:paragraph -->' . "\n",
				$inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
				esc_html( $text )
			);
			$block_content .= '<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->' . "\n";
			$block_content .= '</div><!-- /wp:group -->' . "\n";
		} else {
			// If no text, use a simple separator block
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr class="wp-block-separator"%s/><!-- /wp:separator -->' . "\n",
				$attrs ?: '',
				$inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : ''
			);
		}

		return $block_content;
	}
}