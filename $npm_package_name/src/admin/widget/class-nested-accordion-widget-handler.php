<?php
/**
 * Widget handler for Elementor nested accordion widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor nested accordion widget.
 */
class Nested_Accordion_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor nested accordion to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$elements      = $element['elements'] ?? array();
		$block_content = '';

		// Styling map
		$style = array();

		// Title spacing
		if ( isset( $settings['accordion_item_title_space_between']['size'] ) ) {
			$style[] = '--title-gap: ' . $settings['accordion_item_title_space_between']['size'] . 'px;';
		}

		// Title distance from content
		if ( isset( $settings['accordion_item_title_distance_from_content']['size'] ) ) {
			$style[] = '--content-gap: ' . $settings['accordion_item_title_distance_from_content']['size'] . 'px;';
		}

		// Padding
		if ( isset( $settings['accordion_padding'] ) ) {
			$p       = $settings['accordion_padding'];
			$style[] = sprintf(
				'padding: %spx %spx %spx %spx;',
				$p['top'],
				$p['right'],
				$p['bottom'],
				$p['left']
			);
		}

		// Margin
		if ( isset( $settings['_margin'] ) ) {
			$m       = $settings['_margin'];
			$style[] = sprintf(
				'margin: %spx %spx %spx %spx;',
				$m['top'],
				$m['right'],
				$m['bottom'],
				$m['left']
			);
		}

		// Border
		if ( isset( $settings['accordion_border_normal_border'] ) ) {
			$border_type  = $settings['accordion_border_normal_border'];
			$border_width = $settings['accordion_border_normal_width'] ?? array();
			$b_top        = $border_width['top'] ?? 0;
			$b_right      = $border_width['right'] ?? 0;
			$b_bottom     = $border_width['bottom'] ?? 0;
			$b_left       = $border_width['left'] ?? 0;
			$style[]      = sprintf(
				'border-style: %s; border-width: %spx %spx %spx %spx;',
				$border_type,
				$b_top,
				$b_right,
				$b_bottom,
				$b_left
			);
		}

		// Border radius
		if ( isset( $settings['accordion_border_radius'] ) ) {
			$r       = $settings['accordion_border_radius'];
			$style[] = sprintf(
				'border-radius: %spx %spx %spx %spx;',
				$r['top'],
				$r['right'],
				$r['bottom'],
				$r['left']
			);
		}

		// CSS classes and ID
		$class      = $settings['_css_classes'] ?? '';
		$css_id     = $settings['_element_id'] ?? '';
		$style_attr = ! empty( $style ) ? ' style="' . esc_attr( implode( ' ', $style ) ) . '"' : '';
		$class_attr = ! empty( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';
		$id_attr    = ! empty( $css_id ) ? ' id="' . esc_attr( $css_id ) . '"' : '';

		$block_content .= sprintf(
			"<!-- wp:group%s%s%s --><div class=\"wp-block-group\">",
			$class_attr,
			$id_attr,
			$style_attr
		);

		// Loop through accordion containers
		foreach ( $elements as $accordion_element ) {
			$title   = $accordion_element['settings']['_title'] ?? 'Accordion Item';
			$content = '';

			if ( ! empty( $accordion_element['elements'] ) ) {
				$content = $this->parse_elementor_elements( $accordion_element['elements'] );
			} elseif ( isset( $accordion_element['settings']['content'] ) ) {
				$content = wp_kses_post( $accordion_element['settings']['content'] );
			}

			$block_content .= sprintf(
				"<!-- wp:details -->\n<details><summary>%s</summary>%s</details>\n<!-- /wp:details -->\n",
				esc_html( $title ),
				$content
			);
		}

		$block_content .= "</div><!-- /wp:group -->\n";

		return $block_content;
	}

	/**
	 * Parse nested Elementor elements (stub method).
	 *
	 * @param array $elements The nested Elementor elements.
	 *
	 * @return string The parsed content.
	 */
	private function parse_elementor_elements( array $elements ): string {
		// This is a stub; implement recursive parsing logic as needed
		return '';
	}
}