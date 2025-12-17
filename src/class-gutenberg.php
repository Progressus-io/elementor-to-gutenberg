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
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
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
		add_action( 'wp_ajax_progressus_form_submit', array( $this, 'handle_form_submission' ) );
		add_action( 'wp_ajax_nopriv_progressus_form_submit', array( $this, 'handle_form_submission' ) );
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
	 * Enqueue scripts and styles.
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

		if ( has_block( 'progressus/testimonials' ) ) {
			wp_enqueue_style(
				'swiper-css',
				'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
				array(),
				'11.0.0'
			);

			wp_enqueue_script(
				'swiper-js',
				'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
				array(),
				'11.0.0',
				true
			);
		}

		// Enqueue form submission script if form block is present
		if ( has_block( 'progressus/form' ) ) {
			wp_localize_script(
				'gutenberg-plugin-scripts',
				'progressusFormData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'progressus_form_nonce' ),
				)
			);
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		Admin_Settings::instance();
		Batch_Convert_Wizard::instance();
	}

	/**
	 * Handle form submission via AJAX
	 */
	public function handle_form_submission() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'progressus_form_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'progressus-gutenberg' ),
				)
			);
		}

		// Get form data
		$form_name = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '';
		$form_data = array();

		// Collect all form fields
		foreach ( $_POST as $key => $value ) {
			if ( ! in_array( $key, array( 'action', 'nonce', 'form_name' ), true ) ) {
				$form_data[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		// Get admin email
		$admin_email = get_option( 'admin_email' );

		// Prepare email content
		$subject = sprintf( __( 'New Form Submission: %s', 'progressus-gutenberg' ), $form_name );
		$message = sprintf( __( "You have received a new form submission from %s:\n\n", 'progressus-gutenberg' ), get_bloginfo( 'name' ) );

		foreach ( $form_data as $field => $value ) {
			$message .= sprintf( "%s: %s\n", ucfirst( str_replace( array( '_', '-' ), ' ', $field ) ), $value );
		}

		$message .= sprintf( "\n\n" . __( 'Submitted at: %s', 'progressus-gutenberg' ), current_time( 'mysql' ) );

		// Set email headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Try to send email
		$email_sent = wp_mail( $admin_email, $subject, $message, $headers );

		if ( $email_sent ) {
			wp_send_json_success(
				array(
					'message' => __( 'Your submission was successful. We will get back to you soon!', 'progressus-gutenberg' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Your submission failed because of an error. Please try again.', 'progressus-gutenberg' ),
				)
			);
		}
	}
}
