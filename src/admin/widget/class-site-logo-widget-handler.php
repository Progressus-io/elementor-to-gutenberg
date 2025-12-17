<?php
/**
 * Site Logo Widget Handler
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor theme-site-logo widget.
 */
class Site_Logo_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor theme-site-logo widget to Gutenberg site-logo block.
	 *
	 * @param array $element Elementor widget data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';

		// Build block attributes from Elementor settings.
		$attributes = array();

		// Width - extract from width setting.
		$width = null;
		if ( isset( $settings['width']['size'] ) ) {
			$width = (int) $settings['width']['size'];
		} elseif ( isset( $settings['width'] ) && is_numeric( $settings['width'] ) ) {
			$width = (int) $settings['width'];
		}
		
		if ( $width ) {
			$attributes['width'] = $width;
		}

		// Class name - check if image has border radius or other style that suggests rounded.
		$class_name = '';
		$border_radius = $settings['image_border_radius'] ?? array();
		if ( ! empty( $border_radius['top'] ) && (int) $border_radius['top'] > 0 ) {
			$class_name = 'is-style-rounded';
		}
		
		if ( ! empty( $class_name ) ) {
			$attributes['className'] = $class_name;
		}

		// Spacing - use Style_Parser for margin/padding.
		$spacing['attributes'] = Style_Parser::parse_spacing( $settings );
		$spacing = array();
        if ( ! empty( $spacing['attributes'] ) ) {
            $attributes['style']['spacing'] = $spacing['attributes'];
		}

		// Add background color if present.
		$bg_color = $settings['_background_color'] ?? '';
		if ( ! empty( $bg_color ) ) {
			if ( ! isset( $attributes['style'] ) ) {
				$attributes['style'] = array();
			}
			$attributes['style']['color'] = array(
				'background' => $bg_color,
			);
		}

		// Encode attributes for the block.
		$attributes_json = wp_json_encode( $attributes );

		// Generate the complete site-logo block markup (self-closing).
		$block_content = '<!-- wp:site-logo ' . $attributes_json . ' /-->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content . "\n";
	}
}
