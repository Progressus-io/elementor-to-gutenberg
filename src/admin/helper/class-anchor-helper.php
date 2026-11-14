<?php
/**
 * Carries an Elementor Menu Anchor over as the HTML anchor of a block.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function block_has_support;
use function esc_attr;
use function parse_blocks;
use function serialize_block;
use function serialize_block_attributes;

defined( 'ABSPATH' ) || exit;

/**
 * Sets an anchor on converted markup so `#anchor` links keep working.
 */
class Anchor_Helper {

	/**
	 * Clean a Menu Anchor value into a usable element ID.
	 *
	 * @param string $anchor Raw anchor from the widget.
	 *
	 * @return string Anchor without the leading hash, whitespace or quotes.
	 */
	public static function sanitize( string $anchor ): string {
		return (string) preg_replace( '/[\s"\'<>#]+/', '', $anchor );
	}

	/**
	 * Set an anchor on the first block of some converted markup.
	 *
	 * A block that cannot take an anchor, or already has one, gets an empty
	 * anchored group placed in front of it instead.
	 *
	 * @param string $content Serialized blocks.
	 * @param string $anchor  Sanitized anchor.
	 *
	 * @return string Serialized blocks carrying the anchor.
	 */
	public static function apply( string $content, string $anchor ): string {
		if ( '' === $anchor ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		foreach ( $blocks as $index => $block ) {
			if ( null === $block['blockName'] ) {
				continue;
			}

			$anchored = self::add_anchor_to_block( $block, $anchor );
			if ( null === $anchored ) {
				break;
			}

			$blocks[ $index ] = $anchored;

			return serialize_blocks( $blocks );
		}

		return self::build_anchor_block( $anchor ) . $content;
	}

	/**
	 * Build an empty group that only carries an anchor.
	 *
	 * @param string $anchor Sanitized anchor.
	 *
	 * @return string Serialized group block.
	 */
	public static function build_anchor_block( string $anchor ): string {
		return '<!-- wp:group ' . serialize_block_attributes( array( 'anchor' => $anchor ) ) . " -->\n"
			. '<div id="' . esc_attr( $anchor ) . '" class="wp-block-group"></div>'
			. "\n<!-- /wp:group -->\n";
	}

	/**
	 * Set the anchor on a parsed block and on the root element of its markup.
	 *
	 * @param array  $block  Parsed block.
	 * @param string $anchor Sanitized anchor.
	 *
	 * @return array|null The anchored block, or null when the block cannot take the anchor.
	 */
	private static function add_anchor_to_block( array $block, string $anchor ): ?array {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
		if ( null === $block_type || ! block_has_support( $block_type, array( 'anchor' ), false ) ) {
			return null;
		}

		if ( ! empty( $block['attrs']['anchor'] ) || ! is_string( $block['innerContent'][0] ?? null ) ) {
			return null;
		}

		$processor = new \WP_HTML_Tag_Processor( $block['innerContent'][0] );
		if ( ! $processor->next_tag() || null !== $processor->get_attribute( 'id' ) ) {
			return null;
		}

		$processor->set_attribute( 'id', $anchor );
		$block['innerContent'][0] = $processor->get_updated_html();
		$block['attrs']['anchor'] = $anchor;

		return $block;
	}
}
