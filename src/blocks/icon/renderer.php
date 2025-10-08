<?php
/**
 * Server-side render for the Styled Icon block.
 *
 * @package Gutenberg
 */

function gutenberg_render_icon_block( $attributes ) {
	$defaults = array(
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

	// Build styles.
	$icon_styles = sprintf(
		'font-size:%dpx;color:%s;background-color:%s;border-radius:%dpx;padding:%dpx;display:inline-block;line-height:1;transition:all 0.3s ease;width:auto;height:auto;',
		intval( $attributes['size'] ),
		esc_attr( $attributes['color'] ),
		esc_attr( $attributes['backgroundColor'] ),
		intval( $attributes['borderRadius'] ),
		intval( $attributes['padding'] )
	);

	$wrapper_style = sprintf(
		'text-align:%s;padding-top:0;padding-bottom:0;',
		esc_attr( $attributes['alignment'] )
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
		$target     = $attributes['linkTarget'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$icon_html  = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $attributes['link'] ),
			$target,
			$icon_html
		);
	}

	// Final markup.
	return sprintf(
		'<div class="wp-block-gutenberg-icon fontawesome-icon-align-%1$s" style="%2$s">%3$s</div>',
		esc_attr( $attributes['alignment'] ),
		$wrapper_style,
		$icon_html
	);
}
