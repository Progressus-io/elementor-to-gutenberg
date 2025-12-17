<?php
/**
 * Menu Widget Handler
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor nav-menu widget.
 */
class Menu_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor nav-menu widget to Gutenberg navigation block.
	 *
	 * @param array $element Elementor widget data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';

		// Build block attributes from Elementor settings.
		$attributes = array();

		// Menu reference - try to find existing menu by name or create a ref ID.
		$menu_name = $settings['menu_name'] ?? $settings['menu'] ?? '';
		if ( ! empty( $menu_name ) ) {
			// Try to get menu ID by name.
			$menu_object = wp_get_nav_menu_object( $menu_name );
			if ( $menu_object ) {
				$attributes['ref'] = $menu_object->term_id;
			}
		}

		// Text color.
		$attributes['customTextColor'] = $settings['color_menu_item'] ?? '';

		// Background color.
		$attributes['customBackgroundColor'] = $settings['_background_color'] ?? '';

		// Icon.
		$attributes['icon'] = 'menu';

		// Overlay colors.
		$attributes['overlayBackgroundColor'] = 'cyan-bluish-gray';

		// Typography.
		$style = array();
		$typography = array();
		
		$font_size = $settings['menu_typography_font_size']['size'] ?? null;
		if ( $font_size ) {
			$typography['fontSize'] = $font_size . 'px';
		}

		$font_weight = $settings['menu_typography_font_weight'] ?? null;
		if ( $font_weight ) {
			$typography['fontWeight'] = $font_weight;
		}

		$line_height = $settings['menu_typography_line_height']['size'] ?? null;
		if ( $line_height ) {
			$typography['lineHeight'] = $line_height;
		}

		$word_spacing = $settings['menu_typography_word_spacing']['size'] ?? null;
		if ( $word_spacing ) {
			$typography['wordSpacing'] = $word_spacing . 'px';
		}

		$font_family = $settings['menu_typography_font_family'] ?? null;
		if ( $font_family ) {
			$typography['fontFamily'] = $font_family;
		}

		if ( ! empty( $typography ) ) {
			$style['typography'] = $typography;
		}

		if ( ! empty( $style ) ) {
			$attributes['style'] = $style;
		}

		// Layout.
		$align_items = $settings['align_items'] ?? 'center';
		$layout_orientation = 'horizontal';

		$attributes['layout'] = array(
			'type'            => 'flex',
			'justifyContent'  => $align_items,
			'orientation'     => $layout_orientation,
		);

		// Encode attributes for the block.
		$attributes_json = wp_json_encode( $attributes );

		// Generate the complete navigation block markup (self-closing).
		$block_content = '<!-- wp:navigation ' . $attributes_json . ' /-->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content . "\n";
	}

}
