<?php
/**
 * Widget handler for Elementor map widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Converts an Elementor map widget into the `progressus/google-map` block.
 */
class Map_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor map widget to Gutenberg block.
	 *
	 * @param array $element Elementor element data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';
		$custom_id  = $settings['_element_id'] ?? '';
		$custom_class = $settings['_css_classes'] ?? '';

		// Normalize location data.
		$loc = array(
			'address' => '',
			'lat'     => null,
			'lng'     => null,
		);

		$loc['address'] = $settings['address'] ?? '';

		$zoom   = isset( $settings['zoom'] ) ? intval( $settings['zoom']['size'] ) : 14;
		$height = isset( $settings['height'] ) ? intval( $settings['height'] ) : 400;

		// Build attributes that represent the original settings (keep location object and address)
		$attributes = array(
			'location' => array(
				'address' => $loc['address'],
				'lat'     => $loc['lat'] ?? null,
				'lng'     => $loc['lng'] ?? null,
			),
			'address' => $loc['address'],
			'zoom'    => $zoom,
			'height'  => $height,
			'mapType' => $settings['map_type'] ?? ( $settings['mapType'] ?? '' ),
			'lat'     => $loc['lat'] ?? null,
			'lng'     => $loc['lng'] ?? null,
		);
		$spacing    = Style_Parser::parse_spacing( $settings );
		$spacing_attrs = ! empty( $spacing['attributes'] ) ? $spacing['attributes'] : array();

		$norm_spacing = array(
			'margin'  => array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ),
			'padding' => array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ),
		);

		foreach ( array( 'margin', 'padding' ) as $box ) {
			if ( ! empty( $spacing_attrs[ $box ] ) && is_array( $spacing_attrs[ $box ] ) ) {
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$val = $spacing_attrs[ $box ][ $side ] ?? null;
					if ( $val === null ) {
						$norm_spacing[ $box ][ $side ] = 0;
						continue;
					}
					// If value contains 'px', strip and cast to int.
					if ( is_string( $val ) && preg_match( '/^([0-9.+-]+)px$/', trim( $val ), $m ) ) {
						$norm_spacing[ $box ][ $side ] = intval( $m[1] );
						continue;
					}
					// If numeric string or number, cast to int.
					if ( is_numeric( $val ) ) {
						$norm_spacing[ $box ][ $side ] = intval( $val );
						continue;
					}
					// Fallback: try to extract digits
					if ( is_string( $val ) && preg_match( '/([0-9]+)/', $val, $m2 ) ) {
						$norm_spacing[ $box ][ $side ] = intval( $m2[1] );
						continue;
					}
					$norm_spacing[ $box ][ $side ] = 0;
				}
			}
		}

		$attributes['_margin'] = $norm_spacing['margin'];
		$attributes['_padding'] = $norm_spacing['padding'];
		$attributes['style']['spacing'] = $norm_spacing;

		$attrs_json = wp_json_encode( $attributes );

		// Save any custom CSS into the Customizer so styles persist.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		// Build iframe src using lat/lng when available, otherwise address.
		if ( $attributes['location']['lat'] !== null && $attributes['location']['lng'] !== null ) {
			$src = sprintf( 'https://maps.google.com/maps?q=%1$s,%2$s&z=%3$d&output=embed', \esc_attr( $attributes['location']['lat'] ), \esc_attr( $attributes['location']['lng'] ), $zoom );
		} elseif ( ! empty( $attributes['location']['address'] ) ) {
			$src = sprintf( 'https://maps.google.com/maps?q=%s&z=%d&output=embed', \rawurlencode( $attributes['location']['address'] ), $zoom );
		} else {
			$src = '';
		}


		// Build shorthand style attribute from normalized spacing to match client-side save output.
		$style_parts = array();
		if ( isset( $attributes['_margin'] ) && is_array( $attributes['_margin'] ) ) {
			$m = $attributes['_margin'];
			$style_parts[] = sprintf( 'margin:%1$spx %2$spx %3$spx %4$spx', intval( $m['top'] ), intval( $m['right'] ), intval( $m['bottom'] ), intval( $m['left'] ) );
		}
		if ( isset( $attributes['_padding'] ) && is_array( $attributes['_padding'] ) ) {
			$p = $attributes['_padding'];
			$style_parts[] = sprintf( 'padding:%1$spx %2$spx %3$spx %4$spx', intval( $p['top'] ), intval( $p['right'] ), intval( $p['bottom'] ), intval( $p['left'] ) );
		}
		$style_attr = implode( ';', $style_parts );

		$open = '<!-- wp:progressus/google-map ' . $attrs_json . ' -->';
		$inner = '';
		if ( ! $src ) {
			$inner = sprintf( '<div class="wp-block-progressus-google-map" style="%1$s"><div style="height:%2$spx;background:#f3f3f3;border:1px solid #ddd"></div></div>', esc_attr( $style_attr ), esc_attr( $height ) );
		} else {
			$inner = sprintf( '<div class="wp-block-progressus-google-map" style="%1$s"><iframe src="%2$s" style="width:100%%;height:%3$spx;border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>', esc_attr( $style_attr ), esc_attr( $src ), esc_attr( $height ) );
		}

		$close = '<!-- /wp:progressus/google-map -->';

		return $open . "\n" . $inner . "\n" . $close . "\n";
	}
}
