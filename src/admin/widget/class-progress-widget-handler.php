<?php
/**
 * Widget handler for Elementor progress widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Content_Extractor;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Helper\Widget_Style_Helper;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function serialize_block_attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor progress widget.
 */
class Progress_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor progress to Gutenberg block.
	 *
	 * The markup is what the blockshift/progress save() writes for the same
	 * attributes; the widget's own spacing and typography go to the stylesheet.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings   = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$spacing    = Style_Parser::parse_spacing( $settings );
		$typography = Style_Parser::parse_typography( $settings );

		$title        = Content_Extractor::normalize_text( (string) ( $settings['title'] ?? '' ) );
		$inner_text   = Content_Extractor::normalize_text( (string) ( $settings['inner_text'] ?? '' ) );
		$percentage   = isset( $settings['percent']['size'] ) ? intval( $settings['percent']['size'] ) : 50;
		$show_title   = '' !== $title && 'hide' !== ( $settings['title_display'] ?? '' );
		$show_percent = 'hide' !== ( $settings['display_percentage'] ?? '' );

		$bar_color        = isset( $settings['bar_color'] ) && '' !== $settings['bar_color'] ? (string) $settings['bar_color'] : '#61ce70';
		$background_color = isset( $settings['bar_bg_color'] ) && '' !== $settings['bar_bg_color'] ? (string) $settings['bar_bg_color'] : '#eee';
		$title_color      = isset( $settings['title_color'] ) ? trim( (string) $settings['title_color'] ) : '';
		$text_color       = isset( $settings['bar_inline_color'] ) && '' !== $settings['bar_inline_color'] ? (string) $settings['bar_inline_color'] : '#fff';

		$border_radius = isset( $settings['bar_border_radius']['size'] ) ? intval( $settings['bar_border_radius']['size'] ) : 0;
		$bar_height    = isset( $settings['bar_height']['size'] ) ? intval( $settings['bar_height']['size'] ) : 30;
		$title_size    = isset( $settings['title_typography']['size']['size'] ) ? intval( $settings['title_typography']['size']['size'] ) : 16;

		$attrs = array(
			'percentage'      => $percentage,
			'barColor'        => $bar_color,
			'backgroundColor' => $background_color,
			'titleColor'      => $title_color,
			'titleSize'       => $title_size,
			'barHeight'       => $bar_height,
			'borderRadius'    => $border_radius,
			'textColor'       => $text_color,
			'title'           => $title,
			'innerText'       => $inner_text,
			'showTitle'       => $show_title,
			'showPercentage'  => $show_percent,
		);

		$wrapper_class = 'wp-block-blockshift-progress';
		$extra_class   = Widget_Style_Helper::externalize( $element, (string) ( $spacing['style'] ?? '' ), (string) ( $typography['style'] ?? '' ) );
		if ( '' !== $extra_class ) {
			$attrs['className'] = $extra_class;
			$wrapper_class     .= ' ' . $extra_class;
		}

		$title_html = '';
		if ( $show_title ) {
			$title_style = Widget_Style_Helper::join(
				array(
					'color'         => $title_color,
					'font-size'     => $title_size . 'px',
					'margin-bottom' => '10px',
				)
			);
			$title_html  = sprintf( '<h4 style="%s">%s</h4>', esc_attr( $title_style ), esc_html( $title ) );
		}

		$bar_style = Widget_Style_Helper::join(
			array(
				'height'           => $bar_height . 'px',
				'background-color' => $background_color,
				'border-radius'    => $border_radius . 'px',
				'position'         => 'relative',
				'overflow'         => 'hidden',
			)
		);

		$fill_style = Widget_Style_Helper::join(
			array(
				'width'            => $percentage . '%',
				'height'           => '100%',
				'background-color' => $bar_color,
				'transition'       => 'width 0.3s ease-in-out',
				'position'         => 'relative',
			)
		);

		$text_style = Widget_Style_Helper::join(
			array(
				'position'  => 'absolute',
				'right'     => '10px',
				'top'       => '50%',
				'transform' => 'translateY(-50%)',
				'color'     => $text_color,
				'z-index'   => '1',
			)
		);

		$percent_html = $show_percent ? '<span class="blockshift-progress-percentage">' . $percentage . '%</span>' : '';

		return sprintf(
			'<!-- wp:blockshift/progress %1$s --><div class="%2$s"><div class="blockshift-progress-bar" style="text-align:left">%3$s<div class="blockshift-progress-bar-container" style="%4$s"><div class="blockshift-progress-bar-fill" style="%5$s"><div style="%6$s">%7$s%8$s</div></div></div></div></div><!-- /wp:blockshift/progress -->',
			serialize_block_attributes( $attrs ),
			esc_attr( $wrapper_class ),
			$title_html,
			esc_attr( $bar_style ),
			esc_attr( $fill_style ),
			esc_attr( $text_style ),
			esc_html( $inner_text ),
			$percent_html
		);
	}
}
