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
		$inline_style  = '';
		$custom_class  = $settings['_css_classes'] ?? '';
		$custom_id     = $settings['_element_id'] ?? '';
		$custom_css    = $settings['custom_css'] ?? '';
		$unique_class  = 'divider-' . uniqid();
		$custom_class  .= ' ' . $unique_class;

		// Alignment → group wrapper.
		$group_attrs = array();
		if ( isset( $settings['align'] ) ) {
			$group_attrs['align'] = $settings['align'];
		}

		// Separator attributes.
		$separator_attrs = array(
			'className' => trim( $custom_class ),
		);

		// Width.
		if ( isset( $settings['width']['size'] ) ) {
			$group_attrs['width'] = esc_attr( $settings['width']['size'] . ( $settings['width']['unit'] ?? 'px' ) ) . ';';
		}

		// Color.
		if ( isset( $settings['color'] ) ) {
			$color = strtolower( $settings['color'] );
			$separator_attrs['style']['color']['background'] = $color;
			$separator_attrs['className']                   .= ' has-text-color has-background has-alpha-channel-opacity';
			$inline_style                                   .= 'background-color:' . esc_attr( $color ) . ';color:' . esc_attr( $color ) . ';';
		}

		// Style (solid/dashed/dotted).
		if ( isset( $settings['style'] ) ) {
			$separator_attrs['className'] .= esc_attr( $settings['style'] ) == 'dotted' ? ' is-style-' . 'dots' : ' is-style-' . esc_attr( $settings['style'] );
		}

		// Margin / Spacing.
		$spacing = Style_Parser::parse_spacing( $settings );
		if ( ! empty( $spacing['attributes'] ) ) {
			$separator_attrs['style']['spacing'] = $spacing['attributes'];
			$inline_style                       .= $spacing['style'];
		}

		// Build block content.
		if ( $text ) {
			$group_attrs['layout'] = array(
				'type'           => 'flex',
				'justifyContent' => 'center',
				'flexWrap'       => 'nowrap',
			);

			$block_content .= sprintf(
				'<!-- wp:group %s --><div class="wp-block-group">',
				$group_attrs ? wp_json_encode( $group_attrs ) : ''
			);

			// First separator.
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr class="wp-block-separator %s" style="%s"/><!-- /wp:separator -->' . "\n",
				$separator_attrs ? wp_json_encode( $separator_attrs ) : '',
				esc_attr( $separator_attrs['className'] ),
				esc_attr( $inline_style )
			);

			// Text.
			$block_content .= sprintf(
				'<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->' . "\n",
				esc_html( $text )
			);

			// Second separator (with css opacity).
			$second_attrs = array(
				'opacity'   => 'css',
				'className' => trim( $unique_class ),
			);
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr class="wp-block-separator has-css-opacity %s"/><!-- /wp:separator -->' . "\n",
				wp_json_encode( $second_attrs ),
				esc_attr( $unique_class )
			);

			$block_content .= '</div><!-- /wp:group -->' . "\n";
		} else {
			// Simple separator.
			$block_content .= sprintf(
				'<!-- wp:separator %s --><hr id="%s" class="wp-block-separator %s" style="%s"/><!-- /wp:separator -->' . "\n",
				$separator_attrs ? wp_json_encode( $separator_attrs ) : '',
				esc_attr( $custom_id ),
				esc_attr( $separator_attrs['className'] ),
				esc_attr( $inline_style )
			);
		}

		// Save inline CSS.
		if ( $inline_style ) {
			$element_selector = '.' . $unique_class;
			$custom_css      .= sprintf( '%s{ %s }', $element_selector, $inline_style );
		}
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}
}
