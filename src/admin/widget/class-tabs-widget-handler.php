<?php
namespace Progressus\Gutenberg\Admin\Widget;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

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
        $tab_titles = [];
        $tab_contents = [];

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

        // Optional: Add minimal JS for tab switching (can be improved or moved to a separate JS file)
        $block_content .= '<script>
        document.addEventListener("DOMContentLoaded",function(){
            var buttons=document.querySelectorAll(".gb-tab-title");
            var contents=document.querySelectorAll(".gb-tab-content");
            buttons.forEach(function(btn){
                btn.addEventListener("click",function(){
                    buttons.forEach(function(b){b.classList.remove("active");});
                    btn.classList.add("active");
                    contents.forEach(function(c){
                        c.style.display=(c.id===btn.getAttribute("data-tab"))?"block":"none";
                    });
                });
            });
            if(buttons.length)buttons[0].classList.add("active");
        });
        </script>';

        // Optional: Add minimal CSS for tabs (can be improved or moved to a separate CSS file)
        $block_content .= '<style>
        .gb-tabs-nav { display: flex; gap: 10px; margin-bottom: 10px; }
        .gb-tab-title { background: #f3f3f3; border: 1px solid #ccc; padding: 8px 16px; cursor: pointer; }
        .gb-tab-title.active { background: #e0e0e0; font-weight: bold; }
        .gb-tab-content { padding: 10px; border: 1px solid #eee; }
        </style>';

        return $block_content;
    }
}