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
use function sanitize_html_class;

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

		if ( empty( $items ) ) {
			return '';
		}

		$typography = Style_Parser::parse_typography( $settings );
		$spacing    = Style_Parser::parse_spacing( $settings );
		$border     = Style_Parser::parse_border( $settings );

		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$text_color   = isset( $settings['text_color'] ) ? strtolower( (string) $settings['text_color'] ) : '';

		$attributes = array();
		if ( ! empty( $typography['attributes'] ) ) {
			$attributes['style']['typography'] = $typography['attributes'];
		}
		if ( ! empty( $spacing['attributes'] ) ) {
			$attributes['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$attributes['style']['border'] = $border['attributes'];
		}

		$inline_style_parts = array( $typography['style'], $spacing['style'], $border['style'] );
		$class_names        = array();
		$list_classes       = array( 'wp-block-list' );

		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$class = trim( $class );
				if ( '' === $class ) {
					continue;
				}

				$sanitized      = sanitize_html_class( $class );
				$class_names[]  = $sanitized;
				$list_classes[] = $sanitized;
			}
		}

		if ( '' !== $text_color ) {
			if ( $this->is_preset_color_slug( $text_color ) ) {
				$attributes['textColor'] = $text_color;
				$inline_style_parts[]    = 'color:var(--wp--preset--color--' . sanitize_html_class( $text_color ) . ');';
			} else {
				$attributes['style']['color']['text'] = $text_color;
				$inline_style_parts[]                 = 'color:' . $text_color . ';';
			}

			$class_names[]  = 'has-text-color';
			$list_classes[] = 'has-text-color';
		}

		if ( ! empty( $class_names ) ) {
			$attributes['className'] = implode( ' ', array_unique( $class_names ) );
		}

		if ( '' !== $custom_id ) {
			$attributes['anchor'] = $custom_id;
		}

		$inline_style = implode( '', array_filter( $inline_style_parts ) );

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
					$icon_class = trim( $icon_class );
					if ( '' !== $icon_class ) {
						$icon_classes[] = sanitize_html_class( $icon_class );
					}
				}

				$icon_markup = sprintf( '<span class="%s" aria-hidden="true"></span>', esc_attr( implode( ' ', $icon_classes ) ) );
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

		$list_attr = '';
		if ( '' !== $custom_id ) {
			$list_attr .= ' id="' . esc_attr( $custom_id ) . '"';
		}

		$list_attr .= ' class="' . esc_attr( implode( ' ', array_unique( $list_classes ) ) ) . '"';

		if ( '' !== $inline_style ) {
			$list_attr .= ' style="' . esc_attr( $inline_style ) . '"';
		}

		$list_markup = sprintf( '<ul%s>%s</ul>', $list_attr, implode( '', $list_items ) );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

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
}
