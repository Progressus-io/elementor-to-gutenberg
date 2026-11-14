<?php
/**
 * Finds the text, links and images stored in the settings of any Elementor widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Walks widget settings without knowing the widget and returns its content in order.
 */
class Content_Extractor {

	/**
	 * Setting keys that normally hold visible text.
	 */
	private const TEXT_KEY_PATTERN = '/title|heading|headline|text|label|name|content|description|caption|editor|quote|price|prefix|suffix|button|badge|ribbon|job|author|message|period|feature|question|answer|testimonial|number|address|phone|email|html/i';

	/**
	 * Setting keys that hold styling or behaviour rather than content.
	 */
	private const STYLE_KEY_PATTERN = '/color|typography|align|size|style|icon|animation|position|width|height|spacing|padding|margin|border|background|shadow|tag$|view$|skin|layout|type$|_id$|^id$|class|css|hover|transition|opacity|radius|gap|overflow|z_index|order|direction|wrap|shape|motion|sticky|display|condition|attributes|placeholder|source|columns|target|format|separator$|shortcode/i';

	/**
	 * Setting keys whose text is a title rather than body copy.
	 */
	private const HEADING_KEY_PATTERN = '/title|heading|headline|name|question/i';

	/**
	 * Setting keys whose text belongs on a button.
	 */
	private const BUTTON_KEY_PATTERN = '/button|cta|link_text/i';

	/**
	 * Setting values that are switches or presets, never content.
	 */
	private const KEYWORD_VALUES = array( 'yes', 'no', 'true', 'false', 'none', 'default', 'custom', 'auto', 'inherit', 'show', 'hide' );

	/**
	 * Extract the content of a widget from its settings.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<int, array<string, mixed>> Items with a `kind` of heading, text, html, button, image or link.
	 */
	public static function extract( array $settings ): array {
		$items = array();
		self::walk( $settings, $items, 0 );

		return $items;
	}

	/**
	 * Extract the plain-text snippets a converted widget should still show.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<int, string> Plain-text snippets of three characters or more.
	 */
	public static function extract_plain_texts( array $settings ): array {
		$texts = array();
		foreach ( self::extract( $settings ) as $item ) {
			if ( ! isset( $item['text'] ) ) {
				continue;
			}

			$text = self::normalize_text( (string) $item['text'] );
			if ( strlen( $text ) >= 3 ) {
				$texts[] = $text;
			}
		}

		return array_values( array_unique( $texts ) );
	}

	/**
	 * Reduce HTML or text to comparable plain text.
	 *
	 * @param string $text HTML or text.
	 *
	 * @return string Plain text with single spaces.
	 */
	public static function normalize_text( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( str_replace( '<', ' <', $text ) ), ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', $text ) ) );
	}

	/**
	 * Collect the content of one level of settings, then of its repeaters.
	 *
	 * @param array $settings Settings at this level.
	 * @param array $items    Collected items, by reference.
	 * @param int   $depth    Current nesting depth.
	 *
	 * @return void
	 */
	private static function walk( array $settings, array &$items, int $depth ): void {
		if ( $depth > 4 ) {
			return;
		}

		$level     = array();
		$links     = array();
		$repeaters = array();

		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] ) {
				continue;
			}

			if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
				$item = self::classify_scalar( $key, trim( (string) $value ) );
				if ( null !== $item ) {
					if ( 'link' === $item['kind'] ) {
						$links[] = $item;
					} else {
						$level[] = $item;
					}
				}
				continue;
			}

			if ( ! is_array( $value ) || empty( $value ) ) {
				continue;
			}

			if ( isset( $value['url'] ) ) {
				$item = self::classify_media( $key, $value );
				if ( null !== $item && 'link' === $item['kind'] ) {
					$links[] = $item;
				} elseif ( null !== $item ) {
					$level[] = $item;
				}
				continue;
			}

			if ( isset( $value[0] ) && is_array( $value[0] ) ) {
				$repeaters[] = $value;
			}
		}

		foreach ( $links as $link ) {
			$level = self::attach_link( $level, $link );
		}

		foreach ( $level as $item ) {
			$items[] = $item;
		}

		foreach ( $repeaters as $rows ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					self::walk( $row, $items, $depth + 1 );
				}
			}
		}
	}

	/**
	 * Classify a scalar setting as content, a link, or nothing.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Trimmed setting value.
	 *
	 * @return array<string, mixed>|null Content item, or null for a non-content setting.
	 */
	private static function classify_scalar( string $key, string $value ): ?array {
		if ( '' === $value || in_array( strtolower( $value ), self::KEYWORD_VALUES, true ) ) {
			return null;
		}

		$is_style_key = 1 === preg_match( self::STYLE_KEY_PATTERN, $key );

		if ( 1 === preg_match( '#^https?://\S+$#i', $value ) ) {
			if ( ! $is_style_key && 1 === preg_match( '/url|link|href|website/i', $key ) ) {
				return array(
					'kind'    => 'link',
					'url'     => $value,
					'new_tab' => false,
				);
			}

			return null;
		}

		$is_text_key = ! $is_style_key && 1 === preg_match( self::TEXT_KEY_PATTERN, $key );
		$has_markup  = wp_strip_all_tags( $value ) !== $value;
		$is_prose    = strlen( $value ) >= 25 && false !== strpos( $value, ' ' );

		if ( ! $is_text_key && ( $is_style_key || ! ( $has_markup || $is_prose ) ) ) {
			return null;
		}

		if ( is_numeric( $value ) && 1 !== preg_match( '/number|price/i', $key ) ) {
			return null;
		}

		if ( '' === self::normalize_text( $value ) ) {
			return null;
		}

		if ( Core_Block_Markup::has_block_html( $value ) ) {
			return array(
				'kind' => 'html',
				'text' => $value,
			);
		}

		if ( 1 === preg_match( self::BUTTON_KEY_PATTERN, $key ) ) {
			return array(
				'kind'    => 'button',
				'text'    => $value,
				'url'     => '',
				'new_tab' => false,
			);
		}

		$is_heading = 1 === preg_match( self::HEADING_KEY_PATTERN, $key ) && strlen( $value ) <= 200;

		return array(
			'kind' => $is_heading ? 'heading' : 'text',
			'text' => $value,
		);
	}

	/**
	 * Classify an Elementor media or URL control value.
	 *
	 * @param string $key   Setting key.
	 * @param array  $value Control value holding a `url`.
	 *
	 * @return array<string, mixed>|null Image or link item, or null when empty or decorative.
	 */
	private static function classify_media( string $key, array $value ): ?array {
		$url = is_string( $value['url'] ) ? trim( $value['url'] ) : '';
		if ( '' === $url ) {
			return null;
		}

		$is_image = ( isset( $value['id'] ) && is_numeric( $value['id'] ) && (int) $value['id'] > 0 )
			|| 1 === preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)(\?|$)/i', $url );

		if ( $is_image && ! array_key_exists( 'is_external', $value ) ) {
			if ( 1 === preg_match( '/background|bg_|overlay|hover/i', $key ) ) {
				return null;
			}

			return array(
				'kind' => 'image',
				'url'  => $url,
				'id'   => isset( $value['id'] ) && is_numeric( $value['id'] ) ? (int) $value['id'] : 0,
				'alt'  => isset( $value['alt'] ) && is_string( $value['alt'] ) ? $value['alt'] : '',
			);
		}

		if ( '#' === $url || 1 !== preg_match( '/link|url|href|website/i', $key ) ) {
			return null;
		}

		return array(
			'kind'    => 'link',
			'url'     => $url,
			'new_tab' => ! empty( $value['is_external'] ),
		);
	}

	/**
	 * Give a link to the content it belongs to on the same settings level.
	 *
	 * A button without a URL takes it first, then the first heading or text, and
	 * a link with nothing to sit on becomes an item of its own.
	 *
	 * @param array $level Items of the current settings level.
	 * @param array $link  Link item.
	 *
	 * @return array Items with the link attached.
	 */
	private static function attach_link( array $level, array $link ): array {
		foreach ( array( array( 'button' ), array( 'heading', 'text' ), array( 'image' ) ) as $kinds ) {
			foreach ( $level as $index => $item ) {
				if ( in_array( $item['kind'], $kinds, true ) && empty( $item['url_set'] ) ) {
					$level[ $index ]['link']    = $link['url'];
					$level[ $index ]['new_tab'] = $link['new_tab'];
					$level[ $index ]['url_set'] = true;

					return $level;
				}
			}
		}

		$level[] = $link;

		return $level;
	}
}
