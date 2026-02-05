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
use Progressus\Gutenberg\Admin\Helper\External_CSS_Service;
use Progressus\Gutenberg\Admin\Helper\Elementor_Fonts_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 9999 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_converted_page_css' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_converted_page_css' ), 9999 );
		add_filter( 'wp_theme_json_data_default', array( $this, 'inject_elementor_typography_theme_json' ) );
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

		$this->enqueue_elementor_fonts();
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

		$this->enqueue_elementor_fonts();

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

		$this->enqueue_woocommerce_widget_styles();

	}

	/**
	 * Enqueue Elementor kit fonts when available.
	 *
	 * @return void
	 */
	private function enqueue_elementor_fonts(): void {
		$requirements = Elementor_Fonts_Service::get_font_requirements();
		$url          = Elementor_Fonts_Service::build_google_fonts_url( $requirements );

		if ( '' === $url ) {
			return;
		}

		wp_enqueue_style( 'progressus-elementor-kit-fonts', $url, array(), null );
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

	/**
	 * Enqueue per-post converted CSS when present.
	 *
	 * enqueue_block_assets runs in both frontend and block editor contexts.
	 *
	 * @return void
	 */
	public function enqueue_converted_page_css(): void {
		External_CSS_Service::enqueue_current_post_css();
	}

	/**
	 * Enqueue WooCommerce widget styles based on Elementor widget markers in content.
	 *
	 * @return void
	 */
	private function enqueue_woocommerce_widget_styles(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		if ( '' === $content ) {
			return;
		}

		$required_handles = $this->get_required_woocommerce_style_handles( $content );
		if ( empty( $required_handles ) ) {
			return;
		}

		$base_url = plugins_url( 'assets/css/woocommerce/', GUTENBERG_PLUGIN_FILE );
		foreach ( $required_handles as $handle => $file ) {
			wp_enqueue_style(
				$handle,
				$base_url . $file,
				array(),
				GUTENBERG_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Determine WooCommerce widget style handles required for the given content.
	 *
	 * @param string $content Post content to inspect.
	 *
	 * @return array<string, string> Map of handle => file name.
	 */
	private function get_required_woocommerce_style_handles( string $content ): array {

		$required      = array();
		$handle_prefix = 'gutenberg-plugin-wc-';

		if ( has_block( 'woocommerce/product-button', get_the_ID() )
		     || has_block( 'woocommerce/add-to-cart-form', get_the_ID() )
		) {
			$required[ $handle_prefix . 'add-to-cart' ] = 'widget-wc-product-add-to-cart.min.css';
		}

		if ( has_block( 'woocommerce/product-price', get_the_ID() ) ) {
			$required[ $handle_prefix . 'price' ] = 'widget-wc-product-price.min.css';
		}

		if ( has_block( 'woocommerce/product-image', get_the_ID() ) ) {
			$required[ $handle_prefix . 'images' ] = 'widget-wc-product-images.min.css';
		}

		if ( has_block( 'woocommerce/product-collection', get_the_ID() ) ) {
			$required[ $handle_prefix . 'products' ] = 'widget-wc-products.min.css';
		}

		if ( has_block( 'woocommerce/product-categories', get_the_ID() ) ) {
			$required[ $handle_prefix . 'archive' ] = 'widget-wc-products-archive.min.css';
		}

		if (
			strpos( $content, 'woocommerce-tabs' ) !== false
			|| strpos( $content, 'wc-tabs' ) !== false
		) {
			$required[ $handle_prefix . 'tabs' ] = 'widget-wc-product-data-tabs.min.css';
		}

		if (
			strpos( $content, 'product_meta' ) !== false
		) {
			$required[ $handle_prefix . 'meta' ] = 'widget-wc-product-meta.min.css';
		}

		if (
			strpos( $content, 'woocommerce-notices-wrapper' ) !== false
			|| strpos( $content, 'wc-block-components-notice-banner' ) !== false
		) {
			$required[ $handle_prefix . 'notices' ] = 'widget-wc-notices.min.css';
		}

		return $required;
	}


	/**
	 * Inject Elementor kit typography into theme.json defaults.
	 *
	 * @param object $theme_json Theme JSON data object.
	 *
	 * @return object
	 */
	public function inject_elementor_typography_theme_json( $theme_json ) {
		if ( ! is_object( $theme_json ) || ! method_exists( $theme_json, 'get_data' ) ) {
			return $theme_json;
		}

		$data = $theme_json->get_data();
		if ( ! is_array( $data ) ) {
			return $theme_json;
		}

		$body_settings    = Style_Parser::get_elementor_kit_typography( 'body' );
		$heading_settings = Style_Parser::get_elementor_kit_typography( 'headings' );

		$body_rules    = Style_Parser::build_typography_declarations( $body_settings );
		$heading_rules = Style_Parser::build_typography_declarations( $heading_settings );

		$body_typography    = $this->map_typography_rules_to_theme_json( $body_rules );
		$heading_typography = $this->map_typography_rules_to_theme_json( $heading_rules );

		if ( ! empty( $body_typography ) ) {
			$data['styles']['elements']['body']['typography'] = array_merge(
				$data['styles']['elements']['body']['typography'] ?? array(),
				$body_typography
			);
		}

		if ( ! empty( $heading_typography ) ) {
			$data['styles']['elements']['heading']['typography'] = array_merge(
				$data['styles']['elements']['heading']['typography'] ?? array(),
				$heading_typography
			);
		}

		$font_requirements = Elementor_Fonts_Service::get_font_requirements();
		if ( ! empty( $font_requirements ) ) {
			$data['settings']['typography']['fontFamilies'] = $this->merge_theme_json_fonts(
				$data['settings']['typography']['fontFamilies'] ?? array(),
				$font_requirements
			);
		}

		if ( class_exists( '\WP_Theme_JSON_Data' ) ) {
			return new \WP_Theme_JSON_Data( $data, 'default' );
		}

		return $theme_json;
	}

	/**
	 * Convert CSS typography declarations into theme.json typography keys.
	 *
	 * @param array<string, string> $rules CSS rules.
	 *
	 * @return array<string, string>
	 */
	private function map_typography_rules_to_theme_json( array $rules ): array {
		$map = array(
			'font-family'    => 'fontFamily',
			'font-size'      => 'fontSize',
			'font-weight'    => 'fontWeight',
			'line-height'    => 'lineHeight',
			'letter-spacing' => 'letterSpacing',
			'text-transform' => 'textTransform',
			'font-style'     => 'fontStyle',
		);

		$output = array();
		foreach ( $map as $css_key => $json_key ) {
			if ( isset( $rules[ $css_key ] ) && '' !== trim( (string) $rules[ $css_key ] ) ) {
				$output[ $json_key ] = trim( (string) $rules[ $css_key ] );
			}
		}

		return $output;
	}

	/**
	 * Merge font families into theme.json settings without overriding existing ones.
	 *
	 * @param array<int, array<string, string>> $existing Existing font families.
	 * @param array<string, array<int, string>> $requirements Font requirements.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function merge_theme_json_fonts( array $existing, array $requirements ): array {
		$slugs = array();
		foreach ( $existing as $item ) {
			if ( ! is_array( $item ) || empty( $item['slug'] ) ) {
				continue;
			}
			$slugs[ (string) $item['slug'] ] = true;
		}

		foreach ( $requirements as $family => $weights ) {
			$family = trim( (string) $family );
			if ( '' === $family ) {
				continue;
			}

			$slug = Style_Parser::clean_class( $family );
			if ( '' === $slug || isset( $slugs[ $slug ] ) ) {
				continue;
			}

			$existing[]     = array(
				'fontFamily' => $family,
				'name'       => $family,
				'slug'       => $slug,
			);
			$slugs[ $slug ] = true;
		}

		return $existing;
	}

}
