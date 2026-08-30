<?php
/**
 * Widget handler for Elementor social icons widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

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

		/*
		 * `icon_color` is a mode - '' for each network's own brand colour, 'custom'
		 * for the two colours below it. Writing that switch straight into
		 * `customIconColor` produced the literal value "custom", which core ignores,
		 * so a widget styled to sit on a dark footer kept its brand colours.
		 */
		$uses_custom_colors = 'custom' === strtolower( trim( (string) ( $settings['icon_color'] ?? '' ) ) );

		if ( $uses_custom_colors ) {
			// Elementor's primary colour paints the shape, the secondary one the glyph.
			$shape = $this->resolve_color( $settings, array( 'icon_primary_color' ) );
			$glyph = $this->resolve_color( $settings, array( 'icon_secondary_color' ) );

			/*
			 * `customIconColor` is what the editor stores; `iconColorValue` is what
			 * the render callback passes down to each icon, so both are needed for
			 * the colour to survive a save and still show on the front end.
			 */
			if ( '' !== $glyph ) {
				$attributes['customIconColor'] = $glyph;
				$attributes['iconColorValue']  = $glyph;
				$class_names[]                 = 'has-icon-color';
				$list_classes[]                = 'has-icon-color';
			}

			if ( '' !== $shape ) {
				$attributes['customIconBackgroundColor'] = $shape;
				$attributes['iconBackgroundColorValue']  = $shape;
				$class_names[]                           = 'has-icon-background-color';
				$list_classes[]                          = 'has-icon-background-color';
			}
		}

		$open_new_tab = false;
		$links_markup = '';

		foreach ( $icons as $icon ) {
			if ( ! is_array( $icon ) ) {
				continue;
			}

			$url     = is_array( $icon['link'] ?? null ) ? (string) ( $icon['link']['url'] ?? '' ) : '';
			$service = $this->detect_service( $icon );

			/*
			 * Elementor shows an icon whether or not a link was filled in, and kits
			 * routinely ship them unlinked. core/social-link renders nothing without
			 * a URL, so an unlinked icon gets the same '#' Elementor itself outputs
			 * rather than being dropped along with the rest of the widget.
			 */
			if ( '' === $url ) {
				if ( '' === $service ) {
					continue;
				}

				$url = '#';
			}

			if ( ! empty( $icon['link']['is_external'] ) ) {
				$open_new_tab = true;
			}

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
	 * Resolve the first of the given colour settings that yields a colour.
	 *
	 * @param array         $settings Widget settings.
	 * @param array<string> $keys     Control names to try, in order.
	 */
	private function resolve_color( array $settings, array $keys ): string {
		foreach ( $keys as $key ) {
			$color = Style_Parser::extract_text_color_css_value( $settings, $key );
			if ( ! empty( $color['color'] ) ) {
				return (string) $color['color'];
			}
		}

		return '';
	}
}
