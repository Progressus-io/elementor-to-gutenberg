<?php
/**
 * Normalize style arrays and drop unsupported defaults.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Style normalization helper.
 */
class Style_Normalizer {

	/**
	 * Normalize a block attributes payload.
	 *
	 * @param string $block_slug Block slug without namespace.
	 * @param array $attrs Raw attributes.
	 *
	 * @return array
	 */
	public static function normalize_attributes( string $block_slug, array $attrs ): array {
		if ( ! empty( $attrs['ariaLabel'] ) && '' === trim( (string) $attrs['ariaLabel'] ) ) {
			unset( $attrs['ariaLabel'] );
		}

		if ( ! empty( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			$attrs['style'] = self::normalize_style_tree( $block_slug, $attrs['style'] );
			if ( empty( $attrs['style'] ) ) {
				unset( $attrs['style'] );
			}
		}

		return $attrs;
	}

	/**
	 * Normalize typography defaults and zero-ish values.
	 *
	 * @param array $typography Typography style tree.
	 *
	 * @return array
	 */
	private static function normalize_typography( array $typography ): array {
		foreach ( array( 'letterSpacing', 'wordSpacing' ) as $spacing_key ) {
			if ( isset( $typography[ $spacing_key ] ) ) {
				$norm = self::normalize_zero_dimension( (string) $typography[ $spacing_key ] );
				if ( '0' === $norm ) {
					unset( $typography[ $spacing_key ] );
				}
			}
		}

		if ( isset( $typography['fontStyle'] ) && 'normal' === strtolower( (string) $typography['fontStyle'] ) ) {
			unset( $typography['fontStyle'] );
		}

		if ( isset( $typography['textDecoration'] ) && 'none' === strtolower( (string) $typography['textDecoration'] ) ) {
			unset( $typography['textDecoration'] );
		}

		return $typography;
	}

	/**
	 * Normalize the style tree by pruning empty leaves and defaults.
	 *
	 * @param string $block_slug Block slug.
	 * @param array $style Style tree.
	 *
	 * @return array
	 */
	public static function normalize_style_tree( string $block_slug, array $style ): array {
		if ( ! empty( $style['typography'] ) && is_array( $style['typography'] ) ) {
			$style['typography'] = self::normalize_typography( $style['typography'] );
			if ( empty( $style['typography'] ) ) {
				unset( $style['typography'] );
			}
		}

		if ( ! empty( $style['spacing'] ) && is_array( $style['spacing'] ) ) {
			foreach ( array( 'padding', 'margin' ) as $dimension ) {
				if ( empty( $style['spacing'][ $dimension ] ) || ! is_array( $style['spacing'][ $dimension ] ) ) {
					continue;
				}

				foreach ( $style['spacing'][ $dimension ] as $key => $value ) {
					$normalized = self::normalize_zero_dimension( (string) $value );
					if ( '0' === $normalized ) {
						$style['spacing'][ $dimension ][ $key ] = '0';
					}
				}
			}
		}

		if ( ! empty( $style['dimensions']['minHeight'] ) ) {
			$style['dimensions']['minHeight'] = self::normalize_zero_dimension( (string) $style['dimensions']['minHeight'] );
		}

		$style = self::prune_empty( $style );

		return $style;
	}

	/**
	 * Normalize zero-like dimension to canonical representation.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	public static function normalize_zero_dimension( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^0(\.0+)?(px|em|rem|%)?$/', $value ) ) {
			return '0';
		}

		return $value;
	}

	/**
	 * Recursively prune empty arrays and empty strings.
	 *
	 * @param array $node Arbitrary nested array.
	 *
	 * @return array
	 */
	public static function prune_empty( array $node ): array {
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				$node[ $key ] = self::prune_empty( $value );
				if ( empty( $node[ $key ] ) ) {
					unset( $node[ $key ] );
				}
			} elseif ( null === $value || '' === $value ) {
				unset( $node[ $key ] );
			}
		}

		return $node;
	}
}
