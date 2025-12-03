<?php
/**
 * Widget handler for Elementor image widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Alignment_Helper;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
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
		$settings      = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$image         = is_array( $settings['image'] ?? null ) ? $settings['image'] : array();
		$image_url     = isset( $image['url'] ) ? (string) $image['url'] : '';
		$alt_text      = isset( $image['alt'] ) ? (string) $image['alt'] : '';
		$attachment    = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$custom_id     = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
		$custom_css    = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
		$custom_raw    = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
		$align_payload = Alignment_Helper::build_block_alignment_payload(
			Alignment_Helper::detect_alignment( $settings, array( 'align', 'image_align' ) )
		);
		$caption       = isset( $settings['caption'] ) ? (string) $settings['caption'] : '';

		if ( '' === $image_url ) {
			return '';
		}

		if ( '' !== $image_url && function_exists( 'download_url' ) ) {
			$uploaded = File_Upload_Service::download_and_upload( $image_url );
			if ( null !== $uploaded ) {
				$image_url = $uploaded;
				if ( function_exists( 'attachment_url_to_postid' ) ) {
					$attachment = attachment_url_to_postid( $image_url );
				}
			}
		}

		$figure_classes = array( 'wp-block-image', 'size-full' );
		if ( ! empty( $align_payload['classes'] ) ) {
			$figure_classes = array_merge( $figure_classes, $align_payload['classes'] );
		}
		$custom_classes = array();

		if ( '' !== $custom_raw ) {
			foreach ( preg_split( '/\s+/', $custom_raw ) as $class ) {
				$clean = Style_Parser::clean_class( $class );
				if ( '' === $clean ) {
					continue;
				}
				$figure_classes[] = $clean;
				$custom_classes[] = $clean;
			}
		}

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
		if ( ! empty( $align_payload['attributes'] ) ) {
			$image_attrs = array_merge( $image_attrs, $align_payload['attributes'] );
		}

		if ( ! empty( $custom_classes ) ) {
			$image_attrs['className'] = implode( ' ', array_unique( $custom_classes ) );
		}

		if ( '' !== $custom_id ) {
			$image_attrs['anchor'] = $custom_id;
		}

		$width = $this->normalize_dimension( $settings['width'] ?? null );
		if ( null !== $width ) {
			$image_attrs['width'] = $width;
			if ( '100%' !== $width ) {
				$figure_classes[] = 'is-resized';
			}
		}

		$img_attributes = array();
		if ( $attachment > 0 ) {
			$img_attributes[] = 'class="wp-image-' . esc_attr( (string) $attachment ) . '"';
		}

		if ( null !== $width && is_numeric( $width ) ) {
			$img_attributes[] = 'width="' . esc_attr( $width ) . '"';
		}

		$img_attributes[] = 'src="' . esc_url( $image_url ) . '"';
		$img_attributes[] = 'alt="' . esc_attr( $alt_text ) . '"';

		$img_html = '<img ' . implode( ' ', $img_attributes ) . ' />';

		if ( 'custom' === ( $settings['link_to'] ?? '' ) && ! empty( $settings['link']['url'] ?? '' ) ) {
			$img_html = sprintf( '<a href="%s">%s</a>', esc_url( (string) $settings['link']['url'] ), $img_html );
		}

		if ( '' !== $caption ) {
			$img_html .= sprintf( '<figcaption>%s</figcaption>', wp_kses_post( $caption ) );
		}

		$figure_attrs = array();

		if ( '' !== $custom_id ) {
			$figure_attrs[] = 'id="' . esc_attr( $custom_id ) . '"';
		}

		$figure_attrs[] = 'class="' . esc_attr( implode( ' ', array_unique( $figure_classes ) ) ) . '"';

		$figure_html = sprintf( '<figure %s>%s</figure>', implode( ' ', $figure_attrs ), $img_html );

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
}
