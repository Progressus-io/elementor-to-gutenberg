<?php
/**
 * Widget handler for Elementor gallery widget.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Widget;

use Progressus\BlockShift\Admin\Widget_Handler_Interface;
use Progressus\BlockShift\Admin\Helper\File_Upload_Service;
use Progressus\BlockShift\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor gallery widget.
 */
class Gallery_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor gallery to Gutenberg gallery block.
	 *
	 * @param array $element The Elementor element data.
	 *
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string {
		$settings      = $element['settings'] ?? array();
		$gallery_items = self::extract_gallery_items( $settings );

		// Map image IDs
		$image_ids = array();
		$images    = array(
			'id'  => array(),
			'url' => array(),
		);
		foreach ( $gallery_items as $item ) {
			$item = is_array( $item ) ? $item : array();
			$url  = isset( $item['url'] ) ? (string) $item['url'] : '';

			/*
			 * The attachment ID survives an import; the stored URL does not, so
			 * resolve through it first and only fall back to downloading the
			 * remote file when the media is genuinely not on this site.
			 */
			$new_url = Style_Parser::resolve_media_url( $item );
			if ( '' === $new_url ) {
				$new_url = File_Upload_Service::download_and_upload( $url ) ?? $url;
			}

			$attachment_id = ( ! empty( $new_url ) ? attachment_url_to_postid( $new_url ) : 0 );
			if ( $attachment_id ) {
				$image_ids[]     = $attachment_id;
				$images['id'][]  = $attachment_id;
				$images['url'][] = $new_url;
			} else {
				$images['id'][]  = 0;
				$images['url'][] = $url;
			}
		}

		// Map thumbnail size
		$size_slug = isset( $settings['thumbnail_size'] ) ? $settings['thumbnail_size'] : 'full';

		// Map custom classes
		$custom_classes = array();
		if ( ! empty( $settings['_css_classes'] ) ) {
			$custom_classes[] = $settings['_css_classes'];
		}
		$custom_classes[] = 'elementor-gallery-widget';

		$custom_id  = $settings['_element_id'] ?? '';
		$custom_css = $settings['custom_css'] ?? '';

		/*
		 * These go into block attributes, so they use the shapes core reads -
		 * `style.spacing.blockGap` and `style.border.radius` - rather than the CSS
		 * property names they were being written under, which core ignored.
		 */
		$style = array();

		if (
			isset( $settings['image_spacing'] ) &&
			'custom' === $settings['image_spacing'] &&
			isset( $settings['image_spacing_custom']['size'] )
		) {
			$spacing                          = intval( $settings['image_spacing_custom']['size'] );
			$style['spacing']['blockGap']     = $spacing . 'px';
		}

		$radius = isset( $settings['image_border_radius'] ) && is_array( $settings['image_border_radius'] )
			? $settings['image_border_radius']
			: array();
		$corners = array(
			'top'    => 'topLeft',
			'right'  => 'topRight',
			'bottom' => 'bottomRight',
			'left'   => 'bottomLeft',
		);

		foreach ( $corners as $side => $corner ) {
			if ( ! isset( $radius[ $side ] ) || '' === $radius[ $side ] ) {
				continue;
			}

			$style['border']['radius'][ $corner ] = intval( $radius[ $side ] ) . 'px';
		}

		$parsed_spacing = Style_Parser::parse_spacing( $settings );
		if ( is_array( $parsed_spacing ) ) {
			$spacing_style = $parsed_spacing['style'] ?? array();
			if ( is_array( $spacing_style ) && is_array( $style ) ) {
				$style = array_merge_recursive( $style, $spacing_style );
			}
		}

		// Compose attributes
		$gallery_attrs = array(
			'ids'       => $image_ids,
			'sizeSlug'  => $size_slug,
			'className' => implode( ' ', $custom_classes ),
			'linkTo'    => 'none',
		);

		$columns = self::extract_columns( $settings, count( $image_ids ) );
		if ( $columns > 0 ) {
			$gallery_attrs['columns'] = $columns;
		}

		if ( $style ) {
			$gallery_attrs['style'] = $style;
		}

		// Build inner image blocks
		$inner_blocks = '';
		$image_count  = isset( $images['id'] ) ? count( $images['id'] ) : 0;
		for ( $i = 0; $i < $image_count; $i++ ) {
			$img_id        = intval( $images['id'][ $i ] );
			$img_url       = esc_url( $images['url'][ $i ] );
			$img_size      = esc_attr( $size_slug );
			$inner_blocks .= sprintf(
				'<!-- wp:image {"id":%d,"sizeSlug":"%s","linkDestination":"none"} -->' .
				'<figure class="wp-block-image size-%s"><img src="%s" alt=""/></figure>' .
				'<!-- /wp:image -->' . "\n",
				$img_id,
				$img_size,
				$img_size,
				$img_url
			);
		}

		$gallery_attrs_str = wp_json_encode( $gallery_attrs );

		/*
		 * The nested-images gallery lays out from a `columns-N` class, not from the
		 * `columns` attribute - that one is only read by the deprecated version. A
		 * hard-coded `columns-default` left every converted carousel free-flowing,
		 * so six logos meant for one row wrapped into three uneven ones.
		 */
		$columns_class = isset( $gallery_attrs['columns'] )
			? 'columns-' . (int) $gallery_attrs['columns']
			: 'columns-default';

		// Compose gallery block content
		$block_content = sprintf(
			'<!-- wp:gallery %s -->' . "\n" .
			'<figure class="wp-block-gallery has-nested-images ' . $columns_class . ' is-cropped">' . "\n%s</figure>\n" .
			'<!-- /wp:gallery -->',
			$gallery_attrs_str,
			$inner_blocks
		);

		// Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::note_skipped_custom_css( $custom_css );
		}

		return $block_content;
	}

	/**
	 * Collect the images a gallery-like widget carries.
	 *
	 * The gallery widget stores them under `wp_gallery`; the image carousel -
	 * which has no core equivalent and converts to a gallery too - uses
	 * `carousel`, and a few Elementor versions use `slides` or `images`.
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return array<int, array> Gallery items.
	 */
	private static function extract_gallery_items( array $settings ): array {
		foreach ( array( 'wp_gallery', 'carousel', 'slides', 'images' ) as $key ) {
			$items = $settings[ $key ] ?? null;
			if ( is_array( $items ) && array() !== $items ) {
				return $items;
			}
		}

		return array();
	}

	/**
	 * Read a column count from the widget's slides-per-view setting.
	 *
	 * A one-slide carousel says nothing useful about columns - it is a
	 * slideshow - so it falls through to the gallery's own default rather than
	 * stacking every image full width.
	 *
	 * @param array $settings Widget settings.
	 */
	private static function extract_columns( array $settings, int $image_count = 0 ): int {
		$raw = $settings['slides_to_show'] ?? $settings['columns'] ?? null;
		if ( is_array( $raw ) ) {
			$raw = $raw['size'] ?? $raw['value'] ?? null;
		}

		$columns = is_numeric( $raw ) ? (int) $raw : 0;
		if ( $columns <= 1 ) {
			return 0;
		}

		$columns = min( 8, $columns );

		/*
		 * The gallery stretches whatever is left over on the last row, so a set
		 * that does not divide evenly ends with one image blown up to the full
		 * width. A carousel shows a single row, so a small set goes in one row
		 * rather than leaving that orphan behind.
		 */
		if ( $image_count > 0 && $image_count <= 8 && 0 !== $image_count % $columns ) {
			return $image_count;
		}

		return $columns;
	}
}
