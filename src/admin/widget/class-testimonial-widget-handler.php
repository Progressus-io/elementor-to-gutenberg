<?php
/**
 * Widget handler for Elementor testimonial widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor testimonial widget.
 */
class Testimonial_Widget_Handler implements Widget_Handler_Interface {
        /**
         * Handle conversion of Elementor testimonial widget.
         *
         * @param array $element Elementor widget data.
         */
        public function handle( array $element ): string {
                $settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
                $custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

                $content_block = $this->render_content_block( $settings );
                $author_block  = $this->render_author_block( $settings );

                $child_blocks = array_filter( array( $content_block, $author_block ) );
                if ( empty( $child_blocks ) ) {
                        return '';
                }

                if ( '' !== $custom_css ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                $attributes           = Style_Parser::parse_container_styles( $settings );
                $attributes['layout'] = array( 'type' => 'constrained' );

                return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
        }

        /**
         * Render the testimonial content.
         *
         * @param array $settings Widget settings.
         */
        private function render_content_block( array $settings ): string {
                $content = isset( $settings['testimonial_content'] ) ? (string) $settings['testimonial_content'] : (string) ( $settings['content'] ?? $settings['testimonial_text'] ?? '' );
                if ( '' === trim( $content ) ) {
                        return '';
                }

                $text_settings = array(
                        'editor'       => $content,
                        'text_color'   => $settings['content_color'] ?? $settings['text_color'] ?? '',
                        '_css_classes' => $settings['content_css_classes'] ?? '',
                        '_element_id'  => $settings['content_element_id'] ?? '',
                );

                $text_settings += $this->remap_typography_settings( $settings, 'content_' );

                $handler = new Text_Editor_Widget_Handler();

                return $handler->handle( array( 'settings' => $text_settings ) );
        }

        /**
         * Render the author/name block.
         *
         * @param array $settings Widget settings.
         */
        private function render_author_block( array $settings ): string {
                $name = isset( $settings['testimonial_name'] ) ? (string) $settings['testimonial_name'] : (string) ( $settings['name'] ?? '' );
                $role = isset( $settings['testimonial_job'] ) ? (string) $settings['testimonial_job'] : (string) ( $settings['job'] ?? '' );

                $parts = array();
                if ( '' !== trim( $name ) ) {
                        $parts[] = $name;
                }
                if ( '' !== trim( $role ) ) {
                        $parts[] = $role;
                }

                if ( empty( $parts ) ) {
                        return '';
                }

                $text = esc_html( implode( ' — ', $parts ) );

                $attributes = array(
                        'className' => 'testimonial-author has-small-font-size',
                );

                $author_classes = array( 'testimonial-author', 'has-small-font-size' );
                $inline_style   = '';

                $color = isset( $settings['name_color'] ) ? strtolower( (string) $settings['name_color'] ) : '';
                if ( '' !== $color ) {
                        if ( $this->is_preset_slug( $color ) ) {
                                $attributes['textColor'] = $color;
                        } else {
                                $attributes['style']['color']['text'] = $color;
                                $inline_style = 'color:' . $color . ';';
                        }
                }

                $markup = sprintf(
                        '<p class="%s"%s>%s</p>',
                        esc_attr( implode( ' ', array_unique( $author_classes ) ) ),
                        '' !== $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        $text
                );

                return Block_Builder::build( 'paragraph', $attributes, $markup );
        }

        /**
         * Remap typography settings using a prefix.
         *
         * @param array  $settings Widget settings.
         * @param string $prefix   Prefix to strip.
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

                        $mapped[ 'typography_' . $suffix ] = $value;
                }

                return $mapped;
        }

        /**
         * Check whether the provided color is a preset slug.
         *
         * @param string $color Color string.
         */
        private function is_preset_slug( string $color ): bool {
                return '' !== $color && false === strpos( $color, '#' ) && false === strpos( $color, 'rgb' );
        }
}
