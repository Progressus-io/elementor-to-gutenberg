<?php
/**
 * The main class of the Mighty Kids plugin.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg;

defined('ABSPATH') || exit;

use Progressus\Gutenberg\Admin\Admin_Settings;

/**
 * Class Gutenberg
 *
 * @package Progressus\Gutenberg
 */
class Gutenberg
{

    /**
     * Instance to call certain functions globally within the plugin
     *
     * @var self|null _instance
     */
    protected static ?Gutenberg $instance = null;

    /**
     * Construct the plugin.
     */
    public function __construct()
    {
        add_action('init', array( $this, 'load_plugin' ), 0);
        add_action('gutenberg_plugin_activated', array( $this, 'activation_hooks' ));
        add_action('gutenberg_plugin_deactivated', array( $this, 'deactivation_hooks' ));
    }

    /**
     * Gutenberg Customization.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @static
     * @return Gutenberg|null Gutenberg instance.
     */
    public static function instance(): ?Gutenberg
    {
        if (is_null(self::$instance) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin activation hooks.
     */
    public function activation_hooks()
    {
    }

    /**
     * Plugin activation hooks.
     */
    public function deactivation_hooks()
    {
    }

    /**
     * Determine which plugin to load.
     */
    public function load_plugin(): void
    {
        $this->init_hooks();
    }

    /**
     * Collection of hooks.
     */
    public function init_hooks(): void
    {
        add_action('init', array( $this, 'init' ), 1);
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_style(
            'font-awesome-custom',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            array(),
            '6.5.0'
        );
    }

    /**
     * Initialize the plugin.
     */
    public function init(): void
    {
		new Admin_Settings();
    }
}
