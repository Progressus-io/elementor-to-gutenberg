<?php
/**
 * Helper for building block markup with wrapper elements.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for building block markup with wrapper elements.
 */
class Block_Builder {
	/**
	 * Blocks that require wrapper div output.
	 *
	 * @var array<string>
	 */
	private static $wrapper_blocks = array( 'group', 'columns', 'column', 'buttons', 'button' );

	/**
	 * Build the serialized markup for a block including wrapper markup when required.
	 *
	 * @param string $block Block name without `core/` prefix (e.g. `group`).
	 * @param array $attrs Attributes array to encode in block comment.
	 * @param string $inner_html Inner HTML for the block.
	 *
	 * @return string
	 */
	public static function build( string $block, array $attrs, string $inner_html ): string {
		if ( 'html' === $block ) {
			$attrs = array();
		}

		$attrs        = self::normalize_attributes( $attrs );
		$attr_json    = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

		if ( 'button' === $block && '' === trim( $inner_html ) ) {
			return sprintf( '<!-- wp:%s%s /-->%s', $block, $attr_json, "\n" );
		}
		$opening      = sprintf( '<!-- wp:%s%s -->', $block, $attr_json );
		$closing      = sprintf( '<!-- /wp:%s -->', $block );
		$block_slug   = self::get_block_slug( $block );
		$is_wrapper   = in_array( $block_slug, self::$wrapper_blocks, true );
		$wrapper_html = $inner_html;

		if ( $is_wrapper ) {
			$wrapper_class = self::build_wrapper_class( $block_slug, $attrs );
			$style_attr    = self::build_style_attribute( $attrs );
			$wrapper_html  = sprintf(
				'<div class="%s"%s>%s</div>',
				esc_attr( $wrapper_class ),
				$style_attr,
				$inner_html
			);
		}

		return $opening . $wrapper_html . $closing . "\n";
	}

	/**
	 * Build the wrapper class attribute from block attributes.
	 *
	 * @param string $block Block name.
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function build_wrapper_class( string $block_slug, array $attrs ): string {
		$classes   = array( 'wp-block-' . $block_slug );
		$align     = isset( $attrs['align'] ) ? trim( (string) $attrs['align'] ) : '';
		$class_raw = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';

		if ( '' !== $align ) {
			$clean_align = Style_Parser::clean_class( 'align' . $align );
			if ( '' !== $clean_align ) {
				$classes[] = $clean_align;
			}
		}

		if ( '' !== $class_raw ) {
			$class_parts = preg_split( '/\s+/', $class_raw );
			if ( is_array( $class_parts ) ) {
				foreach ( $class_parts as $class_part ) {
					$sanitized = Style_Parser::clean_class( $class_part );
					if ( '' !== $sanitized ) {
						$classes[] = $sanitized;
					}
				}
			}
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Build inline style attribute for wrapper blocks.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function build_style_attribute( array $attrs ): string {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return '';
		}

		$style       = $attrs['style'];
		$style_rules = array();

		if ( ! empty( $style['spacing']['blockGap'] ) ) {
			$style_rules[] = 'gap:' . self::normalize_style_value( $style['spacing']['blockGap'] );
		}

		foreach ( array( 'padding', 'margin' ) as $type ) {
			if ( empty( $style['spacing'][ $type ] ) || ! is_array( $style['spacing'][ $type ] ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( empty( $style['spacing'][ $type ][ $side ] ) ) {
					continue;
				}

				$style_rules[] = sprintf(
					'%s-%s:%s',
					$type,
					$side,
					self::normalize_style_value( $style['spacing'][ $type ][ $side ] )
				);
			}
		}

		if ( ! empty( $style['color']['background'] ) ) {
			$style_rules[] = 'background-color:' . self::normalize_style_value( $style['color']['background'] );
		}

		if ( ! empty( $style['typography'] ) && is_array( $style['typography'] ) ) {
			foreach ( $style['typography'] as $property => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$style_rules[] = sprintf( '%s:%s', self::camel_to_kebab( $property ), self::normalize_style_value( $value ) );
			}
		}

		if ( ! empty( $style['border'] ) && is_array( $style['border'] ) ) {
			$style_rules = array_merge( $style_rules, self::build_border_rules( $style['border'] ) );
		}

		if ( empty( $style_rules ) ) {
			return '';
		}

		return ' style="' . esc_attr( implode( ';', $style_rules ) ) . '"';
	}

	/**
	 * Normalize style values for inline usage (e.g. presets to CSS vars).
	 *
	 * @param string $value Raw value from attributes.
	 *
	 * @return string
	 */
	private static function normalize_style_value( $value ): string {
		$value = (string) $value;
		if ( 0 === strpos( $value, 'var:' ) ) {
			$without_prefix = substr( $value, 4 );
			$parts          = explode( '|', $without_prefix );
			if ( ! empty( $parts ) ) {
				$value = 'var(--wp--' . implode( '--', array_map( 'sanitize_html_class', $parts ) ) . ')';
			}
		}

		return $value;
	}

	/**
	 * Convert camelCase properties to kebab-case.
	 *
	 * @param string $property Property name.
	 */
	private static function camel_to_kebab( string $property ): string {
		return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $property ) );
	}

	/**
	 * Normalise attributes by removing empty values recursively.
	 *
	 * @param array $attrs Raw attributes.
	 */
	private static function normalize_attributes( array $attrs ): array {
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$attrs[ $key ] = self::normalize_attributes( $value );
				if ( empty( $attrs[ $key ] ) ) {
					unset( $attrs[ $key ] );
				}
			} elseif ( null === $value || '' === $value ) {
				unset( $attrs[ $key ] );
			}
		}

		return $attrs;
	}

	/**
	 * Extract the slug part from a block name.
	 *
	 * @param string $block Block name (e.g. core/group).
	 */
	private static function get_block_slug( string $block ): string {
		if ( false === strpos( $block, '/' ) ) {
			return $block;
		}

		$parts = explode( '/', $block );

		return end( $parts ) ?: $block;
	}

	/**
	 * Build inline CSS rules for border data.
	 *
	 * @param array $border Border attribute data.
	 */
	private static function build_border_rules( array $border ): array {
		$rules = array();

		if ( ! empty( $border['style'] ) ) {
			$rules[] = 'border-style:' . self::normalize_style_value( $border['style'] );
		}

		if ( ! empty( $border['radius'] ) && is_array( $border['radius'] ) ) {
			$map = array(
				'topLeft'     => 'border-top-left-radius',
				'topRight'    => 'border-top-right-radius',
				'bottomRight' => 'border-bottom-right-radius',
				'bottomLeft'  => 'border-bottom-left-radius',
			);

			foreach ( $map as $key => $property ) {
				if ( empty( $border['radius'][ $key ] ) ) {
					continue;
				}

				$rules[] = sprintf( '%s:%s', $property, self::normalize_style_value( $border['radius'][ $key ] ) );
			}
		}

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( empty( $border[ $side ] ) || ! is_array( $border[ $side ] ) ) {
				continue;
			}

			if ( ! empty( $border[ $side ]['width'] ) ) {
				$rules[] = sprintf( 'border-%s-width:%s', $side, self::normalize_style_value( $border[ $side ]['width'] ) );
			}

			if ( ! empty( $border[ $side ]['color'] ) ) {
				$rules[] = sprintf( 'border-%s-color:%s', $side, self::normalize_style_value( $border[ $side ]['color'] ) );
			}
		}

		return $rules;
	}
}
