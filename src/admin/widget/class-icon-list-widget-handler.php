<?php
/**
 * Widget handler for Elementor icon list widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;

use function esc_attr;
use function esc_html;
use function esc_url;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon list widget.
 */
class Icon_List_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor icon list to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$custom_class = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

		// Icon list items can be stored under different keys.
		$items = array();
		if ( isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ) {
			$items = $settings['icon_list'];
		} elseif ( isset( $settings['icon_list_items'] ) && is_array( $settings['icon_list_items'] ) ) {
			$items = $settings['icon_list_items'];
		}

		if ( empty( $items ) ) {
			return '';
		}

		$attrs              = array();
		$classes            = array();
		$resolved_color     = null;
		$anchor_color_style = '';
		$parts              = array();

		// ----- COLOR -----
		$color_sources = array();
		if ( isset( $settings['text_color'] ) ) {
			$color_sources[] = $settings['text_color'];
		}
		if (
			isset( $settings['__globals__'] ) &&
			is_array( $settings['__globals__'] ) &&
			isset( $settings['__globals__']['text_color'] )
		) {
			$color_sources[] = $settings['__globals__']['text_color'];
		}

		foreach ( $color_sources as $source ) {
			$data = Style_Parser::resolve_elementor_color_reference( $source );
			if ( '' !== $data['slug'] || '' !== $data['color'] ) {
				$resolved_color = $data;
				break;
			}
		}

		if ( null !== $resolved_color ) {
			if ( '' !== $resolved_color['slug'] ) {
				$attrs['textColor'] = $resolved_color['slug'];
				$classes[]          = 'has-text-color';
				$classes[]          = 'has-' . Style_Parser::clean_class( $resolved_color['slug'] ) . '-color';
			} elseif ( '' !== $resolved_color['color'] ) {
				$attrs['style']['color']['text'] = $resolved_color['color'];
				$classes[]                       = 'has-text-color';
			}

			if ( '' !== $resolved_color['color'] ) {
				$anchor_color_style = ' style="color:' . esc_attr( $resolved_color['color'] ) . ';"';
			}
		}

		$typography_settings = array();
		$map_keys            = array(
			'icon_typography_font_family'       => 'typography_font_family',
			'icon_typography_text_transform'    => 'typography_text_transform',
			'icon_typography_font_style'        => 'typography_font_style',
			'icon_typography_font_weight'       => 'typography_font_weight',
			'icon_typography_text_decoration'   => 'typography_text_decoration',
			'icon_typography_font_size'         => 'typography_font_size',
			'icon_typography_line_height'       => 'typography_line_height',
			'icon_typography_letter_spacing'    => 'typography_letter_spacing',
			'icon_typography_word_spacing'      => 'typography_word_spacing',
			'icon_typography_typography'        => 'typography_typography',
			'icon_typography_global_typography' => 'typography_global_typography',
		);

		foreach ( $map_keys as $source => $target ) {
			if ( isset( $settings[ $source ] ) ) {
				$typography_settings[ $target ] = $settings[ $source ];
			}
		}

		if (
			isset( $settings['__globals__'] ) &&
			is_array( $settings['__globals__'] ) &&
			! empty( $settings['__globals__']['icon_typography_typography'] )
		) {
			$typography_settings['__globals__']['typography_typography']
				= $settings['__globals__']['icon_typography_typography'];
		}

		if ( ! empty( $typography_settings ) ) {
			$typo = Style_Parser::parse_typography( $typography_settings );

			if ( ! empty( $typo['attributes'] ) ) {
				// Gutenberg expects style.typography.{fontFamily,fontSize,...}.
				$attrs['style']['typography'] = $typo['attributes'];
			}
		}

		$align = Alignment_Helper::detect_alignment( $settings, array( 'align', 'alignment' ) );
		if ( '' !== $align ) {
			$attrs['textAlign'] = $align;

			switch ( $align ) {
				case 'center':
					$classes[] = 'has-text-align-center';
					break;
				case 'right':
					$classes[] = 'has-text-align-right';
					break;
				case 'left':
				default:
					$classes[] = 'has-text-align-left';
					break;
			}
		}

		if ( '' !== $custom_class ) {
			$classes[] = $custom_class;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label_raw = isset( $item['text'] ) ? trim( (string) $item['text'] ) : '';
			if ( '' === $label_raw ) {
				continue;
			}

			// Match the design: uppercase social names.
			$label = strtoupper( $label_raw );

			$url = '';
			if (
				isset( $item['link'] ) &&
				is_array( $item['link'] ) &&
				! empty( $item['link']['url'] )
			) {
				$url = (string) $item['link']['url'];
			}

			if ( '' !== $url ) {
				$parts[] = sprintf(
					'<a href="%s"%s>%s</a>',
					esc_url( $url ),
					$anchor_color_style,
					esc_html( $label )
				);
			} else {
				$parts[] = esc_html( $label );
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		// Join items with non-breaking spaces so they stay visually grouped inline.
		$html_text = implode( '&nbsp;&nbsp;&nbsp;', $parts );

		// Save custom CSS if present.
		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$attrs_json = '';
		if ( ! empty( $attrs ) ) {
			$attrs_json = ' ' . wp_json_encode( $attrs );
		}

		$class_attr = '';
		if ( ! empty( $classes ) ) {
			$class_attr = ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}

		return sprintf(
			'<!-- wp:paragraph%s --><p%s>%s</p><!-- /wp:paragraph -->' . "\n",
			$attrs_json,
			$class_attr,
			$html_text
		);
	}
}
