<?php
/**
 * Server-side render for the Styled Icon block.
 *
 * @package Gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function blockshift_render_icon_block( $attributes ) {
	$defaults   = array(
		'icon'                 => 'star-filled',
		'iconStyle'            => 'fas',
		'size'                 => 32,
		'color'                => '#333333',
		'backgroundColor'      => 'transparent',
		'borderRadius'         => 0,
		'padding'              => 0,
		'alignment'            => 'left',
		'hoverColor'           => '',
		'hoverBackgroundColor' => '',
		'hoverEffect'          => 'none',
		'link'                 => '',
		'linkTarget'           => false,
		'ariaLabel'            => '',
	);
	$attributes = wp_parse_args( $attributes, $defaults );

	// Validate values placed into inline style declarations; esc_attr() alone does
	// not stop ";"-delimited CSS injection, so colours are matched against a
	// CSS-colour allowlist and the alignment keyword against a fixed set.
	$is_css_color = static function ( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return false;
		}
		if ( preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return true;
		}
		if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\([a-z0-9.,%\/\s-]+\)$/i', $value ) ) {
			return true;
		}
		if ( preg_match( '/^var\(\s*--[a-z0-9_-]+\s*(?:,[^;{}<>]*)?\)$/i', $value ) ) {
			return true;
		}
		return (bool) preg_match( '/^[a-z]+$/i', $value );
	};
	$color        = $is_css_color( $attributes['color'] ) ? trim( (string) $attributes['color'] ) : '#333333';
	$background   = $is_css_color( $attributes['backgroundColor'] ) ? trim( (string) $attributes['backgroundColor'] ) : 'transparent';
	$alignment    = in_array( $attributes['alignment'], array( 'left', 'center', 'right', 'justify' ), true ) ? $attributes['alignment'] : 'left';

	// Build styles.
	$icon_styles = sprintf(
		'font-size:%dpx;color:%s;background-color:%s;border-radius:%dpx;padding:%dpx;display:inline-block;line-height:1;transition:all 0.3s ease;width:auto;height:auto;',
		intval( $attributes['size'] ),
		esc_attr( $color ),
		esc_attr( $background ),
		intval( $attributes['borderRadius'] ),
		intval( $attributes['padding'] )
	);

	$wrapper_style = sprintf(
		'text-align:%s;padding-top:0;padding-bottom:0;',
		esc_attr( $alignment )
	);

	// Build icon element.
	$icon_html = sprintf(
		'<i class="%1$s %2$s fontawesome-icon-hover-%3$s" style="%4$s" aria-label="%5$s" aria-hidden="true" data-hover-effect="%3$s" data-icon="%6$s" data-icon-style="%2$s"></i>',
		esc_attr( $attributes['iconStyle'] ),
		esc_attr( $attributes['icon'] ),
		esc_attr( $attributes['hoverEffect'] ),
		$icon_styles,
		esc_attr( $attributes['ariaLabel'] ),
		esc_attr( $attributes['icon'] )
	);

	// Wrap in link if applicable.
	if ( ! empty( $attributes['link'] ) ) {
		$target    = $attributes['linkTarget'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$icon_html = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $attributes['link'] ),
			$target,
			$icon_html
		);
	}

	// Final markup.
	return sprintf(
		'<div class="wp-block-blockshift-icon fontawesome-icon-align-%1$s" style="%2$s">%3$s</div>',
		esc_attr( $alignment ),
		$wrapper_style,
		$icon_html
	);
}
