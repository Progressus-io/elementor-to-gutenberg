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
			'heading'   => array(
				'style' => array(
					'typography' => true,
					'spacing'    => true,
					'color'      => true,
				),
				'attrs' => array(
					'textAlign' => true,
					'level'     => true,
					'anchor'    => true,
					'className' => true,
				),
			),
			'paragraph' => array(
				'style' => array(
					'typography' => true,
					'spacing'    => true,
					'color'      => true,
				),
				'attrs' => array(
					'align'     => true,
					'dropCap'   => true,
					'className' => true,
				),
			),
			'group'     => array(
				'style' => array(
					'spacing'    => true,
					'color'      => true,
					'typography' => true,
				),
				'attrs' => array(
					'layout' => true,
				),
			),
			'button'    => array(
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

		$always_allow = array(
			'className' => true,
			'align'   => in_array( $block_slug, array( 'group', 'image', 'columns', 'column', 'buttons', 'button' ), true ),
			'anchor'  => in_array( $block_slug, array( 'heading', 'group' ), true ),
		);

		foreach ( $attrs as $key => $value ) {
			if ( 'style' === $key && is_array( $value ) ) {
				$split              = $this->split_style_tree( $style_allow, $value );
				$supported['style'] = $split['supported'];
				$external           = array_merge( $external, $split['external'] );
				$dropped['style']   = $split['dropped'];
				if ( empty( $supported['style'] ) ) {
					unset( $supported['style'] );
				}
			} elseif ( isset( $attr_allow[ $key ] ) || isset( $always_allow[ $key ] ) ) {
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
				$external = array_merge( $external, $this->map_style_tree( $key, $value ) );
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
	 * Map a style tree into CSS declarations using explicit schema mappings.
	 *
	 * @param string $prefix Style prefix.
	 * @param array $style Style tree.
	 *
	 * @return array
	 */
	private function map_style_tree( string $prefix, array $style ): array {
		$output = array();
		foreach ( $style as $key => $value ) {
			$path = $this->canonicalize_path( array( $prefix, $key ) );

			if ( is_array( $value ) ) {
				$output = array_merge( $output, $this->map_style_tree( $path, $value ) );
				continue;
			}

			$property = $this->map_path_to_property( $path );
			if ( '' === $property ) {
				continue;
			}

			$output[ $property ] = $value;
		}

		return $output;
	}

	/**
	 * Convert a normalized path string into a CSS property name.
	 *
	 * @param string $path Normalized path like "spacing.padding.top".
	 *
	 * @return string
	 */
	private function map_path_to_property( string $path ): string {
		$map = array(
			'dimensions.minheight'       => 'min-height',
			'dimensions.width'           => 'width',
			'spacing.padding.top'        => 'padding-top',
			'spacing.padding.right'      => 'padding-right',
			'spacing.padding.bottom'     => 'padding-bottom',
			'spacing.padding.left'       => 'padding-left',
			'spacing.margin.top'         => 'margin-top',
			'spacing.margin.right'       => 'margin-right',
			'spacing.margin.bottom'      => 'margin-bottom',
			'spacing.margin.left'        => 'margin-left',
			'typography.fontfamily'      => 'font-family',
			'typography.fontsize'        => 'font-size',
			'typography.fontstyle'       => 'font-style',
			'typography.fontweight'      => 'font-weight',
			'typography.letterspacing'   => 'letter-spacing',
			'typography.lineheight'      => 'line-height',
			'typography.textdecoration'  => 'text-decoration',
			'typography.texttransform'   => 'text-transform',
			'typography.wordspacing'     => 'word-spacing',
			'color.text'                 => 'color',
			'color.background'           => 'background-color',
			'border.color'               => 'border-color',
			'border.width'               => 'border-width',
			'border.style'               => 'border-style',
			'border.radius'              => 'border-radius',
			'background.image'           => 'background-image',
			'background.backgroundimage' => 'background-image',
			'background.position'        => 'background-position',
			'background.size'            => 'background-size',
			'background.repeat'          => 'background-repeat',
			'background.attachment'      => 'background-attachment',
			'boxshadow'                  => 'box-shadow',
		);

		return $map[ $path ] ?? '';
	}

	/**
	 * Normalize a style path into a canonical dot-notation string.
	 *
	 * @param array $segments Path segments.
	 *
	 * @return string
	 */
	private function canonicalize_path( array $segments ): string {
		$normalized = array();
		foreach ( $segments as $segment ) {
			$parts = explode( '.', (string) $segment );
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' === $part ) {
					continue;
				}
				$part         = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $part );
				$part         = strtolower( (string) $part );
				$part         = str_replace( array( '-', '_' ), '', $part );
				$normalized[] = $part;
			}
		}

		return implode( '.', $normalized );
	}
}
