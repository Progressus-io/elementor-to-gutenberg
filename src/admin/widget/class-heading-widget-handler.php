<?php
/**
 * Widget handler for Elementor heading widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor heading widget.
 */
class Heading_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle Elementor heading widget.
	 *
	 * @param array $element Elementor element data.
	 *
	 * @return string
	 */
	public function handle( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

		$title = isset( $settings['title'] ) ? (string) $settings['title'] : '';
		if ( '' === $title ) {
			return '';
		}

		// Determine heading level.
		$header_size = isset( $settings['header_size'] ) ? (string) $settings['header_size'] : 'h2';
		if ( ! preg_match( '/^h[1-6]$/', $header_size ) ) {
			$header_size = 'h2';
		}
		$level = (int) substr( $header_size, 1 );
		// Alignment.
		$align = Alignment_Helper::detect_alignment( $settings, array( 'align' ) );
		$align_payload = Alignment_Helper::build_text_alignment_payload( $align );

		// Spacing (margin / padding).
		$spacing      = Style_Parser::parse_spacing( $settings );
		$spacing_attr = isset( $spacing['attributes'] ) ? $spacing['attributes'] : array();
		$spacing_css  = isset( $spacing['style'] ) ? $spacing['style'] : '';

		// Typography (font family / size / weight / transform / line-height / letter-spacing / word-spacing).
		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();
		$typography_css  = isset( $typography['style'] ) ? $typography['style'] : '';

		// Build block attributes for Gutenberg.
		$attrs = array(
			'level'     => $level,
			'className' => 'wp-block-heading',
		);

		$heading_classes = array( 'wp-block-heading' );
		if ( ! empty( $align_payload['attributes'] ) ) {
			$attrs = array_merge( $attrs, $align_payload['attributes'] );
		}

		if ( ! empty( $align_payload['classes'] ) ) {
			$heading_classes = array_merge( $heading_classes, $align_payload['classes'] );
		}

		if ( ! empty( $spacing_attr ) ) {
			$attrs['style']['spacing'] = $spacing_attr;
		}

		if ( ! empty( $typography_attr ) ) {
			$attrs['style']['typography'] = $typography_attr;
		}

		// Inline style for the HTML tag itself (used on the frontend immediately).
		$style_parts = array();

		if ( '' !== $spacing_css ) {
			$style_parts[] = $spacing_css;
		}

		if ( '' !== $typography_css ) {
			$style_parts[] = $typography_css;
		}

		if ( '' !== $align_payload['style'] ) {
			$style_parts[] = $align_payload['style'];
		}

		$style_attr = '';
		if ( ! empty( $style_parts ) ) {
			$style_attr = ' style="' . esc_attr( implode( '', $style_parts ) ) . '"';
		}

		$inner_html = sprintf(
			'<%1$s class="%4$s"%3$s>%2$s</%1$s>',
			$header_size,
			esc_html( $title ),
			$style_attr,
			esc_attr( implode( ' ', array_unique( $heading_classes ) ) )
		);

		return Block_Builder::build( 'heading', $attrs, $inner_html );
	}
}
