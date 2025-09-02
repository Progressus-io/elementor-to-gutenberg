<?php
namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

class Tabs_Widget_Handler implements Widget_Handler_Interface {

    /**
     * Convert Elementor tabs widget to Gutenberg columns + custom HTML.
     *
     * @param array $element Elementor widget data.
     * @return string Gutenberg block markup.
     */
    public function handle( array $element ): string {
        if ( empty( $element['settings']['tabs'] ) || ! is_array( $element['settings']['tabs'] ) ) {
            return '';
        }

        $tabs = $element['settings']['tabs'];
        $tab_titles   = [];
        $tab_contents = [];
		$custom_css   = $settings['custom_css'] ?? '';

        foreach ( $tabs as $index => $tab ) {
            $tab_id  = 'tab-' . $index;
            $title   = isset( $tab['tab_title'] ) ? $tab['tab_title'] : 'Tab ' . ($index + 1);
            $content = isset( $tab['tab_content'] ) ? $tab['tab_content'] : '';

            $tab_titles[] = sprintf(
                '<button class="gb-tab-title" data-tab="%s">%s</button>',
                esc_attr( $tab_id ),
                esc_html( $title )
            );

            $tab_contents[] = sprintf(
                '<div class="gb-tab-content" id="%s" style="display:%s;">%s</div>',
                esc_attr( $tab_id ),
                $index === 0 ? 'block' : 'none',
                wpautop( $content )
            );
        }

        // Custom HTML for tabs navigation and content
        $tabs_html  = '<div class="gb-tabs">';
        $tabs_html .= '<div class="gb-tabs-nav">' . implode( '', $tab_titles ) . '</div>';
        $tabs_html .= '<div class="gb-tabs-contents">' . implode( '', $tab_contents ) . '</div>';
        $tabs_html .= '</div>';

        // Gutenberg columns block wrapper
        $block_content  = '<!-- wp:columns -->';
        $block_content .= '<div class="wp-block-columns">';
        $block_content .= '<!-- wp:column {"width":"100%"} --><div class="wp-block-column" style="flex-basis:100%">';
        $block_content .= $tabs_html;
        $block_content .= '</div><!-- /wp:column -->';
        $block_content .= '</div><!-- /wp:columns -->';

        $unique_class = 'elementor-custom-' . uniqid();
        $attrs_array['className'] = trim( ($attrs_array['className'] ?? '') . ' ' . $unique_class );

        // Save custom CSS to the Customizer's Additional CSS
		if ( ! empty( $custom_css ) ) {
			Style_Parser::save_custom_css( $custom_css );
		}

        return $block_content;
    }
}