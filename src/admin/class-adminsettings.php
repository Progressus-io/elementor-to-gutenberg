<?php

namespace Progressus\Gutenberg\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminSettings
 *
 * Handles the admin settings for the Mighty Kids plugin.
 *
 * @package Progressus\Gutenberg\Admin
 */
class AdminSettings
{
    private static $instance = null;

    public static function instance()
    {
        if ( is_null(self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
    }

    public function add_admin_menu()
    {
        add_menu_page(
            esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
            esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
            'manage_options',
            'gutenberg-settings',
            array( $this, 'settings_page_content' ),
            'dashicons-admin-generic',
            100
        );
    }

    public function settings_init()
    {
        register_setting(
            'gutenberg_settings_group',
            'gutenberg_json_data',
            array( 'sanitize_callback' => array( $this, 'handle_json_upload' ) )
        );

        add_settings_section(
            'gutenberg_settings_section',
            esc_html__( 'Upload JSON Data', 'elementor-to-gutenberg' ),
            null,
            'gutenberg-settings'
        );

        add_settings_field(
            'gutenberg_json_upload',
            esc_html__( 'JSON File', 'elementor-to-gutenberg' ),
            array( $this, 'json_upload_field_callback' ),
            'gutenberg-settings',
            'gutenberg_settings_section'
        );
    }

    public function json_upload_field_callback()
    {
        echo '<input type="file" name="json_upload" accept=".json">';
    }

    public function handle_json_upload( $option )
    {
        if ( ! empty( $_FILES['json_upload']['tmp_name'] ) ) {
            $file         = $_FILES['json_upload'];
            $json_content = file_get_contents( $file['tmp_name'] );
            $data         = json_decode( $json_content, true );

            if ( JSON_ERROR_NONE === json_last_error() ) {
                // Convert JSON to Gutenberg content
                $gutenberg_content = $this->convert_json_to_gutenberg_content($data);

                // Use title and type from JSON, fallback to defaults if missing
                $post_title = isset($data['title']) ? $data['title'] : 'Untitled';
                $post_type  = isset($data['type']) ? $data['type'] : 'page';

                // Create new post/page
                $new_post_id = wp_insert_post(
                    array(
                    'post_title'   => $post_title,
                    'post_content' => $gutenberg_content,
                    'post_type'    => $post_type,
                    'post_status'  => 'publish',
                    ) 
                );

                if (is_wp_error($new_post_id) ) {
                    add_settings_error(
                        'gutenberg_json_data',
                        'json_upload_error',
                        esc_html__('Failed to create new page.', 'elementor-to-gutenberg'),
                        'error'
                    );
                    return get_option('gutenberg_json_data');
                }

                return $gutenberg_content;
            } else {
                add_settings_error(
                    'gutenberg_json_data',
                    'json_upload_error',
                    esc_html__( 'Invalid JSON file uploaded.', 'elementor-to-gutenberg' ),
                    'error'
                );
                return get_option( 'gutenberg_json_data' ); // Return existing data on error
            }
        }
        return $option; // Return existing option if no new file is uploaded
    }

    public function settings_page_content()
    {
        // Admin page content will go here
        echo '<div class="wrap">';
        echo '<h1>Gutenberg Settings</h1>';
        echo '<form method="post" action="options.php" enctype="multipart/form-data">';
        settings_fields( 'gutenberg_settings_group' );
        do_settings_sections( 'gutenberg-settings' );
        submit_button( 'Upload JSON File' );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Parse typography settings from the element.
     *
     * @param  array $settings The settings array.
     * @return string The parsed typography styles.
     */
    public function parse_typography_settings( $settings ): string{
        $typography = '';
        if ( isset( $settings['typography_font_family'] ) ) {
            $typography .= 'font-family:' . esc_attr( $settings['typography_font_family'] ) . ';';
        }
        if ( isset( $settings['typography_text_transform'] ) ) {
            $typography .= 'text-transform:' . esc_attr( $settings['typography_text_transform'] ) . ';';
        }
        if ( isset( $settings['typography_font_size'] ) && is_array( $settings['typography_font_size'] ) ) {
            $size = isset( $settings['typography_font_size']['size'] ) ? $settings['typography_font_size']['size'] : '';
            $unit = isset( $settings['typography_font_size']['unit'] ) ? $settings['typography_font_size']['unit'] : 'px';
            if ( $size !== '' ) {
                $typography .= 'font-size:' . esc_attr( $size ) . esc_attr( $unit ) . ';';
            }
        }
        if ( isset( $settings['typography_font_weight'] ) ) {
            $typography .= 'font-weight:' . esc_attr( $settings['typography_font_weight'] ) . ';';
        }
        if ( isset( $settings['typography_line_height'] ) && is_array( $settings['typography_line_height'] ) ) {
            $size = isset( $settings['typography_line_height']['size'] ) ? $settings['typography_line_height']['size'] : '';
            $unit = isset( $settings['typography_line_height']['unit'] ) ? $settings['typography_line_height']['unit'] : '';
            if ( $size !== '' ) {
                $typography .= 'line-height:' . esc_attr( $size ) . ( $unit ? esc_attr( $unit ) : '' ) . ';';
            }
        }
        return ! empty($typography) ? $typography : '';
    }

    /**
     * Convert JSON data to Gutenberg blocks.
     *
     * @param  array $json_data The JSON data to convert.
     * @return string The converted Gutenberg content.
     */
    public function convert_json_to_gutenberg_content( $json_data )
    {
        if (! isset($json_data['content']) || ! is_array($json_data['content']) ) {
            return '';
        }

        $content = '';

        // Start parsing from top-level content
        $content .= $this->parse_elements($json_data['content']);

        return $content;
    }

    /**
     * Convert JSON data to Gutenberg blocks.
     *
     * @param  array $elements The elements to convert.
     * @return string The converted Gutenberg content.
     */
    public function parse_elements( $elements )
    {
        $block_content = '';
        foreach ( $elements as $element ) {
            // Handle containers
            if ( isset( $element['elType'] ) && $element['elType'] === 'container' ) {
                $inner = '';
                if ( ! empty( $element['elements'] ) ) {
                    $inner = $this->parse_elements( $element['elements'] );
                }
                // Use group block for containers
                $block_content .= "<!-- wp:group --><div class=\"wp-block-group\">{$inner}</div><!-- /wp:group -->";
            }
            // Handle widgets
            elseif ( isset( $element['elType'] ) && $element['elType'] === 'widget' ) {
                switch ( $element['widgetType'] ) {
                case 'heading':
                    $title = isset( $element['settings']['title']) ? $element['settings']['title'] : '';
                    $color = isset( $element['settings']['title_color'] ) ? $element['settings']['title_color'] : '';
                    $class = $color ? 'has-text-color' : '';
                    $style = '';
                    if ( $color ) {
                        $style .= 'color:' . esc_attr( $color ) . ';';
                    }

                    $typography = $this->parse_typography_settings( $element['settings'] );
                    if ( $typography ) {
                        $style .= $typography;
                    }
                    // Move styles to block attributes for Gutenberg compatibility
                    $attrs_array = array();

                    // Prepare block style attributes
                    if ($style) {
                        // Only set supported attributes: color, fontSize, fontFamily, fontWeight, lineHeight, margin, padding
                        if ($color) {
                            $attrs_array['style']['color']['text'] = $color;
                        }
                        // Typography
                        if (isset($element['settings']['typography_font_size']['size'])) {
                            $attrs_array['style']['typography']['fontSize'] = $element['settings']['typography_font_size']['size'] . (isset($element['settings']['typography_font_size']['unit']) ? $element['settings']['typography_font_size']['unit'] : 'px');
                        }
                        if (isset($element['settings']['typography_font_family'])) {
                            $attrs_array['style']['typography']['fontFamily'] = $element['settings']['typography_font_family'];
                        }
                        if (isset($element['settings']['typography_font_weight'])) {
                            $attrs_array['style']['typography']['fontWeight'] = $element['settings']['typography_font_weight'];
                        }
                        if (isset($element['settings']['typography_line_height']['size'])) {
                            $attrs_array['style']['typography']['lineHeight'] = $element['settings']['typography_line_height']['size'] . (isset($element['settings']['typography_line_height']['unit']) ? $element['settings']['typography_line_height']['unit'] : '');
                        }
                        // Margin & Padding
                        foreach ( ['margin', 'padding'] as $spacing ) {
                            $spacing_key = '_' . $spacing;
                            if ( isset( $element['settings'][$spacing_key]) && is_array($element['settings'][$spacing_key])) {
                                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                                    if (isset($element['settings'][$spacing_key][$side])) {
                                        $attrs_array['style'][$spacing][$side] = $element['settings'][$spacing_key][$side] . (isset($element['settings'][$spacing_key]['unit']) ? $element['settings'][$spacing_key]['unit'] : 'px');
                                    }
                                }
                            }
                        }
                    }

                    $attrs = wp_json_encode($attrs_array);

                    // Use heading level 2 by default, can be customized if needed
                    $block_content .= "<!-- wp:heading {$attrs} -->";
                    $block_content .= "<h2 class=\"wp-block-heading {$class}\"";
                    if ($style) {
                        $block_content .= " style=\"" . esc_attr($style) . "\"";
                    }
                    $block_content .= ">" . esc_html($title) . "</h2><!-- /wp:heading -->\n";
                    break;
                case 'wpforms':
                    $form_id = isset($element['settings']['form_id']) ? $element['settings']['form_id'] : '';
                    if ($form_id ) {
                        $attrs = '{"id":"' . esc_attr($form_id) . '"}';
                        $block_content .= "<!-- wp:wpforms {$attrs} /-->\n";
                    }
                    break;
                case 'text-editor':
                    $text = isset($element['settings']['editor']) ? $element['settings']['editor'] : '';
                    $block_content .= "<!-- wp:paragraph -->{$text}<!-- /wp:paragraph -->\n";
                    break;
                case 'image':
                    $url = isset( $element['settings']['image']['url'] ) ? $element['settings']['image']['url'] : '';
                    $alt = isset( $element['settings']['image']['alt'] ) ? $element['settings']['image']['alt'] : '';
                    $attrs = '{"url":"' . esc_url( $url ) . '","alt":"' . esc_attr( $alt ) . '"}';
                    $inner .= "<!-- wp:image {$attrs} /-->\n";
                    break;
                case 'button':
                    $text = isset( $element['settings']['text'] ) ? $element['settings']['text'] : '';
                    $url = isset( $element['settings']['link']['url'] ) ? $element['settings']['link']['url'] : '';
                    $attrs = $url ? '{"url":"' . esc_url($url) . '"}' : '{}';
                    $block_content .= "<!-- wp:button {$attrs} --><a class=\"wp-block-button__link\">{$text}</a><!-- /wp:button -->\n";
                    break;
                case 'video':
                    $video_url = isset( $element['settings']['video_link'] ) ? $element['settings']['video_link'] : '';
                    if ( $video_url ) {
                        $attrs          = '{"url":"' . esc_url( $video_url ) . '"}';
                        $block_content .= "<!-- wp:video {$attrs} /-->\n";
                    }
                    break;
                case 'spacer':
                    $height = isset( $element['settings']['height'] ) ? intval( $element['settings']['height'] ) : 20;
                    $attrs  = '{"height":"' . $height . 'px"}';
                    $block_content .= "<!-- wp:spacer {$attrs} /-->\n";
                    break;
                case 'icon':
                    $icon = isset( $element['settings']['icon'] ) ? $element['settings']['icon'] : '';
                    $size = isset( $element['settings']['size'] ) ? intval( $element['settings']['size'] ) : 24;
                    if ( $icon ) {
                        $attrs = '{"icon":"' . esc_attr( $icon ) . '","size":"' . esc_attr( $size ) . '"}';
                        $block_content .= "<!-- wp:icon {$attrs} /-->\n";
                    }
                    break;
                case 'social-icons':
                    $social_icons = isset( $element['settings']['social_icons'] ) ? $element['settings']['social_icons'] : array();
                    if ( ! empty( $social_icons ) ) {
                        $icons_content = '';
                        foreach ( $social_icons as $icon ) {
                            $icon_name = isset( $icon['name'] ) ? $icon['name'] : '';
                            $icon_url  = isset( $icon['url'] ) ? $icon['url'] : '';
                            if ( $icon_name && $icon_url ) {
                                $icons_content .= "<!-- wp:social-link --><a href=\"" . esc_url( $icon_url ) . "\" class=\"wp-block-social-link wp-block-social-link--" . esc_attr( $icon_name ) . "\">" . esc_html( $icon_name ) . "</a><!-- /wp:social-link -->\n";
                            }
                        }
                        $block_content .= "<!-- wp:social-links -->{$icons_content}<!-- /wp:social-links -->\n";
                    }
                    break;
                case 'gallery':
                    $gallery_images = isset( $element['settings']['gallery'] ) ? $element['settings']['gallery'] : array();
                    if ( ! empty( $gallery_images ) ) {
                        $gallery_content = '';
                        foreach ( $gallery_images as $image ) {
                            $url = isset( $image['url'] ) ? $image['url'] : '';
                            $alt = isset( $image['alt'] ) ? $image['alt'] : '';
                            if ($url ) {
                                $attrs = '{"url":"' . esc_url( $url ) . '","alt":"' . esc_attr( $alt ) . '"}';
                                $gallery_content .= "<!-- wp:image {$attrs} /-->\n";
                            }
                        }
                        $block_content .= "<!-- wp:gallery -->{$gallery_content}<!-- /wp:gallery -->\n";
                    }
                    break;
                case 'list':
                    $items = isset( $element['settings']['items'] ) ? $element['settings']['items'] : array();
                    if ( ! empty( $items) ) {
                        $list_content = '<ul>';
                        foreach ( $items as $item ) {
                            $list_content .= '<li>' . esc_html( $item ) . '</li>';
                        }
                        $list_content  .= '</ul>';
                        $block_content .= "<!-- wp:list -->{$list_content}<!-- /wp:list -->\n";
                    }
                    break;
                case 'tabs':
                    $tabs = isset( $element['settings']['tabs'] ) ? $element['settings']['tabs'] : array();
                    if ( ! empty( $tabs ) ) {
                        foreach ( $tabs as $tab ) {
                            $tab_title   = isset( $tab['title'] ) ? $tab['title'] : '';
                            $tab_content = isset( $tab['content'] ) ? $tab['content'] : '';
                            // Use heading for tab title and group for tab content
                            $block_content .= "<!-- wp:heading -->{$tab_title}<!-- /wp:heading -->\n";
                            $block_content .= "<!-- wp:group -->{$tab_content}<!-- /wp:group -->\n";
                        }
                    }
                    break;
                case 'accordion':
                    $accordions = isset( $element['settings']['accordions'] ) ? $element['settings']['accordions'] : array();
                    if ( ! empty( $accordions ) ) {
                        $accordion_content = '';
                        foreach ( $accordions as $accordion ) {
                            $accordion_title        = isset( $accordion['title'] ) ? $accordion['title'] : '';
                            $accordion_content_text = isset( $accordion['content'] ) ? $accordion['content'] : '';
                            $accordion_content      .= "<!-- wp:accordion-item -->\n<!-- wp:accordion-title -->{$accordion_title}<!-- /wp:accordion-title -->\n<!-- wp:accordion-content -->{$accordion_content_text}<!-- /wp:accordion-content -->\n<!-- /wp:accordion-item -->\n";
                        }
                        $block_content .= "<!-- wp:details --><summary>{$accordion_title}</summary>{$accordion_content_text}<!-- /wp:details -->\n";                        
                    }
                    break;
                default:
                    // Unknown widget, fallback to paragraph
                    $block_content .= "<!-- wp:paragraph -->{$element['widgetType']}<!-- /wp:paragraph -->\n";
                    break;
                }
            } else {
                // Handle unknown elements
                $block_content .= "<!-- wp:paragraph -->" . esc_html__( 'Unknown element', 'elementor-to-gutenberg' ) . "<!-- /wp:paragraph -->\n";
            }
        }
        return $block_content;
    }
}
