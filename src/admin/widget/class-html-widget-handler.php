<?php
/**
 * Widget handler for the Elementor HTML widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Helper\Core_Block_Markup;
use Progressus\BlockShift\Admin\Widget_Handler_Interface;

use function current_user_can;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Converts the Elementor HTML widget into a core/html block.
 */
class Html_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Markup of the HTML widgets converted so far, keyed by the token standing in for it.
	 *
	 * @var array<string, string>
	 */
	private static array $raw_markup = array();

	/**
	 * Handle conversion of the Elementor HTML widget.
	 *
	 * The markup is kept as written only for a user allowed to post unfiltered
	 * HTML; for anyone else it goes through the post content filter. The block
	 * holds a token until restore() runs, because the wrapping blocks strip
	 * script and style elements from everything nested in them.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$html = isset( $element['settings']['html'] ) && is_string( $element['settings']['html'] ) ? $element['settings']['html'] : '';

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return Core_Block_Markup::html( wp_kses_post( $html ) );
		}

		$html = Core_Block_Markup::escape_block_delimiters( $html );
		if ( '' === $html ) {
			return '';
		}

		$token                      = 'BLOCKSHIFT_RAW_HTML_' . count( self::$raw_markup ) . '_TOKEN';
		self::$raw_markup[ $token ] = $html;

		return Core_Block_Markup::html( $token );
	}

	/**
	 * Put the markup of the converted HTML widgets back in place of their tokens.
	 *
	 * @param string $content Converted content.
	 *
	 * @return string Content with the HTML widget markup restored.
	 */
	public static function restore( string $content ): string {
		return empty( self::$raw_markup ) ? $content : strtr( $content, self::$raw_markup );
	}

	/**
	 * Forget the markup kept for the previous conversion.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$raw_markup = array();
	}
}
