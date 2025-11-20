<?php
/**
 * Server-side rendering of the `progressus/tabs` block.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Blocks;

use function esc_html;
use function get_block_wrapper_attributes;
use function register_block_type;

/**
 * Renders the `progressus/tabs` block on the server.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 * @return string Returns the tabs block markup.
 */
function render_tabs_block( $attributes, $content, $block ) {
	$tabs = isset( $attributes['tabs'] ) ? $attributes['tabs'] : array();
	$active_tab = isset( $attributes['activeTab'] ) ? intval( $attributes['activeTab'] ) : 0;
	$tab_style = isset( $attributes['tabStyle'] ) ? \esc_html( $attributes['tabStyle'] ) : 'horizontal';
	$tab_color = isset( $attributes['tabColor'] ) ? \esc_html( $attributes['tabColor'] ) : '#f9f9f9';
	$active_tab_color = isset( $attributes['activeTabColor'] ) ? \esc_html( $attributes['activeTabColor'] ) : '#007cba';
	$content_background_color = isset( $attributes['contentBackgroundColor'] ) ? \esc_html( $attributes['contentBackgroundColor'] ) : '#ffffff';
	$border_color = isset( $attributes['borderColor'] ) ? \esc_html( $attributes['borderColor'] ) : '#dddddd';
	$border_width = isset( $attributes['borderWidth'] ) ? intval( $attributes['borderWidth'] ) : 1;
	$border_style = isset( $attributes['borderStyle'] ) ? \esc_html( $attributes['borderStyle'] ) : 'solid';
	$border_radius = isset( $attributes['borderRadius'] ) ? intval( $attributes['borderRadius'] ) : 4;
	$tabs_padding = isset( $attributes['tabsPadding'] ) ? $attributes['tabsPadding'] : array( 'top' => 12, 'right' => 16, 'bottom' => 12, 'left' => 16 );
	$content_padding = isset( $attributes['contentPadding'] ) ? $attributes['contentPadding'] : array( 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20 );
	$tabs_margin = isset( $attributes['tabsMargin'] ) ? $attributes['tabsMargin'] : array( 'top' => 0, 'right' => 2, 'bottom' => 0, 'left' => 0 );
	$content_margin = isset( $attributes['contentMargin'] ) ? $attributes['contentMargin'] : array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 );

	// Sanitize active tab index
	if ( $active_tab >= count( $tabs ) ) {
		$active_tab = 0;
	}

	$wrapper_attributes = \get_block_wrapper_attributes( array(
		'class'           => 'wp-block-progressus-tabs',
		'data-tab-style'  => $tab_style,
		'data-active-tab' => $active_tab,
	) );

	$tabs_style = '';
	if ( $tab_style === 'vertical' ) {
		$tabs_style = 'display: flex; flex-direction: row;';
	}

	$tab_header_style = sprintf(
		'background-color: %s; border-color: %s; padding: %dpx %dpx %dpx %dpx; margin: %dpx %dpx %dpx %dpx; cursor: pointer; border: %dpx %s %s; border-radius: %dpx;',
		$tab_color,
		$border_color,
		$tabs_padding['top'],
		$tabs_padding['right'],
		$tabs_padding['bottom'],
		$tabs_padding['left'],
		$tabs_margin['top'],
		$tabs_margin['right'],
		$tabs_margin['bottom'],
		$tabs_margin['left'],
		$border_width,
		$border_style,
		$border_color,
		$border_radius
	);

	$active_tab_header_style = sprintf(
		'background-color: %s; border-color: %s; padding: %dpx %dpx %dpx %dpx; margin: %dpx %dpx %dpx %dpx; cursor: pointer; border: %dpx %s %s; border-radius: %dpx; color: white;',
		$active_tab_color,
		$border_color,
		$tabs_padding['top'],
		$tabs_padding['right'],
		$tabs_padding['bottom'],
		$tabs_padding['left'],
		$tabs_margin['top'],
		$tabs_margin['right'],
		$tabs_margin['bottom'],
		$tabs_margin['left'],
		$border_width,
		$border_style,
		$border_color,
		$border_radius
	);

	$content_style = sprintf(
		'background-color: %s; padding: %dpx %dpx %dpx %dpx; margin: %dpx %dpx %dpx %dpx; border: %dpx %s %s; border-radius: %dpx; %s',
		$content_background_color,
		$content_padding['top'],
		$content_padding['right'],
		$content_padding['bottom'],
		$content_padding['left'],
		$content_margin['top'],
		$content_margin['right'],
		$content_margin['bottom'],
		$content_margin['left'],
		$border_width,
		$border_style,
		$border_color,
		$border_radius,
		$tab_style === 'horizontal' ? 'border-top: none;' : ''
	);

	$headers_direction = $tab_style === 'vertical' ? 'column' : 'row';

	$output = sprintf( '<div %s>', $wrapper_attributes );
	$output .= sprintf( '<div class="progressus-tabs" style="%s">', $tabs_style );
	
	// Render tab headers
	$output .= sprintf( '<div class="progressus-tabs-headers" style="display: flex; flex-direction: %s;">', $headers_direction );
	
	foreach ( $tabs as $index => $tab ) {
		$tab_title = isset( $tab['title'] ) ? \esc_html( $tab['title'] ) : sprintf( 'Tab %d', $index + 1 );
		$is_active = $index === $active_tab;
		$header_class = $is_active ? 'progressus-tab-header active' : 'progressus-tab-header';
		$header_style = $is_active ? $active_tab_header_style : $tab_header_style;
		
		$output .= sprintf(
			'<div class="%s" style="%s" data-tab-index="%d" tabindex="0" role="tab" aria-selected="%s">%s</div>',
			$header_class,
			$header_style,
			$index,
			$is_active ? 'true' : 'false',
			$tab_title
		);
	}
	
	$output .= '</div>'; // Close headers
	
	// Render tab content
	$output .= sprintf( '<div class="progressus-tabs-content" style="%s" role="tablist">', $content_style );
	
	foreach ( $tabs as $index => $tab ) {
		$tab_content = isset( $tab['content'] ) ? wp_kses_post( $tab['content'] ) : '';
		$is_active = $index === $active_tab;
		$content_class = $is_active ? 'progressus-tab-content active' : 'progressus-tab-content';
		$content_display = $is_active ? 'block' : 'none';
		
		$output .= sprintf(
			'<div class="%s" style="display: %s;" role="tabpanel" aria-labelledby="tab-%d" id="tabpanel-%d">%s</div>',
			$content_class,
			$content_display,
			$index,
			$index,
			$tab_content
		);
	}
	
	$output .= '</div>'; // Close content
	$output .= '</div>'; // Close tabs
	$output .= '</div>'; // Close wrapper

	return $output;
}

// Register the render callback
if ( \function_exists( 'register_block_type' ) ) {
	\register_block_type(
		'progressus/tabs',
		array(
			'render_callback' => __NAMESPACE__ . '\render_tabs_block',
		)
	);
}