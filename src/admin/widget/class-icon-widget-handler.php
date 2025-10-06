<?php
/**
 * Widget handler for Elementor icon widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Converts Elementor Icon widget into Gutenberg `gutenberg/icon` block.
 */
class Icon_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor icon to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$custom_class  = $settings['_css_classes'] ?? '';
		$custom_id     = $settings['_element_id'] ?? '';
		$custom_css    = $settings['custom_css'] ?? '';

		// Extract icon details.
		$icon_value   = '';
		$icon_library = 'fa-solid';

		if ( isset( $settings['selected_icon']['value'] ) ) {
			$icon_value   = $settings['selected_icon']['value'];
			$icon_library = $settings['selected_icon']['library'] ?? 'fa-solid';
		} elseif ( isset( $settings['icon'] ) ) {
			$icon_value = $settings['icon'];
		}
		$icon = explode( ' ', $icon_value )[1] ?? $icon_value;

		// Icon appearance.
		$size             = isset( $settings['size'] ) ? intval( $settings['size']['size'] ) : 24;
		$color            = $settings['primary_color'] ?? '#333333';
		$background_color = $settings['background_color'] ?? '';
		$border_radius    = isset( $settings['border_radius'] ) ? intval( $settings['border_radius'] ) : 0;
		$padding          = isset( $settings['padding'] ) ? intval( $settings['padding'] ) : 0;
		$hover_color      = $settings['hover_color'] ?? '';
		$hover_effect     = $settings['hover_effect'] ?? 'scale-up';
		$link             = $settings['link']['url'] ?? '';
		$link_target      = ! empty( $settings['link']['is_external'] );

		// Determine FA icon style.
		$icon_style_class = 'fas';
		if ( 'fa-regular' === $icon_library ) {
			$icon_style_class = 'far';
		} elseif ( 'fa-brands' === $icon_library ) {
			$icon_style_class = 'fab';
		}

		// Prepare block attributes.
		$attrs = array(
			'icon'           => $icon,
			'size'           => $size,
			'color'          => $color,
			'backgroundColor'=> $background_color,
			'borderRadius'   => $border_radius,
			'padding'        => $padding,
			'hoverColor'     => $hover_color,
			'hoverEffect'    => $hover_effect,
			'link'           => $link,
			'linkTarget'     => $link_target,
			'className'      => $custom_class,
			'anchor'         => $custom_id,
		);

		$attrs = array_filter(
			$attrs,
			static function ( $value ) {
				return ! ( $value === null || $value === '' );
			}
		);

		$attrs_json = wp_json_encode( $attrs );

		// Build inline style.
		$style = sprintf(
			'font-size:%dpx;color:%s;%s%sborder-radius:%dpx;padding:%dpx;display:inline-block;line-height:1;transition:all 0.3s ease;width:auto;height:auto',
			$size,
			esc_attr( $color ),
			$background_color ? 'background-color:' . esc_attr( $background_color ) . ';' : 'background-color:transparent;',
			$hover_color ? '--fontawesome-icon-hover-color:' . esc_attr( $hover_color ) . ';' : '',
			$border_radius,
			$padding
		);

		// Build icon HTML.
		$icon_html = sprintf(
			'<i class="%1$s fontawesome-icon-hover-%2$s" style="%3$s" aria-label="" aria-hidden="true" data-hover-effect="%2$s" data-icon="%4$s" data-icon-style="%5$s"></i>',
			esc_attr( $icon_value ),
			esc_attr( $hover_effect ),
			esc_attr( $style ),
			esc_attr( $icon ),
			esc_attr( $icon_style_class ),
		);

		// Wrap with link if set.
		if ( $link ) {
			$target_attr = $link_target ? ' target="_blank" rel="noopener noreferrer"' : '';
			$icon_html   = sprintf(
				'<a href="%s"%s aria-label="">%s</a>',
				esc_url( $link ),
				$target_attr,
				$icon_html
			);
		}

		// Wrap icon block container.
		$block_inner_html = sprintf(
			'<div class="wp-block-gutenberg-icon fontawesome-icon-align-left" style="text-align:left">%s</div>',
			$icon_html
		);

		// Build Gutenberg block comment structure.
		$block_content = sprintf(
			"<!-- wp:gutenberg/icon %s -->\n%s\n<!-- /wp:gutenberg/icon -->\n",
			$attrs_json,
			$block_inner_html
		);

		// Save any custom CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}
}
