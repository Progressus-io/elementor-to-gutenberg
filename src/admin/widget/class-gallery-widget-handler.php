<?php
/**
 * Widget handler for Elementor gallery widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor gallery widget.
 */
class Gallery_Widget_Handler implements Widget_Handler_Interface {
    /**
     * Handle conversion of Elementor gallery to Gutenberg gallery block.
     *
     * @param array $element The Elementor element data.
     * @return string The Gutenberg block content.
     */
    public function handle( array $element ): string {
        $settings      = $element['settings'] ?? array();
        $gallery_items = $settings['wp_gallery'] ?? array();

        // Map image IDs
        $image_ids = array();
        foreach ( $gallery_items as $item ) {
            $url           = $item['url'] ?? '';
            $new_url       = File_Upload_Service::download_and_upload( $url ) ?? $url;
            $attachment_id = ( ! empty( $new_url ) ? attachment_url_to_postid( $new_url ) : 0 );
            if ( $attachment_id ) {
                $images['id'][] = $attachment_id;
                $images['url'][] = $new_url;
            } else {
                $images['id'][] = 0;
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

        // Map spacing (image_spacing_custom)
        $style = array();

        // Map spacing (image_spacing_custom)
        if (
            isset( $settings['image_spacing'] ) &&
            $settings['image_spacing'] === 'custom' &&
            isset( $settings['image_spacing_custom']['size'] )
        ) {
            $spacing      = intval( $settings['image_spacing_custom']['size'] );
            $style['gap'] = "{$spacing}px";
        }

        // Map border radius
        if ( isset( $settings['image_border_radius'] ) ) {
            foreach ( ['top','right','bottom','left'] as $side ) {
            if ( isset( $settings['image_border_radius'][ $side ] ) ) {
                $style["border-{$side}-radius"] = intval( $settings['image_border_radius'][ $side ] ) . "px";
            }
            }
        }

        // Map border width
        if ( isset( $settings['image_border_width'] ) ) {
            foreach ( ['top','right','bottom','left'] as $side ) {
            if ( isset( $settings['image_border_width'][ $side ] ) ) {
                $style["border-{$side}-width"] = intval( $settings['image_border_width'][ $side ] ) . "px";
            }
            }
        }

        $style = array_merge_recursive( $style, Style_Parser::parse_spacing( $settings )['style'] );       

        // Compose attributes
        $gallery_attrs = array(
            'ids'       => $image_ids,
            'sizeSlug'  => $size_slug,
            'className' => implode( ' ', $custom_classes ),
            'linkTo'    => 'none'
        );

        if ( $style ) {
            $gallery_attrs['style'] = $style;
        }

        // Build inner image blocks
        $inner_blocks = '';
        $image_count = isset( $images['id'] ) ? count( $images['id'] ) : 0;
        for ( $i = 0; $i < $image_count; $i++ ) {
            $img_id    = intval( $images['id'][$i] );
            $img_url   = esc_url( $images['url'][$i] );
            $img_size  = esc_attr( $size_slug );
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

        // Compose gallery block content
        $block_content = sprintf(
            '<!-- wp:gallery %s -->' . "\n" .
            '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . "\n%s</figure>\n" .
            '<!-- /wp:gallery -->',
            $gallery_attrs_str,
            $inner_blocks
        );     

        return $block_content;
    }
}