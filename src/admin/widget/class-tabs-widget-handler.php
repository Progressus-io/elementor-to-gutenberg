<?php

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use function esc_attr;
use function wp_strip_all_tags;

class Tabs_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$tabs     = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();

		if ( empty( $tabs ) ) {
			return '';
		}

		// Reuse Nested handler output format by proxying through the same block.
		$nested = new Nested_Tabs_Widget_Handler();
		$fake   = array(
			'settings' => $settings,
			'elements' => array(),
		);

		foreach ( $tabs as $tab ) {
			$title              = isset( $tab['tab_title'] ) ? (string) $tab['tab_title'] : '';
			$content            = isset( $tab['tab_content'] ) ? (string) $tab['tab_content'] : '';
			$fake['elements'][] = array(
				'settings' => array(
					'_title'  => wp_strip_all_tags( $title ),
					'content' => $content,
				),
			);
		}

		return $nested->handle( $fake );
	}
}