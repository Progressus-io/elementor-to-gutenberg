<?php

namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use function esc_attr;
use function esc_html;
use function wpautop;

class Tabs_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor tabs widget to Gutenberg columns + custom HTML.
	 *
	 * @param array $element Elementor widget data.
	 *
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings        = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$tabs            = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();
		$custom_css      = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$spacing         = Style_Parser::parse_spacing( $settings );
		$spacing_attr    = isset( $spacing['attributes'] ) ? $spacing['attributes'] : array();
		$spacing_css     = isset( $spacing['style'] ) ? $spacing['style'] : '';
		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();
		$typography_css  = isset( $typography['style'] ) ? $typography['style'] : '';

		if ( empty( $tabs ) ) {
			return '';
		}

		$tab_titles   = array();
		$tab_contents = array();

		$style_parts = array();

		if ( '' !== $spacing_css ) {
			$style_parts[] = $spacing_css;
		}

		if ( '' !== $typography_css ) {
			$style_parts[] = $typography_css;
		}

		$style_attr = '';
		if ( ! empty( $style_parts ) ) {
			$style_attr = ' style="' . esc_attr( implode( '', $style_parts ) ) . '"';
		}

		foreach ( $tabs as $index => $tab ) {
			$tab_id  = 'tab-' . $index;
			$title   = isset( $tab['tab_title'] ) ? $tab['tab_title'] : 'Tab ' . ( $index + 1 );
			$content = isset( $tab['tab_content'] ) ? $tab['tab_content'] : '';

			$tab_titles[] = sprintf(
				'<button class="gb-tab-title" data-tab="%s"%s>%s</button>',
				esc_attr( $tab_id ),
				$style_attr,
				esc_html( $title )
			);

			$tab_contents[] = sprintf(
				'<div class="gb-tab-content" id="%s" style="display:%s;"%s>%s</div>',
				esc_attr( $tab_id ),
				$index === 0 ? 'block' : 'none',
				$style_attr,
				wpautop( $content )
			);
		}

		// Custom HTML for tabs navigation and content
		$tabs_html = '<div class="gb-tabs">';
		$tabs_html .= '<div class="gb-tabs-nav">' . implode( '', $tab_titles ) . '</div>';
		$tabs_html .= '<div class="gb-tabs-contents">' . implode( '', $tab_contents ) . '</div>';
		$tabs_html .= '</div>';

		// Gutenberg columns block wrapper
		$block_content = '<!-- wp:columns -->';
		$block_content .= '<div class="wp-block-columns">';
		$block_content .= '<!-- wp:column {"width":"100%"} --><div class="wp-block-column" style="flex-basis:100%">';
		$block_content .= $tabs_html;
		$block_content .= '</div><!-- /wp:column -->';
		$block_content .= '</div><!-- /wp:columns -->';

		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$attrs = array();

		if ( ! empty( $spacing_attr ) ) {
			$attrs['style']['spacing'] = $spacing_attr;
		}

		if ( ! empty( $typography_attr ) ) {
			$attrs['style']['typography'] = $typography_attr;
		}

		if ( empty( $attrs ) ) {
			return $block_content;
		}

		return Block_Builder::build( 'group', $attrs, $block_content );
	}
}