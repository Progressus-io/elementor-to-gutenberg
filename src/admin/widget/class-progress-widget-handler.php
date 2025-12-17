<?php
/**
 * Widget handler for Elementor progress widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor progress widget.
 */
class Progress_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor progress to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings        = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$spacing         = Style_Parser::parse_spacing( $settings );
		$spacing_attr    = isset( $spacing['attributes'] ) ? $spacing['attributes'] : array();
		$spacing_css     = isset( $spacing['style'] ) ? $spacing['style'] : '';
		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();
		$typography_css  = isset( $typography['style'] ) ? $typography['style'] : '';

		// Extract progress settings.
		// Extract base settings
		$title        = isset( $settings['title'] ) ? $settings['title'] : '';
		$show_title   = ! isset( $settings['title_display'] ) || empty( $settings['title_display'] );
		$inner_text   = isset( $settings['inner_text'] ) ? $settings['inner_text'] : '';
		$percentage   = isset( $settings['percent']['size'] ) ? intval( $settings['percent']['size'] ) : 50;
		$show_percent = ! isset( $settings['display_percentage'] ) || empty( $settings['display_percentage'] );

		// Extract styles
		$bar_color        = isset( $settings['bar_color'] ) ? $settings['bar_color'] : '#61ce70';
		$background_color = isset( $settings['bar_bg_color'] ) ? $settings['bar_bg_color'] : '#eee';
		$title_color      = isset( $settings['title_color'] ) ? $settings['title_color'] : '';
		$text_color       = isset( $settings['bar_inline_color'] ) ? $settings['bar_inline_color'] : '#fff';

		// Extract dimensions
		$border_radius = isset( $settings['bar_border_radius']['size'] )
			? intval( $settings['bar_border_radius']['size'] )
			: 0;
		$bar_height    = isset( $settings['bar_height']['size'] )
			? intval( $settings['bar_height']['size'] )
			: 30;

		// Typography settings
		$title_typography = $settings['title_typography'] ?? array();
		$title_size       = isset( $title_typography['size']['size'] )
			? intval( $title_typography['size']['size'] )
			: 16;

		// Prepare block attributes
		$attrs = array(
			'percentage'      => $percentage,
			'barColor'        => $bar_color,
			'backgroundColor' => $background_color,
			'titleColor'      => $title_color,
			'barHeight'       => $bar_height,
			'borderRadius'    => $border_radius,
			'textColor'       => $text_color,
			'title'           => $title,
			'innerText'       => $inner_text,
		);

		if ( ! empty( $spacing_attr ) ) {
			$attrs['style']['spacing'] = $spacing_attr;
		}

		if ( ! empty( $typography_attr ) ) {
			$attrs['style']['typography'] = $typography_attr;
		}

		// Convert attributes to JSON for block serialization
		$attrs_json = wp_json_encode( $attrs );

		// Generate styles
		$wrapper_style = array();

		if ( '' !== $spacing_css ) {
			$wrapper_style[] = $spacing_css;
		}

		if ( '' !== $typography_css ) {
			$wrapper_style[] = $typography_css;
		}

		$wrapper_style = implode( '', array_filter( $wrapper_style ) );

		$title_style = array();

		if ( '' !== $spacing_css ) {
			$title_style[] = $spacing_css;
		}

		if ( '' !== $typography_css ) {
			$title_style[] = $typography_css;
		}

		if ( $title_color ) {
			$title_style[] = sprintf( 'color:%s', htmlspecialchars( $title_color, ENT_QUOTES, 'UTF-8' ) );
		}
		if ( $title_size ) {
			$title_style[] = sprintf( 'font-size:%dpx', $title_size );
		}
		$title_style[] = 'margin-bottom:10px';
		$title_style   = implode( ';', array_filter( $title_style ) );

		$bar_style = sprintf(
			'background-color:%s;height:%dpx;border-radius:%dpx;position:relative;overflow:hidden',
			htmlspecialchars( $background_color, ENT_QUOTES, 'UTF-8' ),
			$bar_height,
			$border_radius
		);

		$progress_style = sprintf(
			'background-color:%s;width:%d%%;height:%d%%;position:relative;transition:width 0.3s ease-in-out',
			htmlspecialchars( $bar_color, ENT_QUOTES, 'UTF-8' ),
			$percentage,
			100
		);

		$text_style_parts = array();

		if ( '' !== $spacing_css ) {
			$text_style_parts[] = $spacing_css;
		}

		if ( '' !== $typography_css ) {
			$text_style_parts[] = $typography_css;
		}

		$text_style_parts[] = 'position:absolute;';
		$text_style_parts[] = 'right:10px;';
		$text_style_parts[] = 'top:50%;';
		$text_style_parts[] = 'transform:translateY(-50%);';
		$text_style_parts[] = 'z-index:1;';

		if ( $text_color ) {
			$text_style_parts[] = sprintf( 'color:%s;', htmlspecialchars( $text_color, ENT_QUOTES, 'UTF-8' ) );
		}

		$text_style = implode( '', $text_style_parts );

		// Generate block content
		return sprintf(
			'<!-- wp:progressus/progress %s --><div class="wp-block-progressus-progress"%s><div class="progressus-progress-bar" style="text-align:left">%s<div class="progressus-progress-bar-container"%s><div class="progressus-progress-bar-fill"%s><div %s>%s<span class="progressus-progress-percentage">%s</span></div></div></div></div></div><!-- /wp:progressus/progress -->',
			$attrs_json ?: '{}',
			$wrapper_style ? sprintf( ' style="%s"', $wrapper_style ) : '',
			$show_title && $title ? sprintf(
				'<h4 %s>%s</h4>',
				$title_style ? sprintf( ' style="%s"', $title_style ) : '',
				htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' )
			) : '',
			sprintf( ' style="%s"', $bar_style ),
			sprintf( ' style="%s"', $progress_style ),
			$text_style ? sprintf( ' style="%s"', $text_style ) : '',
			$inner_text ? htmlspecialchars( $inner_text, ENT_QUOTES, 'UTF-8' ) : '',
			$show_percent ? $percentage . '%' : ''
		);
	}
}
