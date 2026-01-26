<?php
/**
 * Deterministic HTML attribute builder.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function esc_attr;

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic HTML attribute builder.
 */
class Html_Attribute_Builder {

	/**
	 * Build an attribute string from ordered pairs.
	 *
	 * @param array<string, string|array> $attrs Attributes list.
	 *
	 * @return string
	 */
	public static function build( array $attrs ): string {
		ksort( $attrs );

		$parts = array();
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_filter( array_map( 'trim', $value ) ) );
			}

			$key   = strtolower( trim( (string) $key ) );
			$value = trim( (string) $value );

			if ( '' === $key || '' === $value ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Merge classes into a deterministic string.
	 *
	 * @param string|array $existing Existing classes.
	 * @param string|array $new New classes.
	 *
	 * @return string
	 */
	public static function merge_classes( $existing, $new ): string {
		$classes = array();
		foreach ( array( $existing, $new ) as $group ) {
			if ( is_string( $group ) ) {
				$group = preg_split( '/\s+/', $group );
			}

			if ( is_array( $group ) ) {
				foreach ( $group as $class ) {
					$clean = Style_Parser::clean_class( (string) $class );
					if ( '' !== $clean ) {
						$classes[ $clean ] = true;
					}
				}
			}
		}

		ksort( $classes );

		return implode( ' ', array_keys( $classes ) );
	}
}
