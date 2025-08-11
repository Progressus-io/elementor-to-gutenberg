<?php
/**
 * Widget handler for Elementor accordion widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor accordion widget.
 */
class Accordion_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor accordion to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$accordions    = $settings['accordions'] ?? array();
		$block_content = '';

		if ( ! empty( $accordions ) && is_array( $accordions ) ) {
			// Get optional styles from settings
			$title_color           = $settings['title_color'] ?? '';
			$title_bg_color        = $settings['title_background_color'] ?? '';
			$content_color         = $settings['content_color'] ?? '';
			$content_bg_color      = $settings['content_background_color'] ?? '';
			$border_radius         = ! empty( $settings['border_radius'] ) ? intval( $settings['border_radius'] ) . 'px' : '0';
			$border_width          = ! empty( $settings['border_width'] ) ? intval( $settings['border_width'] ) . 'px' : '0';
			$border_color          = $settings['border_color'] ?? '';
			$spacing_between_items = ! empty( $settings['items_gap'] ) ? intval( $settings['items_gap'] ) . 'px' : '16px';

			$accordion_content = '';

			foreach ( $accordions as $item ) {
				$title   = isset( $item['title'] ) ? wp_kses_post( $item['title'] ) : '';
				$content = isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '';

				if ( $title && $content ) {
					// Generate inline styles
					$summary_styles = array();
					$content_styles = array();
					$details_styles = array();

					if ( $title_color ) {
						$summary_styles[] = "color: {$title_color}";
					}
					if ( $title_bg_color ) {
						$summary_styles[] = "background-color: {$title_bg_color}";
					}
					if ( $content_color ) {
						$content_styles[] = "color: {$content_color}";
					}
					if ( $content_bg_color ) {
						$content_styles[] = "background-color: {$content_bg_color}";
					}
					if ( $border_radius ) {
						$details_styles[] = "border-radius: {$border_radius}";
					}
					if ( $border_width && $border_color ) {
						$details_styles[] = "border: {$border_width} solid {$border_color}";
					}
					if ( $spacing_between_items ) {
						$details_styles[] = "margin-bottom: {$spacing_between_items}";
					}

					$accordion_content .= sprintf(
						"<!-- wp:html -->\n<details style=\"%s\">\n<summary style=\"padding:0.5em 0;%s\">%s</summary>\n<div style=\"padding:0.5em 1em;%s\">%s</div>\n</details>\n<!-- /wp:html -->\n",
						implode( '; ', $details_styles ),
						implode( '; ', $summary_styles ),
						$title,
						implode( '; ', $content_styles ),
						$content
					);
				}
			}

			$block_content .= $accordion_content;
		}

		return $block_content;
	}
}