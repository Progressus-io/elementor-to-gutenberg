<?php
/**
 * The main class of the Migrate Off Elementor plugin.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift;

defined( 'ABSPATH' ) || exit;

use Progressus\BlockShift\Admin\Admin_Settings;
use Progressus\BlockShift\Admin\Batch_Convert_Wizard;
use Progressus\BlockShift\Admin\Conversion_Log_Admin;
use Progressus\BlockShift\Admin\Data_Migration;
use Progressus\BlockShift\Admin\Helper\External_CSS_Service;
use Progressus\BlockShift\Admin\Helper\Elementor_Fonts_Service;
use Progressus\BlockShift\Admin\Helper\Style_Parser;

/**
 * Class Gutenberg
 *
 * @package Progressus\BlockShift
 */
class Gutenberg {
	public const FULL_WIDTH_TEMPLATE_ID = 'progressus-blockshift//full-width-page';

	/**
	 * Slug used as the value of `_wp_page_template` for converted pages on
	 * classic themes. Matches the file name of templates/blockshift-full-width-page.php.
	 */
	public const FULL_WIDTH_PAGE_TEMPLATE_SLUG = 'templates/blockshift-full-width-page.php';

	/**
	 * CSS handle for the global stylesheet that styles the template.
	 */
	public const FULL_WIDTH_CSS_HANDLE = 'blockshift-full-width-page';

	/**
	 * Option holding, per form name, the field names that form's block declares.
	 * Written when a page containing a form block is rendered, and read by the
	 * submission handler so it never has to trust the shape of the request.
	 */
	private const OPTION_FORM_FIELDS = 'blockshift_form_fields';

	/**
	 * Form name used when a form block does not set one. Matches the default in
	 * src/blocks/form/block.json.
	 */
	private const DEFAULT_FORM_NAME = 'contact-form';

	/**
	 * Fields declared by src/blocks/form/block.json for a form block that has
	 * never had its field list customised.
	 */
	private const DEFAULT_FORM_FIELDS = array( 'name', 'email', 'message' );


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
		add_action( 'blockshift_activated', array( $this, 'activation_hooks' ) );
		add_action( 'blockshift_deactivated', array( $this, 'deactivation_hooks' ) );
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

		$templates[ self::FULL_WIDTH_PAGE_TEMPLATE_SLUG ] = __( 'Full Width Page', 'migrate-off-elementor' );

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

		$plugin_template = trailingslashit( BLOCKSHIFT_DIR_PATH ) . self::FULL_WIDTH_PAGE_TEMPLATE_SLUG;
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
			$classes[] = 'blockshift-full-width-page-active';
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
			trailingslashit( BLOCKSHIFT_DIR_URL ) . 'assets/css/blockshift-full-width-page.css',
			array(),
			defined( 'BLOCKSHIFT_VERSION' ) ? BLOCKSHIFT_VERSION : null
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
		$blocks_dir = BLOCKSHIFT_DIR_PATH . '/build/blocks';
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
		add_action( 'wp_ajax_blockshift_form_submit', array( $this, 'handle_form_submission' ) );
		add_action( 'wp_ajax_nopriv_blockshift_form_submit', array( $this, 'handle_form_submission' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 9999 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_converted_page_css' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_converted_page_css' ), 9999 );
		add_filter( 'wp_theme_json_data_default', array( $this, 'inject_elementor_typography_theme_json' ) );
		add_filter( 'wp_resource_hints', array( $this, 'add_font_resource_hints' ), 10, 2 );
		add_filter( 'render_block_blockshift/google-map', array( $this, 'apply_google_maps_api_key' ), 10, 2 );
	}

	/**
	 * Swap a Map block's keyless embed for the Google Maps Embed API when the
	 * site owner has stored an API key.
	 *
	 * The key is applied here, at render time, and never written into post
	 * content: saved markup stays byte-identical, every already-converted page
	 * picks the key up at once, and clearing the key restores the keyless embed
	 * without touching a single post. With no key stored this returns the block
	 * exactly as it was saved.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block, including its attributes.
	 *
	 * @return string
	 */
	public function apply_google_maps_api_key( $block_content, $block ): string {
		$block_content = (string) $block_content;

		$api_key = Admin_Settings::get_google_maps_api_key();
		if ( '' === $api_key ) {
			return $block_content;
		}

		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		$src = self::build_google_maps_embed_src( $attributes, $api_key );
		if ( '' === $src ) {
			return $block_content;
		}

		$replaced = preg_replace_callback(
			'#(<iframe\b[^>]*\ssrc=")https://maps\.google\.com/maps\?[^"]*(")#i',
			static function ( $matches ) use ( $src ) {
				return $matches[1] . esc_url( $src ) . $matches[2];
			},
			$block_content,
			1
		);

		return null === $replaced ? $block_content : $replaced;
	}

	/**
	 * Build a Maps Embed API URL from a Map block's attributes.
	 *
	 * Mirrors the attribute precedence in src/blocks/google-map/save.js: the
	 * `location` object wins over the legacy flat attributes, and coordinates win
	 * over the address.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $api_key    Google Maps API key.
	 *
	 * @return string Embed URL, or an empty string when the block has no location.
	 */
	private static function build_google_maps_embed_src( array $attributes, string $api_key ): string {
		$location = isset( $attributes['location'] ) && is_array( $attributes['location'] ) ? $attributes['location'] : array();

		$address = '';
		if ( isset( $location['address'] ) && '' !== $location['address'] ) {
			$address = (string) $location['address'];
		} elseif ( isset( $attributes['address'] ) ) {
			$address = (string) $attributes['address'];
		}

		$lat = null;
		if ( isset( $location['lat'] ) && null !== $location['lat'] && '' !== $location['lat'] ) {
			$lat = (float) $location['lat'];
		} elseif ( isset( $attributes['lat'] ) && null !== $attributes['lat'] && '' !== $attributes['lat'] ) {
			$lat = (float) $attributes['lat'];
		}

		$lng = null;
		if ( isset( $location['lng'] ) && null !== $location['lng'] && '' !== $location['lng'] ) {
			$lng = (float) $location['lng'];
		} elseif ( isset( $attributes['lng'] ) && null !== $attributes['lng'] && '' !== $attributes['lng'] ) {
			$lng = (float) $attributes['lng'];
		}

		$zoom = isset( $attributes['zoom'] ) ? (int) $attributes['zoom'] : 14;
		if ( $zoom < 1 || $zoom > 21 ) {
			$zoom = 14;
		}

		if ( null !== $lat && null !== $lng ) {
			$query = $lat . ',' . $lng;
		} elseif ( '' !== $address ) {
			$query = $address;
		} else {
			return '';
		}

		return 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode( $api_key )
			. '&q=' . rawurlencode( $query )
			. '&zoom=' . $zoom;
	}

	/**
	 * Enqueue styles for the block editor.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'blockshift-layout-fixes',
			BLOCKSHIFT_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			BLOCKSHIFT_VERSION
		);

		$this->expose_google_maps_api_key_to_editor();

		$this->enqueue_converted_post_fonts( $this->detect_editor_post_id() );
	}

	/**
	 * Make the optional Google Maps API key readable by the Map block's editor
	 * component, so the editor preview matches what the front end will render.
	 *
	 * The key is not part of any block attribute and is never written into post
	 * content; it only reaches users who can already edit content.
	 */
	private function expose_google_maps_api_key_to_editor(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! wp_script_is( 'wp-blocks', 'registered' ) ) {
			return;
		}

		wp_add_inline_script(
			'wp-blocks',
			'window.blockshiftGoogleMap = ' . wp_json_encode(
				array(
					'apiKey'      => Admin_Settings::get_google_maps_api_key(),
					'settingsUrl' => admin_url( 'admin.php?page=blockshift-settings' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Enqueue styles for admin screens.
	 *
	 * Scoped to the screens that need them: this plugin's own screens, which use
	 * the icon font in their UI, and the block editor, where the icon blocks
	 * render. Every other screen in wp-admin - core's and other plugins' - used
	 * to load 102 KB of icon CSS and its webfonts for nothing.
	 */
	public function fontawesome_icon_block_enqueue_fontawesome() {
		if ( ! $this->is_plugin_admin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'font-awesome-custom',
			BLOCKSHIFT_DIR_URL . '/assets/vendor/fontawesome/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'blockshift-layout-fixes-admin',
			BLOCKSHIFT_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			BLOCKSHIFT_VERSION
		);
	}

	/**
	 * Whether the current admin screen is one of this plugin's own screens, or a
	 * block editor screen where this plugin's blocks can be rendered.
	 */
	private function is_plugin_admin_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the screen slug to decide whether to enqueue an asset; no state changes.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$plugin_pages = array(
			'blockshift-settings',
			Batch_Convert_Wizard::MENU_SLUG,
			Conversion_Log_Admin::MENU_SLUG,
		);

		if ( '' !== $page && in_array( $page, $plugin_pages, true ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the post being served carries any of this plugin's content.
	 *
	 * Converted pages and this plugin's blocks both leave "blockshift" in the
	 * post content - as a `wp:blockshift/*` block name, a `blockshift-*` class,
	 * or a converted-page wrapper id - so one lookup over content WordPress has
	 * already loaded answers it.
	 */
	private function current_post_has_plugin_content(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		if ( '' === $content ) {
			return false;
		}

		return false !== strpos( $content, 'blockshift' );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * The converted-page fonts and the WooCommerce widget styles decide for
	 * themselves, per post, whether they are needed. Everything else here belongs
	 * to this plugin's own markup, so it is skipped entirely on pages that carry
	 * none of it - which used to be every page on the site.
	 */
	public function enqueue_scripts(): void {
		$this->enqueue_converted_post_fonts();
		$this->enqueue_woocommerce_widget_styles();

		if ( ! $this->current_post_has_plugin_content() ) {
			return;
		}

		wp_enqueue_style(
			'font-awesome-custom',
			BLOCKSHIFT_DIR_URL . '/assets/vendor/fontawesome/css/all.min.css',
			array(),
			'6.5.0'
		);

		wp_enqueue_style(
			'blockshift-layout-fixes',
			BLOCKSHIFT_DIR_URL . '/assets/css/layout-fixes.css',
			array(),
			BLOCKSHIFT_VERSION
		);

		wp_enqueue_script(
			'blockshift-scripts',
			BLOCKSHIFT_DIR_URL . '/assets/js/scripts.js',
			array( 'jquery' ),
			BLOCKSHIFT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( has_block( 'blockshift/icon' ) ) {
			wp_enqueue_style( 'dashicons' );
		}

		if ( has_block( 'blockshift/testimonials' ) ) {
			wp_enqueue_style(
				'swiper-css',
				BLOCKSHIFT_DIR_URL . '/assets/vendor/swiper/swiper-bundle.min.css',
				array(),
				'11.0.0'
			);

			wp_enqueue_script(
				'swiper-js',
				BLOCKSHIFT_DIR_URL . '/assets/vendor/swiper/swiper-bundle.min.js',
				array(),
				'11.0.0',
				true
			);
		}

		// Enqueue form submission script if form block is present
		if ( has_block( 'blockshift/form' ) ) {
			wp_localize_script(
				'blockshift-scripts',
				'blockshiftFormData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'blockshift_form_nonce' ),
				)
			);

			$this->remember_declared_form_fields( (int) get_queried_object_id() );
		}
	}

	/**
	 * Record the fields each form block on a rendered page declares, so the
	 * submission handler has an allow-list to work from without querying for it.
	 *
	 * @param int $post_id Post being rendered.
	 */
	private function remember_declared_form_fields( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		$declared = self::collect_declared_form_fields( (string) get_post_field( 'post_content', $post_id ) );
		if ( empty( $declared ) ) {
			return;
		}

		$stored = get_option( self::OPTION_FORM_FIELDS, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$merged = array_merge( $stored, $declared );
		if ( $merged === $stored ) {
			return;
		}

		update_option( self::OPTION_FORM_FIELDS, $merged, false );
	}

	/**
	 * Map of form name => declared field names, read out of the form blocks in
	 * a piece of post content.
	 *
	 * @param string $content Post content.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function collect_declared_form_fields( string $content ): array {
		$map = array();

		if ( '' === $content || false === strpos( $content, 'wp:blockshift/form' ) ) {
			return $map;
		}

		self::walk_blocks_for_form_fields( parse_blocks( $content ), $map );

		return $map;
	}

	/**
	 * Recursive worker for collect_declared_form_fields().
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<string, array<int, string>> $map   Accumulator, by reference.
	 */
	private static function walk_blocks_for_form_fields( array $blocks, array &$map ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( isset( $block['blockName'] ) && 'blockshift/form' === $block['blockName'] ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

				$form_name = isset( $attrs['formName'] ) ? sanitize_text_field( (string) $attrs['formName'] ) : self::DEFAULT_FORM_NAME;
				if ( '' === $form_name ) {
					$form_name = self::DEFAULT_FORM_NAME;
				}

				$fields = array();
				if ( isset( $attrs['formFields'] ) && is_array( $attrs['formFields'] ) ) {
					foreach ( $attrs['formFields'] as $field ) {
						if ( ! is_array( $field ) || empty( $field['customId'] ) ) {
							continue;
						}

						$key = sanitize_key( (string) $field['customId'] );
						if ( '' !== $key ) {
							$fields[] = $key;
						}
					}
				}

				// A form block that never overrode the attribute carries the
				// defaults declared in block.json.
				$map[ $form_name ] = empty( $fields ) ? self::DEFAULT_FORM_FIELDS : array_values( array_unique( $fields ) );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk_blocks_for_form_fields( $block['innerBlocks'], $map );
			}
		}
	}

	/**
	 * The fields a submission for the given form name is allowed to carry.
	 *
	 * @param string $form_name Submitted form name.
	 *
	 * @return array<int, string>
	 */
	private function get_declared_form_fields( string $form_name ): array {
		$stored = get_option( self::OPTION_FORM_FIELDS, array() );
		if ( is_array( $stored ) && ! empty( $stored[ $form_name ] ) && is_array( $stored[ $form_name ] ) ) {
			return $stored[ $form_name ];
		}

		// Nothing recorded for this form - which happens when a full-page cache
		// means the front-end render never ran. Read the declaration off the page
		// the submission came from instead.
		$referer = wp_get_referer();
		if ( is_string( $referer ) && '' !== $referer ) {
			$post_id = url_to_postid( $referer );
			if ( $post_id > 0 ) {
				$declared = self::collect_declared_form_fields( (string) get_post_field( 'post_content', $post_id ) );
				if ( ! empty( $declared[ $form_name ] ) ) {
					return $declared[ $form_name ];
				}
			}
		}

		return self::DEFAULT_FORM_FIELDS;
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

		$handle = 'blockshift-google-fonts-' . md5( $url );
		if ( isset( $this->enqueued_font_handles[ $handle ] ) ) {
			return;
		}

		wp_enqueue_style( $handle, $url, array(), BLOCKSHIFT_VERSION );
		$this->enqueued_font_handles[ $handle ] = true;
	}

	/**
	 * Add Google Fonts preconnect hints.
	 *
	 * @param array  $urls Existing hint entries.
	 * @param string $relation_type Relation type.
	 *
	 * @return array
	 */
	public function add_font_resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		// Only hint at hosts this response is actually going to use. The font
		// stylesheet is enqueued conditionally, on wp_enqueue_scripts, which runs
		// before wp_resource_hints - so if nothing was enqueued for this request,
		// there is nothing to preconnect to, and a page with no converted fonts
		// must not open a connection to Google.
		if ( empty( $this->enqueued_font_handles ) ) {
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
				'title'       => __( 'Full Width Page', 'migrate-off-elementor' ),
				'description' => __( 'Template for converted full width pages that keeps the active theme header and footer without forcing constrained page layout.', 'migrate-off-elementor' ),
				'post_types'  => array( 'page' ),
				'content'     => sprintf(
					'<!-- wp:template-part {"slug":"header","theme":"%1$s","tagName":"header"} /-->' . "\n\n" .
					'<!-- wp:group {"tagName":"main","className":"blockshift-full-width-page"} -->' . "\n" .
					'<main class="wp-block-group blockshift-full-width-page"><!-- wp:post-content /--></main>' . "\n" .
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'blockshift_form_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'migrate-off-elementor' ),
				)
			);
		}

		// Get form data
		$form_name = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '';
		$form_data = array();

		// Collect only the fields the submitted form's block declares, and ignore
		// everything else in the request: an anonymous caller no longer decides
		// what ends up in the site owner's inbox.
		foreach ( $this->get_declared_form_fields( $form_name ) as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// sanitize_text_field() returns '' for arrays and objects, which is
			// what keeps a crafted array value from causing a fatal here.
			$form_data[ sanitize_key( $field ) ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		}

		// Get admin email
		$admin_email = get_option( 'admin_email' );

		// Prepare email content
		/* translators: %s: name of the form submitted */
		$subject = sprintf( __( 'New Form Submission: %s', 'migrate-off-elementor' ), $form_name );
		/* translators: %s: name of the WordPress site */
		$message = sprintf( __( "You have received a new form submission from %s:\n\n", 'migrate-off-elementor' ), get_bloginfo( 'name' ) );

		foreach ( $form_data as $field => $value ) {
			$message .= sprintf( "%s: %s\n", ucfirst( str_replace( array( '_', '-' ), ' ', $field ) ), $value );
		}

		/* translators: %s: date and time of form submission */
		$message .= sprintf( "\n\n" . __( 'Submitted at: %s', 'migrate-off-elementor' ), current_time( 'mysql' ) );

		// Set email headers
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Try to send email
		$email_sent = wp_mail( $admin_email, $subject, $message, $headers );

		if ( $email_sent ) {
			wp_send_json_success(
				array(
					'message' => __( 'Your submission was successful. We will get back to you soon!', 'migrate-off-elementor' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Your submission failed because of an error. Please try again.', 'migrate-off-elementor' ),
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

		$base_url = plugins_url( 'assets/css/woocommerce/', BLOCKSHIFT_FILE );
		foreach ( $required_handles as $handle => $file ) {
			wp_enqueue_style(
				$handle,
				$base_url . $file,
				array(),
				BLOCKSHIFT_VERSION
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
		$handle_prefix = 'blockshift-wc-';

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
