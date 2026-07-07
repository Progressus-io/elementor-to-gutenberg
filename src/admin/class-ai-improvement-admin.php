<?php
/**
 * Automated AI improvement admin workflow.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

defined( 'ABSPATH' ) || exit;

use Progressus\BlockShift\Admin\Helper\AI_Prompt_Builder;
use Progressus\BlockShift\Admin\Helper\AI_Workspace_Repository;
use Progressus\BlockShift\Admin\Helper\External_CSS_Service;
use Progressus\BlockShift\Admin\Helper\AI_Remediation_Screenshot_Api_Service;
use Progressus\BlockShift\Admin\Helper\AI_Remediation_Screenshot_Meta_Service;
use Progressus\BlockShift\Admin\Helper\Claude_Api_Service;
use WP_Error;
use WP_Post;

use function absint;
use function add_query_arg;
use function add_submenu_page;
use function admin_url;
use function current_time;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_permalink;
use function get_post;
use function get_post_field;
use function get_post_meta;
use function get_post_status;
use function get_post_type;
use function get_the_title;
use function get_transient;
use function home_url;
use function is_array;
use function is_wp_error;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function trim;
use function set_transient;
use function sprintf;
use function update_post_meta;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_parse_url;
use function wp_remote_get;
use function wp_remote_retrieve_response_code;
use function wp_kses_post;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_update_post;

class AI_Improvement_Admin {

	public const MENU_SLUG = 'metg-ai-improvement';

	private const NONCE_AUTO_IMPROVE   = 'metg_ai_auto_improve';
	private const NONCE_REFINE         = 'metg_ai_refine';
	private const NONCE_MOBILE_IMPROVE = 'metg_ai_mobile_improve';

	/**
	 * Markers that wrap the AI mobile CSS block inside the external CSS file.
	 *
	 * Re-running mobile improvement replaces only the content between these
	 * markers; everything else (desktop CSS) is preserved untouched.
	 */
	private const MOBILE_CSS_START_MARKER = '/* === AI MOBILE START === */';
	private const MOBILE_CSS_END_MARKER   = '/* === AI MOBILE END === */';

	/**
	 * Singleton instance.
	 *
	 * @var AI_Improvement_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
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
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_metg_ai_auto_improve', array( $this, 'handle_auto_improve' ) );
		add_action( 'admin_post_metg_ai_refine', array( $this, 'handle_refine' ) );
		add_action( 'admin_post_metg_ai_mobile_improve', array( $this, 'handle_mobile_improve' ) );
		add_action( 'admin_post_metg_ai_regenerate_screenshots', array( $this, 'handle_regenerate_screenshots' ) );
	}

	/**
	 * Enqueue page-specific assets.
	 *
	 * Only loads on this plugin's AI improvement page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $_hook ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$css_path = BLOCKSHIFT_DIR_PATH . '/assets/css/ai-improve.css';
		$js_path  = BLOCKSHIFT_DIR_PATH . '/assets/js/ai-improve.js';

		wp_enqueue_style(
			'metg-ai-improve',
			plugins_url( 'assets/css/ai-improve.css', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $css_path ) ? (string) filemtime( $css_path ) : BLOCKSHIFT_VERSION
		);

		wp_enqueue_script(
			'metg-ai-improve',
			plugins_url( 'assets/js/ai-improve.js', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $js_path ) ? (string) filemtime( $js_path ) : BLOCKSHIFT_VERSION,
			true
		);

		$target_id_asset = isset( $_GET['target_id'] ) ? absint( wp_unslash( $_GET['target_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source_id_asset = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'metg-ai-improve',
			'metgAiImprove',
			array(
				'processingLabel'     => __( 'Processing…', 'blockshift-migrate-from-elementor' ),
				'improvingLabel'      => __( 'Improving with AI…', 'blockshift-migrate-from-elementor' ),
				'refiningLabel'       => __( 'Refining with AI…', 'blockshift-migrate-from-elementor' ),
				'mobileLabel'         => __( 'Improving mobile with AI…', 'blockshift-migrate-from-elementor' ),
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'feedbackNonce'       => wp_create_nonce( AI_Enhancement_Admin::FEEDBACK_NONCE ),
				'targetId'            => $target_id_asset,
				'sourceId'            => $source_id_asset,
				'feedbackTitle'       => __( 'How did AI Enhancement go?', 'blockshift-migrate-from-elementor' ),
				'feedbackIssue'       => __( 'Issue type', 'blockshift-migrate-from-elementor' ),
				'feedbackIssueDetail' => __( 'Describe the issue', 'blockshift-migrate-from-elementor' ),
				'feedbackNote'        => __( 'Additional notes', 'blockshift-migrate-from-elementor' ),
				'feedbackConsent'     => __( 'I consent to sending this anonymised AI enhancement report to the plugin developer for quality improvement. No passwords, API keys, or user data are included.', 'blockshift-migrate-from-elementor' ),
				'feedbackSubmit'      => __( 'Send Feedback', 'blockshift-migrate-from-elementor' ),
				'feedbackCancel'      => __( 'Cancel', 'blockshift-migrate-from-elementor' ),
				'feedbackSending'     => __( 'Sending…', 'blockshift-migrate-from-elementor' ),
				'feedbackSuccess'     => __( 'Thank you! Feedback submitted.', 'blockshift-migrate-from-elementor' ),
				'feedbackNoIssue'     => __( 'No issue', 'blockshift-migrate-from-elementor' ),
				'feedbackLayout'      => __( 'Layout issues after AI', 'blockshift-migrate-from-elementor' ),
				'feedbackMissing'     => __( 'Wrong or missing content', 'blockshift-migrate-from-elementor' ),
				'feedbackCss'         => __( 'CSS / styling problems', 'blockshift-migrate-from-elementor' ),
				'feedbackQuality'     => __( 'AI output quality', 'blockshift-migrate-from-elementor' ),
				'feedbackOther'       => __( 'Other', 'blockshift-migrate-from-elementor' ),
			)
		);
	}

	/**
	 * Register hidden submenu page.
	 */
	public function register_menu(): void {
		// Parent = null registers the page so it loads via ?page=... but never
		// appears in any submenu. The wizard deep-links into it directly, so
		// listing it in the sidebar would just be noise.
		add_submenu_page(
			null,
			esc_html__( 'Improve Converted Page with AI', 'blockshift-migrate-from-elementor' ),
			esc_html__( 'Improve Converted Page with AI', 'blockshift-migrate-from-elementor' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Build admin URL for workflow page.
	 */
	public static function get_page_url( int $source_id, int $target_id ): string {
		return add_query_arg(
			array(
				'page'      => self::MENU_SLUG,
				'source_id' => $source_id,
				'target_id' => $target_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render review page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'blockshift-migrate-from-elementor' ) );
		}

		$target_id = isset( $_GET['target_id'] ) ? absint( wp_unslash( $_GET['target_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source_id = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $target_id <= 0 ) {
			wp_die( esc_html__( 'Missing converted Gutenberg page ID.', 'blockshift-migrate-from-elementor' ) );
		}

		$target_post = get_post( $target_id );
		if ( ! $target_post instanceof WP_Post ) {
			wp_die( esc_html__( 'Converted Gutenberg page not found.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( $source_id <= 0 ) {
			$source_id = (int) get_post_meta( $target_id, '_metg_source_id', true );
		}

		if ( $source_id <= 0 ) {
			wp_die( esc_html__( 'Source Elementor page ID could not be resolved.', 'blockshift-migrate-from-elementor' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_metg_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			wp_die( esc_html__( 'The selected source and target page mapping is invalid.', 'blockshift-migrate-from-elementor' ) );
		}

		$source_post = get_post( $source_id );
		if ( ! $source_post instanceof WP_Post ) {
			wp_die( esc_html__( 'Source Elementor page not found.', 'blockshift-migrate-from-elementor' ) );
		}

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

		$existing_workspace = AI_Workspace_Repository::get( $target_id );

		// Load screenshot URL arrays from dedicated meta; fall back to workspace (backward compat).
		$elementor_shots        = AI_Remediation_Screenshot_Meta_Service::get_elementor_urls( $target_id );
		$gutenberg_shots        = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_urls( $target_id );
		$elementor_mobile_shots = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_urls( $target_id );
		$gutenberg_mobile_shots = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_urls( $target_id );
		if ( empty( $elementor_shots ) && ! empty( $existing_workspace['elementor_screenshot'] ) ) {
			$elementor_shots = (array) $existing_workspace['elementor_screenshot'];
		}
		if ( empty( $gutenberg_shots ) && ! empty( $existing_workspace['gutenberg_screenshot'] ) ) {
			$gutenberg_shots = (array) $existing_workspace['gutenberg_screenshot'];
		}

		$prompt = AI_Prompt_Builder::build(
			array(
				'source_id'         => $source_id,
				'target_id'         => $target_id,
				'source_title'      => get_the_title( $source_id ),
				'target_title'      => get_the_title( $target_id ),
				'elementor_json'    => $elementor_json,
				'gutenberg_content' => $gutenberg_content,
			)
		);

		$workspace_to_save = array(
			'target_post_id'              => $target_id,
			'source_post_id'              => $source_id,
			'prepared_prompt'             => $prompt,
			'elementor_json_snapshot'     => $elementor_json,
			'gutenberg_snapshot'          => $gutenberg_content,
			'elementor_screenshot'        => $elementor_shots,
			'gutenberg_screenshot'        => $gutenberg_shots,
			'elementor_mobile_screenshot' => $elementor_mobile_shots,
			'gutenberg_mobile_screenshot' => $gutenberg_mobile_shots,
			'css_result_draft'            => isset( $existing_workspace['css_result_draft'] ) ? (string) $existing_workspace['css_result_draft'] : '',
			'gutenberg_result_draft'      => isset( $existing_workspace['gutenberg_result_draft'] ) ? (string) $existing_workspace['gutenberg_result_draft'] : '',
		);
		AI_Workspace_Repository::save( $target_id, $workspace_to_save );

		$notice_code = isset( $_GET['metg_ai_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['metg_ai_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->render_notice( $notice_code, $target_id );
		$this->render_form( $target_post, $source_post, AI_Workspace_Repository::get( $target_id ) );
	}

	/**
	 * Handle automated AI improvement via Claude API.
	 */
	public function handle_auto_improve(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'blockshift-migrate-from-elementor' ) );
		}

		check_admin_referer( self::NONCE_AUTO_IMPROVE );

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'blockshift-migrate-from-elementor' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_metg_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		$result = self::run_improvement( $source_id, $target_id );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'ai_failed';
			// Store the concrete reason so the redirect target can show "why".
			set_transient( 'metg_ai_error_' . $target_id, $result['error'], 60 );
			$this->redirect_with_notice( $source_id, $target_id, $notice );
		}

		$this->redirect_with_notice( $source_id, $target_id, 'updated' );
	}

	/**
	 * Core AI improvement logic — shared by the single-page admin handler and
	 * the bulk AJAX handler in the conversion wizard.
	 *
	 * @param int $source_id Elementor source post ID.
	 * @param int $target_id Converted Gutenberg post ID.
	 * @return array{success: bool, error: string, notice: string}
	 */
	public static function run_improvement( int $source_id, int $target_id ): array {
		$failure = static function ( string $error, string $notice = 'ai_failed' ): array {
			return array(
				'success' => false,
				'error'   => $error,
				'notice'  => $notice,
			);
		};

		// Pre-flight: make sure the page is publicly reachable before spending an
		// AI call. The remote screenshot service runs off-site, so any URL it
		// cannot open (maintenance mode, unpublished, password-protected,
		// localhost / private IP, HTTP error) must be caught here with a concrete
		// reason rather than producing a cryptic screenshot failure later.
		$access = self::check_page_accessibility( $source_id, $target_id );
		if ( ! $access['accessible'] ) {
			return $failure( $access['reason'], 'page_inaccessible' );
		}

		// Generate fresh screenshots. Do NOT send the AI request if they could not
		// be captured — enhancing without screenshots produces poor results.
		$screenshot_result = AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );
		if ( ! $screenshot_result['success'] ) {
			return $failure(
				sprintf(
					/* translators: %s: screenshot error details */
					__( 'Screenshots could not be generated, so AI enhancement was not run: %s', 'blockshift-migrate-from-elementor' ),
					$screenshot_result['error']
				),
				'screenshot_failed'
			);
		}

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

		$current_css = self::fix_css_namespace( self::read_post_css( $target_id ), $source_id, $target_id );

		$template_type = '';
		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			$template_type = (string) get_post_meta( $source_id, '_elementor_template_type', true );
		}

		$prompt = AI_Prompt_Builder::build(
			array(
				'source_id'         => $source_id,
				'target_id'         => $target_id,
				'source_title'      => get_the_title( $source_id ),
				'target_title'      => get_the_title( $target_id ),
				'elementor_json'    => $elementor_json,
				'gutenberg_content' => $gutenberg_content,
				'current_css'       => $current_css,
				'template_type'     => $template_type,
			)
		);

		$elementor_shots = AI_Remediation_Screenshot_Meta_Service::get_elementor_urls( $target_id );
		$gutenberg_shots = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_urls( $target_id );

		$api_result = Claude_Api_Service::send( $prompt, $elementor_shots, $gutenberg_shots );

		if ( ! $api_result['success'] ) {
			self::log_improvement(
				array(
					'step'      => 'api_failed',
					'target_id' => $target_id,
					'error'     => $api_result['error'],
				)
			);
			return $failure( $api_result['error'], 'ai_failed' );
		}

		$parsed           = Claude_Api_Service::parse_response( $api_result['content'] );
		$css_result       = self::fix_css_namespace( $parsed['css'], $source_id, $target_id );
		$gutenberg_result = self::sanitize_block_content( $parsed['gutenberg'] );

		self::log_improvement(
			array(
				'step'              => 'parse_complete',
				'target_id'         => $target_id,
				'css_length'        => strlen( $css_result ),
				'gutenberg_length'  => strlen( $gutenberg_result ),
				'gutenberg_preview' => substr( $gutenberg_result, 0, 120 ),
			)
		);

		if ( '' === trim( $gutenberg_result ) ) {
			self::log_improvement(
				array(
					'step'      => 'parse_failed_empty_gutenberg',
					'target_id' => $target_id,
				)
			);
			return $failure(
				__( 'No valid Gutenberg content could be parsed from the AI response.', 'blockshift-migrate-from-elementor' ),
				'ai_parse_failed'
			);
		}

		$update_result = wp_update_post(
			array(
				'ID'           => $target_id,
				'post_content' => $gutenberg_result,
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			self::log_improvement(
				array(
					'step'      => 'wp_update_post_failed',
					'target_id' => $target_id,
					'error'     => $update_result->get_error_message(),
				)
			);
			return $failure( $update_result->get_error_message(), 'update_failed' );
		}

		self::log_improvement(
			array(
				'step'        => 'wp_update_post_success',
				'target_id'   => $target_id,
				'returned_id' => $update_result,
			)
		);

		if ( '' !== trim( $css_result ) ) {
			External_CSS_Service::save_post_css( $target_id, $css_result );
		}

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			External_CSS_Service::register_global_css_post( $target_id );
		}

		$workspace                           = AI_Workspace_Repository::get( $target_id );
		$workspace['target_post_id']         = $target_id;
		$workspace['source_post_id']         = $source_id;
		$workspace['css_result_draft']       = $css_result;
		$workspace['gutenberg_result_draft'] = $gutenberg_result;
		$workspace['updated_at']             = current_time( 'mysql' );
		AI_Workspace_Repository::save( $target_id, $workspace );

		update_post_meta( $target_id, '_metg_last_ai_improved', current_time( 'mysql' ) );

		return array(
			'success' => true,
			'error'   => '',
			'notice'  => 'updated',
		);
	}

	/**
	 * Sanitize AI-generated block content before it is persisted as post_content.
	 *
	 * Mirrors WordPress core: users who cannot post unfiltered HTML get the content
	 * run through wp_kses_post(), exactly as wp_update_post() would apply on save.
	 * Users with the unfiltered_html capability keep the content verbatim, matching
	 * how the normal block editor behaves, so valid block markup is never corrupted.
	 *
	 * @param string $content Raw Gutenberg content returned by the AI.
	 *
	 * @return string Sanitized block content.
	 */
	private static function sanitize_block_content( string $content ): string {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $content;
		}

		return wp_kses_post( $content );
	}

	/**
	 * Append a diagnostic log entry to the same log file used by Claude_Api_Service.
	 *
	 * @param array $data Associative array of fields to log.
	 */
	private static function log_improvement( array $data ): void {
		$upload_dir = wp_upload_dir();
		$log_file   = trailingslashit( $upload_dir['basedir'] ) . 'metg-claude-api.log';

		$entry = array_merge(
			array(
				'timestamp' => gmdate( 'Y-m-d H:i:s' ),
				'source'    => 'run_improvement',
			),
			$data
		);

		$line = wp_json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false !== $line ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, $line . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Verify the converted page is publicly reachable before any AI call.
	 *
	 * The screenshot service runs on a remote host, so it can only load a page
	 * that is reachable from the public internet. This runs a layered set of
	 * checks and returns the FIRST concrete reason the page cannot be enhanced,
	 * so the user sees exactly why instead of a generic screenshot error.
	 *
	 * @param int $source_id Elementor source post ID.
	 * @param int $target_id Converted Gutenberg post ID.
	 * @return array{accessible: bool, reason: string}
	 */
	private static function check_page_accessibility( int $source_id, int $target_id ): array {
		$deny = static function ( string $reason ): array {
			return array(
				'accessible' => false,
				'reason'     => $reason,
			);
		};

		// Maintenance mode: the whole site returns the maintenance screen.
		if ( file_exists( (string) ABSPATH . '.maintenance' ) || ( defined( 'WP_MAINTENANCE_MODE' ) && WP_MAINTENANCE_MODE ) ) {
			return $deny( __( 'The website is in maintenance mode, so the screenshot service cannot load it. Disable maintenance mode and try again.', 'blockshift-migrate-from-elementor' ) );
		}

		$is_library = 'elementor_library' === get_post_type( $source_id );

		// Resolve the public URL the screenshot service will actually open.
		if ( $is_library ) {
			$page_url = home_url( '/' );
		} else {
			// Publish / password checks only apply to standalone converted pages.
			if ( 'publish' !== (string) get_post_status( $target_id ) ) {
				return $deny( __( 'The converted page is not published yet. Publish it so the screenshot service can load it, then try again.', 'blockshift-migrate-from-elementor' ) );
			}
			if ( '' !== (string) get_post_field( 'post_password', $target_id ) ) {
				return $deny( __( 'The converted page is password-protected. Remove the password so the screenshot service can load it, then try again.', 'blockshift-migrate-from-elementor' ) );
			}
			$page_url = (string) get_permalink( $target_id );
		}

		if ( '' === $page_url ) {
			return $deny( __( 'The public URL of the converted page could not be resolved.', 'blockshift-migrate-from-elementor' ) );
		}

		$host = (string) wp_parse_url( $page_url, PHP_URL_HOST );
		if ( self::is_non_public_host( $host ) ) {
			return $deny(
				sprintf(
					/* translators: %s: site hostname (e.g. localhost) */
					__( 'This site (%s) is only reachable on your local network, so the remote screenshot service cannot open it. AI Enhancement needs a publicly accessible URL — run it on a live or staging site.', 'blockshift-migrate-from-elementor' ),
					$host
				)
			);
		}

		$response = wp_remote_get(
			$page_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'sslverify'   => true,
				'headers'     => array( 'User-Agent' => 'METG-AI-Enhancement/1.0' ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 400 ) {
				return $deny(
					sprintf(
						/* translators: 1: page URL, 2: HTTP status code */
						__( 'The converted page (%1$s) returned HTTP status %2$d, so the screenshot service cannot load it. Check that the page is public and not behind login or HTTP authentication.', 'blockshift-migrate-from-elementor' ),
						$page_url,
						$code
					)
				);
			}
		}

		return array(
			'accessible' => true,
			'reason'     => '',
		);
	}

	/**
	 * Determine whether a hostname is non-public (localhost, dev TLD, or a
	 * private/reserved IP) and therefore unreachable by the remote screenshot
	 * service.
	 *
	 * @param string $host Hostname extracted from the page URL.
	 * @return bool True when the host cannot be reached from the public internet.
	 */
	private static function is_non_public_host( string $host ): bool {
		$host = strtolower( trim( $host ) );

		if ( '' === $host ) {
			return true;
		}

		// localhost and common local/development TLDs.
		if ( 'localhost' === $host || 1 === preg_match( '/\.(local|localhost|test|internal|invalid|example)$/', $host ) ) {
			return true;
		}

		// IPv6 loopback.
		if ( '::1' === $host ) {
			return true;
		}

		// IPv4 literal in a private or reserved range (e.g. 127.0.0.1, 192.168.x).
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return false;
	}

	/**
	 * Handle the "Refine with AI" action (Round 2+).
	 *
	 * Takes fresh screenshots, then sends the current page state plus the user's
	 * focus instruction to Claude for a targeted refinement pass.
	 */
	public function handle_refine(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'blockshift-migrate-from-elementor' ) );
		}

		check_admin_referer( self::NONCE_REFINE );

		$target_id         = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id         = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$focus_instruction = isset( $_POST['focus_instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['focus_instruction'] ) ) : '';

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'blockshift-migrate-from-elementor' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_metg_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		$result = self::run_refinement( $source_id, $target_id, $focus_instruction );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'ai_failed';
			set_transient( 'metg_ai_error_' . $target_id, $result['error'], 60 );
			$this->redirect_with_notice( $source_id, $target_id, $notice );
		}

		$this->redirect_with_notice( $source_id, $target_id, 'refined' );
	}

	/**
	 * Core refinement logic — Round 2+ targeted improvement.
	 *
	 * Sends the current page state, fresh screenshots, and user's focus instruction
	 * to Claude using the refinement system prompt. The full CSS file is replaced
	 * with the result on every run.
	 *
	 * @param int    $source_id         Elementor source post ID.
	 * @param int    $target_id         Converted Gutenberg post ID.
	 * @param string $focus_instruction User's instruction on what to fix.
	 * @return array{success: bool, error: string, notice: string}
	 */
	public static function run_refinement( int $source_id, int $target_id, string $focus_instruction ): array {
		$failure = static function ( string $error, string $notice = 'ai_failed' ): array {
			return array(
				'success' => false,
				'error'   => $error,
				'notice'  => $notice,
			);
		};

		// Pre-flight: block the AI call if the page is not publicly reachable.
		$access = self::check_page_accessibility( $source_id, $target_id );
		if ( ! $access['accessible'] ) {
			return $failure( $access['reason'], 'page_inaccessible' );
		}

		// Take fresh screenshots right before sending to Claude; do not send the
		// AI request if they could not be captured.
		$screenshot_result = AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );
		if ( ! $screenshot_result['success'] ) {
			return $failure(
				sprintf(
					/* translators: %s: screenshot error details */
					__( 'Screenshots could not be generated, so AI refinement was not run: %s', 'blockshift-migrate-from-elementor' ),
					$screenshot_result['error']
				),
				'screenshot_failed'
			);
		}

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

		$current_css = self::fix_css_namespace( self::read_post_css( $target_id ), $source_id, $target_id );

		$template_type = '';
		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			$template_type = (string) get_post_meta( $source_id, '_elementor_template_type', true );
		}

		$prompt = AI_Prompt_Builder::build_refinement(
			array(
				'source_id'         => $source_id,
				'target_id'         => $target_id,
				'source_title'      => get_the_title( $source_id ),
				'target_title'      => get_the_title( $target_id ),
				'elementor_json'    => $elementor_json,
				'gutenberg_content' => $gutenberg_content,
				'current_css'       => $current_css,
				'focus_instruction' => $focus_instruction,
				'template_type'     => $template_type,
			)
		);

		$elementor_shots = AI_Remediation_Screenshot_Meta_Service::get_elementor_urls( $target_id );
		$gutenberg_shots = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_urls( $target_id );

		$api_result = Claude_Api_Service::send(
			$prompt,
			$elementor_shots,
			$gutenberg_shots,
			Claude_Api_Service::get_refinement_system_prompt()
		);

		if ( ! $api_result['success'] ) {
			self::log_improvement(
				array(
					'step'      => 'refine_api_failed',
					'target_id' => $target_id,
					'error'     => $api_result['error'],
				)
			);
			return $failure( $api_result['error'], 'ai_failed' );
		}

		$parsed           = Claude_Api_Service::parse_response( $api_result['content'] );
		$css_result       = self::fix_css_namespace( $parsed['css'], $source_id, $target_id );
		$gutenberg_result = self::sanitize_block_content( $parsed['gutenberg'] );

		self::log_improvement(
			array(
				'step'              => 'refine_parse_complete',
				'target_id'         => $target_id,
				'css_length'        => strlen( $css_result ),
				'gutenberg_length'  => strlen( $gutenberg_result ),
				'gutenberg_preview' => substr( $gutenberg_result, 0, 120 ),
			)
		);

		if ( '' === trim( $gutenberg_result ) ) {
			self::log_improvement(
				array(
					'step'      => 'refine_parse_failed_empty_gutenberg',
					'target_id' => $target_id,
				)
			);
			return $failure(
				__( 'No valid Gutenberg content could be parsed from the AI refinement response.', 'blockshift-migrate-from-elementor' ),
				'ai_parse_failed'
			);
		}

		$update_result = wp_update_post(
			array(
				'ID'           => $target_id,
				'post_content' => $gutenberg_result,
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			self::log_improvement(
				array(
					'step'      => 'refine_wp_update_post_failed',
					'target_id' => $target_id,
					'error'     => $update_result->get_error_message(),
				)
			);
			return $failure( $update_result->get_error_message(), 'update_failed' );
		}

		// Replace the full CSS file on every refinement run.
		if ( '' !== trim( $css_result ) ) {
			External_CSS_Service::save_post_css( $target_id, $css_result );
		}

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			External_CSS_Service::register_global_css_post( $target_id );
		}

		$workspace                           = AI_Workspace_Repository::get( $target_id );
		$workspace['target_post_id']         = $target_id;
		$workspace['source_post_id']         = $source_id;
		$workspace['css_result_draft']       = $css_result;
		$workspace['gutenberg_result_draft'] = $gutenberg_result;
		$workspace['updated_at']             = current_time( 'mysql' );
		AI_Workspace_Repository::save( $target_id, $workspace );

		update_post_meta( $target_id, '_metg_last_ai_improved', current_time( 'mysql' ) );

		return array(
			'success' => true,
			'error'   => '',
			'notice'  => 'refined',
		);
	}

	/**
	 * Handle the "Improve Mobile with AI" action.
	 *
	 * A separate AI pass that uses ONLY the mobile screenshots and instructs Claude
	 * to output mobile-only @media query CSS. The result is merged into the existing
	 * CSS file inside marker comments so desktop styles and Gutenberg post_content
	 * are never touched.
	 */
	public function handle_mobile_improve(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'blockshift-migrate-from-elementor' ) );
		}

		check_admin_referer( self::NONCE_MOBILE_IMPROVE );

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'blockshift-migrate-from-elementor' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_metg_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		$result = self::run_mobile_improvement( $source_id, $target_id );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'mobile_failed';
			set_transient( 'metg_ai_error_' . $target_id, $result['error'], 60 );
			$this->redirect_with_notice( $source_id, $target_id, $notice );
		}

		$this->redirect_with_notice( $source_id, $target_id, 'mobile_improved' );
	}

	/**
	 * Core mobile improvement logic.
	 *
	 * Sends only the mobile screenshots to Claude with the mobile system prompt,
	 * extracts CSS_RESULT, wraps it in mobile markers, and merges it into the
	 * existing CSS file. Does not modify Gutenberg post_content.
	 *
	 * @param int $source_id Elementor source post ID.
	 * @param int $target_id Converted Gutenberg post ID.
	 * @return array{success: bool, error: string, notice: string}
	 */
	public static function run_mobile_improvement( int $source_id, int $target_id ): array {
		$failure = static function ( string $error, string $notice = 'mobile_failed' ): array {
			return array(
				'success' => false,
				'error'   => $error,
				'notice'  => $notice,
			);
		};

		// Pre-flight: block the AI call if the page is not publicly reachable.
		$access = self::check_page_accessibility( $source_id, $target_id );
		if ( ! $access['accessible'] ) {
			return $failure( $access['reason'] );
		}

		// Take fresh screenshots so the mobile pass works against the current
		// rendering; do not send the AI request if they could not be captured.
		$screenshot_result = AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );
		if ( ! $screenshot_result['success'] ) {
			return $failure(
				sprintf(
					/* translators: %s: screenshot error details */
					__( 'Screenshots could not be generated, so mobile AI enhancement was not run: %s', 'blockshift-migrate-from-elementor' ),
					$screenshot_result['error']
				)
			);
		}

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

		$current_css = self::fix_css_namespace( self::read_post_css( $target_id ), $source_id, $target_id );

		$template_type = '';
		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			$template_type = (string) get_post_meta( $source_id, '_elementor_template_type', true );
		}

		$prompt = AI_Prompt_Builder::build_mobile(
			array(
				'source_id'         => $source_id,
				'target_id'         => $target_id,
				'source_title'      => get_the_title( $source_id ),
				'target_title'      => get_the_title( $target_id ),
				'elementor_json'    => $elementor_json,
				'gutenberg_content' => $gutenberg_content,
				'current_css'       => $current_css,
				'template_type'     => $template_type,
			)
		);

		// Mobile pass: send only mobile screenshot chunks, no desktop screenshots.
		$elementor_mobile_shots = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_urls( $target_id );
		$gutenberg_mobile_shots = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_urls( $target_id );

		$api_result = Claude_Api_Service::send(
			$prompt,
			array(),
			array(),
			Claude_Api_Service::get_mobile_improvement_system_prompt(),
			$elementor_mobile_shots,
			$gutenberg_mobile_shots
		);

		if ( ! $api_result['success'] ) {
			self::log_improvement(
				array(
					'step'      => 'mobile_api_failed',
					'target_id' => $target_id,
					'error'     => $api_result['error'],
				)
			);
			return $failure( $api_result['error'], 'mobile_failed' );
		}

		$mobile_css = self::fix_css_namespace(
			Claude_Api_Service::parse_css_only_response( $api_result['content'] ),
			$source_id,
			$target_id
		);

		self::log_improvement(
			array(
				'step'        => 'mobile_parse_complete',
				'target_id'   => $target_id,
				'css_length'  => strlen( $mobile_css ),
				'css_preview' => substr( $mobile_css, 0, 200 ),
			)
		);

		$merged_css = self::merge_mobile_css( $current_css, $mobile_css );

		if ( '' !== trim( $merged_css ) ) {
			External_CSS_Service::save_post_css( $target_id, $merged_css );
		}

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			External_CSS_Service::register_global_css_post( $target_id );
		}

		update_post_meta( $target_id, '_metg_last_ai_mobile_improved', current_time( 'mysql' ) );

		return array(
			'success' => true,
			'error'   => '',
			'notice'  => 'mobile_improved',
		);
	}

	/**
	 * Merge mobile-only CSS into the existing CSS file content.
	 *
	 * Wraps the mobile CSS between MOBILE_CSS_START_MARKER and MOBILE_CSS_END_MARKER.
	 * If a previous mobile block already exists between those markers it is
	 * replaced; otherwise the wrapped block is appended. Desktop CSS outside the
	 * markers is preserved verbatim.
	 *
	 * @param string $existing_css Current full CSS file content.
	 * @param string $mobile_css   New mobile-only CSS body (no markers).
	 * @return string Updated CSS string ready to write back to the file.
	 */
	private static function merge_mobile_css( string $existing_css, string $mobile_css ): string {
		$mobile_css = trim( $mobile_css );

		$wrapped = self::MOBILE_CSS_START_MARKER . "\n" . $mobile_css . "\n" . self::MOBILE_CSS_END_MARKER;

		// Match an existing mobile block (with possible surrounding whitespace).
		$pattern = '/\s*' . preg_quote( self::MOBILE_CSS_START_MARKER, '/' ) . '.*?' . preg_quote( self::MOBILE_CSS_END_MARKER, '/' ) . '/s';

		if ( preg_match( $pattern, $existing_css ) ) {
			$replaced = preg_replace( $pattern, "\n\n" . $wrapped, $existing_css );
			return is_string( $replaced ) ? trim( $replaced ) . "\n" : $existing_css;
		}

		if ( '' === trim( $existing_css ) ) {
			return $wrapped . "\n";
		}

		return trim( $existing_css ) . "\n\n" . $wrapped . "\n";
	}

	/**
	 * Correct the CSS namespace if Claude used the target ID instead of the source ID.
	 *
	 * The Gutenberg page HTML wrapper always uses metg-page-{source_id} as its class,
	 * so all CSS selectors must target that class. Claude sometimes uses the target ID
	 * instead. This method replaces any wrong occurrences as a guaranteed safety net.
	 *
	 * @param string $css       Raw CSS from Claude.
	 * @param int    $source_id Elementor source post ID (correct namespace).
	 * @param int    $target_id Converted Gutenberg post ID (wrong namespace to replace).
	 * @return string
	 */
	private static function fix_css_namespace( string $css, int $source_id, int $target_id ): string {
		if ( $source_id === $target_id || '' === $css ) {
			return $css;
		}

		return str_replace(
			'metg-page-' . $target_id,
			'metg-page-' . $source_id,
			$css
		);
	}

	/**
	 * Read the current CSS file content for a post.
	 *
	 * Returns an empty string if no CSS file exists yet.
	 *
	 * @param int $target_id Target post ID.
	 * @return string
	 */
	private static function read_post_css( int $target_id ): string {
		$css_meta = External_CSS_Service::get_post_css_meta( $target_id );
		if ( ! is_array( $css_meta ) || empty( $css_meta['path'] ) ) {
			return '';
		}

		$path = (string) $css_meta['path'];
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}

		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Handle the Regenerate Screenshots action.
	 */
	public function handle_regenerate_screenshots(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'blockshift-migrate-from-elementor' ) );
		}

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		check_admin_referer( 'metg_ai_regenerate_screenshots_' . $target_id );

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'blockshift-migrate-from-elementor' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'blockshift-migrate-from-elementor' ) );
		}

		$result = AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );
		$notice = $result['success'] ? 'screenshots_regenerated' : 'screenshots_failed';
		$this->redirect_with_notice( $source_id, $target_id, $notice );
	}

	/**
	 * Redirect with admin notice code.
	 */
	private function redirect_with_notice( int $source_id, int $target_id, string $notice_code ): void {
		$url = add_query_arg(
			array(
				'page'           => self::MENU_SLUG,
				'source_id'      => $source_id,
				'target_id'      => $target_id,
				'metg_ai_notice' => $notice_code,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render top notice based on code.
	 *
	 * @param string $notice_code Notice code from query string.
	 * @param int    $target_id   Target post ID (used to retrieve stored error messages).
	 */
	private function render_notice( string $notice_code, int $target_id = 0 ): void {
		if ( '' === $notice_code ) {
			return;
		}

		// Failure codes that carry a detailed "why" message in a transient.
		$detail_prefixes = array(
			'ai_failed'         => esc_html__( 'Claude API call failed', 'blockshift-migrate-from-elementor' ),
			'mobile_failed'     => esc_html__( 'Mobile improvement failed', 'blockshift-migrate-from-elementor' ),
			'page_inaccessible' => esc_html__( 'AI enhancement was not run because this page is not accessible', 'blockshift-migrate-from-elementor' ),
			'screenshot_failed' => esc_html__( 'AI enhancement was not run because screenshots could not be generated', 'blockshift-migrate-from-elementor' ),
		);

		if ( isset( $detail_prefixes[ $notice_code ] ) ) {
			$ai_error = '';
			if ( $target_id > 0 ) {
				$ai_error = (string) get_transient( 'metg_ai_error_' . $target_id );
				delete_transient( 'metg_ai_error_' . $target_id );
			}
			$prefix = $detail_prefixes[ $notice_code ];
			$msg    = '' !== $ai_error
				/* translators: 1: failure prefix, 2: detailed reason for the failure */
				? sprintf( esc_html__( '%1$s: %2$s', 'blockshift-migrate-from-elementor' ), $prefix, esc_html( $ai_error ) )
				: $prefix . '.';
			?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php
			return;
		}

		$messages = array(
			'updated'                 => array( 'success', esc_html__( 'Page updated and AI CSS appended successfully.', 'blockshift-migrate-from-elementor' ) ),
			'missing_gutenberg'       => array( 'error', esc_html__( 'Gutenberg result is required before updating.', 'blockshift-migrate-from-elementor' ) ),
			'css_append_failed'       => array( 'error', esc_html__( 'Could not append CSS because the external CSS file for this page could not be resolved.', 'blockshift-migrate-from-elementor' ) ),
			'update_failed'           => array( 'error', esc_html__( 'Failed to update Gutenberg page content.', 'blockshift-migrate-from-elementor' ) ),
			'invalid_mapping'         => array( 'error', esc_html__( 'Source and target mapping validation failed.', 'blockshift-migrate-from-elementor' ) ),
			'screenshots_regenerated' => array( 'success', esc_html__( 'Screenshots regenerated successfully.', 'blockshift-migrate-from-elementor' ) ),
			'screenshots_failed'      => array( 'error', esc_html__( 'Screenshot regeneration failed. Check the screenshot service settings and connectivity.', 'blockshift-migrate-from-elementor' ) ),
			'ai_parse_failed'         => array( 'error', esc_html__( 'Claude returned a response but no valid Gutenberg content could be parsed.', 'blockshift-migrate-from-elementor' ) ),
			'refined'                 => array( 'success', esc_html__( 'Page refined successfully. Fresh screenshots were captured before this run.', 'blockshift-migrate-from-elementor' ) ),
			'mobile_improved'         => array( 'success', esc_html__( 'Mobile CSS improved successfully. Desktop styles were not modified.', 'blockshift-migrate-from-elementor' ) ),
			'mobile_failed'           => array( 'error', esc_html__( 'Mobile improvement failed. Check the screenshot service and Claude API settings.', 'blockshift-migrate-from-elementor' ) ),
		);

		if ( ! isset( $messages[ $notice_code ] ) ) {
			return;
		}

		$notice_type = $messages[ $notice_code ][0];
		$message     = $messages[ $notice_code ][1];
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Render redesigned improvement workflow page.
	 *
	 * Two-column layout: screenshot thumbnails with lightbox, desktop/mobile tabs,
	 * AI improvement forms, and a sidebar with page details and AI status.
	 */
	private function render_form( WP_Post $target_post, WP_Post $source_post, array $workspace ): void {
		$target_id    = (int) $target_post->ID;
		$source_id    = (int) $source_post->ID;
		$target_title = get_the_title( $target_id );
		$source_title = get_the_title( $source_id );

		$elementor_shots        = isset( $workspace['elementor_screenshot'] ) ? (array) $workspace['elementor_screenshot'] : array();
		$gutenberg_shots        = isset( $workspace['gutenberg_screenshot'] ) ? (array) $workspace['gutenberg_screenshot'] : array();
		$elementor_mobile_shots = isset( $workspace['elementor_mobile_screenshot'] ) ? (array) $workspace['elementor_mobile_screenshot'] : array();
		$gutenberg_mobile_shots = isset( $workspace['gutenberg_mobile_screenshot'] ) ? (array) $workspace['gutenberg_mobile_screenshot'] : array();

		$screenshot_status       = AI_Remediation_Screenshot_Meta_Service::get_status( $target_id );
		$screenshot_generated_at = (string) get_post_meta( $target_id, AI_Remediation_Screenshot_Meta_Service::META_GENERATED_AT, true );
		$service_configured      = '' !== AI_Remediation_Screenshot_Api_Service::get_endpoint_url();
		$last_improved           = (string) get_post_meta( $target_id, '_metg_last_ai_improved', true );
		$last_mobile_improved    = (string) get_post_meta( $target_id, '_metg_last_ai_mobile_improved', true );
		$has_mobile_shots        = ! empty( $elementor_mobile_shots ) && ! empty( $gutenberg_mobile_shots );

		$pill_map = array(
			AI_Remediation_Screenshot_Meta_Service::STATUS_SUCCESS       => array( 'success', esc_html__( 'Generated', 'blockshift-migrate-from-elementor' ) ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_FAILED        => array( 'error', esc_html__( 'Failed', 'blockshift-migrate-from-elementor' ) ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_PENDING       => array( 'pending', esc_html__( 'Pending', 'blockshift-migrate-from-elementor' ) ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_NOT_GENERATED => array( 'neutral', esc_html__( 'Not generated', 'blockshift-migrate-from-elementor' ) ),
		);
		$pill     = isset( $pill_map[ $screenshot_status ] ) ? $pill_map[ $screenshot_status ] : array( 'neutral', esc_html( $screenshot_status ) );

		$enhancement_url = admin_url( 'admin.php?page=' . AI_Enhancement_Admin::MENU_SLUG );
		$target_edit_url = admin_url( 'post.php?post=' . $target_id . '&action=edit' );
		$source_edit_url = admin_url( 'post.php?post=' . $source_id . '&action=edit' );
		$source_prev_url = \get_permalink( $source_id );
		$target_prev_url = \get_permalink( $target_id );

		$suggestions = array(
			__( 'Fix hero section spacing and alignment', 'blockshift-migrate-from-elementor' ),
			__( 'Match typography — font sizes and weights', 'blockshift-migrate-from-elementor' ),
			__( 'Improve button styles and colors', 'blockshift-migrate-from-elementor' ),
			__( 'Fix colors and contrast', 'blockshift-migrate-from-elementor' ),
			__( 'Fix image sizing and alignment', 'blockshift-migrate-from-elementor' ),
			__( 'Fix section padding and spacing', 'blockshift-migrate-from-elementor' ),
			__( 'Improve heading styles', 'blockshift-migrate-from-elementor' ),
			__( 'Fix navigation menu styling', 'blockshift-migrate-from-elementor' ),
		);

		?>
		<div class="metg-ai-page">

			<div class="metg-ai-header">
				<div class="metg-ai-header-nav">
					<a href="<?php echo esc_url( $enhancement_url ); ?>" class="metg-ai-back-link">&#8592; <?php esc_html_e( 'Back to AI Enhancement', 'blockshift-migrate-from-elementor' ); ?></a>
				</div>
				<div class="metg-ai-header-main">
					<div class="metg-ai-header-title">
						<h1><?php esc_html_e( 'AI Enhancement', 'blockshift-migrate-from-elementor' ); ?></h1>
						<div class="metg-ai-header-path">
							<span><?php echo esc_html( $source_title ); ?></span>
							<span class="metg-ai-arrow">&#8594;</span>
							<span><?php echo esc_html( $target_title ); ?></span>
						</div>
					</div>
					<div class="metg-ai-header-actions">
						<?php if ( $source_prev_url ) : ?>
							<a href="<?php echo esc_url( $source_prev_url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'View Source &#8599;', 'blockshift-migrate-from-elementor' ); ?></a>
						<?php endif; ?>
						<?php if ( $target_prev_url ) : ?>
							<a href="<?php echo esc_url( $target_prev_url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'Preview &#8599;', 'blockshift-migrate-from-elementor' ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $last_improved ) : ?>
							<button type="button" id="metg-ai-feedback-btn" class="button"><?php esc_html_e( 'Send Feedback', 'blockshift-migrate-from-elementor' ); ?></button>
						<?php endif; ?>
						<a href="<?php echo esc_url( $target_edit_url ); ?>" class="button button-primary"><?php esc_html_e( 'Edit in Gutenberg', 'blockshift-migrate-from-elementor' ); ?></a>
					</div>
				</div>
			</div>

			<div class="metg-ai-layout">

				<div class="metg-ai-main">

					<div class="metg-ai-card">
						<div class="metg-ai-card-header">
							<h2><?php esc_html_e( 'Screenshots', 'blockshift-migrate-from-elementor' ); ?></h2>
							<span class="metg-status-pill metg-status-pill--<?php echo esc_attr( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?></span>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="metg-inline-form">
								<?php wp_nonce_field( 'metg_ai_regenerate_screenshots_' . $target_id ); ?>
								<input type="hidden" name="action" value="metg_ai_regenerate_screenshots" />
								<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
								<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
								<button type="submit" class="button button-small"><?php esc_html_e( 'Regenerate', 'blockshift-migrate-from-elementor' ); ?></button>
							</form>
						</div>

						<div class="metg-ai-tabs" role="tablist">
							<button type="button" class="metg-ai-tab metg-ai-tab--active" role="tab" data-tab="desktop" aria-selected="true"><?php esc_html_e( 'Desktop', 'blockshift-migrate-from-elementor' ); ?></button>
							<button type="button" class="metg-ai-tab" role="tab" data-tab="mobile" aria-selected="false"><?php esc_html_e( 'Mobile', 'blockshift-migrate-from-elementor' ); ?></button>
						</div>

						<div class="metg-ai-tab-panel" data-panel="desktop">
							<div class="metg-ai-compare-grid">
								<div class="metg-compare-side">
									<div class="metg-compare-label"><?php esc_html_e( 'Elementor (Original)', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php
									$d_ele_urls  = array_values( array_filter( $elementor_shots, 'is_string' ) );
									$d_ele_first = ! empty( $d_ele_urls ) ? $d_ele_urls[0] : '';
									if ( $d_ele_first ) :
										?>
										<div class="metg-screenshot-thumb-wrap" data-urls="<?php echo esc_attr( wp_json_encode( $d_ele_urls ) ); ?>">
											<img class="metg-screenshot-thumb" src="<?php echo esc_url( $d_ele_first ); ?>" alt="" loading="lazy" />
											<button type="button" class="metg-screenshot-zoom-btn" aria-label="<?php esc_attr_e( 'View full screenshot', 'blockshift-migrate-from-elementor' ); ?>">&#x2922;</button>
										</div>
									<?php else : ?>
										<div class="metg-screenshot-empty"><?php esc_html_e( 'No desktop screenshot yet', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php endif; ?>
								</div>
								<div class="metg-compare-side">
									<div class="metg-compare-label"><?php esc_html_e( 'Gutenberg (Converted)', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php
									$d_gb_urls  = array_values( array_filter( $gutenberg_shots, 'is_string' ) );
									$d_gb_first = ! empty( $d_gb_urls ) ? $d_gb_urls[0] : '';
									if ( $d_gb_first ) :
										?>
										<div class="metg-screenshot-thumb-wrap" data-urls="<?php echo esc_attr( wp_json_encode( $d_gb_urls ) ); ?>">
											<img class="metg-screenshot-thumb" src="<?php echo esc_url( $d_gb_first ); ?>" alt="" loading="lazy" />
											<button type="button" class="metg-screenshot-zoom-btn" aria-label="<?php esc_attr_e( 'View full screenshot', 'blockshift-migrate-from-elementor' ); ?>">&#x2922;</button>
										</div>
									<?php else : ?>
										<div class="metg-screenshot-empty"><?php esc_html_e( 'No desktop screenshot yet', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="metg-ai-tab-panel" data-panel="mobile" hidden>
							<div class="metg-ai-compare-grid">
								<div class="metg-compare-side">
									<div class="metg-compare-label"><?php esc_html_e( 'Elementor Mobile', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php
									$m_ele_urls  = array_values( array_filter( $elementor_mobile_shots, 'is_string' ) );
									$m_ele_first = ! empty( $m_ele_urls ) ? $m_ele_urls[0] : '';
									if ( $m_ele_first ) :
										?>
										<div class="metg-screenshot-thumb-wrap" data-urls="<?php echo esc_attr( wp_json_encode( $m_ele_urls ) ); ?>">
											<img class="metg-screenshot-thumb" src="<?php echo esc_url( $m_ele_first ); ?>" alt="" loading="lazy" />
											<button type="button" class="metg-screenshot-zoom-btn" aria-label="<?php esc_attr_e( 'View full screenshot', 'blockshift-migrate-from-elementor' ); ?>">&#x2922;</button>
										</div>
									<?php else : ?>
										<div class="metg-screenshot-empty"><?php esc_html_e( 'No mobile screenshot yet', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php endif; ?>
								</div>
								<div class="metg-compare-side">
									<div class="metg-compare-label"><?php esc_html_e( 'Gutenberg Mobile', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php
									$m_gb_urls  = array_values( array_filter( $gutenberg_mobile_shots, 'is_string' ) );
									$m_gb_first = ! empty( $m_gb_urls ) ? $m_gb_urls[0] : '';
									if ( $m_gb_first ) :
										?>
										<div class="metg-screenshot-thumb-wrap" data-urls="<?php echo esc_attr( wp_json_encode( $m_gb_urls ) ); ?>">
											<img class="metg-screenshot-thumb" src="<?php echo esc_url( $m_gb_first ); ?>" alt="" loading="lazy" />
											<button type="button" class="metg-screenshot-zoom-btn" aria-label="<?php esc_attr_e( 'View full screenshot', 'blockshift-migrate-from-elementor' ); ?>">&#x2922;</button>
										</div>
									<?php else : ?>
										<div class="metg-screenshot-empty"><?php esc_html_e( 'No mobile screenshot yet', 'blockshift-migrate-from-elementor' ); ?></div>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<?php if ( '' !== $screenshot_generated_at || ! $service_configured ) : ?>
						<div class="metg-ai-card-footer">
							<?php if ( '' !== $screenshot_generated_at ) : ?>
								<?php
								/* translators: %s: date/time screenshots were captured */
								printf( esc_html__( 'Last captured: %s', 'blockshift-migrate-from-elementor' ), esc_html( $screenshot_generated_at ) );
								?>
							<?php endif; ?>
							<?php if ( ! $service_configured ) : ?>
								<span class="metg-warning-inline"><?php esc_html_e( 'Screenshot service not configured — see Settings.', 'blockshift-migrate-from-elementor' ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>

					<div class="metg-ai-card">
						<?php if ( '' === $last_improved ) : ?>
							<div class="metg-ai-card-header">
								<h2><?php esc_html_e( 'AI Improvement', 'blockshift-migrate-from-elementor' ); ?></h2>
								<span class="metg-status-pill metg-status-pill--neutral"><?php esc_html_e( 'Not yet run', 'blockshift-migrate-from-elementor' ); ?></span>
							</div>
							<div class="metg-ai-card-body">
								<p class="metg-card-desc"><?php esc_html_e( 'Analyse and improve the converted page using AI. The page content and CSS will be updated automatically.', 'blockshift-migrate-from-elementor' ); ?></p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="metg-ai-improve-form">
									<?php wp_nonce_field( self::NONCE_AUTO_IMPROVE ); ?>
									<input type="hidden" name="action" value="metg_ai_auto_improve" />
									<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
									<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
									<?php submit_button( esc_html__( 'Improve with AI', 'blockshift-migrate-from-elementor' ), 'primary', 'metg_auto_improve_submit', false ); ?>
								</form>
							</div>
						<?php else : ?>
							<div class="metg-ai-card-header">
								<h2><?php esc_html_e( 'Refine with AI', 'blockshift-migrate-from-elementor' ); ?></h2>
								<span class="metg-status-pill metg-status-pill--success"><?php esc_html_e( 'Improved', 'blockshift-migrate-from-elementor' ); ?></span>
							</div>
							<div class="metg-ai-card-body">
								<p class="metg-card-desc"><?php esc_html_e( 'Tell AI exactly what to focus on. Fresh screenshots are captured automatically before each run.', 'blockshift-migrate-from-elementor' ); ?></p>
								<div class="metg-refine-suggestions">
									<span class="metg-refine-suggestions-label"><?php esc_html_e( 'Quick suggestions:', 'blockshift-migrate-from-elementor' ); ?></span>
									<?php foreach ( $suggestions as $suggestion ) : ?>
										<button type="button" class="metg-suggestion-chip" data-suggestion="<?php echo esc_attr( $suggestion ); ?>"><?php echo esc_html( $suggestion ); ?></button>
									<?php endforeach; ?>
								</div>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="metg-ai-refine-form">
									<?php wp_nonce_field( self::NONCE_REFINE ); ?>
									<input type="hidden" name="action" value="metg_ai_refine" />
									<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
									<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
									<textarea
										name="focus_instruction"
										id="metg-focus-instruction"
										rows="4"
										placeholder="<?php echo esc_attr__( 'Describe what needs fixing, e.g. "The hero section spacing is too tight and the heading font is too small"', 'blockshift-migrate-from-elementor' ); ?>"
										class="metg-refine-textarea"
									></textarea>
									<?php submit_button( esc_html__( 'Refine with AI', 'blockshift-migrate-from-elementor' ), 'primary', 'metg_refine_submit', false ); ?>
								</form>
							</div>
						<?php endif; ?>
					</div>

					<div class="metg-ai-card">
						<div class="metg-ai-card-header">
							<h2><?php esc_html_e( 'Mobile Optimisation', 'blockshift-migrate-from-elementor' ); ?></h2>
							<?php if ( '' !== $last_mobile_improved ) : ?>
								<span class="metg-status-pill metg-status-pill--success"><?php esc_html_e( 'Improved', 'blockshift-migrate-from-elementor' ); ?></span>
							<?php else : ?>
								<span class="metg-status-pill metg-status-pill--neutral"><?php esc_html_e( 'Not yet run', 'blockshift-migrate-from-elementor' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="metg-ai-card-body">
							<p class="metg-card-desc"><?php esc_html_e( 'Compares mobile screenshots and generates @media query CSS. Desktop styles and block content are not modified.', 'blockshift-migrate-from-elementor' ); ?></p>
							<?php if ( ! $has_mobile_shots ) : ?>
								<div class="metg-notice metg-notice--warning">
									<?php esc_html_e( 'Mobile screenshots are missing — click Regenerate in the Screenshots card above before running this pass.', 'blockshift-migrate-from-elementor' ); ?>
								</div>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="metg-ai-mobile-improve-form">
								<?php wp_nonce_field( self::NONCE_MOBILE_IMPROVE ); ?>
								<input type="hidden" name="action" value="metg_ai_mobile_improve" />
								<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
								<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
								<?php submit_button( esc_html__( 'Improve Mobile with AI', 'blockshift-migrate-from-elementor' ), 'secondary', 'metg_mobile_improve_submit', false ); ?>
							</form>
						</div>
					</div>

				</div>

				<aside class="metg-ai-sidebar">

					<div class="metg-ai-card">
						<div class="metg-ai-card-header">
							<h2><?php esc_html_e( 'Page Details', 'blockshift-migrate-from-elementor' ); ?></h2>
						</div>
						<div class="metg-ai-card-body">
							<dl class="metg-ai-dl">
								<dt><?php esc_html_e( 'Source (Elementor)', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd>
									<a href="<?php echo esc_url( $source_edit_url ); ?>"><?php echo esc_html( $source_title ); ?></a>
									<?php if ( $source_prev_url ) : ?>
										<a href="<?php echo esc_url( $source_prev_url ); ?>" target="_blank" rel="noopener" class="metg-ext-link" title="<?php esc_attr_e( 'Preview', 'blockshift-migrate-from-elementor' ); ?>">&#8599;</a>
									<?php endif; ?>
								</dd>
								<dt><?php esc_html_e( 'Target (Gutenberg)', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd>
									<a href="<?php echo esc_url( $target_edit_url ); ?>"><?php echo esc_html( $target_title ); ?></a>
									<?php if ( $target_prev_url ) : ?>
										<a href="<?php echo esc_url( $target_prev_url ); ?>" target="_blank" rel="noopener" class="metg-ext-link" title="<?php esc_attr_e( 'Preview', 'blockshift-migrate-from-elementor' ); ?>">&#8599;</a>
									<?php endif; ?>
								</dd>
								<dt><?php esc_html_e( 'Source ID', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd><?php echo esc_html( (string) $source_id ); ?></dd>
								<dt><?php esc_html_e( 'Target ID', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd><?php echo esc_html( (string) $target_id ); ?></dd>
							</dl>
						</div>
					</div>

					<div class="metg-ai-card">
						<div class="metg-ai-card-header">
							<h2><?php esc_html_e( 'AI Status', 'blockshift-migrate-from-elementor' ); ?></h2>
						</div>
						<div class="metg-ai-card-body">
							<dl class="metg-ai-dl">
								<dt><?php esc_html_e( 'Desktop', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd>
									<?php if ( '' !== $last_improved ) : ?>
										<?php echo esc_html( $last_improved ); ?>
									<?php else : ?>
										<span class="metg-muted"><?php esc_html_e( 'Not yet run', 'blockshift-migrate-from-elementor' ); ?></span>
									<?php endif; ?>
								</dd>
								<dt><?php esc_html_e( 'Mobile', 'blockshift-migrate-from-elementor' ); ?></dt>
								<dd>
									<?php if ( '' !== $last_mobile_improved ) : ?>
										<?php echo esc_html( $last_mobile_improved ); ?>
									<?php else : ?>
										<span class="metg-muted"><?php esc_html_e( 'Not yet run', 'blockshift-migrate-from-elementor' ); ?></span>
									<?php endif; ?>
								</dd>
							</dl>
						</div>
					</div>

				</aside>

			</div>

			<div id="metg-lightbox" class="metg-lightbox" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Screenshot viewer', 'blockshift-migrate-from-elementor' ); ?>">
				<div class="metg-lightbox-overlay" id="metg-lightbox-overlay"></div>
				<div class="metg-lightbox-panel">
					<div class="metg-lightbox-toolbar">
						<a id="metg-lightbox-open" href="#" target="_blank" rel="noopener" class="metg-lightbox-open-link"><?php esc_html_e( 'Open full image &#8599;', 'blockshift-migrate-from-elementor' ); ?></a>
						<button type="button" id="metg-lightbox-close" class="metg-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'blockshift-migrate-from-elementor' ); ?>">&#x2715;</button>
					</div>
					<div id="metg-lightbox-images" class="metg-lightbox-images"></div>
				</div>
			</div>

			<div id="metg-ai-loader" hidden>
				<div class="metg-ai-loader-card">
					<svg class="metg-ai-loader-spinner" viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<circle class="track" cx="22" cy="22" r="20" fill="none" stroke="#2271b1" stroke-width="3" />
						<circle class="arc"   cx="22" cy="22" r="20" fill="none" stroke="#2271b1" stroke-width="3" />
					</svg>
					<div>
						<strong class="metg-ai-loader-title"><?php esc_html_e( 'Improving with AI&#8230;', 'blockshift-migrate-from-elementor' ); ?></strong>
						<span class="metg-ai-loader-message"><?php esc_html_e( 'Analysing page structure and generating improvements. This may take up to 2 minutes.', 'blockshift-migrate-from-elementor' ); ?></span>
					</div>
				</div>
			</div>

		</div>
		<?php
	}
}
