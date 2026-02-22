<?php
/**
 * Widget handler for Elementor heading widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Block_Output_Builder;
use Progressus\Gutenberg\Admin\Helper\Html_Attribute_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

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

		$header_size = isset( $settings['header_size'] ) ? (string) $settings['header_size'] : 'h2';
		if ( ! preg_match( '/^h[1-6]$/', $header_size ) ) {
			$header_size = 'h2';
		}
		$level = (int) substr( $header_size, 1 );

		$align         = Alignment_Helper::detect_alignment( $settings, array( 'align' ) );
		$align_payload = Alignment_Helper::build_text_alignment_payload( $align );

		$attrs = array(
			'level'     => $level,
			'className' => 'wp-block-heading',
		);

		$element_class = Style_Parser::get_element_unique_class( $element );
		if ( '' !== $element_class ) {
			$attrs['className'] .= ' ' . $element_class;
		}

		if ( ! empty( $align_payload['attributes'] ) ) {
			$attrs = array_merge( $attrs, $align_payload['attributes'] );
		}

		$this->register_heading_external_styles( $element_class, $settings );

		return Block_Builder::build_prepared(
			'heading',
			$attrs,
			static function ( array $prepared_attrs ) use ( $header_size, $title ): string {
				$classes = array( 'wp-block-heading' );

				if ( isset( $prepared_attrs['textAlign'] ) && is_string( $prepared_attrs['textAlign'] ) && '' !== $prepared_attrs['textAlign'] ) {
					$classes[] = 'has-text-align-' . Style_Parser::clean_class( (string) $prepared_attrs['textAlign'] );
				}

				if ( isset( $prepared_attrs['className'] ) && is_string( $prepared_attrs['className'] ) && '' !== trim( $prepared_attrs['className'] ) ) {
					$parts = preg_split( '/\s+/', trim( $prepared_attrs['className'] ) );
					if ( is_array( $parts ) ) {
						foreach ( $parts as $part ) {
							$clean = Style_Parser::clean_class( (string) $part );
							if ( '' !== $clean ) {
								$classes[] = $clean;
							}
						}
					}
				}

				$inner_attrs = array(
					'class' => implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) ),
				);

				if ( isset( $prepared_attrs['anchor'] ) && is_string( $prepared_attrs['anchor'] ) && '' !== $prepared_attrs['anchor'] ) {
					$inner_attrs['id'] = (string) $prepared_attrs['anchor'];
				}

				return sprintf(
					'<%1$s %3$s>%2$s</%1$s>',
					$header_size,
					esc_html( $title ),
					Html_Attribute_Builder::build( $inner_attrs )
				);
			}
		);
	}

	/**
	 * Register per-element heading typography and color rules.
	 *
	 * @param string $element_class Element CSS class.
	 * @param array $settings Widget settings.
	 *
	 * @return void
	 */
	private function register_heading_external_styles( string $element_class, array $settings ): void {
		if ( '' === $element_class ) {
			return;
		}

		$collector = Block_Output_Builder::get_collector();
		if ( null === $collector ) {
			return;
		}

		$selector   = '.' . $element_class . '.wp-block-heading, .' . $element_class . ' .wp-block-heading';
		$typography = Style_Parser::extract_typography_css_rules( $settings );
		$base_rules = isset( $typography['base'] ) && is_array( $typography['base'] ) ? $typography['base'] : array();

		$collector->add_font_usage(
			(string) ( $typography['font_family'] ?? '' ),
			(string) ( $base_rules['font-weight'] ?? '' ),
			(string) ( $base_rules['font-style'] ?? '' )
		);

		$color_data = Style_Parser::extract_text_color_css_value( $settings, 'title_color' );
		if ( '' !== $color_data['color'] ) {
			$base_rules['color'] = $color_data['color'];
		} elseif ( false === $color_data['safe'] ) {
			$collector->record_conversion( 'heading', 'unsafe-color-omitted', array( 'value' => (string) ( $settings['title_color'] ?? '' ) ) );
		}

		if ( ! empty( $base_rules ) ) {
			$collector->register_rule( $selector, $base_rules, 'widget-heading-base' );
		}

		$tablet = isset( $typography['tablet'] ) && is_array( $typography['tablet'] ) ? $typography['tablet'] : array();
		$mobile = isset( $typography['mobile'] ) && is_array( $typography['mobile'] ) ? $typography['mobile'] : array();

		if ( ! empty( $tablet ) ) {
			$collector->register_media_rule( '(max-width: ' . (string) Style_Parser::BREAKPOINT_TABLET_MAX . 'px)', $selector, $tablet, 'widget-heading-tablet' );
		}

		if ( ! empty( $mobile ) ) {
			$collector->register_media_rule( '(max-width: ' . (string) Style_Parser::BREAKPOINT_MOBILE_MAX . 'px)', $selector, $mobile, 'widget-heading-mobile' );
		}
	}
}
