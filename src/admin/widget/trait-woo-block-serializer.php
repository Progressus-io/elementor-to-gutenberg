<?php

namespace Progressus\Gutenberg\Admin\Widget;

use function is_array;

defined( 'ABSPATH' ) || exit;

trait Woo_Block_Serializer_Trait {
	/**
	 * Determine if a block type is registered.
	 *
	 * @param string $block_name Block type name.
	 *
	 * @return bool
	 */
	private function is_block_registered( string $block_name ): bool {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return false;
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		return is_object( $block_type );
	}

	/**
	 * Serialize a block with attributes and inner HTML.
	 *
	 * @param string $block_name Block type name.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param string $inner_html Inner HTML.
	 *
	 * @return string
	 */
	private function serialize_block( string $block_name, array $attrs, string $inner_html ): string {
		$parsed = array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => '' === $inner_html ? array() : array( $inner_html ),
		);

		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block( $parsed ) . "\n";
		}

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( '' === $inner_html ) {
			return sprintf( '<!-- wp:%s%s /-->%s', sanitize_key( $block_name ), $attr_json, "\n" );
		}

		return sprintf(
			"<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->\n",
			sanitize_key( $block_name ),
			$attr_json,
			$inner_html,
			sanitize_key( $block_name )
		);
	}

	/**
	 * Serialize a preferred block or fallback to a shortcode block.
	 *
	 * @param string $preferred_block Preferred block type.
	 * @param string $fallback_shortcode Fallback shortcode.
	 * @param array<string, mixed> $attrs Block attributes.
	 *
	 * @return string
	 */
	private function serialize_block_or_shortcode( string $preferred_block, string $fallback_shortcode, array $attrs = array() ): string {
		if ( $this->is_block_registered( $preferred_block ) ) {
			return $this->serialize_block( $preferred_block, $attrs, '' );
		}

		return $this->serialize_block( 'core/shortcode', array(), $fallback_shortcode );
	}

	/**
	 * Serialize the first registered block from a list.
	 *
	 * @param array<int, string> $candidates Candidate block types.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param string $inner_html Inner HTML.
	 *
	 * @return string
	 */
	private function serialize_first_registered_block( array $candidates, array $attrs = array(), string $inner_html = '' ): string {
		foreach ( $candidates as $name ) {
			$name = (string) $name;
			if ( '' !== $name && $this->is_block_registered( $name ) ) {
				return $this->serialize_block( $name, $attrs, $inner_html );
			}
		}

		return '';
	}

	/**
	 * Get block pattern content by name.
	 *
	 * @param string $pattern_name Pattern name.
	 *
	 * @return string
	 */
	private function get_block_pattern_content( string $pattern_name ): string {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return '';
		}

		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name );
		if ( ! is_array( $pattern ) ) {
			return '';
		}

		$content = isset( $pattern['content'] ) ? (string) $pattern['content'] : '';
		return $content;
	}
}
