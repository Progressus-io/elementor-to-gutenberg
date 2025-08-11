<?php
/**
 * Widget handler for Elementor icon list widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon list widget.
 */
class Icon_List_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor icon list to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings       = $element['settings'] ?? array();
		$icon_list      = $settings['icon_list'] ?? array();
		$block_content  = '';

		if ( ! empty( $icon_list ) ) {
			// Build icon list attributes
			$icon_list_attrs = array(
				'itemCount' => count( $icon_list ),
			);

			// Add tooltip if present
			if ( ! empty( $settings['premium_tooltip_text'] ) ) {
				$icon_list_attrs['tooltip'] = $settings['premium_tooltip_text'];
			}
			if ( isset( $settings['premium_tooltip_position'] ) ) {
				$icon_list_attrs['tooltipPosition'] = $settings['premium_tooltip_position'];
			}

			$attrs = wp_json_encode( $icon_list_attrs );

			// Generate icon list content
			$icon_list_content = '<ul class="icon-list">';

			foreach ( $icon_list as $list_item ) {
				$item_text         = $list_item['text'] ?? '';
				$item_icon_value   = '';
				$item_icon_library = '';

				// Handle icon structure for each item
				if ( isset( $list_item['selected_icon']['value'] ) ) {
					$item_icon_value   = $list_item['selected_icon']['value'];
					$item_icon_library = $list_item['selected_icon']['library'] ?? 'fa-solid';
				}

				$icon_list_content .= '<li class="icon-list-item">';

				// Add icon if present
				if ( $item_icon_value ) {
					$icon_class = 'fas';
					if ( $item_icon_library === 'fa-solid' ) {
						$icon_class = 'fas';
					} elseif ( $item_icon_library === 'fa-regular' ) {
						$icon_class = 'far';
					} elseif ( $item_icon_library === 'fa-brands' ) {
						$icon_class = 'fab';
					}

					$item_icon_html = '<i class="' . esc_attr( $icon_class ) . ' ' . esc_attr( $item_icon_value ) . '"></i>';

					// Add tooltip wrapper if tooltip is present
					if ( isset( $icon_list_attrs['tooltip'] ) ) {
						$tooltip_position = $icon_list_attrs['tooltipPosition'] ?? 'top';
						$item_icon_html = '<span class="tooltip-wrapper" data-tooltip="' . esc_attr( $icon_list_attrs['tooltip'] ) . '" data-tooltip-position="' . esc_attr( $tooltip_position ) . '">' . $item_icon_html . '</span>';
					}

					$icon_list_content .= '<span class="icon-list-icon">' . $item_icon_html . '</span>';
				}

				// Add text
				if ( $item_text ) {
					$icon_list_content .= '<span class="icon-list-text">' . esc_html( $item_text ) . '</span>';
				}

				$icon_list_content .= '</li>';
			}

			$icon_list_content .= '</ul>';

			$block_content .= sprintf(
				"<!-- wp:html %s --><div class=\"wp-block-icon-list\">%s</div><!-- /wp:html -->\n",
				$attrs,
				$icon_list_content
			);
		}

		return $block_content;
	}
}