<?php
/**
 * Server-side rendering of the `progressus/counter` block.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Blocks;

use function esc_html;
use function get_block_wrapper_attributes;
use function register_block_type;

/**
 * Renders the `progressus/counter` block on the server.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 * @return string Returns the counter block markup.
 */
function render_counter_block( $attributes, $content, $block ) {
	$start_value = isset( $attributes['startValue'] ) ? intval( $attributes['startValue'] ) : 0;
	$end_value   = isset( $attributes['endValue'] ) ? intval( $attributes['endValue'] ) : 100;
	$duration    = isset( $attributes['duration'] ) ? intval( $attributes['duration'] ) : 2000;
	$prefix      = isset( $attributes['prefix'] ) ? \esc_html( $attributes['prefix'] ) : '';
	$suffix      = isset( $attributes['suffix'] ) ? \esc_html( $attributes['suffix'] ) : '';

	$wrapper_attributes = \get_block_wrapper_attributes( array(
		'class'          => 'wp-block-progressus-counter',
		'data-start'     => $start_value,
		'data-end'       => $end_value,
		'data-duration'  => $duration,
	) );

	return sprintf(
		'<div %1$s><span class="prefix">%2$s</span><span class="counter-value">%3$s</span><span class="suffix">%4$s</span></div>',
		$wrapper_attributes,
		$prefix,
		$start_value, // Start with initial value
		$suffix
	);
}

// Register the render callback
if ( \function_exists( 'register_block_type' ) ) {
	\register_block_type(
		'progressus/counter',
		array(
			'render_callback' => __NAMESPACE__ . '\render_counter_block',
		)
	);
}