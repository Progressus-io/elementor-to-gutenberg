<?php
/**
 * Widget handler for Elementor icon list widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function esc_url;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor icon list widget.
 */
class Icon_List_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor icon list widget.
	 *
	 * @param array $element Elementor widget data.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$items      = is_array( $settings['icon_list'] ?? null ) ? $settings['icon_list'] : array();
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_id  = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_raw = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$text_color = isset( $settings['text_color'] ) ? strtolower( (string) $settings['text_color'] ) : '';

		if ( empty( $items ) ) {
			return '';
		}

		$custom_classes = array();
		if ( '' !== $custom_raw ) {
			foreach ( preg_split( '/\s+/', $custom_raw ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$custom_classes[] = $clean;
			}
		}

		$attributes = array();
		if ( ! empty( $custom_classes ) ) {
			$attributes['className'] = implode( ' ', array_unique( $custom_classes ) );
		}

		$markup_classes = $custom_classes;
		$style_color    = '';

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$attributes['textColor'] = $text_color;
				$markup_classes[]        = 'has-text-color';
				$markup_classes[]        = 'has-' . Style_Parser::clean_class( $text_color ) . '-color';
			} elseif ( $this->is_hex_color( $text_color ) ) {
				$attributes['style']['color']['text'] = $text_color;
				$markup_classes[]                     = 'has-text-color';
				$style_color                          = 'color:' . $text_color . ';';
			}
		}

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$list_items = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$text = isset( $item['text'] ) ? (string) $item['text'] : (string) ( $item['title'] ?? '' );
			if ( '' === trim( $text ) ) {
				continue;
			}

			$url      = is_array( $item['link'] ?? null ) ? (string) ( $item['link']['url'] ?? '' ) : '';
			$icon_val = '';

			if ( isset( $item['selected_icon']['value'] ) ) {
				$icon_val = (string) $item['selected_icon']['value'];
			} elseif ( isset( $item['icon'] ) ) {
				$icon_val = (string) $item['icon'];
			}

			$icon_markup = '';
			if ( '' !== $icon_val ) {
				$icon_classes = array( 'icon-list-icon' );
				foreach ( preg_split( '/\s+/', $icon_val ) as $icon_class ) {
					$icon_class = Style_Parser::clean_class( $icon_class );
					if ( '' !== $icon_class ) {
						$icon_classes[] = $icon_class;
					}
				}
				$icon_markup = sprintf( '<span class="%s" aria-hidden="true"></span>', esc_attr( implode( ' ', array_unique( $icon_classes ) ) ) );
			}

			$text_markup = esc_html( $text );
			if ( '' !== $url ) {
				$text_markup = sprintf( '<a href="%s">%s</a>', esc_url( $url ), $text_markup );
			}

			$list_items[] = sprintf( '<li>%s%s</li>', $icon_markup, $text_markup );
		}

		if ( empty( $list_items ) ) {
			return '';
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		$list_attrs = '';
		if ( '' !== $custom_id ) {
			$list_attrs .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		if ( ! empty( $markup_classes ) ) {
			$list_attrs .= ' class="' . esc_attr( implode( ' ', array_unique( $markup_classes ) ) ) . '"';
		}

		if ( '' !== $style_color ) {
			$list_attrs .= ' style="' . esc_attr( $style_color ) . '"';
		}

		$list_markup = sprintf( '<ul%s>%s</ul>', $list_attrs, implode( '', $list_items ) );

		return Block_Builder::build( 'list', $attributes, $list_markup );
	}

	/**
	 * Check if a color value is a preset slug.
	 *
	 * @param string $color Color value.
	 */
	private function is_preset_color_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' ) && false === strpos( $color, 'rgb' );
	}

	private function is_hex_color( string $color ): bool {
		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
	}
}
