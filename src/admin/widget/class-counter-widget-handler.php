<?php
/**
 * Widget handler for Elementor counter widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Alignment_Helper;
use Progressus\BlockShift\Admin\Helper\Content_Extractor;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Helper\Widget_Style_Helper;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function serialize_block_attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor counter widget.
 */
class Counter_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor counter to Gutenberg block.
	 *
	 * The markup is what the blockshift/counter save() writes for the same
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

		$starting_number = isset( $settings['starting_number'] ) ? intval( $settings['starting_number'] ) : 0;
		$ending_number   = isset( $settings['ending_number'] ) ? intval( $settings['ending_number'] ) : 100;
		$duration        = isset( $settings['duration'] ) ? intval( $settings['duration'] ) : 2000;
		$prefix          = Content_Extractor::normalize_text( (string) ( $settings['prefix'] ?? '' ) );
		$suffix          = Content_Extractor::normalize_text( (string) ( $settings['suffix'] ?? '' ) );
		$title           = Content_Extractor::normalize_text( (string) ( $settings['title'] ?? '' ) );

		$number_color = isset( $settings['number_color'] ) ? trim( (string) $settings['number_color'] ) : '';
		$title_color  = isset( $settings['title_color'] ) ? trim( (string) $settings['title_color'] ) : '';
		$alignment    = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment', 'text_align' ) );
		$align        = '' === $alignment ? 'center' : $alignment;

		$number_typography = $settings['number_typography'] ?? array();
		$title_typography  = $settings['title_typography'] ?? array();
		$number_size       = isset( $number_typography['size'] ) ? intval( $number_typography['size'] ) : 50;
		$title_size        = isset( $title_typography['size'] ) ? intval( $title_typography['size'] ) : 20;

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

		$wrapper_class = 'wp-block-blockshift-counter';
		$extra_class   = Widget_Style_Helper::externalize( $element, (string) ( $spacing['style'] ?? '' ), (string) ( $typography['style'] ?? '' ) );
		if ( '' !== $extra_class ) {
			$attrs['className'] = $extra_class;
			$wrapper_class     .= ' ' . $extra_class;
		}

		$counter_style = Widget_Style_Helper::join(
			array(
				'text-align' => $align,
				'color'      => $number_color,
				'font-size'  => $number_size . 'px',
			)
		);

		$block_content = sprintf(
			'<div class="%s"><div class="counter-preview" style="%s" data-start="%d" data-end="%d" data-duration="%d"><span class="prefix">%s</span><span class="counter-value">%d</span><span class="suffix">%s</span></div>',
			esc_attr( $wrapper_class ),
			esc_attr( $counter_style ),
			$starting_number,
			$ending_number,
			$duration,
			esc_html( $prefix ),
			$starting_number,
			esc_html( $suffix )
		);

		if ( '' !== $title ) {
			$title_style = Widget_Style_Helper::join(
				array(
					'color'      => $title_color,
					'font-size'  => $title_size . 'px',
					'text-align' => $align,
				)
			);

			$block_content .= sprintf( '<h4 class="counter-title" style="%s">%s</h4>', esc_attr( $title_style ), esc_html( $title ) );
		}

		$block_content .= '</div>';

		return sprintf(
			'<!-- wp:blockshift/counter %s -->%s<!-- /wp:blockshift/counter -->',
			serialize_block_attributes( $attrs ),
			$block_content
		);
	}
}
