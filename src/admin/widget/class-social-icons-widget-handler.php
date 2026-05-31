<?php
/**
 * Widget handler for Elementor social icons widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function sanitize_html_class;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor social icons widget.
 */
class Social_Icons_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor social icons widget.
	 *
	 * @param array $element Elementor widget data.
	 */
	public function handle( array $element ): string {
		$settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$icons      = is_array( $settings['social_icon_list'] ?? null ) ? $settings['social_icon_list'] : array();
		$custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

		if ( empty( $icons ) ) {
			return '';
		}

		$attributes   = array();
		$class_names  = array();
		$list_classes = array( 'wp-block-social-links' );

		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
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

		$icon_color = isset( $settings['icon_color'] ) ? strtolower( (string) $settings['icon_color'] ) : '';
		if ( '' !== $icon_color ) {
			if ( $this->is_preset_slug( $icon_color ) ) {
				$attributes['iconColor'] = $icon_color;
			} else {
				$attributes['customIconColor'] = $icon_color;
			}
			$class_names[]  = 'has-icon-color';
			$list_classes[] = 'has-icon-color';
		}

		$icon_background = isset( $settings['icon_background_color'] ) ? strtolower( (string) $settings['icon_background_color'] ) : '';
		if ( '' !== $icon_background ) {
			if ( $this->is_preset_slug( $icon_background ) ) {
				$attributes['iconBackgroundColor'] = $icon_background;
			} else {
				$attributes['customIconBackgroundColor'] = $icon_background;
			}
			$class_names[]  = 'has-icon-background-color';
			$list_classes[] = 'has-icon-background-color';
		}

		$open_new_tab = false;
		$links_markup = '';

		foreach ( $icons as $icon ) {
			if ( ! is_array( $icon ) ) {
				continue;
			}

			$url = is_array( $icon['link'] ?? null ) ? (string) ( $icon['link']['url'] ?? '' ) : '';
			if ( '' === $url ) {
				continue;
			}

			if ( ! empty( $icon['link']['is_external'] ) ) {
				$open_new_tab = true;
			}

			$service = $this->detect_service( $icon );

			$link_attrs = array( 'url' => $url );
			if ( '' !== $service ) {
				$link_attrs['service'] = $service;
			}

			$links_markup .= sprintf(
				'<!-- wp:social-link%s /-->',
				empty( $link_attrs ) ? '' : ' ' . wp_json_encode( $link_attrs )
			);
		}

		if ( '' === $links_markup ) {
			return '';
		}

		if ( $open_new_tab ) {
			$attributes['openInNewTab'] = true;
		}

		if ( ! empty( $class_names ) ) {
			$attributes['className'] = implode( ' ', array_unique( $class_names ) );
		}

		$inner_markup = sprintf(
			'<ul class="%s">%s</ul>',
			esc_attr( implode( ' ', array_unique( $list_classes ) ) ),
			$links_markup
		);

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'social-links', $attributes, $inner_markup );
	}

	/**
	 * Determine the best matching social service for the icon.
	 *
	 * @param array $icon Icon settings.
	 */
	private function detect_service( array $icon ): string {
		$value = '';
		if ( isset( $icon['social_icon']['value'] ) ) {
			$value = strtolower( (string) $icon['social_icon']['value'] );
		} elseif ( isset( $icon['icon'] ) ) {
			$value = strtolower( (string) $icon['icon'] );
		}

		$map = array(
			'facebook'  => array( 'facebook' ),
			'twitter'   => array( 'twitter', 'x', 'x-twitter', 'xcorp' ),
			'linkedin'  => array( 'linkedin' ),
			'instagram' => array( 'instagram' ),
			'youtube'   => array( 'youtube' ),
			'pinterest' => array( 'pinterest' ),
			'tiktok'    => array( 'tiktok' ),
			'github'    => array( 'github' ),
			'wordpress' => array( 'wordpress' ),
		);

		foreach ( $map as $service => $needles ) {
			foreach ( $needles as $needle ) {
				if ( '' !== $needle && false !== strpos( $value, $needle ) ) {
					return $service;
				}
			}
		}

		return '';
	}

	/**
	 * Check whether a color is a preset slug.
	 *
	 * @param string $color Color string.
	 */
	private function is_preset_slug( string $color ): bool {
		return '' !== $color && false === strpos( $color, '#' ) && false === strpos( $color, 'rgb' );
	}
}
