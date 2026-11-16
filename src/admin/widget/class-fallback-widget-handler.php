<?php
/**
 * Fallback for widgets that have no dedicated handler.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Content_Extractor;
use Progressus\BlockShift\Admin\Helper\Core_Block_Markup;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function esc_html;
use function esc_url;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the text, links and images of an unsupported widget as core blocks.
 */
class Fallback_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Class of the editor-only note left where a widget had no dedicated conversion.
	 */
	public const NOTICE_CLASS = 'blockshift-unsupported-widget';

	/**
	 * Convert the content found in a widget's settings into core blocks.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content, led by an editor-only note.
	 */
	public function handle( array $element ): string {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$content  = $this->render_items( Content_Extractor::extract( $settings ) );

		return self::build_notice( $element, '' !== $content ) . $content;
	}

	/**
	 * Render extracted content items as core blocks.
	 *
	 * @param array<int, array<string, mixed>> $items Items from Content_Extractor::extract().
	 *
	 * @return string Serialized blocks.
	 */
	public function render_items( array $items ): string {
		$blocks = '';
		foreach ( $items as $item ) {
			$text    = isset( $item['text'] ) ? (string) $item['text'] : '';
			$link    = isset( $item['link'] ) ? (string) $item['link'] : '';
			$new_tab = ! empty( $item['new_tab'] );

			switch ( $item['kind'] ) {
				case 'heading':
					$blocks .= Core_Block_Markup::heading( $this->link_text( $text, $link, $new_tab ), 3 );
					break;
				case 'text':
					$blocks .= Core_Block_Markup::paragraph( $this->link_text( $text, $link, $new_tab ) );
					break;
				case 'html':
					$blocks .= ( new Text_Editor_Widget_Handler() )->handle( array( 'settings' => array( 'editor' => $text ) ) );
					break;
				case 'button':
					$blocks .= Core_Block_Markup::button( $text, $link, $new_tab );
					break;
				case 'image':
					$blocks .= Core_Block_Markup::image( (string) $item['url'], (int) $item['id'], (string) $item['alt'], $link, $new_tab );
					break;
				case 'link':
					$blocks .= Core_Block_Markup::paragraph( $this->link_text( (string) $item['url'], (string) $item['url'], $new_tab ) );
					break;
			}
		}

		return $blocks;
	}

	/**
	 * Build the note that marks a widget without a dedicated conversion.
	 *
	 * The note carries the class the front end hides, so only editors see it.
	 *
	 * @param array $element      The Elementor element data.
	 * @param bool  $kept_content Whether any of the widget's content was carried over.
	 *
	 * @return string Serialized paragraph block.
	 */
	public static function build_notice( array $element, bool $kept_content ): string {
		$name = trim( (string) ( $element['widgetType'] ?? ( $element['elType'] ?? '' ) ) );
		if ( '' === $name ) {
			$name = 'unknown';
		}

		if ( $kept_content ) {
			/* translators: %s: Elementor widget type. */
			$text = sprintf( __( 'Unsupported widget: %s. Its text, links and images were kept below; rebuild the rest with blocks.', 'migrate-off-elementor' ), $name );
		} else {
			/* translators: %s: Elementor widget type. */
			$text = sprintf( __( 'Unsupported widget: %s', 'migrate-off-elementor' ), $name );
		}

		return Core_Block_Markup::paragraph( esc_html( $text ), self::NOTICE_CLASS );
	}

	/**
	 * Wrap text in a link when a URL is given.
	 *
	 * @param string $text    Text or inline HTML.
	 * @param string $url     Link URL, or an empty string.
	 * @param bool   $new_tab Whether the link opens in a new tab.
	 *
	 * @return string Text, linked when a URL is given and the text holds no link yet.
	 */
	private function link_text( string $text, string $url, bool $new_tab ): string {
		if ( '' === $url || false !== stripos( $text, '<a ' ) ) {
			return $text;
		}

		return '<a href="' . esc_url( $url ) . '"' . ( $new_tab ? ' target="_blank" rel="noreferrer noopener"' : '' ) . '>' . Core_Block_Markup::inline_html( $text ) . '</a>';
	}
}
