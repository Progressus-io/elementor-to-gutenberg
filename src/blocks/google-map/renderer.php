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
	$location_json = $location_attr ? \wp_json_encode( $location_attr ) : '';

	$map_type = isset( $attributes['mapType'] ) ? $attributes['mapType'] : '';
	$zoom_attr = isset( $attributes['zoom'] ) ? intval( $attributes['zoom'] ) : '';
	$height_attr = isset( $attributes['height'] ) ? intval( $attributes['height'] ) : '';

	// Append data-location attribute to wrapper HTML (wrapper already contains attributes string).
	// wrapper example: 'class="wp-block-..." data-something="..."'
	// Build wrapper attributes string. We always append marker/map attributes so
	// front-end JS can read marker color / visibility even when `location`
	// wasn't serialized as a single `location` attribute.
	$wrapper_with_data = rtrim( $wrapper, '>' );
	// include data-location only when we have serialized location data
	if ( $location_json ) {
		$wrapper_with_data .= ' data-location="' . esc_attr( $location_json ) . '"';
	}
	// marker color/visibility attributes removed — frontend will not render markers
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

	// Append inline style for margin/padding when attributes present (Elementor-style _margin/_padding)
	$style_parts = array();
	if ( isset( $attributes['_margin'] ) && is_array( $attributes['_margin'] ) ) {
		$m = $attributes['_margin'];
		if ( isset( $m['unit'] ) ) {
			$unit = isset( $m['unit'] ) ? $m['unit'] : 'px';
			$top = isset( $m['top'] ) && $m['top'] !== '' ? $m['top'] : '0';
			$right = isset( $m['right'] ) && $m['right'] !== '' ? $m['right'] : '0';
			$bottom = isset( $m['bottom'] ) && $m['bottom'] !== '' ? $m['bottom'] : '0';
			$left = isset( $m['left'] ) && $m['left'] !== '' ? $m['left'] : '0';
			$style_parts[] = sprintf( 'margin:%1$s%5$s %2$s%5$s %3$s%5$s %4$s%5$s', esc_attr( $top ), esc_attr( $right ), esc_attr( $bottom ), esc_attr( $left ), esc_attr( $unit ) );
		} else {
			// tabs-style numeric shape
			$top = isset( $m['top'] ) ? $m['top'] : 0;
			$right = isset( $m['right'] ) ? $m['right'] : 0;
			$bottom = isset( $m['bottom'] ) ? $m['bottom'] : 0;
			$left = isset( $m['left'] ) ? $m['left'] : 0;
			$style_parts[] = sprintf( 'margin:%1$spx %2$spx %3$spx %4$spx', esc_attr( $top ), esc_attr( $right ), esc_attr( $bottom ), esc_attr( $left ) );
		}
	}
	if ( isset( $attributes['_padding'] ) && is_array( $attributes['_padding'] ) ) {
		$p = $attributes['_padding'];
		if ( isset( $p['unit'] ) ) {
			$unit = isset( $p['unit'] ) ? $p['unit'] : 'px';
			$top = isset( $p['top'] ) && $p['top'] !== '' ? $p['top'] : '0';
			$right = isset( $p['right'] ) && $p['right'] !== '' ? $p['right'] : '0';
			$bottom = isset( $p['bottom'] ) && $p['bottom'] !== '' ? $p['bottom'] : '0';
			$left = isset( $p['left'] ) && $p['left'] !== '' ? $p['left'] : '0';
			$style_parts[] = sprintf( 'padding:%1$s%5$s %2$s%5$s %3$s%5$s %4$s%5$s', esc_attr( $top ), esc_attr( $right ), esc_attr( $bottom ), esc_attr( $left ), esc_attr( $unit ) );
		} else {
			$top = isset( $p['top'] ) ? $p['top'] : 0;
			$right = isset( $p['right'] ) ? $p['right'] : 0;
			$bottom = isset( $p['bottom'] ) ? $p['bottom'] : 0;
			$left = isset( $p['left'] ) ? $p['left'] : 0;
			$style_parts[] = sprintf( 'padding:%1$spx %2$spx %3$spx %4$spx', esc_attr( $top ), esc_attr( $right ), esc_attr( $bottom ), esc_attr( $left ) );
		}
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

if ( \function_exists( 'register_block_type' ) ) {
	\register_block_type( 'progressus/google-map', array( 'render_callback' => __NAMESPACE__ . '\\render_google_map_block' ) );
}
