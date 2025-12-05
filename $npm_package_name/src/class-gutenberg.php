<?php
/**
 * The main class of the Mighty Kids plugin.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg;

defined( 'ABSPATH' ) || exit;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Batch_Convert_Wizard;

/**
 * Class Gutenberg
 *
 * @package Progressus\Gutenberg
 */
class Gutenberg {

	/**
	 * Instance to call certain functions globally within the plugin
	 *
	 * @var self|null _instance
	 */
	protected static ?Gutenberg $instance = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_plugin' ), 0 );
		add_action( 'gutenberg_plugin_activated', array( $this, 'activation_hooks' ) );
		add_action( 'gutenberg_plugin_deactivated', array( $this, 'deactivation_hooks' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers blocks from the build folder.
	 */
	public function register_blocks() {
		// auto-register all blocks inside build/blocks:
		$blocks_dir = GUTENBERG_PLUGIN_DIR_PATH . '/build/blocks';

		foreach ( glob( $blocks_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
			register_block_type( $block_dir );
		}
	}

	/**
	 * Gutenberg Customization.
	 *
	 * @return Gutenberg|null Gutenberg instance.
	 */
	public static function instance(): ?Gutenberg {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin activation hooks.
	 */
	public function activation_hooks() {
	}

	/**
	 * Plugin activation hooks.
	 */
	public function deactivation_hooks() {
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin(): void {
		$this->init_hooks();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'fontawesome_icon_block_enqueue_fontawesome' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue styles for the block editor.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'gutenberg-plugin-layout-fixes',
			GUTENBERG_PLUGIN_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			GUTENBERG_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueue styles for admin screens.
	 */
	public function fontawesome_icon_block_enqueue_fontawesome() {
		wp_enqueue_style(
			'font-awesome-custom',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'gutenberg-plugin-layout-fixes-admin',
			GUTENBERG_PLUGIN_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			GUTENBERG_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueue scripts and styles on the frontend.
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_style(
			'font-awesome-custom',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'gutenberg-plugin-layout-fixes',
			GUTENBERG_PLUGIN_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'gutenberg-plugin-scripts',
			GUTENBERG_PLUGIN_DIR_URL . '/assets/js/scripts.js',
			array( 'jquery' ),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		if ( has_block( 'progressus/icon' ) ) {
			wp_enqueue_style( 'dashicons' );
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		Admin_Settings::instance();
		Batch_Convert_Wizard::instance();
	}
}
