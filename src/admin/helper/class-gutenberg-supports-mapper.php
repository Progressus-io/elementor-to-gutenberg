<?php
/**
 * Map raw declarations onto Gutenberg-supported attributes.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Mapper for Gutenberg supported attributes.
 */
class Gutenberg_Supports_Mapper {

	/**
	 * Compatibility map keyed by block slug.
	 *
	 * @var array<string, array<string, array<string, bool>>>
	 */
	private array $matrix = array();

	public function __construct() {
		$this->matrix = array(
			'heading' => array(
				'style' => array(
					'typography' => true,
					'spacing'    => true,
					'color'      => true,
				),
				'attrs' => array(
					'textAlign' => true,
				),
			),
			'group'   => array(
				'style' => array(
					'spacing'    => true,
					'color'      => true,
					'typography' => true,
				),
				'attrs' => array(
					'layout' => true,
				),
			),
			'button'  => array(
				'style' => array(
					'spacing'    => true,
					'typography' => true,
					'color'      => true,
				),
				'attrs' => array(
					'width' => true,
				),
			),
		);
	}

	/**
	 * Split supported and unsupported attributes.
	 *
	 * @param string $block_slug Block slug without namespace.
	 * @param array $attrs Raw attributes.
	 *
	 * @return array{supported: array, external: array, dropped: array}
	 */
	public function split_attributes( string $block_slug, array $attrs ): array {
		$supported = array();
		$external  = array();
		$dropped   = array();

		$block_matrix = $this->matrix[ $block_slug ] ?? array();
		$style_allow  = $block_matrix['style'] ?? array();
		$attr_allow   = $block_matrix['attrs'] ?? array();

		foreach ( $attrs as $key => $value ) {
			if ( 'style' === $key && is_array( $value ) ) {
				$split              = $this->split_style_tree( $style_allow, $value );
				$supported['style'] = $split['supported'];
				$external           = array_merge( $external, $split['external'] );
				$dropped['style']   = $split['dropped'];
				if ( empty( $supported['style'] ) ) {
					unset( $supported['style'] );
				}
			} elseif ( isset( $attr_allow[ $key ] ) ) {
				$supported[ $key ] = $value;
			} else {
				$dropped[ $key ] = $value;
			}
		}

		return array(
			'supported' => $supported,
			'external'  => $external,
			'dropped'   => Style_Normalizer::prune_empty( $dropped ),
		);
	}

	/**
	 * Split style tree by whitelist.
	 *
	 * @param array $allow_map Allowed top-level style keys.
	 * @param array $style Style tree.
	 *
	 * @return array{supported: array, external: array, dropped: array}
	 */
	private function split_style_tree( array $allow_map, array $style ): array {
		$supported = array();
		$external  = array();
		$dropped   = array();

		foreach ( $style as $key => $value ) {
			if ( isset( $allow_map[ $key ] ) ) {
				$supported[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$external = array_merge( $external, $this->flatten_style_tree( $key, $value ) );
			} else {
				$external[ $key ] = $value;
			}
			$dropped[ $key ] = $value;
		}

		return array(
			'supported' => Style_Normalizer::prune_empty( $supported ),
			'external'  => Style_Normalizer::prune_empty( $external ),
			'dropped'   => Style_Normalizer::prune_empty( $dropped ),
		);
	}

	/**
	 * Flatten a style tree into CSS property => value pairs using kebab-case keys.
	 *
	 * @param string $prefix Style prefix.
	 * @param array $style Style tree.
	 *
	 * @return array
	 */
	private function flatten_style_tree( string $prefix, array $style ): array {
		$output = array();
		foreach ( $style as $key => $value ) {
			$prop = $this->camel_to_kebab( $prefix . '-' . $key );
			if ( is_array( $value ) ) {
				$output = array_merge( $output, $this->flatten_style_tree( $prop, $value ) );
			} else {
				$output[ $prop ] = $value;
			}
		}

		return $output;
	}

	/**
	 * Convert camelCase to kebab-case.
	 *
	 * @param string $value Camel string.
	 *
	 * @return string
	 */
	private function camel_to_kebab( string $value ): string {
		$value = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value );

		return strtolower( (string) $value );
	}
}
