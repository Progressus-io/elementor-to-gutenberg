<?php
/**
 * Widget handler for Elementor icon widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon widget.
 */
class Icon_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor icon to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings       = $element['settings'] ?? array();
		$icon_value     = '';
		$icon_library   = '';
		$block_content  = '';

		// Handle the icon structure
		if ( isset( $settings['selected_icon']['value'] ) ) {
			$icon_value   = $settings['selected_icon']['value'];
			$icon_library = $settings['selected_icon']['library'] ?? 'fa-solid';
		} elseif ( isset( $settings['icon'] ) ) {
			// Fallback to old structure
			$icon_value = $settings['icon'];
		}

		$size  = isset( $settings['size'] ) ? intval( $settings['size'] ) : 24;
		$color = $settings['icon_color'] ?? '';

		// Build icon attributes
		$icon_attrs = array(
			'icon' => $icon_value,
			'size' => $size,
		);

		// Add color if present
		if ( $color ) {
			$icon_attrs['style']['color'] = $color;
		}

		// Add tooltip if present
		if ( ! empty( $settings['premium_tooltip_text'] ) ) {
			$icon_attrs['tooltip'] = $settings['premium_tooltip_text'];
		}
		if ( isset( $settings['premium_tooltip_position'] ) ) {
			$icon_attrs['tooltipPosition'] = $settings['premium_tooltip_position'];
		}

		$attrs = wp_json_encode( $icon_attrs );

		// Generate icon HTML
		$icon_class = 'fas';
		if ( $icon_library === 'fa-solid' ) {
			$icon_class = 'fas';
		} elseif ( $icon_library === 'fa-regular' ) {
			$icon_class = 'far';
		} elseif ( $icon_library === 'fa-brands' ) {
			$icon_class = 'fab';
		}

		$icon_style = 'font-size: ' . esc_attr( $size ) . 'px;';
		if ( $color ) {
			$icon_style .= 'color: ' . esc_attr( $color ) . ';';
		}

		$icon_html = '';
		if ( $icon_value ) {
			$icon_html = sprintf(
				'<i class="%s %s" style="%s"></i>',
				esc_attr( $icon_class ),
				esc_attr( $icon_value ),
				esc_attr( $icon_style )
			);

			// Add tooltip wrapper if tooltip is present
			if ( isset( $icon_attrs['tooltip'] ) ) {
				$tooltip_position = $icon_attrs['tooltipPosition'] ?? 'top';
				$icon_html = sprintf(
					'<span class="tooltip-wrapper" data-tooltip="%s" data-tooltip-position="%s">%s</span>',
					esc_attr( $icon_attrs['tooltip'] ),
					esc_attr( $tooltip_position ),
					$icon_html
				);
			}
		}

		$block_content .= sprintf(
			"<!-- wp:html %s --><div class=\"wp-block-icon\">%s</div><!-- /wp:html -->\n",
			$attrs,
			$icon_html
		);

		return $block_content;
	}
}