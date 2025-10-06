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
		$custom_class  = $settings['_css_classes'] ?? '';
		$custom_id     = $settings['_element_id'] ?? '';
		$custom_css    = $settings['custom_css'] ?? '';
		$size_slug     = 'full';

		// Spacing.
		$spacing = Style_Parser::parse_spacing( $settings );

		$border_width  = $settings['image_border_width'] ?? array();
		$border_radius = $settings['image_border_radius'] ?? array();

		$border_attr      = array();
		$border_style_css = '';

		if ( ! empty( $border_width['top'] ) ) {
			$border_attr['width'] = $border_width['top'] . $border_width['unit'];
			$border_style_css .= 'border-width:' . esc_attr( $border_width['top'] . $border_width['unit'] ) . ';';
		}
		if ( ! empty( $border_radius['top'] ) ) {
			$border_attr['radius'] = $border_radius['top'] . $border_radius['unit'];
			$border_style_css .= 'border-radius:' . esc_attr( $border_radius['top'] . $border_radius['unit'] ) . ';';
		}

		// Build attributes.
		$attrs_array = array(
			'id'              => $attachment_id,
			'sizeSlug'        => $size_slug,
			'linkDestination' => $settings['link_to'] === 'custom' ? 'custom' : 'none',
			'className'       => 'is-style-default ' . trim( $custom_class ),
		);

		if ( ! empty( $settings['align'] ) ) {
			$custom_class .= ' align' . esc_attr( $settings['align'] );
			$attrs_array['align'] = $settings['align'];
		}

		// Width from Elementor.
		if ( isset( $settings['width']['size'] ) && '' !== $settings['width']['size'] ) {
			$attrs_array['width'] = $settings['width']['size'] . ( $settings['width']['unit'] ?? 'px' );
		}

		if ( ! empty( $spacing['attributes'] ) ) {
			$attrs_array['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border_attr ) ) {
			$attrs_array['style']['border'] = $border_attr;
		}

		// Classes for figure.
		$figure_class = 'wp-block-image size-' . $size_slug;
		if ( isset( $attrs_array['width'] ) ) {
			$figure_class .= ' is-resized';
		}
		if ( ! empty( $border_attr ) ) {
			$figure_class .= ' has-custom-border';
		}
		$figure_class .= ' is-style-default ' . trim( $custom_class );

		// Inline style for <figure>.
		$figure_style = $spacing['style'];

		// Inline style for <img>.
		$img_style = $border_style_css;
		if ( isset( $attrs_array['width'] ) ) {
			$img_style .= 'width:' . esc_attr( $attrs_array['width'] ) . ';';
		}

		// Encode attrs.
		$attrs = wp_json_encode( $attrs_array );

		// <img> tag.
		$img_tag = sprintf(
			'<img src="%s" alt="%s" class="wp-image-%d"%s />',
			esc_url( $new_url ),
			esc_attr( $alt ),
			esc_attr( $attachment_id ),
			$img_style ? ' style="' . esc_attr( $img_style ) . '"' : ''
		);

		// Wrap with <a> if link_to = custom.
		if ( $settings['link_to'] === 'custom' && ! empty( $settings['link']['url'] ) ) {
			$img_tag = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings['link']['url'] ),
				$img_tag
			);
		}

		// Final block.
		$block_content = sprintf(
			'<!-- wp:image %1s --><figure id="%2s" class="%3s"%4s>%5s</figure><!-- /wp:image -->' . "\n",
			$attrs,
			esc_attr( $custom_id ),
			esc_attr( trim( $figure_class ) ),
			$figure_style ? ' style="' . esc_attr( $figure_style ) . '"' : '',
			$img_tag
		);

		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return $block_content;
	}
}
