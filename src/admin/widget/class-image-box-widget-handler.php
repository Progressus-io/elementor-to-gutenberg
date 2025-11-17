<?php
/**
 * Widget handler for Elementor image box widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor image box widget.
 */
class Image_Box_Widget_Handler implements Widget_Handler_Interface {
        /**
         * Handle conversion of Elementor image box to Gutenberg blocks.
         *
         * @param array $element Elementor widget element.
         */
        public function handle( array $element ): string {
                $settings    = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
                $custom_css  = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
                $child_blocks = array();

                $image_block = $this->render_image_block( $settings );
                if ( '' !== $image_block ) {
                        $child_blocks[] = $image_block;
                }

                $heading_block = $this->render_heading_block( $settings );
                if ( '' !== $heading_block ) {
                        $child_blocks[] = $heading_block;
                }

                $description_block = $this->render_description_block( $settings );
                if ( '' !== $description_block ) {
                        $child_blocks[] = $description_block;
                }

                if ( empty( $child_blocks ) ) {
                        return '';
                }

                if ( '' !== $custom_css ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                $attributes            = Style_Parser::parse_container_styles( $settings );
                $attributes['layout']  = array( 'type' => 'constrained' );

                return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
        }

        /**
         * Render the image portion of the image box.
         *
         * @param array $settings Widget settings.
         */
        private function render_image_block( array $settings ): string {
                $image = $settings['image'] ?? $settings['selected_image'] ?? $settings['image_image'] ?? null;
                if ( ! is_array( $image ) ) {
                        return '';
                }

                $url = isset( $image['url'] ) ? trim( (string) $image['url'] ) : '';
                if ( '' === $url ) {
                        return '';
                }

                $alt        = isset( $image['alt'] ) ? (string) $image['alt'] : '';
                $attachment = isset( $image['id'] ) && is_numeric( $image['id'] ) ? (int) $image['id'] : 0;
                $link       = is_array( $settings['link'] ?? null ) ? (string) ( $settings['link']['url'] ?? '' ) : '';

                $image_attrs = array(
                        'url'             => $url,
                        'sizeSlug'        => 'full',
                        'linkDestination' => '' !== $link ? 'custom' : 'none',
                );

                if ( $attachment > 0 ) {
                        $image_attrs['id'] = $attachment;
                }

                $img_html = sprintf(
                        '<img src="%s" alt="%s" />',
                        esc_url( $url ),
                        esc_attr( $alt )
                );

                if ( '' !== $link ) {
                        $img_html = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $img_html );
                }

                $figure_html = sprintf(
                        '<figure class="%s">%s</figure>',
                        esc_attr( 'wp-block-image' ),
                        $img_html
                );

                return Block_Builder::build( 'image', $image_attrs, $figure_html );
        }

        /**
         * Render the heading portion of the image box.
         *
         * @param array $settings Widget settings.
         */
        private function render_heading_block( array $settings ): string {
                $title = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : (string) ( $settings['title'] ?? '' );
                if ( '' === trim( $title ) ) {
                        return '';
                }

                $heading_settings = array(
                        'title'        => $title,
                        'header_size'  => $settings['title_size'] ?? $settings['title_tag'] ?? 'h3',
                        'title_color'  => $settings['title_color'] ?? '',
                        '_css_classes' => $settings['title_css_classes'] ?? '',
                        '_element_id'  => $settings['title_element_id'] ?? '',
                );

                $heading_settings += $this->remap_typography_settings( $settings, 'title_' );

                $handler = new Heading_Widget_Handler();

                return $handler->handle( array( 'settings' => $heading_settings ) );
        }

        /**
         * Render the description portion of the image box.
         *
         * @param array $settings Widget settings.
         */
        private function render_description_block( array $settings ): string {
                $description = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : (string) ( $settings['description'] ?? '' );
                if ( '' === trim( $description ) ) {
                        return '';
                }

                $text_settings = array(
                        'editor'       => $description,
                        'text_color'   => $settings['description_color'] ?? '',
                        '_css_classes' => $settings['description_css_classes'] ?? '',
                        '_element_id'  => $settings['description_element_id'] ?? '',
                );

                $text_settings += $this->remap_typography_settings( $settings, 'description_' );

                $handler = new Text_Editor_Widget_Handler();

                return $handler->handle( array( 'settings' => $text_settings ) );
        }

        /**
         * Remap Elementor typography settings with a given prefix to the standard keys.
         *
         * @param array  $settings Widget settings.
         * @param string $prefix   Prefix to strip.
         *
         * @return array
         */
        private function remap_typography_settings( array $settings, string $prefix ): array {
                $mapped      = array();
                $prefix_base = $prefix . 'typography_';
                $prefix_len  = strlen( $prefix_base );

                foreach ( $settings as $key => $value ) {
                        if ( 0 !== strpos( $key, $prefix_base ) ) {
                                continue;
                        }

                        $suffix = substr( $key, $prefix_len );
                        if ( false === $suffix ) {
                                continue;
                        }

                        $mapped_key           = 'typography_' . $suffix;
                        $mapped[ $mapped_key ] = $value;
                }

                return $mapped;
        }
}
