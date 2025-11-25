<?php
/**
 * Server-side render for the `progressus/google-map` block.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Blocks;
use function esc_attr;
use function get_block_wrapper_attributes;
use function register_block_type;

/**
 * Render the google map block server-side.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string HTML output
 */
function render_google_map_block( $attributes, $content, $block ) {
	$loc = isset( $attributes['location'] ) ? $attributes['location'] : null;
	$address = '';
	if ( is_array( $loc ) && isset( $loc['address'] ) ) {
		$address = $loc['address'];
	} elseif ( isset( $attributes['address'] ) ) {
		$address = $attributes['address'];
	}

	$lat = null;
	if ( is_array( $loc ) && isset( $loc['lat'] ) && $loc['lat'] !== null ) {
		$lat = floatval( $loc['lat'] );
	} elseif ( isset( $attributes['lat'] ) && $attributes['lat'] !== null ) {
		$lat = floatval( $attributes['lat'] );
	}

	$lng = null;
	if ( is_array( $loc ) && isset( $loc['lng'] ) && $loc['lng'] !== null ) {
		$lng = floatval( $loc['lng'] );
	} elseif ( isset( $attributes['lng'] ) && $attributes['lng'] !== null ) {
		$lng = floatval( $attributes['lng'] );
	}
	$zoom = isset( $attributes['zoom'] ) ? intval( $attributes['zoom'] ) : 14;
	$height = isset( $attributes['height'] ) ? intval( $attributes['height'] ) : 400;

	if ( $lat !== null && $lng !== null ) {
		$src = sprintf( 'https://maps.google.com/maps?q=%1$s,%2$s&z=%3$d&output=embed', \esc_attr( $lat ), \esc_attr( $lng ), $zoom );
	} elseif ( ! empty( $address ) ) {
		$src = sprintf( 'https://maps.google.com/maps?q=%s&z=%d&output=embed', rawurlencode( $address ), $zoom );
	} else {
		$src = '';
	}

	$wrapper = \get_block_wrapper_attributes( array( 'class' => 'wp-block-progressus-google-map' ) );

	// Attach serialized location data when present for richer frontend access.
	$location_attr = isset( $attributes['location'] ) && is_array( $attributes['location'] ) ? $attributes['location'] : null;
	$location_json = $location_attr ? wp_json_encode( $location_attr ) : '';

	$map_type = isset( $attributes['mapType'] ) ? $attributes['mapType'] : '';
	$zoom_attr = isset( $attributes['zoom'] ) ? intval( $attributes['zoom'] ) : '';
	$height_attr = isset( $attributes['height'] ) ? intval( $attributes['height'] ) : '';

	$wrapper_with_data = rtrim( $wrapper, '>' );
	if ( $location_json ) {
		$wrapper_with_data .= ' data-location="' . esc_attr( $location_json ) . '"';
	}
	if ( $map_type ) {
		$wrapper_with_data .= ' data-map-type="' . esc_attr( $map_type ) . '"';
	}
	if ( $zoom_attr !== '' ) {
		$wrapper_with_data .= ' data-zoom="' . esc_attr( $zoom_attr ) . '"';
	}
	if ( $height_attr !== '' ) {
		$wrapper_with_data .= ' data-height="' . esc_attr( $height_attr ) . '"';
	}
	$wrapper_with_data .= '>'; 

	$style_parts = array();
	// Helper: normalize a side value which may be numeric or include units (e.g. '2px' or '1.5%').
	$normalize = static function( $value ) {
		if ( $value === '' || $value === null ) {
			return '0px';
		}
		// If it's numeric, append 'px'.
		if ( is_numeric( $value ) ) {
			return $value . 'px';
		}
		// If it already contains letters/percent/unit, return as-is.
		if ( is_string( $value ) && preg_match( '/[a-z%]$/i', trim( $value ) ) ) {
			return $value;
		}
		// Fallback: cast to string and append px.
		return (string) $value . 'px';
	};

	if ( isset( $attributes['style']['spacing']['margin'] ) && is_array( $attributes['style']['spacing']['margin'] ) ) {
		$m = $attributes['style']['spacing']['margin'];
		$top = isset( $m['top'] ) ? $normalize( $m['top'] ) : '0px';
		$right = isset( $m['right'] ) ? $normalize( $m['right'] ) : '0px';
		$bottom = isset( $m['bottom'] ) ? $normalize( $m['bottom'] ) : '0px';
		$left = isset( $m['left'] ) ? $normalize( $m['left'] ) : '0px';
		$style_parts[] = sprintf( 'margin:%1$s %2$s %3$s %4$s', \esc_attr( $top ), \esc_attr( $right ), \esc_attr( $bottom ), \esc_attr( $left ) );
	}

	if ( isset( $attributes['style']['spacing']['padding'] ) && is_array( $attributes['style']['spacing']['padding'] ) ) {
		$p = $attributes['style']['spacing']['padding'];
		$pt = isset( $p['top'] ) ? $normalize( $p['top'] ) : '0px';
		$pr = isset( $p['right'] ) ? $normalize( $p['right'] ) : '0px';
		$pb = isset( $p['bottom'] ) ? $normalize( $p['bottom'] ) : '0px';
		$pl = isset( $p['left'] ) ? $normalize( $p['left'] ) : '0px';
		$style_parts[] = sprintf( 'padding:%1$s %2$s %3$s %4$s', \esc_attr( $pt ), \esc_attr( $pr ), \esc_attr( $pb ), \esc_attr( $pl ) );
	}

	if ( ! empty( $style_parts ) ) {
		$style_attr = implode( ';', $style_parts );
		// inject style attribute into wrapper
		$wrapper_with_data = rtrim( $wrapper_with_data, '>' );
		$wrapper_with_data .= ' style="' . esc_attr( $style_attr ) . '">';
	}

	if ( ! $src ) {
		return sprintf( '<div %1$s><div style="height:%2$spx;background:#f3f3f3;border:1px solid #ddd"></div></div>', $wrapper_with_data, \esc_attr( $height ) );
	}

	return sprintf( '<div %1$s><iframe src="%2$s" style="width:100%%;height:%3$spx;border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>', $wrapper_with_data, \esc_attr( $src ), \esc_attr( $height ) );
}

if ( function_exists( 'register_block_type' ) ) {
	register_block_type( 'progressus/google-map', array( 'render_callback' => __NAMESPACE__ . '\\render_google_map_block' ) );
}
