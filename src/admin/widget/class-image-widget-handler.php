<?php
/**
 * Widget handler for Elementor image widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor image widget.
 */
class Image_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor image to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$url           = $settings['image']['url'] ?? '';
		$alt           = $settings['image']['alt'] ?? '';
		$new_url       = File_Upload_Service::download_and_upload( $url ) ?? $url;
		$attachment_id = ! empty( $new_url ) ? attachment_url_to_postid( $new_url ) : 0;

		$attrs_array = array(
			'id'            => $attachment_id,
			'sizeSlug'      => 'full',
			'linkDestination' => 'none',
		);
		$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );

		if ( isset( $settings['align'] ) ) {
			$attrs_array['align'] = $settings['align'];
		}
		if ( isset( $settings['width']['size'] ) && '' !== $settings['width']['size'] ) {
			$attrs_array['width'] = $settings['width']['size'] . ( $settings['width']['unit'] ?? '%' );
		}
		if ( isset( $settings['height']['size'] ) && '' !== $settings['height']['size'] ) {
			$attrs_array['height'] = $settings['height']['size'] . ( $settings['height']['unit'] ?? '%' );
		}
		if ( isset( $settings['space']['size'] ) && '' !== $settings['space']['size'] ) {
			$attrs_array['space'] = $settings['space']['size'] . ( $settings['space']['unit'] ?? '%' );
		}
		if ( isset( $settings['premium_tooltip_text'] ) ) {
			$attrs_array['premiumTooltipText'] = $settings['premium_tooltip_text'];
		}
		if ( isset( $settings['premium_tooltip_position'] ) ) {
			$attrs_array['premiumTooltipPosition'] = $settings['premium_tooltip_position'];
		}

		$classes = ! empty( $settings['align'] ) ? 'align' . esc_attr( $settings['align'] ) : '';
		$attrs   = wp_json_encode( $attrs_array );
		$img_tag = sprintf(
			'<img src="%s" alt="%s" class="wp-image-%d" />',
			esc_url( $new_url ),
			esc_attr( $alt ),
			esc_attr( $attachment_id )
		);

		$block_content = sprintf(
			'<!-- wp:image %s --><figure class="wp-block-image %s">%s</figure><!-- /wp:image -->' . "\n",
			$attrs,
			$classes,
			$img_tag
		);

		return $block_content;
	}
}