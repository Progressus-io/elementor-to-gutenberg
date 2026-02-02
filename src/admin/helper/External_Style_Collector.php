<?php

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

class External_Style_Collector {

	/**
	 * Map of className => declarations array.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $rules = array();

	/**
	 * Inventory for externalized declarations and drops.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $inventory = array(
		'externalized' => array(),
		'dropped'      => array(),
		'conversions'  => array(),
	);

	/**
	 * Externalize known risky style leaves from Gutenberg attrs.
	 *
	 * - Mutates $attrs by removing extracted style leaves and adding a generated className.
	 * - Returns the mutated attrs.
	 *
	 * @param string $block_slug Block slug like "group", "columns", "button".
	 * @param array $attrs Block attrs array (will be mutated).
	 *
	 * @return array
	 */
	public function externalize_attrs( string $block_slug, array $attrs ): array {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return $attrs;
		}

		$extracted = array();

		$image = $this->get_style_leaf( $attrs, array( 'background', 'image' ) );
		if ( '' !== $image ) {
			$extracted['background-image'] = $this->format_background_image( $image );

			$pos = $this->get_style_leaf( $attrs, array( 'background', 'position' ) );
			if ( '' !== $pos ) {
				$extracted['background-position'] = $pos;
			}

			$size = $this->get_style_leaf( $attrs, array( 'background', 'size' ) );
			if ( '' !== $size ) {
				$extracted['background-size'] = $size;
			}

			$repeat = $this->get_style_leaf( $attrs, array( 'background', 'repeat' ) );
			if ( '' !== $repeat ) {
				$extracted['background-repeat'] = $repeat;
			}

			$attrs = $this->unset_style_leaf( $attrs, array( 'background', 'image' ) );
			$attrs = $this->unset_style_leaf( $attrs, array( 'background', 'position' ) );
			$attrs = $this->unset_style_leaf( $attrs, array( 'background', 'size' ) );
			$attrs = $this->unset_style_leaf( $attrs, array( 'background', 'repeat' ) );
		}

		$box_shadow = $this->get_style_leaf( $attrs, array( 'boxShadow' ) );
		if ( '' !== $box_shadow ) {
			$extracted['box-shadow'] = $box_shadow;
			$attrs                   = $this->unset_style_leaf( $attrs, array( 'boxShadow' ) );
		}

		$letter = $this->get_style_leaf( $attrs, array( 'typography', 'letterSpacing' ) );
		if ( '' !== $letter ) {
			$norm = $this->normalize_zero_dimension( $letter );
			if ( null === $norm ) {
				$extracted['letter-spacing'] = $letter;
			}
			$attrs = $this->unset_style_leaf( $attrs, array( 'typography', 'letterSpacing' ) );
		}

		$word = $this->get_style_leaf( $attrs, array( 'typography', 'wordSpacing' ) );
		if ( '' !== $word ) {
			$norm = $this->normalize_zero_dimension( $word );
			if ( null === $norm ) {
				$extracted['word-spacing'] = $word;
			}
			$attrs = $this->unset_style_leaf( $attrs, array( 'typography', 'wordSpacing' ) );
		}

		if ( empty( $extracted ) ) {
			return $attrs;
		}

		// Ensure style tree is cleaned up if empty.
		$attrs = $this->cleanup_empty_style( $attrs );

		// Generate a stable-ish class per extracted set.
		$fingerprint = md5( $block_slug . '|' . wp_json_encode( $extracted ) );
		$class       = 'etg-ext-' . substr( $fingerprint, 0, 10 );

		$attrs['className'] = $this->append_class( isset( $attrs['className'] ) ? (string) $attrs['className'] : '', $class );

		$this->add_rule( '.' . $class, $extracted );

		$this->inventory['externalized'][] = array(
			'block'  => $block_slug,
			'rules'  => $extracted,
			'reason' => 'style-tree',
		);

		return $attrs;
	}

	/**
	 * Externalize arbitrary declaration set and return generated class.
	 *
	 * @param string $block_slug Block slug.
	 * @param array $declarations CSS declarations.
	 *
	 * @return string
	 */
	public function externalize_declarations( string $block_slug, array $declarations ): string {
		$declarations = Style_Normalizer::prune_empty( $declarations );
		if ( empty( $declarations ) ) {
			return '';
		}

		$fingerprint = md5( $block_slug . '|' . wp_json_encode( $declarations ) );
		$class       = 'etg-ext-' . substr( $fingerprint, 0, 10 );

		$this->add_rule( '.' . $class, $declarations );
		$this->inventory['externalized'][] = array(
			'block'  => $block_slug,
			'rules'  => $declarations,
			'reason' => 'unsupported-style',
		);

		return $class;
	}

	/**
	 * Register a custom rule with a selector, optionally tracking inventory.
	 *
	 * @param string $selector CSS selector.
	 * @param array $declarations CSS declarations.
	 * @param string $reason Optional reason label.
	 *
	 * @return void
	 */
	public function register_rule( string $selector, array $declarations, string $reason = '' ): void {
		$declarations = Style_Normalizer::prune_empty( $declarations );
		if ( '' === trim( $selector ) || empty( $declarations ) ) {
			return;
		}

		$declarations = $this->append_important_declarations( $declarations );
		$this->add_rule( $selector, $declarations );

		$this->inventory['externalized'][] = array(
			'block'  => 'page',
			'rules'  => $declarations,
			'reason' => '' === $reason ? 'custom-rule' : $reason,
		);
	}

	/**
	 * Record dropped attributes.
	 *
	 * @param string $block_slug Block slug.
	 * @param string $type Drop type.
	 * @param array $payload Payload.
	 *
	 * @return void
	 */
	public function record_dropped( string $block_slug, string $type, array $payload ): void {
		$this->inventory['dropped'][] = array(
			'block'   => $block_slug,
			'type'    => $type,
			'payload' => $payload,
			'tag'     => 'ETG_EXTRA_ATTRS_MAP_V1',
		);
	}

	/**
	 * Record conversion decision (e.g., group->cover).
	 *
	 * @param string $block_slug Block slug.
	 * @param string $decision Decision code.
	 * @param array $context Context array.
	 *
	 * @return void
	 */
	public function record_conversion( string $block_slug, string $decision, array $context = array() ): void {
		$this->inventory['conversions'][] = array(
			'block'    => $block_slug,
			'decision' => $decision,
			'context'  => $context,
			'tag'      => 'ETG_EXTRA_ATTRS_MAP_V1',
		);
	}

	/**
	 * Record sanitization of inner HTML.
	 *
	 * @param string $block_slug Block slug.
	 * @param string $result Sanitized output.
	 *
	 * @return void
	 */
	public function record_inner_sanitization( string $block_slug, string $result ): void {
		$this->inventory['dropped'][] = array(
			'block'   => $block_slug,
			'type'    => 'inner-html',
			'payload' => $result,
			'tag'     => 'ETG_EXTRA_ATTRS_MAP_V1',
		);
	}

	/**
	 * Get inventory for debugging/logging.
	 *
	 * @return array
	 */
	public function get_inventory(): array {
		return $this->inventory;
	}

	/**
	 * Render collected CSS.
	 *
	 * @return string
	 */
	public function render_css(): string {
		if ( empty( $this->rules ) ) {
			return '';
		}

		$out = '';
		foreach ( $this->rules as $selector => $declarations ) {
			if ( empty( $declarations ) ) {
				continue;
			}

			$out .= $selector . " {\n";
			foreach ( $declarations as $prop => $val ) {
				$prop = trim( (string) $prop );
				$val  = trim( (string) $val );
				if ( '' === $prop || '' === $val ) {
					continue;
				}
				$out .= "\t" . $prop . ': ' . $val . ";\n";
			}
			$out .= "}\n";
		}

		return $out;
	}

	/**
	 * Add sanitized declarations to the ruleset for a selector.
	 *
	 * @param string $selector CSS selector.
	 * @param array<string, mixed> $declarations Declarations to append.
	 *
	 * @return void
	 */
	private function add_rule( string $selector, array $declarations ): void {
		if ( ! isset( $this->rules[ $selector ] ) ) {
			$this->rules[ $selector ] = array();
		}

		$declarations = $this->sanitize_declarations( $declarations );

		foreach ( $declarations as $prop => $val ) {
			$this->rules[ $selector ][ $prop ] = (string) $val;
		}
	}

	/**
	 * Read a style value from the nested style tree.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param array<int, string> $path Style path segments.
	 *
	 * @return string
	 */
	private function get_style_leaf( array $attrs, array $path ): string {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return '';
		}

		$node = $attrs['style'];
		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return '';
			}
			$node = $node[ $key ];
		}

		if ( is_array( $node ) || is_object( $node ) ) {
			return '';
		}

		return trim( (string) $node );
	}

	/**
	 * Remove a style leaf from the attributes tree.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param array<int, string> $path Style path segments.
	 *
	 * @return array<string, mixed>
	 */
	private function unset_style_leaf( array $attrs, array $path ): array {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return $attrs;
		}

		$ref = &$attrs['style'];

		$last = array_pop( $path );
		foreach ( $path as $key ) {
			if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
				return $attrs;
			}
			$ref = &$ref[ $key ];
		}

		if ( is_array( $ref ) && array_key_exists( $last, $ref ) ) {
			unset( $ref[ $last ] );
		}

		return $attrs;
	}

	/**
	 * Remove empty style structures after extractions.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function cleanup_empty_style( array $attrs ): array {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return $attrs;
		}

		$attrs['style'] = $this->recursive_prune_empty_arrays( $attrs['style'] );

		if ( empty( $attrs['style'] ) ) {
			unset( $attrs['style'] );
		}

		return $attrs;
	}

	/**
	 * Recursively prune empty arrays and empty scalar values.
	 *
	 * @param array<string, mixed> $node Style node.
	 *
	 * @return array<string, mixed>
	 */
	private function recursive_prune_empty_arrays( array $node ): array {
		foreach ( $node as $k => $v ) {
			if ( is_array( $v ) ) {
				$node[ $k ] = $this->recursive_prune_empty_arrays( $v );
				if ( empty( $node[ $k ] ) ) {
					unset( $node[ $k ] );
				}
			} elseif ( null === $v || '' === $v ) {
				unset( $node[ $k ] );
			}
		}

		return $node;
	}

	/**
	 * Append a class to a class list while keeping it unique.
	 *
	 * @param string $existing Existing class list.
	 * @param string $new_class Class to add.
	 *
	 * @return string
	 */
	private function append_class( string $existing, string $new_class ): string {
		$existing  = trim( $existing );
		$new_class = trim( $new_class );
		if ( '' === $new_class ) {
			return $existing;
		}

		$list = '' === $existing ? array() : preg_split( '/\s+/', $existing );
		$list = is_array( $list ) ? $list : array();

		$list[] = $new_class;

		$unique = array();
		foreach ( $list as $c ) {
			$c = trim( (string) $c );
			if ( '' === $c ) {
				continue;
			}
			$unique[ $c ] = true;
		}

		return implode( ' ', array_keys( $unique ) );
	}

	/**
	 * Normalize a background image declaration into a url(...) value.
	 *
	 * @param string $raw Raw background image value.
	 *
	 * @return string
	 */
	private function format_background_image( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		// If it already looks like url(...), keep it.
		if ( 0 === strpos( $raw, 'url(' ) ) {
			return $raw;
		}

		$raw = trim( $raw, "\"'" );
		$raw = str_replace( '"', '\\"', $raw );

		return 'url("' . $raw . '")';
	}

	/**
	 * Returns "0" when the value is zero-ish; null when it is non-zero and should be externalized.
	 *
	 * @param string $value
	 *
	 * @return string|null
	 */
	private function normalize_zero_dimension( string $value ): ?string {
		$v = strtolower( trim( $value ) );

		if ( '0' === $v || '0px' === $v || '0em' === $v || '0rem' === $v ) {
			return '0';
		}

		if ( preg_match( '/^0(\.0+)?(px|em|rem|%)?$/', $v ) ) {
			return '0';
		}

		return null;
	}

	/**
	 * Normalize declarations before persisting them.
	 *
	 * @param array $declarations Raw declarations.
	 *
	 * @return array
	 */
	private function sanitize_declarations( array $declarations ): array {
		$sanitized = array();
		foreach ( $declarations as $prop => $val ) {
			$prop = trim( (string) $prop );
			if ( '' === $prop ) {
				continue;
			}

			if ( 'background-image' === $prop ) {
				$val = $this->format_background_image( (string) $val );
			}

			$sanitized[ $prop ] = $val;
		}

		return $sanitized;
	}

	/**
	 * Append !important to every declaration value.
	 *
	 * @param array $declarations Declarations to update.
	 *
	 * @return array
	 */
	private function append_important_declarations( array $declarations ): array {
		$updated = array();

		foreach ( $declarations as $prop => $val ) {
			$prop = trim( (string) $prop );
			if ( '' === $prop ) {
				continue;
			}

			$val = trim( (string) $val );
			if ( '' === $val ) {
				continue;
			}

			$updated[ $prop ] = $val;
		}

		return $updated;
	}
}
