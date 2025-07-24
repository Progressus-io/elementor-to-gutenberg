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
        if ( is_null( self::$instance ) ) {
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
                // JSON is valid, save it
                return $data;
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
}