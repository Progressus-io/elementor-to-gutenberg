<?php
/**
 * Widget handler for Elementor image widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function sanitize_html_class;
use function wp_kses_post;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor image widget.
 */
class Image_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor image to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$image        = is_array( $settings['image'] ?? null ) ? $settings['image'] : array();
		$image_url    = isset( $image['url'] ) ? (string) $image['url'] : '';
		$alt_text     = isset( $image['alt'] ) ? (string) $image['alt'] : '';
		$attachment   = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$align        = isset( $settings['align'] ) ? trim( (string) $settings['align'] ) : '';
		$caption      = isset( $settings['caption'] ) ? (string) $settings['caption'] : '';

		if ( '' !== $image_url && function_exists( 'download_url' ) ) {
			$uploaded = File_Upload_Service::download_and_upload( $image_url );
			if ( null !== $uploaded ) {
				$image_url = $uploaded;
				if ( function_exists( 'attachment_url_to_postid' ) ) {
					$attachment = attachment_url_to_postid( $image_url );
				}
			}
		}

		$spacing = Style_Parser::parse_spacing( $settings );
		$border  = Style_Parser::parse_border( $settings );

		$image_attrs = array(
			'sizeSlug'        => 'full',
			'linkDestination' => $this->map_link_destination( $settings ),
		);

		if ( $attachment > 0 ) {
			$image_attrs['id'] = $attachment;
		}
		if ( '' !== $image_url ) {
			$image_attrs['url'] = $image_url;
		}
		if ( '' !== $align ) {
			$image_attrs['align'] = $align;
		}
		if ( '' !== $custom_class ) {
			$image_attrs['className'] = $this->sanitize_class_string( $custom_class );
		}

		if ( ! empty( $spacing['attributes'] ) ) {
			$image_attrs['style']['spacing'] = $spacing['attributes'];
		}
		if ( ! empty( $border['attributes'] ) ) {
			$image_attrs['style']['border'] = $border['attributes'];
		}

		$figure_classes = array( 'wp-block-image' );
		if ( '' !== $align ) {
			$figure_classes[] = 'align' . sanitize_html_class( $align );
		}
		if ( '' !== $custom_class ) {
			foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
				$class = trim( $class );
				if ( '' !== $class ) {
					$figure_classes[] = sanitize_html_class( $class );
				}
			}
		}

		$figure_style_parts = array( $spacing['style'], $border['style'] );
		$img_style_parts    = array();

		$width = $this->normalize_dimension( $settings['width'] ?? null );
		if ( null !== $width ) {
			$image_attrs['width'] = $width;
			$figure_classes[]     = 'is-resized';
			$img_style_parts[]    = 'width:' . $width . ';';
		}

		$img_attributes = array();
		if ( $attachment > 0 ) {
			$img_attributes[] = 'class="wp-image-' . esc_attr( (string) $attachment ) . '"';
		}
		if ( ! empty( $img_style_parts ) ) {
			$img_attributes[] = 'style="' . esc_attr( implode( '', $img_style_parts ) ) . '"';
		}

		$img_html = sprintf(
			'<img src="%s" alt="%s"%s />',
			esc_url( $image_url ),
			esc_attr( $alt_text ),
			$img_attributes ? ' ' . implode( ' ', $img_attributes ) : ''
		);

		if ( 'custom' === ( $settings['link_to'] ?? '' ) && ! empty( $settings['link']['url'] ?? '' ) ) {
			$img_html = sprintf( '<a href="%s">%s</a>', esc_url( (string) $settings['link']['url'] ), $img_html );
		}

		if ( '' !== $caption ) {
			$img_html .= sprintf( '<figcaption>%s</figcaption>', wp_kses_post( $caption ) );
		}

		$figure_style = implode( '', array_filter( $figure_style_parts ) );
		$figure_attr  = array();
		if ( '' !== $custom_id ) {
			$figure_attr[] = 'id="' . esc_attr( $custom_id ) . '"';
		}
		$figure_attr[] = 'class="' . esc_attr( implode( ' ', array_unique( $figure_classes ) ) ) . '"';
		if ( '' !== $figure_style ) {
			$figure_attr[] = 'style="' . esc_attr( $figure_style ) . '"';
		}

		$figure_html = sprintf( '<figure %s>%s</figure>', implode( ' ', array_filter( $figure_attr ) ), $img_html );

		if ( '' !== $custom_css ) {
			Style_Parser::save_custom_css( $custom_css );
		}

		return Block_Builder::build( 'image', $image_attrs, $figure_html );
	}

	/**
	 * Map Elementor link destination to Gutenberg setting.
	 *
	 * @param array $settings Elementor settings.
	 */
	private function map_link_destination( array $settings ): string {
		$link_to = isset( $settings['link_to'] ) ? (string) $settings['link_to'] : 'none';
		if ( 'custom' === $link_to ) {
			return 'custom';
		}
		if ( 'media' === $link_to ) {
			return 'media';
		}

		return 'none';
	}

	/**
	 * Normalize dimension values from Elementor settings.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalize_dimension( $value ): ?string {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) ) {
				return $this->normalize_dimension( $value['size'] . ( $value['unit'] ?? 'px' ) );
			}
			if ( isset( $value['value'] ) ) {
				return $this->normalize_dimension( $value['value'] . ( $value['unit'] ?? 'px' ) );
			}
		}

		if ( null === $value ) {
			return null;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Sanitize custom class strings.
	 *
	 * @param string $class_string Raw class string.
	 */
	private function sanitize_class_string( string $class_string ): string {
		$classes = array();
		foreach ( preg_split( '/\s+/', $class_string ) as $class ) {
			$class = trim( $class );
			if ( '' !== $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		return implode( ' ', $classes );
	}
}
