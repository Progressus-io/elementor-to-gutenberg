<?php
/**
 * Style helpers for the handlers that write this plugin's own blocks.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Builds inline styles the way the blocks' save() does and moves everything else to the stylesheet.
 */
class Widget_Style_Helper {

	/**
	 * Join style declarations, leaving out the empty ones as the blocks' save() does.
	 *
	 * @param array<string, string|int|float> $declarations Property => value pairs.
	 *
	 * @return string Inline style value.
	 */
	public static function join( array $declarations ): string {
		$parts = array();
		foreach ( $declarations as $property => $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$parts[] = $property . ':' . $value;
			}
		}

		return implode( ';', $parts );
	}

	/**
	 * Move widget CSS a block's save() does not write to the converted page's stylesheet.
	 *
	 * The rule is keyed to the widget's unique class, which the caller adds to the block.
	 *
	 * @param array  $element   Elementor element.
	 * @param string ...$styles Inline CSS strings such as `margin-top:10px;margin-bottom:10px`.
	 *
	 * @return string The class carrying the rule, or an empty string when there was nothing to move.
	 */
	public static function externalize( array $element, string ...$styles ): string {
		$collector = External_Style_Collector::get_active();
		$class     = Style_Parser::get_element_unique_class( $element );
		if ( null === $collector || '' === $class ) {
			return '';
		}

		$declarations = array();
		foreach ( explode( ';', implode( ';', $styles ) ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( 2 === count( $parts ) && '' !== trim( $parts[0] ) && '' !== trim( $parts[1] ) ) {
				$declarations[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		$collector->register_rule( '.' . $class, $declarations, 'widget-box' );

		return $class;
	}
}
