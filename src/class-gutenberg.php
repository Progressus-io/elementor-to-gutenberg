<?php
/**
 * The main class of the Migrate Elementor to Gutenberg plugin.
 *
 * @package Progressus\MigrateElementorToGutenberg
 */

namespace Progressus\MigrateElementorToGutenberg;

defined( 'ABSPATH' ) || exit;

use Progressus\MigrateElementorToGutenberg\Admin\Admin_Settings;
use Progressus\MigrateElementorToGutenberg\Admin\AI_Enhancement_Admin;
use Progressus\MigrateElementorToGutenberg\Admin\AI_Improvement_Admin;
use Progressus\MigrateElementorToGutenberg\Admin\Batch_Convert_Wizard;
use Progressus\MigrateElementorToGutenberg\Admin\Conversion_Log_Admin;
use Progressus\MigrateElementorToGutenberg\Admin\Data_Migration;
use Progressus\MigrateElementorToGutenberg\Admin\Helper\External_CSS_Service;
use Progressus\MigrateElementorToGutenberg\Admin\Helper\Elementor_Fonts_Service;
use Progressus\MigrateElementorToGutenberg\Admin\Helper\Style_Parser;

/**
 * Class Gutenberg
 *
 * @package Progressus\MigrateElementorToGutenberg
 */
class Gutenberg {
	public const FULL_WIDTH_TEMPLATE_ID = 'progressus-metg//full-width-page';

	/**
	 * Slug used as the value of `_wp_page_template` for converted pages on
	 * classic themes. Matches the file name of templates/metg-full-width-page.php.
	 */
	public const FULL_WIDTH_PAGE_TEMPLATE_SLUG = 'templates/metg-full-width-page.php';

	/**
	 * CSS handle for the global stylesheet that styles the template.
	 */
	public const FULL_WIDTH_CSS_HANDLE = 'metg-full-width-page';


	/**
	 * Instance to call certain functions globally within the plugin
	 *
	 * @var self|null _instance
	 */
	protected static ?Gutenberg $instance = null;


	/**
	 * Runtime registry to prevent duplicate font enqueues.
	 *
	 * @var array<string, bool>
	 */
	private array $enqueued_font_handles = array();

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_plugin' ), 0 );
		add_action( 'metg_activated', array( $this, 'activation_hooks' ) );
		add_action( 'metg_deactivated', array( $this, 'deactivation_hooks' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );

		add_filter( 'theme_page_templates', array( $this, 'register_classic_page_template' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_classic_page_template' ), 999 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_full_width_page_css' ), 999 );

		add_filter( 'body_class', array( $this, 'add_full_width_page_body_class' ), 999 );
	}

	/**
	 * Append our template to the Page Attributes "Template" dropdown for
	 * classic themes. Block themes use register_block_template() instead.
	 *
	 * @param array  $templates  Existing template list.
	 * @param mixed  $theme      WP_Theme instance.
	 * @param mixed  $post       WP_Post or null.
	 * @param string $post_type  Post type for the listing.
	 *
	 * @return array
	 */
	public function register_classic_page_template( $templates, $theme = null, $post = null, $post_type = '' ) {
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		if ( '' !== $post_type && 'page' !== $post_type ) {
			return $templates;
		}

		$templates[ self::FULL_WIDTH_PAGE_TEMPLATE_SLUG ] = __( 'Full Width Page', 'migrate-elementor-to-gutenberg' );

		return $templates;
	}

	/**
	 * Resolve a request for our classic-theme template to the file shipped
	 * inside the plugin (instead of the active theme folder).
	 *
	 * @param string $template Path resolved by core.
	 *
	 * @return string
	 */
	public function load_classic_page_template( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}

		$assigned = (string) get_page_template_slug( get_queried_object_id() );
		if ( self::FULL_WIDTH_PAGE_TEMPLATE_SLUG !== $assigned ) {
			return $template;
		}

		$plugin_template = trailingslashit( METG_DIR_PATH ) . self::FULL_WIDTH_PAGE_TEMPLATE_SLUG;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Add a body class when the Full Width Page template is in effect.
	 *
	 * @param array $classes Existing body classes.
	 *
	 * @return array
	 */
	public function add_full_width_page_body_class( $classes ): array {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		if ( ! is_singular( 'page' ) ) {
			return $classes;
		}

		if ( $this->is_full_width_template_active( get_queried_object_id() ) ) {
			$classes[] = 'metg-full-width-page-active';
		}

		return $classes;
	}

	/**
	 * Front-end: enqueue the global stylesheet only when our template is
	 * being rendered (classic-theme slug match OR block-theme template ID match).
	 */
	public function enqueue_full_width_page_css(): void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		if ( ! $this->is_full_width_template_active( get_queried_object_id() ) ) {
			return;
		}

		wp_enqueue_style(
			self::FULL_WIDTH_CSS_HANDLE,
			trailingslashit( METG_DIR_URL ) . 'assets/css/metg-full-width-page.css',
			array(),
			defined( 'METG_VERSION' ) ? METG_VERSION : null
		);
	}

	/**
	 * True when the given page is using the Full Width Page template
	 * (either the classic-theme PHP template or the block-theme registered
	 * template).
	 *
	 * @param int $post_id Post ID.
	 */
	public function is_full_width_template_active( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$assigned = (string) get_page_template_slug( $post_id );
		if ( self::FULL_WIDTH_PAGE_TEMPLATE_SLUG === $assigned ) {
			return true;
		}

		$block_template_id = self::FULL_WIDTH_TEMPLATE_ID;
		if ( $assigned === $block_template_id ) {
			return true;
		}
		$slug_only = $assigned;
		if ( false !== strpos( $assigned, '//' ) ) {
			$parts     = explode( '//', $assigned, 2 );
			$slug_only = isset( $parts[1] ) ? (string) $parts[1] : $assigned;
		}
		if ( false !== strpos( $block_template_id, '//' ) ) {
			$parts       = explode( '//', $block_template_id, 2 );
			$theirs_slug = isset( $parts[1] ) ? (string) $parts[1] : '';
			if ( '' !== $theirs_slug && $slug_only === $theirs_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers blocks from the build folder.
	 */
	public function register_blocks() {
		// auto-register all blocks inside build/blocks:
		$blocks_dir = METG_DIR_PATH . '/build/blocks';
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
		add_filter( 'wp_resource_hints', array( $this, 'add_font_resource_hints' ), 10, 2 );
	}

	/**
	 * Enqueue styles for the block editor.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'metg-layout-fixes',
			METG_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			METG_VERSION
		);

		$this->enqueue_converted_post_fonts( $this->detect_editor_post_id() );
	}

	/**
	 * Enqueue styles for admin screens.
	 */
	public function fontawesome_icon_block_enqueue_fontawesome() {
		wp_enqueue_style(
			'font-awesome-custom',
			METG_DIR_URL . '/assets/vendor/fontawesome/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'metg-layout-fixes-admin',
			METG_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			METG_VERSION
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_style(
			'font-awesome-custom',
			METG_DIR_URL . '/assets/vendor/fontawesome/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'metg-layout-fixes',
			METG_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			METG_VERSION
		);

		wp_enqueue_script(
			'metg-scripts',
			METG_DIR_URL . '/assets/js/scripts.js',
			array( 'jquery' ),
			METG_VERSION,
			true
		);

		if ( has_block( 'progressus/icon' ) ) {
			wp_enqueue_style( 'dashicons' );
		}

		if ( has_block( 'progressus/testimonials' ) ) {
			wp_enqueue_style(
				'swiper-css',
				METG_DIR_URL . '/assets/vendor/swiper/swiper-bundle.min.css',
				array(),
				'11.0.0'
			);

			wp_enqueue_script(
				'swiper-js',
				METG_DIR_URL . '/assets/vendor/swiper/swiper-bundle.min.js',
				array(),
				'11.0.0',
				true
			);
		}

		$this->enqueue_converted_post_fonts();

		// Enqueue form submission script if form block is present
		if ( has_block( 'progressus/form' ) ) {
			wp_localize_script(
				'metg-scripts',
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
	 * Enqueue converted post fonts.
	 *
	 * @param int|null $post_id Optional post ID.
	 *
	 * @return void
	 */
	private function enqueue_converted_post_fonts( ?int $post_id = null ): void {
		if ( null === $post_id ) {
			if ( ! is_singular() ) {
				return;
			}
			$post_id = (int) get_queried_object_id();
		}

		if ( $post_id <= 0 ) {
			return;
		}

		$url = Elementor_Fonts_Service::get_post_fonts_url( $post_id );
		if ( '' === $url ) {
			return;
		}

		$handle = 'metg-google-fonts-' . md5( $url );
		if ( isset( $this->enqueued_font_handles[ $handle ] ) ) {
			return;
		}

		wp_enqueue_style( $handle, $url, array(), METG_VERSION );
		$this->enqueued_font_handles[ $handle ] = true;
	}

	/**
	 * Add Google Fonts preconnect hints.
	 *
	 * @param array $urls Existing hint entries.
	 * @param string $relation_type Relation type.
	 *
	 * @return array
	 */
	public function add_font_resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);

		return $urls;
	}

	/**
	 * Detect current edited post ID in block editor.
	 *
	 * @return int
	 */
	private function detect_editor_post_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['post'];
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && isset( $screen->post_id ) ) {
			return (int) $screen->post_id;
		}

		global $post;
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}

		return 0;
	}


	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		Data_Migration::maybe_run();
		$this->register_full_width_page_template();
		Admin_Settings::instance();
		Batch_Convert_Wizard::instance();
		AI_Enhancement_Admin::instance();
		AI_Improvement_Admin::instance();
		Conversion_Log_Admin::instance();
	}

	/**
	 * Register a reusable full-width page block template for converted pages.
	 */
	public function register_full_width_page_template(): void {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		register_block_template(
			self::FULL_WIDTH_TEMPLATE_ID,
			array(
				'title'       => __( 'Full Width Page', 'migrate-elementor-to-gutenberg' ),
				'description' => __( 'Template for converted full width pages that keeps the active theme header and footer without forcing constrained page layout.', 'migrate-elementor-to-gutenberg' ),
				'post_types'  => array( 'page' ),
				'content'     => sprintf(
					'<!-- wp:template-part {"slug":"header","theme":"%1$s","tagName":"header"} /-->' . "\n\n" .
					'<!-- wp:group {"tagName":"main","className":"metg-full-width-page"} -->' . "\n" .
					'<main class="wp-block-group metg-full-width-page"><!-- wp:post-content /--></main>' . "\n" .
					'<!-- /wp:group -->' . "\n\n" .
					'<!-- wp:template-part {"slug":"footer","theme":"%1$s","tagName":"footer"} /-->',
					get_stylesheet()
				),
			)
		);
	}

	/**
	 * Handle form submission via AJAX
	 */
	public function handle_form_submission() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'progressus_form_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'migrate-elementor-to-gutenberg' ),
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
		/* translators: %s: name of the form submitted */
		$subject = sprintf( __( 'New Form Submission: %s', 'migrate-elementor-to-gutenberg' ), $form_name );
		/* translators: %s: name of the WordPress site */
		$message = sprintf( __( "You have received a new form submission from %s:\n\n", 'migrate-elementor-to-gutenberg' ), get_bloginfo( 'name' ) );

		foreach ( $form_data as $field => $value ) {
			$message .= sprintf( "%s: %s\n", ucfirst( str_replace( array( '_', '-' ), ' ', $field ) ), $value );
		}

		/* translators: %s: date and time of form submission */
		$message .= sprintf( "\n\n" . __( 'Submitted at: %s', 'migrate-elementor-to-gutenberg' ), current_time( 'mysql' ) );

		// Set email headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Try to send email
		$email_sent = wp_mail( $admin_email, $subject, $message, $headers );

		if ( $email_sent ) {
			wp_send_json_success(
				array(
					'message' => __( 'Your submission was successful. We will get back to you soon!', 'migrate-elementor-to-gutenberg' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Your submission failed because of an error. Please try again.', 'migrate-elementor-to-gutenberg' ),
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

		$base_url = plugins_url( 'assets/css/woocommerce/', METG_FILE );
		foreach ( $required_handles as $handle => $file ) {
			wp_enqueue_style(
				$handle,
				$base_url . $file,
				array(),
				METG_VERSION
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
		$handle_prefix = 'metg-wc-';

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
