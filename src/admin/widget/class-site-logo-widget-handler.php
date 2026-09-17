<?php
/**
 * Site Logo Widget Handler
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\BlockShift\Admin\Widget_Handler_Interface;
use Progressus\BlockShift\Admin\Helper\Style_Parser;

/**
 * Widget handler for Elementor theme-site-logo widget.
 */
class Site_Logo_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Convert Elementor theme-site-logo widget to Gutenberg site-logo block.
	 *
	 * @param array $element Elementor widget data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$settings   = $element['settings'] ?? array();
		$custom_css = $settings['custom_css'] ?? '';

		// Build block attributes from Elementor settings.
		$attributes = array();

		// Width - extract from width setting.
		$width = null;
		if ( isset( $settings['width']['size'] ) ) {
			$width = (int) $settings['width']['size'];
		} elseif ( isset( $settings['width'] ) && is_numeric( $settings['width'] ) ) {
			$width = (int) $settings['width'];
		}

		if ( $width ) {
			$attributes['width'] = $width;
		}

		// Class name - check if image has border radius or other style that suggests rounded.
		$class_name    = '';
		$border_radius = $settings['image_border_radius'] ?? array();
		if ( ! empty( $border_radius['top'] ) && (int) $border_radius['top'] > 0 ) {
			$class_name = 'is-style-rounded';
		}

		if ( ! empty( $class_name ) ) {
			$attributes['className'] = $class_name;
		}

		// Spacing - use Style_Parser for margin/padding.
		$spacing['attributes'] = Style_Parser::parse_spacing( $settings );
		$spacing               = array();
		if ( ! empty( $spacing['attributes'] ) ) {
			$attributes['style']['spacing'] = $spacing['attributes'];
		}

		// Add background color if present.
		$bg_color = $settings['_background_color'] ?? '';
		if ( ! empty( $bg_color ) ) {
			if ( ! isset( $attributes['style'] ) ) {
				$attributes['style'] = array();
			}
			$attributes['style']['color'] = array(
				'background' => $bg_color,
			);
		}

		/*
		 * core/site-logo always renders the logo set for the site, so a widget that
		 * overrides it with its own picture - which is how a dark header shows a
		 * white logo while the site logo stays dark - has to become an image block
		 * instead, or the converted header shows the wrong file.
		 */
		$custom_logo = $this->resolve_custom_logo( $settings );
		if ( '' !== $custom_logo['url'] ) {
			return $this->build_custom_logo_block( $custom_logo, $width, (string) $custom_css );
		}

		// Encode attributes for the block.
		$attributes_json = wp_json_encode( $attributes );

		// Generate the complete site-logo block markup (self-closing).
		$block_content = '<!-- wp:site-logo ' . $attributes_json . ' /-->';

		// Save custom CSS to the Customizer's Additional CSS.
		if ( ! empty( $custom_css ) ) {
			Style_Parser::note_skipped_custom_css( $custom_css );
		}

		return $block_content . "\n";
	}

	/**
	 * Resolve a logo picture the widget supplies in place of the site logo.
	 *
	 * @param array $settings Elementor widget settings.
	 *
	 * @return array{url:string,id:int}
	 */
	private function resolve_custom_logo( array $settings ): array {
		$none = array(
			'url' => '',
			'id'  => 0,
		);

		$uses_custom = isset( $settings['site_logo_fallback'] )
			&& in_array( strtolower( (string) $settings['site_logo_fallback'] ), array( 'yes', 'true', '1' ), true );

		if ( ! $uses_custom || ! isset( $settings['custom_image'] ) || ! is_array( $settings['custom_image'] ) ) {
			return $none;
		}

		$url = Style_Parser::resolve_media_url( $settings['custom_image'] );
		if ( '' === $url ) {
			$url = isset( $settings['custom_image']['url'] ) ? esc_url_raw( (string) $settings['custom_image']['url'] ) : '';
		}

		if ( '' === $url ) {
			return $none;
		}

		return array(
			'url' => $url,
			'id'  => isset( $settings['custom_image']['id'] ) ? (int) $settings['custom_image']['id'] : 0,
		);
	}

	/**
	 * Build the image block that stands in for an overridden site logo.
	 *
	 * @param array    $logo       Resolved logo url and attachment id.
	 * @param int|null $width      Width the widget asks for, in pixels.
	 * @param string   $custom_css Widget custom CSS, recorded rather than emitted.
	 */
	private function build_custom_logo_block( array $logo, ?int $width, string $custom_css ): string {
		$attributes = array(
			'url'             => $logo['url'],
			'sizeSlug'        => 'full',
			'linkDestination' => 'custom',
		);

		if ( $logo['id'] > 0 ) {
			$attributes['id'] = $logo['id'];
		}
		if ( $width ) {
			$attributes['width'] = $width . 'px';
		}

		/*
		 * A sized image block marks the figure `is-resized` and puts the width in
		 * the image's own style, not in a `width` attribute. Writing it the other
		 * way left the header template part unopenable in the editor.
		 */
		$img = '<img src="' . esc_url( $logo['url'] ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"';
		if ( $logo['id'] > 0 ) {
			$img .= ' class="wp-image-' . esc_attr( (string) $logo['id'] ) . '"';
		}
		if ( $width ) {
			$img .= ' style="width:' . esc_attr( (string) $width ) . 'px"';
		}
		$img .= '/>';

		$figure_class = 'wp-block-image size-full' . ( $width ? ' is-resized' : '' );

		$figure = '<figure class="' . esc_attr( $figure_class ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . $img . '</a></figure>';

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return '<!-- wp:image ' . wp_json_encode( $attributes ) . ' -->' . $figure . '<!-- /wp:image -->' . "\n";
	}
}
