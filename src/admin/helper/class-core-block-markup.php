<?php
/**
 * Builds a handful of core blocks exactly as their save() writes them.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function esc_attr;
use function esc_url;
use function serialize_block;
use function wp_kses;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes simple core blocks for the handlers that only carry content over.
 */
class Core_Block_Markup {

	/**
	 * Inline tags a heading, paragraph or button may keep.
	 */
	private const INLINE_TAGS = array(
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'u'      => array(),
		's'      => array(),
		'sub'    => array(),
		'sup'    => array(),
		'code'   => array(),
		'mark'   => array(),
		'br'     => array(),
	);

	/**
	 * Reduce HTML to the inline tags rich text keeps.
	 *
	 * @param string $html Raw HTML or text.
	 *
	 * @return string Inline HTML.
	 */
	public static function inline_html( string $html ): string {
		return (string) preg_replace( '/\s*\R\s*/', '<br>', trim( wp_kses( $html, self::INLINE_TAGS ) ) );
	}

	/**
	 * Whether a value holds block-level HTML that a single paragraph cannot carry.
	 *
	 * @param string $html Raw HTML or text.
	 *
	 * @return bool
	 */
	public static function has_block_html( string $html ): bool {
		return 1 === preg_match( '#<(p|div|ul|ol|li|h[1-6]|table|blockquote|figure|img|iframe|section|article|pre|hr)\b#i', $html );
	}

	/**
	 * Build a core/heading block.
	 *
	 * @param string $html  Heading content.
	 * @param int    $level Heading level, 1 to 6.
	 *
	 * @return string Serialized block, or an empty string without content.
	 */
	public static function heading( string $html, int $level = 2 ): string {
		$html = self::inline_html( $html );
		if ( '' === $html ) {
			return '';
		}

		$level = min( 6, max( 1, $level ) );
		$attrs = 2 === $level ? array() : array( 'level' => $level );

		return self::serialize( 'core/heading', $attrs, '<h' . $level . ' class="wp-block-heading">' . $html . '</h' . $level . '>' );
	}

	/**
	 * Build a core/paragraph block.
	 *
	 * @param string $html       Paragraph content.
	 * @param string $class_name Optional class.
	 *
	 * @return string Serialized block, or an empty string without content.
	 */
	public static function paragraph( string $html, string $class_name = '' ): string {
		$html = self::inline_html( $html );
		if ( '' === $html ) {
			return '';
		}

		if ( '' === $class_name ) {
			return self::serialize( 'core/paragraph', array(), '<p>' . $html . '</p>' );
		}

		return self::serialize( 'core/paragraph', array( 'className' => $class_name ), '<p class="' . esc_attr( $class_name ) . '">' . $html . '</p>' );
	}

	/**
	 * Build a core/buttons block holding one button.
	 *
	 * @param string $text    Button text.
	 * @param string $url     Button URL.
	 * @param bool   $new_tab Whether the link opens in a new tab.
	 *
	 * @return string Serialized block, or an empty string without text.
	 */
	public static function button( string $text, string $url, bool $new_tab = false ): string {
		$text = trim( wp_kses( $text, array_diff_key( self::INLINE_TAGS, array( 'a' => true ) ) ) );
		if ( '' === $text ) {
			return '';
		}

		$link_attributes = 'class="wp-block-button__link wp-element-button"';
		if ( '' !== $url ) {
			$link_attributes .= ' href="' . esc_url( $url ) . '"';
		}
		if ( $new_tab && '' !== $url ) {
			$link_attributes .= ' target="_blank" rel="noreferrer noopener"';
		}

		$button = "<!-- wp:button -->\n" . '<div class="wp-block-button"><a ' . $link_attributes . '>' . $text . '</a></div>' . "\n<!-- /wp:button -->";

		return "<!-- wp:buttons -->\n" . '<div class="wp-block-buttons">' . $button . '</div>' . "\n<!-- /wp:buttons -->\n";
	}

	/**
	 * Build a core/image block.
	 *
	 * @param string $url     Image URL.
	 * @param int    $id      Attachment ID, or zero.
	 * @param string $alt     Alternative text.
	 * @param string $link    Optional link URL.
	 * @param bool   $new_tab Whether the link opens in a new tab.
	 *
	 * @return string Serialized block, or an empty string without a URL.
	 */
	public static function image( string $url, int $id = 0, string $alt = '', string $link = '', bool $new_tab = false ): string {
		if ( '' === $url ) {
			return '';
		}

		$attrs = array();
		$image = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
		if ( $id > 0 ) {
			$attrs['id'] = $id;
			$image      .= ' class="wp-image-' . $id . '"';
		}
		$image .= '/>';

		$attrs['sizeSlug'] = 'full';
		if ( '' !== $link ) {
			$attrs['linkDestination'] = 'custom';
			$image                    = '<a href="' . esc_url( $link ) . '"' . ( $new_tab ? ' target="_blank" rel="noreferrer noopener"' : '' ) . '>' . $image . '</a>';
		}

		return self::serialize( 'core/image', $attrs, '<figure class="wp-block-image size-full">' . $image . '</figure>' );
	}

	/**
	 * Build a core/html block.
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string Serialized block, or an empty string without content.
	 */
	public static function html( string $html ): string {
		$html = self::escape_block_delimiters( $html );
		if ( '' === $html ) {
			return '';
		}

		return self::serialize( 'core/html', array(), $html );
	}

	/**
	 * Keep raw HTML from opening or closing a block of its own.
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string Trimmed HTML whose comments no longer read as block delimiters.
	 */
	public static function escape_block_delimiters( string $html ): string {
		return trim( str_replace( array( '<!-- wp:', '<!-- /wp:' ), array( '<!-- wp :', '<!-- /wp :' ), $html ) );
	}

	/**
	 * Build a core/separator block.
	 *
	 * @return string Serialized block.
	 */
	public static function separator(): string {
		return self::serialize( 'core/separator', array(), '<hr class="wp-block-separator has-alpha-channel-opacity"/>' );
	}

	/**
	 * Serialize a block that has no inner blocks.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @param string $html  Block HTML.
	 *
	 * @return string Serialized block.
	 */
	private static function serialize( string $name, array $attrs, string $html ): string {
		$html = "\n" . $html . "\n";

		return serialize_block(
			array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			)
		) . "\n";
	}
}
