<?php
/**
 * Widget handler for Elementor icon box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon box widget.
 */
class Icon_Box_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor icon box to Gutenberg block.
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

		$title       = $settings['title_text'] ?? '';
		$description = $settings['description_text'] ?? '';
		$size        = isset( $settings['size'] ) ? intval( $settings['size'] ) : 24;
		$shape       = $settings['shape'] ?? 'square';

		// Build icon box attributes
		$icon_box_attrs = array(
			'icon'        => $icon_value,
			'size'        => $size,
			'shape'       => $shape,
			'title'       => $title,
			'description' => $description,
		);

		// Add tooltip if present
		if ( ! empty( $settings['premium_tooltip_text'] ) ) {
			$icon_box_attrs['tooltip'] = $settings['premium_tooltip_text'];
		}
		if ( isset( $settings['premium_tooltip_position'] ) ) {
			// Sanitize tooltip position to use the first valid value
			$valid_positions = ['top', 'bottom', 'left', 'right'];
			$tooltip_position = $settings['premium_tooltip_position'];
			$tooltip_position = explode( ',', $tooltip_position )[0]; // Take first value if comma-separated
			$icon_box_attrs['tooltipPosition'] = in_array( $tooltip_position, $valid_positions, true ) ? $tooltip_position : 'top';
		}

		$attrs = wp_json_encode( $icon_box_attrs );

		// Generate icon HTML
		$icon_class = 'fas';
		if ( $icon_library === 'fa-solid' ) {
			$icon_class = 'fas';
		} elseif ( $icon_library === 'fa-regular' ) {
			$icon_class = 'far';
		} elseif ( $icon_library === 'fa-brands' ) {
			$icon_class = 'fab';
		}

		$icon_html = '';
		if ( $icon_value ) {
			$icon_html = sprintf(
				'<i class="%s %s" style="font-size: %spx;"></i>',
				esc_attr( $icon_class ),
				esc_attr( $icon_value ),
				esc_attr( $size )
			);

			// Add tooltip wrapper if tooltip is present
			if ( isset( $icon_box_attrs['tooltip'] ) ) {
				$tooltip_position = $icon_box_attrs['tooltipPosition'] ?? 'top';
				$icon_html = sprintf(
					'<span class="tooltip-wrapper" data-tooltip="%s" data-tooltip-position="%s">%s</span>',
					esc_attr( $icon_box_attrs['tooltip'] ),
					esc_attr( $tooltip_position ),
					$icon_html
				);
			}
		}

		// Generate the icon box content
		$icon_box_content = '';
		if ( $icon_html ) {
			$icon_box_content .= '<div class="icon-box-icon">' . $icon_html . '</div>';
		}
		if ( $title ) {
			$icon_box_content .= '<h3 class="icon-box-title">' . esc_html( $title ) . '</h3>';
		}
		if ( $description ) {
			$icon_box_content .= '<div class="icon-box-description">' . wp_kses_post( $description ) . '</div>';
		}

		$block_content .= sprintf(
			"<!-- wp:html %s --><div class=\"wp-block-icon-box\">%s</div><!-- /wp:html -->\n",
			esc_attr( $attrs ),
			$icon_box_content
		);

		return $block_content;
	}
}