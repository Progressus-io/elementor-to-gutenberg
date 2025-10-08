<?php
/**
 * Helper for parsing Elementor elements recursively into Gutenberg blocks.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use Progressus\Gutenberg\Admin\Widget_Handler_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class to convert Elementor elements array into Gutenberg block markup.
 */
class Elementor_Elements_Parser {

	/**
	 * Parse Elementor elements to Gutenberg blocks.
	 *
	 * Mirrors Admin_Settings::parse_elementor_elements to allow reuse in
	 * handlers that need recursive parsing of nested structures.
	 *
	 * @param array $elements Elementor elements array.
	 *
	 * @return string Gutenberg block markup.
	 */
	public static function parse( array $elements ): string {
		$block_content = '';
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'container' === $element['elType'] ) {
				$inner         = ! empty( $element['elements'] ) ? self::parse( $element['elements'] ) : '';
				$block_content .= sprintf(
					'<!-- wp:group --><div class="wp-block-group">%s</div><!-- /wp:group -->' . "\n",
					$inner
				);
			} elseif ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$handler = Widget_Handler_Factory::get_handler( $element['widgetType'] );
				if ( null !== $handler ) {
					$block_content .= $handler->handle( $element );
				} else {
					$block_content .= sprintf(
						'<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
						esc_html( $element['widgetType'] )
					);
				}
			} else {
				$block_content .= sprintf(
					'<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
					esc_html__( 'Unknown element', 'elementor-to-gutenberg' )
				);
			}
		}

		return $block_content;
	}
}