<?php
/**
 * Widget handler for Elementor counter widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

// @phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor counter widget.
 */
class Counter_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor counter to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings = $element['settings'] ?? array();

		// Extract counter settings.
		$starting_number = isset( $settings['starting_number'] ) ? intval( $settings['starting_number'] ) : 0;
		$ending_number   = isset( $settings['ending_number'] ) ? intval( $settings['ending_number'] ) : 100;
		$prefix          = isset( $settings['prefix'] ) ? \esc_html( $settings['prefix'] ) : '';
		$suffix          = isset( $settings['suffix'] ) ? \esc_html( $settings['suffix'] ) : '';
		$duration        = isset( $settings['duration'] ) ? intval( $settings['duration'] ) : 2000;
		$title           = isset( $settings['title'] ) ? \esc_html( $settings['title'] ) : '';

		// Extract styles.
		$number_color = isset( $settings['number_color'] ) ? $settings['number_color'] : '';
		$title_color  = isset( $settings['title_color'] ) ? $settings['title_color'] : '';
		$align        = isset( $settings['align'] ) ? $settings['align'] : 'center';

		// Font sizes.
		$number_typography = $settings['number_typography'] ?? array();
		$title_typography  = $settings['title_typography'] ?? array();
		$number_size       = isset( $number_typography['size'] ) ? intval( $number_typography['size'] ) : 50;
		$title_size        = isset( $title_typography['size'] ) ? intval( $title_typography['size'] ) : 20;

		// Prepare attributes.
		$attrs = array(
			'startValue'  => $starting_number,
			'endValue'    => $ending_number,
			'prefix'      => $prefix,
			'suffix'      => $suffix,
			'duration'    => $duration,
			'title'       => $title,
			'numberColor' => $number_color,
			'titleColor'  => $title_color,
			'numberSize'  => $number_size,
			'titleSize'   => $title_size,
			'alignment'   => $align,
		);

		// Convert attributes to JSON
		$attrs_json = \wp_json_encode( $attrs );

		// Generate block styles
		$counter_style = sprintf(
			'text-align:%s;color:%s;font-size:%dpx',
			\esc_attr( $align ),
			\esc_attr( $number_color ),
			$number_size
		);

		$title_style = sprintf(
			'color:%s;font-size:%dpx;text-align:%s',
			\esc_attr( $title_color ),
			$title_size,
			\esc_attr( $align )
		);

		// Generate block content
		$block_content = sprintf(
			'<div class="wp-block-progressus-counter"><div class="counter-preview" style="%s" data-start="%d" data-end="%d" data-duration="%d"><span class="prefix">%s</span><span class="counter-value">%d</span><span class="suffix">%s</span></div>',
			$counter_style,
			$starting_number,
			$ending_number,
			$duration,
			\esc_html( $prefix ),
			$starting_number,
			\esc_html( $suffix )
		);

		// Add title if present
		if ( ! empty( $title ) ) {
			$block_content .= sprintf(
				'<h4 class="counter-title" style="%s">%s</h4>',
				$title_style,
				\esc_html( $title )
			);
		}

		$block_content .= '</div>';

		// Generate complete Gutenberg block
		return sprintf(
			'<!-- wp:progressus/counter %s -->%s<!-- /wp:progressus/counter -->',
			$attrs_json,
			$block_content
		);
	}
}
