<?php
// phpcs:ignoreFile

/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use Progressus\BlockShift\Admin\Helper\File_Upload_Service;
use Progressus\BlockShift\Admin\Helper\Block_Builder;
use Progressus\BlockShift\Admin\Layout\Container_Classifier;
use Progressus\BlockShift\Admin\Helper\Style_Parser;
use Progressus\BlockShift\Admin\Helper\Alignment_Helper;
use Progressus\BlockShift\Admin\Helper\External_CSS_Service;
use Progressus\BlockShift\Admin\Helper\External_Style_Collector;
use Progressus\BlockShift\Admin\Helper\AI_Remediation_Screenshot_Api_Service;
use Progressus\BlockShift\Admin\Conversion_Log;
use Progressus\BlockShift\Admin\Conversion_Log_Admin;

use function esc_html;
use function esc_html__;
use function get_option;
use function sanitize_key;
use function current_time;
use function update_option;
use function sanitize_text_field;
use function wp_strip_all_tags;
use function wp_unslash;
use function esc_attr;
use function wp_json_encode;
use function wp_update_post;
use function add_menu_page;
use function add_submenu_page;
use function add_filter;
use function admin_url;
use function sprintf;

defined( 'ABSPATH' ) || exit;

/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 */
class Admin_Settings {
	/**
	 * Singleton instance.
	 *
	 * @var Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * @var External_Style_Collector|null
	 */
	private $external_css_collector = null;

	/**
	 * Page wrapper placeholder token.
	 */
	private const PAGE_WRAPPER_TOKEN = 'ETG_PAGE_ID_PLACEHOLDER';

	/**
	 * Option key storing the section content width (in pixels) applied to converted
	 * top-level Elementor sections. Matches Elementor's default kit container width
	 * (typically 1140px for Hello / SaaSland kits).
	 */
	private const OPTION_SECTION_CONTENT_WIDTH = 'metg_section_content_width';

	/**
	 * Default content width (in pixels) when the user hasn't configured one. 1140px
	 * matches Elementor Hello theme defaults, which most SaaS/marketing kits inherit.
	 */
	private const DEFAULT_SECTION_CONTENT_WIDTH = 1140;

	/**
	 * Option key storing global conversion preferences (currently: copy meta + featured image).
	 */
	private const OPTION_CONVERSION_PREFERENCES = 'metg_conversion_preferences';

	/**
	 * Option key for the conversion logging toggle.
	 */
	private const OPTION_CONVERSION_LOGGING = 'metg_conversion_logging';

	/**
	 * Active per-conversion log collector (reset before each conversion run).
	 *
	 * @var Conversion_Log|null
	 */
	private ?Conversion_Log $conversion_log = null;

	/**
	 * Get global conversion preferences with defaults applied.
	 *
	 * @return array{copy_meta_and_featured_image: bool}
	 */
	public static function get_conversion_preferences(): array {
		$raw = get_option( self::OPTION_CONVERSION_PREFERENCES, array() );
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'copy_meta_and_featured_image' => ! empty( $raw['copy_meta_and_featured_image'] ),
		);
	}

	/**
	 * Whether the global "Copy metadata and featured image" preference is enabled.
	 */
	public static function is_copy_meta_enabled(): bool {
		$prefs = self::get_conversion_preferences();

		return ! empty( $prefs['copy_meta_and_featured_image'] );
	}

	/**
	 * Whether conversion logging is enabled (defaults to true on first install).
	 */
	public static function is_logging_enabled(): bool {
		$val = get_option( self::OPTION_CONVERSION_LOGGING, null );
		// Default to enabled when option has never been saved.
		if ( null === $val ) {
			return true;
		}
		return (bool) $val;
	}

	/**
	 * Return the Conversion_Log instance from the most recent conversion run.
	 * Returns null if logging is disabled or no conversion has run yet.
	 */
	public function get_conversion_log(): ?Conversion_Log {
		return $this->conversion_log;
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Settings
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_filter( 'plugin_action_links_' . BLOCKSHIFT_BASENAME, array( $this, 'add_plugin_action_links' ) );
		add_filter( 'page_row_actions', array( $this, 'metg_add_convert_button' ), 10, 2 );
		add_action( 'admin_post_metg_convert_page', array( $this, 'metg_handle_convert_page' ) );
		add_action( 'admin_post_metg_save_screenshot_settings', array( $this, 'save_screenshot_settings' ) );
		add_action( 'admin_post_metg_save_settings', array( $this, 'save_all_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the Progressus design-system stylesheet + inline icon engine
	 * on the Settings screen so its .pgs markup is styled and its
	 * <i data-icon> placeholders are replaced with inline SVG.
	 */
	public function enqueue_assets(): void {
		if ( empty( $_GET['page'] ) || 'gutenberg-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$css_path  = BLOCKSHIFT_DIR_PATH . '/assets/css/batch-wizard.css';
		$icons_path = BLOCKSHIFT_DIR_PATH . '/assets/js/pgs-icons.js';

		wp_enqueue_style(
			'metg-pgs-admin',
			plugins_url( 'assets/css/batch-wizard.css', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $css_path ) ? (string) filemtime( $css_path ) : BLOCKSHIFT_VERSION
		);

		wp_enqueue_script(
			'metg-pgs-icons',
			plugins_url( 'assets/js/pgs-icons.js', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $icons_path ) ? (string) filemtime( $icons_path ) : BLOCKSHIFT_VERSION,
			true
		);
	}

	/**
	 * Save every setting on the Migration Tool page in a single round-trip.
	 *
	 * Why: the page renders one form with all sections so users hit one button.
	 */
	public function save_all_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change plugin settings.', 'blockshift-migrate-from-elementor' ) );
		}

		check_admin_referer( 'metg_save_settings' );

		$claude_raw = isset( $_POST['metg_claude_settings'] ) ? wp_unslash( $_POST['metg_claude_settings'] ) : array();
		$claude_raw = is_array( $claude_raw ) ? $claude_raw : array();
		$api_key    = isset( $claude_raw['api_key'] ) ? sanitize_text_field( (string) $claude_raw['api_key'] ) : '';
		update_option( 'metg_claude_settings', array( 'api_key' => $api_key ), false );

		$prefs_raw = isset( $_POST['metg_conversion_preferences'] ) ? wp_unslash( $_POST['metg_conversion_preferences'] ) : array();
		$prefs_raw = is_array( $prefs_raw ) ? $prefs_raw : array();
		update_option(
			self::OPTION_CONVERSION_PREFERENCES,
			array(
				'copy_meta_and_featured_image' => ! empty( $prefs_raw['copy_meta_and_featured_image'] ),
			),
			false
		);

		$logging_raw = isset( $_POST['metg_logging_settings'] ) ? wp_unslash( $_POST['metg_logging_settings'] ) : array();
		$logging_raw = is_array( $logging_raw ) ? $logging_raw : array();
		update_option( self::OPTION_CONVERSION_LOGGING, ! empty( $logging_raw['enabled'] ), false );

		$layout_raw = isset( $_POST['metg_layout_settings'] ) ? wp_unslash( $_POST['metg_layout_settings'] ) : array();
		$layout_raw = is_array( $layout_raw ) ? $layout_raw : array();
		$width      = isset( $layout_raw['section_content_width'] ) ? (int) $layout_raw['section_content_width'] : self::DEFAULT_SECTION_CONTENT_WIDTH;
		if ( $width < 320 ) {
			$width = 320;
		}
		if ( $width > 2560 ) {
			$width = 2560;
		}
		update_option( self::OPTION_SECTION_CONTENT_WIDTH, $width, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'gutenberg-settings',
					'metg_settings_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Add a Gutenberg conversion action to page row actions.
	 *
	 * @param array<string, mixed> $actions Existing row actions.
	 * @param \WP_Post $post Current post object.
	 *
	 * @return array<string, mixed>
	 */
	public function metg_add_convert_button( $actions, $post ) {
		if ( $post->post_type === 'page' ) {
			$json_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( empty( $json_data ) ) {
				return $actions;
			}
			$url                             = wp_nonce_url(
				admin_url( 'admin-post.php?action=metg_convert_page&page_id=' . $post->ID ),
				'metg_convert_page_' . $post->ID
			);
			$actions['convert_to_gutenberg'] = '<a href="' . esc_url( $url ) . '">Convert to Gutenberg</a>';
		}

		return $actions;
	}


	/**
	 * Handle the admin convert page action.
	 *
	 * @return void
	 */
	public function metg_handle_convert_page() {
		if ( ! isset( $_GET['page_id'] ) ) {
			wp_die( 'Page ID missing.' );
		}

		$page_id = absint( $_GET['page_id'] );

		// Verify nonce
		check_admin_referer( 'metg_convert_page_' . $page_id );

		// Get JSON template stored in post meta
		$json_data = get_post_meta( $page_id, '_elementor_data', true ); // Example for Elementor
		if ( empty( $json_data ) ) {
			wp_die( 'No template JSON found for this page.' );
		}

		$data['content'] = json_decode( $json_data, true );
		// Convert JSON → Gutenberg blocks
		$blocks = $this->convert_json_to_gutenberg_content( $data );

		// Create new page with blocks
		$new_page_id = $this->insert_new_page( $page_id, $blocks );
		if ( $new_page_id ) {
			$this->finalize_converted_post( (int) $new_page_id, (string) $blocks, true );
			if ( self::source_uses_elementor_full_width_template( (int) $page_id ) ) {
				$this->assign_metg_full_width_template( (int) $new_page_id );
			}
		}

		if ( $new_page_id ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_page_id . '&action=edit' ) );
			exit;
		}

		wp_die( 'Failed to create Gutenberg page.' );
	}

	/**
	 * Detect whether the source page is using one of Elementor's full-width
	 * page templates (Elementor Canvas, Elementor Full Width, Elementor
	 * Header/Footer) or has an explicit full-width Elementor page setting.
	 *
	 * Same logic the batch wizard uses, lifted into a small static helper so
	 * the row-action convert path can reuse it without spinning up the
	 * wizard class.
	 *
	 * @param int $source_id Source Elementor page ID.
	 */
	public static function source_uses_elementor_full_width_template( int $source_id ): bool {
		if ( $source_id <= 0 ) {
			return false;
		}

		$template_slug = (string) get_page_template_slug( $source_id );
		$elementor_templates = array( 'elementor_canvas', 'elementor_full_width', 'elementor_header_footer' );
		if ( in_array( $template_slug, $elementor_templates, true ) ) {
			return true;
		}
		if ( '' !== $template_slug && 0 === strpos( $template_slug, 'elementor' ) ) {
			return true;
		}

		$page_settings = get_post_meta( $source_id, '_elementor_page_settings', true );
		if ( is_array( $page_settings ) ) {
			$page_layout = isset( $page_settings['page_layout'] ) ? (string) $page_settings['page_layout'] : '';
			$template    = isset( $page_settings['template'] ) ? (string) $page_settings['template'] : '';

			if ( '' !== $page_layout && false !== strpos( $page_layout, 'elementor' ) ) {
				return true;
			}
			if ( '' !== $template && false !== strpos( $template, 'elementor' ) ) {
				return true;
			}
			if ( in_array( $page_layout, array( 'canvas', 'full_width', 'elementor_canvas', 'elementor_full_width' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Assign the Full Width Page template to the converted page.
	 *
	 * Always stores the classic-template path slug (`templates/metg-full-width-page.php`)
	 * — the `template_include` filter in class-gutenberg.php intercepts the
	 * request and loads the plugin's template file regardless of whether the
	 * active theme is classic or block-based. Storing the same slug for both
	 * theme types avoids the previous block-theme lookup miss where the slug
	 * `full-width-page` was unrecognized because the block template was
	 * registered under the `progressus-metg` namespace rather than the active
	 * theme.
	 *
	 * @param int $target_id Converted page ID.
	 */
	private function assign_metg_full_width_template( int $target_id ): void {
		if ( $target_id <= 0 ) {
			return;
		}

		$slug = \Progressus\BlockShift\Gutenberg::FULL_WIDTH_PAGE_TEMPLATE_SLUG;

		update_post_meta( $target_id, '_wp_page_template', $slug );
		delete_post_meta( $target_id, 'wp_template' );
		clean_post_cache( $target_id );
	}

	/**
	 * Insert new page with Gutenberg blocks.
	 *
	 * @param int $page_id Page ID.
	 * @param array $blocks Gutenberg blocks.
	 *
	 * @return int New page ID.
	 */
	public function insert_new_page( $page_id, $blocks ): int {
		$new_page_id = wp_insert_post( array(
				'post_title'   => get_the_title( $page_id ) . ' (Gutenberg)',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $blocks,
			)
		);

		return $new_page_id;
	}

	/**
	 * Register the plugin's top-level admin menu and its Settings submenu.
	 *
	 * Why: the user wants the plugin promoted out of "Tools" into the main
	 * dashboard sidebar so it sits alongside other site-wide tools.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			esc_html__( 'BlockShift – Migrate from Elementor', 'blockshift-migrate-from-elementor' ),
			esc_html__( 'BlockShift – Migrate from Elementor', 'blockshift-migrate-from-elementor' ),
			'manage_options',
			'gutenberg-settings',
			array( $this, 'settings_page_content' ),
			'dashicons-migrate',
			76
		);

		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Settings', 'blockshift-migrate-from-elementor' ),
			esc_html__( 'Settings', 'blockshift-migrate-from-elementor' ),
			'manage_options',
			'gutenberg-settings',
			array( $this, 'settings_page_content' )
		);

		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
	}

	/**
	 * Move Conversion Wizard above Settings in the submenu.
	 */
	public function reorder_submenu(): void {
		global $submenu;
		if ( ! isset( $submenu['gutenberg-settings'] ) || ! is_array( $submenu['gutenberg-settings'] ) ) {
			return;
		}

		$desired = array(
			Batch_Convert_Wizard::MENU_SLUG,
			AI_Enhancement_Admin::MENU_SLUG,
			Conversion_Log_Admin::MENU_SLUG,
			'gutenberg-settings',
		);

		$indexed   = array();
		$remaining = array();
		foreach ( $submenu['gutenberg-settings'] as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			$pos  = array_search( $slug, $desired, true );
			if ( false !== $pos ) {
				$indexed[ (int) $pos ] = $item;
			} else {
				$remaining[] = $item;
			}
		}

		ksort( $indexed );
		$submenu['gutenberg-settings'] = array_values( array_merge( $indexed, $remaining ) );
	}

	/**
	 * Add quick links on the Plugins screen.
	 *
	 * @param array<string, string> $links Existing links.
	 *
	 * @return array<string, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		return $links;
	}

	/**
	 * Get direct URL to the migration wizard.
	 */
	private function get_wizard_url(): string {
		return admin_url( 'admin.php?page=' . Batch_Convert_Wizard::MENU_SLUG );
	}

	/**
	 * Handle JSON file upload and conversion.
	 *
	 * @param mixed $option The option value.
	 *
	 * @return string The processed Gutenberg content or existing option.
	 */
	public function handle_json_upload( $option ): string {
		if ( empty( $_FILES['json_upload']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// return a string to satisfy the declared return type.
			if ( is_string( $option ) ) {
				return $option;
			}

			return get_option( 'gutenberg_json_data', '' );
		}

		$json_content = File_Upload_Service::upload_file( $_FILES['json_upload'], 'json' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $json_content ) {
			return get_option( 'gutenberg_json_data', '' );
		}

		$data              = json_decode( $json_content, true );
		$gutenberg_content = $this->convert_json_to_gutenberg_content( $data );

		$post_title = $data['title'] ?? 'Untitled';
		$post_type  = $data['type'] ?? 'page';

		// Check if a post with the same title and type exists
		$existing_post = get_page_by_title( $post_title, OBJECT, $post_type );

		if ( $existing_post ) {
			// Update existing post
			$new_post_id = wp_update_post(
				array(
					'ID'           => $existing_post->ID,
					'post_content' => $gutenberg_content,
					'post_status'  => 'publish',
				)
			);
		} else {
			// Create new post
			$new_post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $post_title ),
					'post_content' => $gutenberg_content,
					'post_type'    => sanitize_key( $post_type ),
					'post_status'  => 'publish',
				)
			);
		}

		if ( is_wp_error( $new_post_id ) ) {
			add_settings_error(
				'gutenberg_json_data',
				'json_upload_error',
				esc_html__( 'Failed to create new page.', 'blockshift-migrate-from-elementor' ),
				'error'
			);

			return get_option( 'gutenberg_json_data', '' );
		}

		add_settings_error(
			'gutenberg_json_data',
			'json_upload_success',
			esc_html__( 'JSON file uploaded and page created successfully!', 'blockshift-migrate-from-elementor' ),
			'updated'
		);

		return $gutenberg_content;
	}

	/**
	 * Save screenshot service settings submitted from the settings page form.
	 *
	 * Accepts POST data from the screenshot settings form, sanitizes all fields,
	 * persists them via AI_Remediation_Screenshot_Api_Service, then redirects back.
	 */
	public function save_screenshot_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change plugin settings.', 'blockshift-migrate-from-elementor' ) );
		}

		check_admin_referer( 'metg_save_screenshot_settings' );

		$raw = isset( $_POST['metg_screenshot_settings'] ) ? wp_unslash( $_POST['metg_screenshot_settings'] ) : array();
		$raw = is_array( $raw ) ? $raw : array();

		$endpoint_url  = isset( $raw['endpoint_url'] ) ? esc_url_raw( sanitize_text_field( (string) $raw['endpoint_url'] ) ) : '';
		$timeout       = isset( $raw['timeout'] ) ? max( 5, min( 120, (int) $raw['timeout'] ) ) : 15;
		$auto_generate = ! empty( $raw['auto_generate'] );

		$settings = array(
			'endpoint_url'  => $endpoint_url,
			'timeout'       => $timeout,
			'auto_generate' => $auto_generate,
		);

		AI_Remediation_Screenshot_Api_Service::save_settings( $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'gutenberg-settings',
					'metg_settings_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page content.
	 */
	public function settings_page_content(): void {
		$claude_settings  = get_option( 'metg_claude_settings', array() );
		$claude_settings  = is_array( $claude_settings ) ? $claude_settings : array();
		$claude_api_key   = isset( $claude_settings['api_key'] ) ? (string) $claude_settings['api_key'] : '';
		$copy_meta_enabled = self::is_copy_meta_enabled();
		$current_width    = $this->get_section_content_width_px();
		?>
        <div class="wrap pgs">
        <div class="pgs-screen" data-screen-label="Settings">

            <header class="pgs-pluginhead">
                <span class="pgs-pluginhead__brand"><span class="pgs-pluginhead__name"><?php esc_html_e( 'BlockShift – Migrate from Elementor', 'blockshift-migrate-from-elementor' ); ?></span></span>
            </header>
            <hr class="wp-header-end" style="margin:0;border:0;">

            <div class="pgs-col">
                <div class="pgs-pagetitle">
                    <div>
                        <h1><?php esc_html_e( 'BlockShift – Migrate from Elementor', 'blockshift-migrate-from-elementor' ); ?></h1>
                        <p><?php esc_html_e( 'Professional migration tool to convert Elementor layouts into native Gutenberg blocks.', 'blockshift-migrate-from-elementor' ); ?></p>
                    </div>
                </div>

                <?php if ( isset( $_GET['metg_settings_saved'] ) && '1' === $_GET['metg_settings_saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="pgs-banner pgs-banner--success" role="status">
                        <span class="pgs-banner__icon"><i data-icon="check-circle-2"></i></span>
                        <div class="pgs-banner__body"><span class="pgs-banner__text"><?php esc_html_e( 'Settings saved.', 'blockshift-migrate-from-elementor' ); ?></span></div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'metg_save_settings' ); ?>
                    <input type="hidden" name="action" value="metg_save_settings" />

                    <div class="pgs-stack" style="gap:var(--gap-section);">

                        <div class="pgs-card">
                            <div class="pgs-card__header">
                                <div>
                                    <div class="pgs-card__eyebrow"><?php esc_html_e( 'Output', 'blockshift-migrate-from-elementor' ); ?></div>
                                    <div class="pgs-card__title"><?php esc_html_e( 'Layout Settings', 'blockshift-migrate-from-elementor' ); ?></div>
                                </div>
                            </div>
                            <div class="pgs-card__body">
                                <div class="pgs-setrow">
                                    <div class="pgs-setrow__meta">
                                        <label class="pgs-setrow__label" for="metg_section_content_width"><?php esc_html_e( 'Section content width', 'blockshift-migrate-from-elementor' ); ?></label>
                                        <div class="pgs-setrow__desc"><?php esc_html_e( 'Controls the content width applied to converted top-level Elementor sections. Match this to your Elementor kit\'s container width so converted pages render at the same width as the originals. Typical values: 1140, 1200, 1024. Clamped to 320–2560.', 'blockshift-migrate-from-elementor' ); ?></div>
                                    </div>
                                    <div class="pgs-setrow__control">
                                        <div class="pgs-field">
                                            <div class="pgs-input">
                                                <input class="pgs-input__el" type="number" id="metg_section_content_width" name="metg_layout_settings[section_content_width]" value="<?php echo esc_attr( (string) $current_width ); ?>" min="320" max="2560" step="10" />
                                                <span class="pgs-input__affix">px</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pgs-card">
                            <div class="pgs-card__header">
                                <div>
                                    <div class="pgs-card__eyebrow"><?php esc_html_e( 'Defaults', 'blockshift-migrate-from-elementor' ); ?></div>
                                    <div class="pgs-card__title"><?php esc_html_e( 'Conversion Preferences', 'blockshift-migrate-from-elementor' ); ?></div>
                                </div>
                            </div>
                            <div class="pgs-card__body">
                                <div class="pgs-setrow">
                                    <div class="pgs-setrow__meta">
                                        <div class="pgs-setrow__label"><?php esc_html_e( 'Metadata', 'blockshift-migrate-from-elementor' ); ?></div>
                                        <div class="pgs-setrow__desc"><?php esc_html_e( 'When enabled, every converted page automatically copies post meta fields and the featured image from the source Elementor page. When disabled, the wizard skips this step entirely.', 'blockshift-migrate-from-elementor' ); ?></div>
                                    </div>
                                    <div class="pgs-setrow__control">
                                        <label class="pgs-check">
                                            <input type="checkbox" class="pgs-check__input" name="metg_conversion_preferences[copy_meta_and_featured_image]" value="1" <?php checked( $copy_meta_enabled ); ?> />
                                            <span class="pgs-check__box" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                            <span class="pgs-check__text"><span class="pgs-check__label"><?php esc_html_e( 'Copy metadata and featured image', 'blockshift-migrate-from-elementor' ); ?></span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pgs-card">
                            <div class="pgs-card__header">
                                <div>
                                    <div class="pgs-card__eyebrow"><?php esc_html_e( 'Visibility', 'blockshift-migrate-from-elementor' ); ?></div>
                                    <div class="pgs-card__title"><?php esc_html_e( 'Logging', 'blockshift-migrate-from-elementor' ); ?></div>
                                </div>
                            </div>
                            <div class="pgs-card__body">
                                <div class="pgs-setrow">
                                    <div class="pgs-setrow__meta">
                                        <div class="pgs-setrow__label"><?php esc_html_e( 'Conversion logging', 'blockshift-migrate-from-elementor' ); ?></div>
                                        <div class="pgs-setrow__desc">
                                            <?php
                                            printf(
                                                wp_kses(
                                                    /* translators: %s: URL to Conversion Log page */
                                                    __( 'When enabled, each conversion records which widgets were converted, unsupported, or produced empty output. View the results in the <a href="%s">Conversion Log</a>. The log keeps the last 300 entries and does not affect conversion speed.', 'blockshift-migrate-from-elementor' ),
                                                    array( 'a' => array( 'href' => array() ) )
                                                ),
                                                esc_url( admin_url( 'admin.php?page=metg-conversion-log' ) )
                                            );
                                            ?>
                                        </div>
                                    </div>
                                    <div class="pgs-setrow__control">
                                        <label class="pgs-switch">
                                            <input type="checkbox" role="switch" class="pgs-switch__input" name="metg_logging_settings[enabled]" value="1" <?php checked( self::is_logging_enabled() ); ?> />
                                            <span class="pgs-switch__track" aria-hidden="true"></span>
                                            <span class="pgs-switch__text"><span class="pgs-switch__label"><?php esc_html_e( 'Enable conversion logging', 'blockshift-migrate-from-elementor' ); ?></span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pgs-card">
                            <div class="pgs-card__header">
                                <div>
                                    <div class="pgs-card__eyebrow"><?php esc_html_e( 'Integration', 'blockshift-migrate-from-elementor' ); ?></div>
                                    <div class="pgs-card__title"><?php esc_html_e( 'Claude AI', 'blockshift-migrate-from-elementor' ); ?></div>
                                </div>
                            </div>
                            <div class="pgs-card__body">
                                <div class="pgs-setrow">
                                    <div class="pgs-setrow__meta">
                                        <label class="pgs-setrow__label" for="metg_claude_api_key"><?php esc_html_e( 'Claude API Key', 'blockshift-migrate-from-elementor' ); ?></label>
                                        <div class="pgs-setrow__desc">
                                            <?php esc_html_e( 'Your Anthropic API key. Required for the "Improve with AI" automated workflow.', 'blockshift-migrate-from-elementor' ); ?>
                                            <a href="https://console.anthropic.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Purchase Claude API key', 'blockshift-migrate-from-elementor' ); ?></a>
                                        </div>
                                    </div>
                                    <div class="pgs-setrow__control">
                                        <div class="pgs-field">
                                            <div class="pgs-input pgs-input--mono">
                                                <input class="pgs-input__el" type="password" id="metg_claude_api_key" name="metg_claude_settings[api_key]" value="<?php echo esc_attr( $claude_api_key ); ?>" autocomplete="off" />
                                                <span class="pgs-input__affix">
                                                    <?php if ( '' !== $claude_api_key ) : ?>
                                                        <span class="pgs-pill pgs-pill--success"><span class="pgs-pill__icon"><i data-icon="check"></i></span><?php esc_html_e( 'Configured', 'blockshift-migrate-from-elementor' ); ?></span>
                                                    <?php else : ?>
                                                        <span class="pgs-pill pgs-pill--neutral"><?php esc_html_e( 'Not configured', 'blockshift-migrate-from-elementor' ); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pgs-actions-end">
                            <button type="submit" class="pgs-btn pgs-btn--primary pgs-btn--md"><span class="pgs-btn__icon"><i data-icon="save"></i></span><span><?php esc_html_e( 'Save Settings', 'blockshift-migrate-from-elementor' ); ?></span></button>
                        </div>

                    </div>
                </form>
            </div>

        </div>
        </div>
		<?php
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param array $json_data The JSON data to convert.
	 *
	 * @return string The converted Gutenberg content.
	 */
	public function convert_json_to_gutenberg_content( array $json_data ): string {
		$this->external_css_collector = new External_Style_Collector();
		Block_Builder::bootstrap( $this->external_css_collector );

		$this->conversion_log = self::is_logging_enabled() ? new Conversion_Log() : null;

		if ( empty( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
			return '';
		}

		$content = $this->parse_elementor_elements( $json_data['content'] );
		$content = $this->wrap_converted_content( $content );
		$this->log_inventory_summary();

		return $content;
	}

	/**
	 * Replace the page wrapper token with the actual post ID.
	 *
	 * @param string $content Content containing the wrapper token.
	 * @param int $post_id Post ID to inject.
	 *
	 * @return string
	 */
	public function replace_page_wrapper_token( string $content, int $post_id ): string {
		if ( '' === $content || $post_id <= 0 ) {
			return $content;
		}

		return str_replace( self::PAGE_WRAPPER_TOKEN, (string) $post_id, $content );
	}

	/**
	 * Finalize converted post content and save external CSS.
	 *
	 * @param int $post_id Post ID.
	 * @param string $content Converted content.
	 * @param bool $update_post Whether to update post content.
	 *
	 * @return string
	 */
	public function finalize_converted_post( int $post_id, string $content, bool $update_post = true ): string {
		if ( $post_id <= 0 ) {
			return $content;
		}

		$updated_content = $this->replace_page_wrapper_token( $content, $post_id );

		if ( $update_post && $updated_content !== $content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				)
			);
		}

		$css = $this->get_external_css();
		if ( '' !== trim( $css ) ) {
			$css = $this->replace_page_wrapper_token( $css, $post_id );
			External_CSS_Service::save_post_css( $post_id, (string) $css );
		}

		$this->persist_post_used_fonts( $post_id );

		return $updated_content;
	}

	/**
	 * Log external CSS inventory summary when debugging is enabled.
	 *
	 * @return void
	 */
	private function log_inventory_summary(): void {
		if ( null === $this->external_css_collector ) {
			return;
		}

		$inventory = $this->external_css_collector->get_inventory();
		if ( empty( $inventory ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$summary = array(
				'externalized' => count( $inventory['externalized'] ?? array() ),
				'dropped'      => count( $inventory['dropped'] ?? array() ),
				'conversions'  => count( $inventory['conversions'] ?? array() ),
			);

			error_log( 'inventory: ' . wp_json_encode( $summary ) );
		}
	}

	/**
	 * Render the collected external CSS.
	 *
	 * @return string
	 */
	private function get_external_css(): string {
		if ( null === $this->external_css_collector ) {
			return '';
		}

		return $this->external_css_collector->render_css();
	}

	/**
	 * Persist collected converted-font usage for the target post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function persist_post_used_fonts( int $post_id ): void {
		if ( $post_id <= 0 || null === $this->external_css_collector ) {
			return;
		}

		$fonts = $this->external_css_collector->get_font_usage();
		if ( empty( $fonts ) ) {
			delete_post_meta( $post_id, '_metg_used_fonts' );
			delete_post_meta( $post_id, '_metg_used_fonts_hash' );
			return;
		}

		$hash = md5( (string) wp_json_encode( $fonts ) );

		update_post_meta( $post_id, '_metg_used_fonts', $fonts );
		update_post_meta( $post_id, '_metg_used_fonts_hash', $hash );
	}

	/**
	 * Wrap converted content in a page-level group for typography scoping.
	 *
	 * @param string $content Converted Gutenberg blocks.
	 *
	 * @return string
	 */
	private function wrap_converted_content( string $content ): string {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$page_class  = $this->get_page_wrapper_class();
		$extra_class = $this->collect_page_typography_rules( $page_class );
		$class_name  = trim( $page_class . ' ' . $extra_class );

		// Zero the block-gap so converted sections butt directly together.
		// Elementor sections have no default vertical gap between them; any
		// section that wants explicit space declares its own margin.
		$attributes = array(
			'align'     => 'full',
			'className' => $class_name,
			'layout'    => $this->build_top_level_constrained_layout(),
			'style'     => array(
				'spacing' => array(
					'blockGap' => '0',
				),
			),
		);

		return Block_Builder::build( 'group', $attributes, $content );
	}

	/**
	 * Build a predictable page wrapper class name.
	 *
	 * @return string
	 */
	private function get_page_wrapper_class(): string {
		return 'metg-page-' . self::PAGE_WRAPPER_TOKEN;
	}

	/**
	 * Get the page wrapper class name used during conversion.
	 *
	 * @return string
	 */
	public static function get_page_wrapper_class_name(): string {
		return 'metg-page-' . self::PAGE_WRAPPER_TOKEN;
	}

	/**
	 * Collect typography rules for the current conversion into external CSS.
	 *
	 * @param string $page_class Base wrapper class.
	 *
	 * @return string Extra class names for wrapper.
	 */
	private function collect_page_typography_rules( string $page_class ): string {
		if ( null === $this->external_css_collector ) {
			return '';
		}

		$page_class = trim( (string) $page_class );
		if ( '' === $page_class ) {
			return '';
		}

		$body_settings    = Style_Parser::get_elementor_kit_typography( 'body' );
		$heading_settings = Style_Parser::get_elementor_kit_typography( 'headings' );

		$body_rules    = Style_Parser::build_typography_declarations( $body_settings );
		$heading_rules = Style_Parser::build_typography_declarations( $heading_settings );

		$this->external_css_collector->add_font_usage(
			(string) ( $body_rules['font-family'] ?? '' ),
			(string) ( $body_rules['font-weight'] ?? '' ),
			(string) ( $body_rules['font-style'] ?? '' )
		);

		$this->external_css_collector->add_font_usage(
			(string) ( $heading_rules['font-family'] ?? '' ),
			(string) ( $heading_rules['font-weight'] ?? '' ),
			(string) ( $heading_rules['font-style'] ?? '' )
		);

		$extra_classes = array();

		if ( isset( $body_rules['font-family'] ) ) {
			$font_slug = Style_Parser::match_font_family_slug( (string) $body_rules['font-family'] );
			if ( null !== $font_slug && '' !== $font_slug ) {
				$extra_classes[] = 'has-' . Style_Parser::clean_class( $font_slug ) . '-font-family';
				unset( $body_rules['font-family'] );
			}
		}

		if ( isset( $heading_rules['font-family'] ) ) {
			$font_slug = Style_Parser::match_font_family_slug( (string) $heading_rules['font-family'] );
			if ( null !== $font_slug && '' !== $font_slug ) {
				$heading_rules['font-family'] = Style_Parser::build_font_family_preset_value( $font_slug );
			}
		}

		$base_selector = '.' . $page_class;

		if ( ! empty( $body_rules ) ) {
			$this->external_css_collector->register_rule( $base_selector, $body_rules, 'kit-typography-body' );
		}

		if ( ! empty( $heading_rules ) && ! empty( $heading_settings['typography_typography'] ) ) {
			$selectors = array(
				$base_selector . ' h1',
				$base_selector . ' h2',
				$base_selector . ' h3',
				$base_selector . ' h4',
				$base_selector . ' h5',
				$base_selector . ' h6',
				$base_selector . ' .wp-block-heading',
			);
			$this->external_css_collector->register_rule( implode( ', ', $selectors ), $heading_rules, 'kit-typography-headings' );
		}

		return implode( ' ', $extra_classes );
	}

	/**
	 * Gutenberg adds `has-background` class automatically when a Group has a background color/gradient.
	 * If our converter does not add it, block validation will warn because the saved HTML differs.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return array
	 */
	private function maybe_add_group_has_background_class( array $attributes ): array {
		$has_background = false;

		if (
			isset( $attributes['style']['color']['background'] )
			&& '' !== trim( (string) $attributes['style']['color']['background'] )
		) {
			$has_background = true;
		}

		if (
			isset( $attributes['style']['color']['gradient'] )
			&& '' !== trim( (string) $attributes['style']['color']['gradient'] )
		) {
			$has_background = true;
		}

		if ( ! $has_background ) {
			return $attributes;
		}

		$existing = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
		$classes  = preg_split( '/\s+/', $existing );
		$classes  = is_array( $classes ) ? array_filter( $classes ) : array();

		if ( ! in_array( 'has-background', $classes, true ) ) {
			$classes[] = 'has-background';
		}

		$attributes['className'] = trim( implode( ' ', $classes ) );

		return $attributes;
	}

	/**
	 * Parse Elementor elements to Gutenberg blocks.
	 *
	 * @param array $elements The Elementor elements array.
	 *
	 * @return string The converted Gutenberg block content.
	 */
	public function parse_elementor_elements( array $elements ): string {
		$blocks = '';
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$blocks .= $this->render_element( $element, true );
		}

		return $blocks;
	}

	/**
	 * Render an Elementor element into block markup.
	 *
	 * @param array $element Elementor element.
	 */
	private function render_element( array $element, bool $is_top_level = false ): string {
		$el_type = $element['elType'] ?? '';
		if ( 'container' === $el_type ) {
			return $this->render_container( $element, $is_top_level );
		}

		if ( 'section' === $el_type ) {
			return $this->render_legacy_section( $element, $is_top_level );
		}

		if ( 'column' === $el_type ) {
			return $this->render_legacy_column( $element );
		}

		if ( 'widget' === $el_type || isset( $element['widgetType'] ) ) {
			$widget_type = $element['widgetType'] ?? '';
			$handler     = Widget_Handler_Factory::get_handler( $widget_type );
			if ( null !== $handler ) {
				$output = $handler->handle( $element );
				if ( null !== $this->conversion_log ) {
					if ( '' === $output ) {
						$this->conversion_log->record_empty_output( $widget_type );
					} else {
						$this->conversion_log->record_converted( $widget_type );
					}
				}
				return $output;
			}

			if ( null !== $this->conversion_log ) {
				$this->conversion_log->record_unsupported( $widget_type );
			}
			return $this->render_placeholder_block( $element );
		}

		if ( null !== $this->conversion_log ) {
			$this->conversion_log->record_unsupported( $element['elType'] ?? 'unknown' );
		}
		return $this->render_placeholder_block( $element );
	}

	/**
	 * Render a legacy section element.
	 *
	 * @param array $element Elementor section element.
	 */
	private function render_legacy_section( array $element, bool $is_top_level = false ): string {
		$children = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		$attributes = Style_Parser::parse_container_styles( $settings );
		$attributes = $this->add_legacy_unique_class( $attributes, $element );

		$column_children = array();
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || ! isset( $child['elType'] ) || 'column' !== $child['elType'] ) {
				continue;
			}

			$column_children[] = $child;
		}

		if ( count( $column_children ) >= 2 ) {
			$inner_html = '';
			foreach ( $column_children as $column_element ) {
				$content = $this->render_legacy_column( $column_element );
				if ( '' === trim( $content ) ) {
					continue;
				}

				$inner_html .= $content;
			}

			if ( '' === trim( $inner_html ) ) {
				return '';
			}

			if ( $is_top_level ) {
				$split           = $this->split_section_attrs_for_wrap( $attributes );
				$inner_attr      = $this->propagate_flex_gap_to_inner( $split['inner'], $settings );
				$columns_block   = Block_Builder::build( 'columns', $inner_attr, $inner_html );

				return $this->wrap_top_level_columns_in_group( $split['outer'], $settings, $columns_block );
			}

			return Block_Builder::build( 'columns', $attributes, $inner_html );
		}

		$inner_html = '';
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$inner_html .= $this->render_element( $child, false );
		}

		if ( '' === trim( $inner_html ) ) {
			return '';
		}

		if ( $is_top_level ) {
			$attributes           = $this->apply_full_width_section_attributes( $attributes, $settings );
			$attributes['layout'] = $this->build_top_level_constrained_layout();
		}

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a legacy column element.
	 *
	 * @param array $element Elementor column element.
	 */
	private function render_legacy_column( array $element ): string {
		$children = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

		$attributes = Style_Parser::parse_container_styles( $settings );
		$attributes = $this->add_legacy_unique_class( $attributes, $element );

		$width_value = null;
		if ( isset( $settings['_inline_size'] ) && is_numeric( $settings['_inline_size'] ) && (float) $settings['_inline_size'] > 0 ) {
			$width_value = (float) $settings['_inline_size'];
		} elseif ( isset( $settings['_column_size'] ) && is_numeric( $settings['_column_size'] ) && (float) $settings['_column_size'] > 0 ) {
			$width_value = (float) $settings['_column_size'];
		}

		if ( null !== $width_value ) {
			$rounded             = round( $width_value, 2 );
			$attributes['width'] = rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' ) . '%';
		}

		$inner_html = '';
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$inner_html .= $this->render_element( $child );
		}

		if ( null !== $width_value && '' === trim( $inner_html ) && null !== $this->external_css_collector ) {
			$unique_class = Style_Parser::get_element_unique_class( $element );
			if ( '' !== $unique_class ) {
				$this->external_css_collector->register_rule(
					'.' . $unique_class,
					array( 'min-width' => (string) $attributes['width'] ),
					'empty-column-spacer'
				);
			}
		}

		return Block_Builder::build( 'column', $attributes, $inner_html );
	}

	/**
	 * Add legacy element unique class to block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $element Elementor element.
	 */
	private function add_legacy_unique_class( array $attributes, array $element ): array {
		$unique_class = Style_Parser::get_element_unique_class( $element );
		if ( '' === $unique_class ) {
			return $attributes;
		}

		return $this->add_class_to_attributes( $attributes, $unique_class );
	}

	/**
	 * Render a container element based on layout classification.
	 *
	 * @param array $element Elementor container element.
	 */
	private function render_container( array $element, bool $is_top_level = false ): string {
		$children           = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$container_settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$container_attr     = Style_Parser::parse_container_styles( $container_settings );

		$min_height_setting = $container_settings['min_height'] ?? null;

		$has_min_height = false;
		if ( is_array( $min_height_setting ) ) {
			$has_min_height = isset( $min_height_setting['size'] ) && '' !== $min_height_setting['size'];
		} elseif ( null !== $min_height_setting && '' !== $min_height_setting ) {
			$has_min_height = true;
		}

		$parent_has_background = ! empty( $container_settings['background_image'] )
		                         || ! empty( $container_settings['_background_image'] );

		$propagate_min_height = $has_min_height && ! $parent_has_background;

		$child_blocks = array();
		$child_data   = array();

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			if ( $propagate_min_height && isset( $child['elType'] ) && 'container' === $child['elType'] ) {
				$child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : array();

				$child_has_background = ! empty( $child_settings['background_image'] )
				                        || ! empty( $child_settings['_background_image'] );

				if ( $child_has_background && empty( $child_settings['min_height'] ) ) {
					$child_settings['min_height'] = $min_height_setting;
					$child['settings']            = $child_settings;
				}
			}

			$child_data[] = array(
				'element' => $child,
				'content' => $this->render_element( $child, false ),
			);
		}

		$child_count       = count( $children );
		$container_classes = Container_Classifier::get_element_classes( $element );

		$container_attr     = $this->apply_container_class_adjustments( $container_attr, $container_classes );
		$justify_content    = $this->detect_container_justify_content( $container_settings );
		$vertical_alignment = $this->detect_container_vertical_alignment( $container_settings );
		if ( null !== $vertical_alignment ) {
			$container_attr['verticalAlignment'] = $vertical_alignment;

			$class = 'are-vertically-aligned-' . sanitize_html_class( $vertical_alignment );
			if ( empty( $container_attr['className'] ) ) {
				$container_attr['className'] = $class;
			} else {
				$container_attr['className'] .= ' ' . $class;
			}
		}

		$child_blocks = ! empty( $child_data )
			? array_map(
				static function ( array $data ): string {
					return $data['content'] ?? '';
				},
				$child_data
			)
			: array();

		$wraps_columns_style = Container_Classifier::is_grid( $element )
			|| Container_Classifier::should_use_columns( $element )
			|| Container_Classifier::is_row( $element, $child_count )
			|| Container_Classifier::is_vertical_stack( $element );

		if ( $is_top_level && $wraps_columns_style ) {
			$split          = $this->split_section_attrs_for_wrap( $container_attr );
			$outer_attr     = $split['outer'];
			$inner_attr     = $this->propagate_flex_gap_to_inner( $split['inner'], $container_settings );

			if ( Container_Classifier::is_grid( $element ) ) {
				$columns     = Container_Classifier::get_grid_column_count( $element, $child_count );
				$inner_block = $this->render_grid_group( $inner_attr, $child_data, $columns );
			} elseif ( Container_Classifier::should_use_columns( $element ) ) {
				$inner_block = $this->render_columns_group( $inner_attr, $child_data, $justify_content );
			} elseif ( Container_Classifier::is_row( $element, $child_count ) ) {
				$inner_block = $this->render_row_group( $inner_attr, $child_blocks, $justify_content );
			} else {
				$inner_block = $this->render_vertical_stack_group( $inner_attr, $child_blocks, $justify_content );
			}

			if ( '' === trim( $inner_block ) ) {
				return '';
			}

			return $this->wrap_top_level_columns_in_group( $outer_attr, $container_settings, $inner_block );
		}

		if ( Container_Classifier::is_grid( $element ) ) {
			$columns = Container_Classifier::get_grid_column_count( $element, $child_count );

			return $this->render_grid_group( $container_attr, $child_data, $columns );
		}

		if ( Container_Classifier::should_use_columns( $element ) ) {
			return $this->render_columns_group( $container_attr, $child_data, $justify_content );
		}

		if ( Container_Classifier::is_row( $element, $child_count ) ) {
			return $this->render_row_group( $container_attr, $child_blocks, $justify_content );
		}

		if ( Container_Classifier::is_vertical_stack( $element ) ) {
			return $this->render_vertical_stack_group( $container_attr, $child_blocks, $justify_content );
		}

		$layout_type = in_array( 'e-con-full', $container_classes, true ) ? 'default' : 'constrained';

		if ( $is_top_level ) {
			$container_attr           = $this->apply_full_width_section_attributes( $container_attr, $container_settings );
			$container_attr['layout'] = $this->build_top_level_constrained_layout();

			// render_group will set layout from $attributes['layout'] when present.
			return $this->render_group( $container_attr, $child_blocks, 'constrained' );
		}

		return $this->render_group( $container_attr, $child_blocks, $layout_type );
	}

	/**
	 * Apply shared full-width behavior when Elementor section/container is intended to span viewport width.
	 *
	 * @param array $attributes Gutenberg block attributes.
	 * @param array $settings Elementor element settings.
	 */
	private function apply_full_width_section_attributes( array $attributes, array $settings ): array {
		if ( $this->is_explicitly_boxed_section( $settings ) ) {
			return $attributes;
		}

		$attributes['align'] = 'full';
		$attributes          = $this->add_class_to_attributes( $attributes, 'metg-full-width-section' );

		$this->register_full_width_section_css();

		return $attributes;
	}

	/**
	 * Register the shared CSS for .metg-full-width-section.
	 *
	 * Historically this registered `width:100vw; margin-inline:calc(50% - 50vw)` to
	 * force a full-bleed, but that combines poorly with WP's native `alignfull`:
	 * on pages with a vertical scrollbar 100vw includes the scrollbar gutter while
	 * the parent's 100% does not, producing a horizontal-scroll overflow bug.
	 *
	 * Gutenberg's constrained layout + `align:"full"` already stretches the section
	 * to the viewport edge correctly (and is scrollbar-safe), so we no longer need
	 * a custom rule. The class name is kept as a targetable hook for theme CSS.
	 */
	private function register_full_width_section_css(): void {
		// Intentional no-op; see docblock.
	}

	/**
	 * Return true when the Elementor section explicitly opts into a boxed layout.
	 *
	 * @param array $settings Elementor element settings.
	 */
	private function is_explicitly_boxed_section( array $settings ): bool {
		$content_width = isset( $settings['content_width'] ) ? strtolower( (string) $settings['content_width'] ) : '';
		if ( 'boxed' === $content_width ) {
			return true;
		}

		$layout = isset( $settings['layout'] ) ? strtolower( (string) $settings['layout'] ) : '';
		if ( 'boxed' === $layout ) {
			return true;
		}

		return false;
	}

	/**
	 * Wrap a rendered wp:columns block in a full-width constrained wp:group.
	 *
	 * This mirrors Elementor's default visual behavior for top-level sections:
	 * the outer wrapper stretches to the viewport (and carries background/padding),
	 * while the inner columns stay within the theme's content width via
	 * layout: constrained.
	 *
	 * @param array  $section_attributes Attributes that belong to the section as a whole
	 *                                   (background, padding, margin, border, className, etc.).
	 * @param array  $settings           Raw Elementor settings for the section.
	 * @param string $columns_inner_html Already-built wp:columns block markup.
	 */
	private function wrap_top_level_columns_in_group( array $section_attributes, array $settings, string $columns_inner_html ): string {
		$outer_attrs = $section_attributes;
		$outer_attrs = $this->apply_full_width_section_attributes( $outer_attrs, $settings );
		$outer_attrs = $this->maybe_add_group_has_background_class( $outer_attrs );

		// Per-section width mode:
		//   - Sections explicitly marked full_width / stretched / content_width:full
		//     in Elementor want their CONTENT to fill the viewport — no inner cap.
		//     We emit `layout:default` so the wp:group does not constrain children.
		//   - All other sections get the boxed treatment: alignfull background,
		//     constrained inner content at the kit container width.
		if ( $this->section_wants_full_width_content( $settings ) ) {
			$outer_attrs['layout'] = array( 'type' => 'default' );
		} else {
			$outer_attrs['layout'] = $this->build_top_level_constrained_layout();
		}

		return Block_Builder::build( 'group', $outer_attrs, $columns_inner_html );
	}

	/**
	 * Detect whether an Elementor section explicitly opts into full-viewport
	 * content (i.e. its inner content should fill the viewport, not the
	 * 1140-ish content size). True for sections that set any of:
	 *   - `layout: "full_width"`
	 *   - `stretch_section: "section-stretched"` / `"yes"`
	 *   - `content_width: "full_width"` / `"full"`
	 *
	 * Boxed sections (and unset/default) return false — they get the
	 * standard `layout:constrained` inner behavior.
	 *
	 * @param array $settings Elementor element settings.
	 */
	private function section_wants_full_width_content( array $settings ): bool {
		$layout = isset( $settings['layout'] ) ? strtolower( (string) $settings['layout'] ) : '';
		if ( 'full_width' === $layout ) {
			return true;
		}

		$stretch = isset( $settings['stretch_section'] ) ? strtolower( (string) $settings['stretch_section'] ) : '';
		if ( 'section-stretched' === $stretch || 'yes' === $stretch ) {
			return true;
		}

		$content_width = isset( $settings['content_width'] ) ? strtolower( (string) $settings['content_width'] ) : '';
		if ( 'full_width' === $content_width || 'full' === $content_width ) {
			return true;
		}

		return false;
	}

	/**
	 * Split a section's parsed attributes into "outer wrapper" and "inner layout
	 * container" buckets when wrapping a top-level multi-column section.
	 *
	 * The outer wp:group keeps everything that belongs to the section as a whole
	 * (background, padding, margin, border, className). The inner wp:columns picks
	 * up structural pieces that govern the column-row layout itself — chiefly
	 * `style.dimensions.minHeight`, so the column row stretches to the section's
	 * declared height (e.g. Elementor's `min_height: 88vh` hero) and bg-image
	 * spacer columns inside actually fill that height instead of collapsing.
	 *
	 * @param array $section_attributes Original section attributes.
	 * @return array{outer: array, inner: array}
	 */
	private function split_section_attrs_for_wrap( array $section_attributes ): array {
		$outer = $section_attributes;
		$inner = array();

		if ( isset( $outer['style']['dimensions']['minHeight'] ) ) {
			if ( ! isset( $inner['style'] ) || ! is_array( $inner['style'] ) ) {
				$inner['style'] = array();
			}
			if ( ! isset( $inner['style']['dimensions'] ) || ! is_array( $inner['style']['dimensions'] ) ) {
				$inner['style']['dimensions'] = array();
			}

			$inner['style']['dimensions']['minHeight'] = $outer['style']['dimensions']['minHeight'];

			unset( $outer['style']['dimensions']['minHeight'] );
			if ( empty( $outer['style']['dimensions'] ) ) {
				unset( $outer['style']['dimensions'] );
			}
			if ( isset( $outer['style'] ) && empty( $outer['style'] ) ) {
				unset( $outer['style'] );
			}
		}

		return array(
			'outer' => $outer,
			'inner' => $inner,
		);
	}

	/**
	 * Apply Elementor's `flex_gap` setting to inner column/grid attributes as
	 * `style.spacing.blockGap`. This makes converted sections honor the
	 * author's chosen column-to-column gap (Elementor v3 containers expose
	 * this via `flex_gap.column` or `flex_gap.size`).
	 *
	 * Without this, wp:columns falls back to its default ~32px gap, which
	 * does not match sections that authored a different gap (often `0`).
	 *
	 * @param array $inner_attrs Inner wp:columns attributes to mutate.
	 * @param array $settings    Source Elementor settings for the parent section/container.
	 *
	 * @return array Mutated inner attributes.
	 */
	private function propagate_flex_gap_to_inner( array $inner_attrs, array $settings ): array {
		if ( empty( $settings['flex_gap'] ) || ! is_array( $settings['flex_gap'] ) ) {
			return $inner_attrs;
		}

		$gap_data = $settings['flex_gap'];

		// Prefer the explicit column gap; fall back to the linked `size`.
		$value = null;
		foreach ( array( 'column', 'size' ) as $key ) {
			if ( ! isset( $gap_data[ $key ] ) ) {
				continue;
			}
			$candidate = $gap_data[ $key ];
			if ( '' === $candidate || null === $candidate ) {
				continue;
			}
			if ( is_numeric( $candidate ) ) {
				$value = (string) (int) $candidate;
				break;
			}
		}

		if ( null === $value ) {
			return $inner_attrs;
		}

		$unit = isset( $gap_data['unit'] ) && '' !== $gap_data['unit'] ? (string) $gap_data['unit'] : 'px';

		// Only known absolute/relative CSS units we trust here.
		if ( ! in_array( $unit, array( 'px', 'em', 'rem', '%' ), true ) ) {
			$unit = 'px';
		}

		$css = $value . $unit;

		if ( ! isset( $inner_attrs['style'] ) || ! is_array( $inner_attrs['style'] ) ) {
			$inner_attrs['style'] = array();
		}
		if ( ! isset( $inner_attrs['style']['spacing'] ) || ! is_array( $inner_attrs['style']['spacing'] ) ) {
			$inner_attrs['style']['spacing'] = array();
		}

		$inner_attrs['style']['spacing']['blockGap'] = $css;

		return $inner_attrs;
	}

	/**
	 * Build the layout attribute for a top-level constrained group.
	 *
	 * Declaring contentSize/wideSize explicitly frees the converted page from
	 * inheriting the theme's global content width — every converted section matches
	 * the Elementor kit width the user configured, without needing a theme.json edit.
	 *
	 * @return array
	 */
	private function build_top_level_constrained_layout(): array {
		$width = $this->get_section_content_width_css();

		return array(
			'type'        => 'constrained',
			'contentSize' => $width,
			'wideSize'    => $width,
		);
	}

	/**
	 * Get the configured section content width as a CSS length (e.g. "1140px").
	 */
	private function get_section_content_width_css(): string {
		return $this->get_section_content_width_px() . 'px';
	}

	/**
	 * Get the configured section content width in pixels. Clamped to [320, 2560] to
	 * avoid accidental zero/negative values breaking every converted page.
	 *
	 * Resolution order (first non-empty wins):
	 *   1. Plugin option `metg_section_content_width` (user override).
	 *   2. Elementor's active kit `container_width` setting (auto-detected).
	 *   3. The hard-coded plugin default (1140 — Hello / SaaSland baseline).
	 *
	 * Auto-detect lets users get pixel-correct conversion without ever opening
	 * the plugin Settings page if their Elementor kit already declares a
	 * container width.
	 */
	private function get_section_content_width_px(): int {
		$raw    = get_option( self::OPTION_SECTION_CONTENT_WIDTH, '' );
		$option = is_numeric( $raw ) ? (int) $raw : 0;

		if ( $option > 0 ) {
			return $this->clamp_content_width( $option );
		}

		$kit_width = $this->read_elementor_kit_container_width();
		if ( $kit_width > 0 ) {
			return $this->clamp_content_width( $kit_width );
		}

		return self::DEFAULT_SECTION_CONTENT_WIDTH;
	}

	/**
	 * Clamp a content-width value to a sane range so a corrupt setting
	 * cannot zero-out every converted page.
	 */
	private function clamp_content_width( int $value ): int {
		if ( $value < 320 ) {
			return 320;
		}
		if ( $value > 2560 ) {
			return 2560;
		}

		return $value;
	}

	/**
	 * Read the active Elementor kit's `container_width` setting (in px).
	 *
	 * Elementor stores per-site layout defaults on a "kit" post whose ID
	 * lives in the `elementor_active_kit` option. The kit's
	 * `_elementor_page_settings` meta carries `container_width` which is the
	 * value Elementor uses for boxed-layout sections. Reading it lets us
	 * default to whatever the user already set in Elementor → Site Settings.
	 *
	 * @return int Width in pixels, or 0 if unavailable.
	 */
	private function read_elementor_kit_container_width(): int {
		$kit_id = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id <= 0 ) {
			return 0;
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return 0;
		}

		$container = $settings['container_width'] ?? null;

		if ( is_array( $container ) ) {
			$size = $container['size'] ?? null;
			$unit = isset( $container['unit'] ) ? (string) $container['unit'] : 'px';

			if ( null === $size || '' === $size || 'px' !== $unit ) {
				return 0;
			}

			$numeric = is_numeric( $size ) ? (int) $size : 0;

			return max( 0, $numeric );
		}

		if ( is_numeric( $container ) ) {
			return max( 0, (int) $container );
		}

		return 0;
	}

	/**
	 * Render a Gutenberg group with constrained layout.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_group( array $attributes, array $child_blocks, string $layout_type = 'constrained' ): string {
		if ( ! isset( $attributes['layout'] ) || ! is_array( $attributes['layout'] ) || empty( $attributes['layout'] ) ) {
			$attributes['layout'] = array( 'type' => $layout_type );
		} elseif ( ! isset( $attributes['layout']['type'] ) ) {
			$attributes['layout']['type'] = $layout_type;
		}

		$attributes = $this->maybe_add_group_has_background_class( $attributes );

		if ( null !== $this->external_css_collector ) {
			$attributes = $this->external_css_collector->externalize_attrs( 'group', $attributes );
		}

		$inner_html = implode( '', $child_blocks );

		$inner_html = trim( (string) $inner_html );
		if ( '' === $inner_html ) {
			return '';
		}

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a Gutenberg group with flex layout for row containers.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_row_group( array $attributes, array $child_blocks, ?string $justify_content = null ): string {
		if ( null === $justify_content || '' === $justify_content ) {
			$justify_content = 'space-between';
		}

		$attributes['layout'] = array(
			'type'           => 'flex',
			'justifyContent' => $justify_content,
			'flexWrap'       => 'wrap',
		);
		$attributes           = $this->maybe_add_group_has_background_class( $attributes );

		$inner_html = implode( '', $child_blocks );

		$inner_html = trim( (string) $inner_html );
		if ( '' === $inner_html ) {
			return '';
		}

		return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
	}

	/**
	 * Render a Gutenberg group for vertical flex stacks (Elementor column-direction containers).
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 * @param string|null $justify_content Optional content justification (left/center/right/space-between).
	 */
	private function render_vertical_stack_group( array $attributes, array $child_blocks, ?string $justify_content = null ): string {
		if ( null === $justify_content || '' === $justify_content ) {
			$justify_content = 'left';
		}

		$attributes['layout'] = array(
			'type'           => 'flex',
			'orientation'    => 'vertical',
			'justifyContent' => $justify_content,
		);
		$attributes           = $this->maybe_add_group_has_background_class( $attributes );

		$inner_html = implode( '', $child_blocks );

		$inner_html = trim( (string) $inner_html );
		if ( '' === $inner_html ) {
			return '';
		}

		return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
	}

	/**
	 * Render a Gutenberg grid layout group.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 * @param int $columns Number of columns.
	 */
	private function render_grid_group( array $attributes, array $child_data, int $columns ): string {
		$attributes['layout'] = array(
			'type'        => 'grid',
			'columnCount' => max( 1, $columns ),
		);
		$attributes           = $this->maybe_add_group_has_background_class( $attributes );

		$inner_html = '';
		foreach ( $child_data as $child ) {
			$content = $child['content'] ?? '';
			$content = trim( (string) $content );
			if ( '' === $content ) {
				continue;
			}

			$inner_html .= Block_Builder::build(
				'group',
				array( 'layout' => array( 'type' => 'constrained' ) ),
				$content
			);
		}

		$inner_html = trim( (string) $inner_html );
		if ( '' === $inner_html ) {
			return '';
		}

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a Gutenberg columns block for typical three/four card rows.
	 *
	 * @param array $attributes Block attributes for core/columns.
	 * @param array $child_data Child element data with rendered content.
	 * @param string|null $justify_content Optional content justification (left/center/right/space-between).
	 */
	private function render_columns_group( array $attributes, array $child_data, ?string $justify_content = null ): string {
		$inner_html         = '';
		$columns_alignments = array();

		foreach ( $child_data as $child ) {
			$element = isset( $child['element'] ) && is_array( $child['element'] ) ? $child['element'] : array();
			$content = isset( $child['content'] ) ? $child['content'] : '';

			if (
				isset( $element['elType'] ) && 'container' === $element['elType']
				&& empty( $element['elements'] )
			) {
				$settings       = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
				$container_attr = Style_Parser::parse_container_styles( $settings );

				if ( ! empty( $container_attr['style']['background']['image'] ) ) {
					if ( ! isset( $container_attr['style']['dimensions'] ) ) {
						$container_attr['style']['dimensions'] = array();
					}
					$container_attr['style']['dimensions']['minHeight'] = '100%';

					$content = Block_Builder::build( 'group', $container_attr, '' );
				}
			}

			if ( '' === $content ) {
				$is_spacer_container = isset( $element['elType'] )
					&& 'container' === $element['elType']
					&& null !== $this->get_column_width( $element );

				if ( ! $is_spacer_container ) {
					continue;
				}
			}

			$width    = $this->get_column_width( $element );
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();

			// Compute vertical alignment for this column.
			$computed_styles = Style_Parser::get_computed_styles( $element );
			$vertical_align  = null;

			// 1. First, respect explicit align-self if present.
			if ( isset( $computed_styles['align-self'] ) ) {
				$vertical_align = $this->map_align_self_to_vertical_alignment( $computed_styles['align-self'] );
			}

			// 2. Fallback: infer from container direction + justify/align options.
			if ( null === $vertical_align && ! empty( $settings ) ) {
				$direction = isset( $settings['flex_direction'] ) ? (string) $settings['flex_direction'] : '';

				// For flex-direction: column, Elementor uses flex_justify_content for vertical alignment.
				if ( 'column' === $direction || '' === $direction ) {
					$keys = array(
						'flex_justify_content',
						'content_position',
						'vertical_align',
						'v_align',
					);
				} else {
					// For rows, vertical axis is align-items / vertical_align.
					$keys = array(
						'flex_align_items',
						'content_position',
						'vertical_align',
						'v_align',
					);
				}

				$alignment = Alignment_Helper::detect_alignment( $settings, $keys );

				if ( '' !== $alignment ) {
					$alignment = strtolower( trim( (string) $alignment ) );

					switch ( $alignment ) {
						case 'center':
						case 'middle':
							$vertical_align = 'center';
							break;
						case 'bottom':
						case 'end':
							$vertical_align = 'bottom';
							break;
						case 'top':
						case 'start':
							$vertical_align = 'top';
							break;
						default:
							$vertical_align = null;
							break;
					}
				}
			}

			$column_attrs = array();

			if ( null !== $width ) {
				$column_attrs['width'] = $width;
			}

			if ( null !== $vertical_align ) {
				$column_attrs['verticalAlignment'] = $vertical_align;
				$columns_alignments[]              = $vertical_align;
			}

			$attrs_json = '';
			if ( ! empty( $column_attrs ) ) {
				$attrs_json = ' ' . wp_json_encode( $column_attrs );
			}

			$style_attr  = '';
			$class_names = array( 'wp-block-column' );

			if ( null !== $width ) {
				$style_attr = ' style="flex-basis:' . esc_attr( $width ) . '"';
			}

			if ( null !== $vertical_align ) {
				$class_names[] = 'is-vertically-aligned-' . sanitize_html_class( $vertical_align );
			}

			$inner_html .= sprintf(
				'<!-- wp:column%s --><div class="%s"%s>%s</div><!-- /wp:column -->',
				$attrs_json,
				esc_attr( implode( ' ', $class_names ) ),
				$style_attr,
				$content
			);
		}

		if ( '' === $inner_html ) {
			return '';
		}

		// If all columns share the same vertical alignment, mirror it on the parent columns block.
		if ( ! empty( $columns_alignments ) ) {
			$unique = array_unique( $columns_alignments );
			if ( 1 === count( $unique ) ) {
				$alignment                       = reset( $unique ); // top/center/bottom.
				$attributes['verticalAlignment'] = $alignment;

				$class = 'are-vertically-aligned-' . sanitize_html_class( $alignment );
				if ( empty( $attributes['className'] ) ) {
					$attributes['className'] = $class;
				} else {
					$attributes['className'] .= ' ' . $class;
				}
			}
		}

		// Use layout support for horizontal justification instead of hard-coding the class.
		if ( null !== $justify_content && '' !== $justify_content ) {
			if ( ! isset( $attributes['layout'] ) || ! is_array( $attributes['layout'] ) ) {
				$attributes['layout'] = array();
			}

			if ( empty( $attributes['layout']['type'] ) ) {
				$attributes['layout']['type'] = 'flex';
			}

			$attributes['layout']['justifyContent'] = $justify_content;
		}

		return Block_Builder::build( 'columns', $attributes, $inner_html );
	}


	/**
	 * Infer a core/column width attribute from an Elementor container element.
	 *
	 * Returns values like "33.33%" when possible.
	 *
	 * @param array $element Elementor container element.
	 *
	 * @return string|null
	 */
	private function get_column_width( array $element ): ?string {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		if ( empty( $settings ) ) {
			return null;
		}


		if ( isset( $settings['_inline_size'] ) && is_numeric( $settings['_inline_size'] ) ) {
			$inline = (float) $settings['_inline_size'];
			if ( $inline > 0 ) {
				$rounded = round( $inline, 2 );

				return rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' ) . '%';
			}
		}

		// `width` next — handle below in the structured-candidate loop.
		$candidates = array( 'width', 'column_width', 'container_width' );

		foreach ( $candidates as $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			$value = $settings[ $key ];

			if ( is_array( $value ) ) {
				$size = isset( $value['size'] ) ? $value['size'] : ( isset( $value['value'] ) ? $value['value'] : null );
				$unit = isset( $value['unit'] ) ? (string) $value['unit'] : '%';

				if ( null === $size || '' === $size ) {
					continue;
				}

				$size = trim( (string) $size );
				if ( '' === $size || ! is_numeric( $size ) ) {
					continue;
				}

				if ( '' === $unit ) {
					$unit = '%';
				}

				// For now we only trust percentage widths. Other units are ignored.
				if ( '%' !== $unit ) {
					continue;
				}

				return $size . $unit;
			}

			$string_value = trim( (string) $value );
			if ( '' === $string_value ) {
				continue;
			}

			if ( false !== strpos( $string_value, '%' ) ) {
				return $string_value;
			}

			if ( is_numeric( $string_value ) ) {
				return $string_value . '%';
			}
		}

		if ( isset( $settings['_column_size'] ) && is_numeric( $settings['_column_size'] ) ) {
			$column_size = (float) $settings['_column_size'];
			if ( $column_size > 0 ) {
				$rounded = round( $column_size, 2 );

				return rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' ) . '%';
			}
		}

		return null;
	}

	/**
	 * Apply Elementor container class adjustments (full/boxed) to block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $classes Elementor class list.
	 */
	private function apply_container_class_adjustments( array $attributes, array $classes ): array {
		if ( in_array( 'e-con-boxed', $classes, true ) ) {
			$attributes = $this->add_class_to_attributes( $attributes, 'has-global-padding' );
		}

		if ( in_array( 'e-con-full', $classes, true ) ) {
			$attributes = $this->remove_class_from_attributes( $attributes, 'has-global-padding' );
		}

		return $attributes;
	}

	/**
	 * Add a className entry to block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @param string $class Class to add.
	 */
	private function add_class_to_attributes( array $attributes, string $class ): array {
		$sanitized = Style_Parser::clean_class( $class );
		if ( '' === $sanitized ) {
			return $attributes;
		}

		$existing   = isset( $attributes['className'] ) ? preg_split( '/\s+/', $attributes['className'] ) : array();
		$existing   = is_array( $existing ) ? array_filter( $existing ) : array();
		$existing[] = $sanitized;

		$unique = array();
		foreach ( $existing as $item ) {
			$item = Style_Parser::clean_class( $item );
			if ( '' === $item ) {
				continue;
			}
			$unique[ $item ] = true;
		}

		if ( empty( $unique ) ) {
			unset( $attributes['className'] );

			return $attributes;
		}

		$attributes['className'] = implode( ' ', array_keys( $unique ) );

		return $attributes;
	}

	/**
	 * Map CSS align-self to Gutenberg verticalAlignment value.
	 *
	 * @param string $align_self
	 *
	 * @return string|null
	 */
	private function map_align_self_to_vertical_alignment( string $align_self ): ?string {
		$align_self = strtolower( trim( $align_self ) );

		switch ( $align_self ) {
			case 'flex-start':
			case 'start':
				return 'top';
			case 'center':
				return 'center';
			case 'flex-end':
			case 'end':
				return 'bottom';
			default:
				return null;
		}
	}

	/**
	 * Remove a class from block attributes if present.
	 *
	 * @param array $attributes Block attributes.
	 * @param string $class Class to remove.
	 */
	private function remove_class_from_attributes( array $attributes, string $class ): array {
		if ( empty( $attributes['className'] ) ) {
			return $attributes;
		}

		$target    = Style_Parser::clean_class( $class );
		$classlist = preg_split( '/\s+/', (string) $attributes['className'] );
		$classlist = is_array( $classlist ) ? array_filter( $classlist ) : array();

		$filtered = array();
		foreach ( $classlist as $item ) {
			$item = Style_Parser::clean_class( $item );
			if ( '' === $item || $item === $target ) {
				continue;
			}
			$filtered[ $item ] = true;
		}

		if ( empty( $filtered ) ) {
			unset( $attributes['className'] );
		} else {
			$attributes['className'] = implode( ' ', array_keys( $filtered ) );
		}

		return $attributes;
	}

	/**
	 * Render a placeholder for unknown widgets.
	 *
	 * @param string $type Widget type.
	 */
	private function render_unknown_widget( string $type ): string {
		$element = array(
			'widgetType' => $type,
			'elType'     => 'widget',
		);

		return $this->render_placeholder_block( $element );
	}

	/**
	 * Render a placeholder block for unsupported widgets.
	 *
	 * @param array<string, mixed> $element Elementor element data.
	 *
	 * @return string
	 */
	private function render_placeholder_block( array $element ): string {
		// Detect widget name (best-effort).
		$widget_name = '';
		if ( isset( $element['widgetType'] ) ) {
			$widget_name = (string) $element['widgetType'];
		} elseif ( isset( $element['elType'] ) ) {
			$widget_name = (string) $element['elType'];
		}

		$widget_name = trim( $widget_name );
		if ( '' === $widget_name ) {
			$widget_name = 'unknown';
		}
		// Visible notice text (plain text, no HTML).
		$notice_text = sprintf( 'Unsupported widget: %s', $widget_name );
		$notice_text = esc_html( $notice_text );

		// core/paragraph canonical markup.
		$inner_html = '<p class="metg-unsupported-widget">' . $notice_text . '</p>';

		$block = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(
				'className' => 'metg-unsupported-widget',
			),
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);

		return serialize_block( $block ) . "\n";
	}

	/**
	 * Detect a flex-like justify-content value for a container and map it
	 * to a Gutenberg-friendly value (left/center/right/space-between).
	 *
	 * @param array $settings Elementor settings.
	 *
	 * @return string|null
	 */
	private function detect_container_justify_content( array $settings ): ?string {
		if ( empty( $settings ) ) {
			return null;
		}

		// Priority keys for flex justify on containers.
		$alignment = Alignment_Helper::detect_alignment(
			$settings,
			array(
				'flex_justify_content',
				'justify_content',
				'horizontal_align',
				'content_position',
			)
		);

		if ( '' === $alignment ) {
			return null;
		}

		switch ( $alignment ) {
			case 'center':
				return 'center';
			case 'right':
			case 'end':
				return 'right';
			case 'justify':
				return 'space-between';
			case 'left':
			case 'start':
			default:
				return 'left';
		}
	}

	/**
	 * Detect a verticalAlignment value (top/center/bottom) for a container.
	 *
	 * @param array $settings Elementor settings.
	 *
	 * @return string|null
	 */
	private function detect_container_vertical_alignment( array $settings ): ?string {
		if ( empty( $settings ) ) {
			return null;
		}

		$direction = isset( $settings['flex_direction'] ) ? (string) $settings['flex_direction'] : '';

		// For column direction, vertical axis is justify-content.
		if ( 'column' === $direction || '' === $direction ) {
			$keys = array(
				'flex_justify_content',
				'content_position',
				'flex_align_items',
				'vertical_align',
				'v_align',
			);
		} else {
			// For row direction, vertical axis is align-items.
			$keys = array(
				'content_position',
				'flex_align_items',
				'vertical_align',
				'v_align',
			);
		}

		$alignment = Alignment_Helper::detect_alignment( $settings, $keys );

		if ( '' === $alignment ) {
			return null;
		}

		$alignment = strtolower( trim( (string) $alignment ) );

		switch ( $alignment ) {
			case 'center':
			case 'middle':
				return 'center';
			case 'bottom':
			case 'end':
				return 'bottom';
			case 'top':
			case 'start':
			default:
				return 'top';
		}
	}


}
