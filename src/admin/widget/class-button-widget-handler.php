<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Html_Attribute_Builder;
use Progressus\Gutenberg\Admin\Helper\Icon_Parser;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Helper\Style_Normalizer;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 */
	public function handle( array $element ): string {
		$settings        = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$text            = isset( $settings['text'] ) ? trim( (string) $settings['text'] ) : '';
		$icon_data       = Icon_Parser::parse_selected_icon( $settings['selected_icon'] ?? null );
		$link_data       = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
		$url             = isset( $link_data['url'] ) ? esc_url( (string) $link_data['url'] ) : '';
		$custom_css      = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_raw      = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
		$color_map       = Style_Parser::parse_button_styles( $settings );
		$computed_styles = Style_Parser::get_computed_styles( $element );

		$spacing      = Style_Parser::parse_spacing( $settings );
		$spacing_attr = isset( $spacing['attributes'] ) ? $spacing['attributes'] : array();

		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) ? $typography['attributes'] : array();

		$border      = Style_Parser::parse_border( $settings );
		$border_attr = isset( $border['attributes'] ) ? $border['attributes'] : array();

		$alignment      = Alignment_Helper::detect_alignment( $settings, array(
			'button_align',
			'align',
			'alignment'
		) );
		$buttons_layout = array();
		if ( '' !== $alignment ) {
			$buttons_layout = array(
				'type'           => 'flex',
				'justifyContent' => Alignment_Helper::map_justify_content( $alignment ),
			);
		}
		$buttons_attrs = array();

		if ( '' !== $alignment ) {
			$justify_class = $alignment;

			// normalize common values
			if ( 'middle' === $justify_class ) {
				$justify_class = 'center';
			}

			if ( 'center' === $justify_class || 'left' === $justify_class || 'right' === $justify_class || 'space-between' === $justify_class ) {
				$buttons_attrs['className'] = 'is-layout-flex is-content-justification-' . $justify_class;
			}
		}

		if ( '' === $text ) {
			$text = isset( $link_data['custom_text'] ) ? trim( (string) $link_data['custom_text'] ) : '';
		}

		if ( '' === $text && '' === $url ) {
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

		$button_attributes = array();

		if ( ! empty( $color_map['attributes'] ) && is_array( $color_map['attributes'] ) ) {
			$button_attributes = array_replace_recursive( $button_attributes, $color_map['attributes'] );
		}

		if ( ! empty( $typography_attr ) ) {
			$button_attributes['style']['typography'] = isset( $button_attributes['style']['typography'] ) && is_array( $button_attributes['style']['typography'] )
				? array_replace_recursive( $button_attributes['style']['typography'], $typography_attr )
				: $typography_attr;
		}

		if ( ! empty( $spacing_attr ) ) {
			$button_attributes['style']['spacing'] = isset( $button_attributes['style']['spacing'] ) && is_array( $button_attributes['style']['spacing'] )
				? array_replace_recursive( $button_attributes['style']['spacing'], $spacing_attr )
				: $spacing_attr;
		}

		if ( ! empty( $border_attr ) ) {
			$button_attributes['style']['border'] = isset( $button_attributes['style']['border'] ) && is_array( $button_attributes['style']['border'] )
				? array_replace_recursive( $button_attributes['style']['border'], $border_attr )
				: $border_attr;
		}

		if ( $this->should_drop_background( $settings, $computed_styles, $button_attributes ) ) {
			if ( isset( $button_attributes['style']['color']['background'] ) ) {
				$button_attributes['style']['color']['background'] = 'transparent';
			}
		}

		if ( empty( $button_attributes['style']['spacing']['padding'] ) ) {
			$button_attributes['style']['spacing']['padding'] = array(
				'top'    => '12px',
				'right'  => '24px',
				'bottom' => '12px',
				'left'   => '24px',
			);
		}

		if ( empty( $button_attributes['style']['border']['radius'] ) ) {
			$button_attributes['style']['border']['radius'] = array(
				'topLeft'     => '4px',
				'topRight'    => '4px',
				'bottomRight' => '4px',
				'bottomLeft'  => '4px',
			);
		}

		$button_attributes = Style_Normalizer::normalize_attributes( 'button', $button_attributes );

		if ( ! empty( $custom_classes ) ) {
			$existing_classnames = array();
			if ( ! empty( $button_attributes['className'] ) ) {
				$existing_classnames = preg_split( '/\s+/', (string) $button_attributes['className'] );
			}

			$combined_classes = array_filter( array_unique( array_merge( $existing_classnames, $custom_classes ) ) );
			if ( ! empty( $combined_classes ) ) {
				$button_attributes['className'] = implode( ' ', $combined_classes );
			}
		}


		if ( '' !== $url ) {
			$button_attributes['url'] = $url;
		}

		$rel_tokens = array();
		if ( ! empty( $link_data['is_external'] ) ) {
			$button_attributes['linkTarget'] = '_blank';
			$rel_tokens[]                    = 'noopener';
		}

		if ( ! empty( $link_data['nofollow'] ) ) {
			$rel_tokens[] = 'nofollow';
		}

		if ( ! empty( $rel_tokens ) ) {
			$button_attributes['rel'] = implode( ' ', array_unique( $rel_tokens ) );
		}

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}


		$icon_html = '';
		if ( '' !== $icon_data['class_name'] ) {
			$icon_html = '<span class="etg-button-icon ' . esc_attr( $icon_data['class_name'] ) . '" aria-hidden="true"></span>';
			Style_Parser::save_custom_css( '/* icon class captured for ETG_EXTRA_ATTRS_MAP_V1 */' );
		} elseif ( '' !== $icon_data['url'] ) {
			$icon_html = '<span class="etg-button-icon"><img src="' . esc_url( $icon_data['url'] ) . '" alt="" aria-hidden="true" /></span>';
		}


		// Normalize typography for core/button to avoid Gutenberg dropping/reshuffling values.
		if ( isset( $button_attributes['style']['typography'] ) && is_array( $button_attributes['style']['typography'] ) ) {
			$typo = $button_attributes['style']['typography'];

			if ( empty( $typo['fontStyle'] ) ) {
				$typo['fontStyle'] = 'normal';
			}

			if ( isset( $typo['fontFamily'] ) ) {
				$family = trim( (string) $typo['fontFamily'] );
				if ( '' !== $family && 0 !== strpos( $family, 'var:' ) && 0 !== strpos( $family, 'var(' ) ) {
					unset( $typo['fontFamily'] );
				}
			}

			foreach ( array( 'letterSpacing', 'wordSpacing' ) as $spacing_key ) {
				if ( isset( $typo[ $spacing_key ] ) ) {
					$val = strtolower( trim( (string) $typo[ $spacing_key ] ) );
					if ( '0' === $val || '0px' === $val || '0em' === $val || '0rem' === $val || '0%' === $val ) {
						unset( $typo[ $spacing_key ] );
					}
				}
			}

			if ( empty( $typo ) ) {
				unset( $button_attributes['style']['typography'] );
			} else {
				$button_attributes['style']['typography'] = $typo;
			}
		}

		$button_block = Block_Builder::build_prepared(
			'button',
			$button_attributes,
			function ( array $prepared_attrs ) use ( $icon_html, $text ): string {
				$anchor_attrs = array(
					'class' => Block_Builder::build_button_link_class( $prepared_attrs ),
				);

				$style = Block_Builder::build_button_link_style( $prepared_attrs );
				if ( '' !== $style ) {
					$anchor_attrs['style'] = $style;
				}

				$href = isset( $prepared_attrs['url'] ) ? (string) $prepared_attrs['url'] : '';
				if ( '' !== $href ) {
					$anchor_attrs['href'] = esc_url( $href );
				}

				if ( ! empty( $prepared_attrs['linkTarget'] ) ) {
					$anchor_attrs['target'] = (string) $prepared_attrs['linkTarget'];
				}

				if ( ! empty( $prepared_attrs['rel'] ) ) {
					$anchor_attrs['rel'] = (string) $prepared_attrs['rel'];
				}

				return sprintf(
					'<a %s>%s%s</a>',
					Html_Attribute_Builder::build( $anchor_attrs ),
					'' !== $icon_html ? $icon_html : '',
					wp_strip_all_tags( $text )
				);
			}
		);

		return Block_Builder::build(
			'buttons',
			$buttons_attrs,
			$button_block
		);
	}

	private function should_drop_background( array $settings, array $computed_styles, array $attrs ): bool {
		foreach (
			array(
				'button_background_background',
				'_button_background_background',
				'background_background',
				'_background_background',
			) as $key
		) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$type = strtolower( trim( (string) $settings[ $key ] ) );
			if ( 'none' === $type ) {
				return true;
			}
		}

		foreach ( array( 'background-color', 'background' ) as $prop ) {
			if ( empty( $computed_styles[ $prop ] ) ) {
				continue;
			}

			$val = trim( (string) $computed_styles[ $prop ] );
			if ( '' === $val ) {
				continue;
			}

			if ( 'none' === strtolower( $val ) || $this->is_transparent_css_value( $val ) ) {
				return true;
			}
		}

		$text = isset( $attrs['style']['color']['text'] ) ? strtolower( trim( (string) $attrs['style']['color']['text'] ) ) : '';
		$bg   = isset( $attrs['style']['color']['background'] ) ? strtolower( trim( (string) $attrs['style']['color']['background'] ) ) : '';

		if ( '' !== $text && '' !== $bg && $text === $bg ) {
			return true;
		}

		$has_widget_bg = false;

		foreach (
			array(
				'button_background_color',
				'background_color',
			) as $bg_key
		) {
			if ( isset( $settings[ $bg_key ] ) && '' !== trim( (string) $settings[ $bg_key ] ) ) {
				$has_widget_bg = true;
				break;
			}
		}

		if ( ! $has_widget_bg && isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) {
			foreach (
				array(
					'button_background_color',
					'background_color',
				) as $bg_key
			) {
				if ( ! empty( $settings['__globals__'][ $bg_key ] ) ) {
					$has_widget_bg = true;
					break;
				}
			}
		}

		$computed_bg = '';
		foreach ( array( 'background-color', 'background' ) as $prop ) {
			if ( ! empty( $computed_styles[ $prop ] ) ) {
				$computed_bg = trim( (string) $computed_styles[ $prop ] );
				break;
			}
		}

		$attrs_bg = isset( $attrs['style']['color']['background'] ) ? trim( (string) $attrs['style']['color']['background'] ) : '';

		if ( '' !== $attrs_bg && ! $has_widget_bg && ( '' === $computed_bg || $this->is_transparent_css_value( $computed_bg ) ) ) {
			return true;
		}

		return false;
	}

	private function is_transparent_css_value( string $value ): bool {
		$v = strtolower( trim( $value ) );
		if ( '' === $v ) {
			return false;
		}

		if ( 'transparent' === $v ) {
			return true;
		}

		if ( false !== strpos( $v, 'transparent' ) ) {
			return true;
		}

		if ( preg_match( '/rgba\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*,\s*(0|0\.0+)\s*\)/', $v ) ) {
			return true;
		}

		if ( preg_match( '/hsla\(\s*[\d.]+\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*,\s*(0|0\.0+)\s*\)/', $v ) ) {
			return true;
		}

		if ( preg_match( '/#([0-9a-f]{8})/i', $v, $m ) ) {
			return '00' === strtolower( substr( $m[1], 6, 2 ) );
		}

		if ( preg_match( '/#([0-9a-f]{4})/i', $v, $m ) ) {
			return '0' === strtolower( substr( $m[1], 3, 1 ) );
		}

		return false;
	}

}
