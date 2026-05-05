<?php
/**
 * Automated AI improvement admin workflow.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Helper\AI_Prompt_Builder;
use Progressus\Gutenberg\Admin\Helper\AI_Workspace_Repository;
use Progressus\Gutenberg\Admin\Helper\External_CSS_Service;
use Progressus\Gutenberg\Admin\Helper\AI_Remediation_Screenshot_Api_Service;
use Progressus\Gutenberg\Admin\Helper\AI_Remediation_Screenshot_Meta_Service;
use Progressus\Gutenberg\Admin\Helper\Claude_Api_Service;
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
use function get_post;
use function get_post_field;
use function get_post_meta;
use function get_post_type;
use function get_the_title;
use function get_transient;
use function is_array;
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
use function wp_safe_redirect;
use function wp_unslash;
use function wp_update_post;

defined( 'ABSPATH' ) || exit;

class AI_Improvement_Admin {

	public const MENU_SLUG = 'ele2gb-ai-improvement';

	private const NONCE_AUTO_IMPROVE   = 'ele2gb_ai_auto_improve';
	private const NONCE_REFINE         = 'ele2gb_ai_refine';
	private const NONCE_MOBILE_IMPROVE = 'ele2gb_ai_mobile_improve';

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
		add_action( 'admin_post_ele2gb_ai_auto_improve', array( $this, 'handle_auto_improve' ) );
		add_action( 'admin_post_ele2gb_ai_refine', array( $this, 'handle_refine' ) );
		add_action( 'admin_post_ele2gb_ai_mobile_improve', array( $this, 'handle_mobile_improve' ) );
		add_action( 'admin_post_ele2gb_ai_regenerate_screenshots', array( $this, 'handle_regenerate_screenshots' ) );
	}

	/**
	 * Enqueue page-specific assets.
	 *
	 * Only loads on this plugin's AI improvement page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'ele2gb-ai-improve',
			plugins_url( 'assets/css/ai-improve.css', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'ele2gb-ai-improve',
			plugins_url( 'assets/js/ai-improve.js', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ele2gb-ai-improve',
			'ele2gbAiImprove',
			array(
				'processingLabel' => __( 'Processing…', 'elementor-to-gutenberg' ),
				'improvingLabel'  => __( 'Improving with AI…', 'elementor-to-gutenberg' ),
				'refiningLabel'   => __( 'Refining with AI…', 'elementor-to-gutenberg' ),
				'mobileLabel'     => __( 'Improving mobile with AI…', 'elementor-to-gutenberg' ),
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
			esc_html__( 'Improve Converted Page with AI', 'elementor-to-gutenberg' ),
			esc_html__( 'Improve Converted Page with AI', 'elementor-to-gutenberg' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
		}

		$target_id = isset( $_GET['target_id'] ) ? absint( wp_unslash( $_GET['target_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source_id = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $target_id <= 0 ) {
			wp_die( esc_html__( 'Missing converted Gutenberg page ID.', 'elementor-to-gutenberg' ) );
		}

		$target_post = get_post( $target_id );
		if ( ! $target_post instanceof WP_Post ) {
			wp_die( esc_html__( 'Converted Gutenberg page not found.', 'elementor-to-gutenberg' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'elementor-to-gutenberg' ) );
		}

		if ( $source_id <= 0 ) {
			$source_id = (int) get_post_meta( $target_id, '_ele2gb_source_id', true );
		}

		if ( $source_id <= 0 ) {
			wp_die( esc_html__( 'Source Elementor page ID could not be resolved.', 'elementor-to-gutenberg' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_ele2gb_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			wp_die( esc_html__( 'The selected source and target page mapping is invalid.', 'elementor-to-gutenberg' ) );
		}

		$source_post = get_post( $source_id );
		if ( ! $source_post instanceof WP_Post ) {
			wp_die( esc_html__( 'Source Elementor page not found.', 'elementor-to-gutenberg' ) );
		}

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

		$existing_workspace = AI_Workspace_Repository::get( $target_id );

		// Load screenshot URLs from dedicated meta; fall back to workspace for backward compatibility.
		$elementor_shot        = AI_Remediation_Screenshot_Meta_Service::get_elementor_url( $target_id );
		$gutenberg_shot        = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_url( $target_id );
		$elementor_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_url( $target_id );
		$gutenberg_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_url( $target_id );
		if ( '' === $elementor_shot && isset( $existing_workspace['elementor_screenshot'] ) ) {
			$elementor_shot = (string) $existing_workspace['elementor_screenshot'];
		}
		if ( '' === $gutenberg_shot && isset( $existing_workspace['gutenberg_screenshot'] ) ) {
			$gutenberg_shot = (string) $existing_workspace['gutenberg_screenshot'];
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
			'target_post_id'                 => $target_id,
			'source_post_id'                 => $source_id,
			'prepared_prompt'                => $prompt,
			'elementor_json_snapshot'        => $elementor_json,
			'gutenberg_snapshot'             => $gutenberg_content,
			'elementor_screenshot'           => $elementor_shot,
			'gutenberg_screenshot'           => $gutenberg_shot,
			'elementor_mobile_screenshot'    => $elementor_mobile_shot,
			'gutenberg_mobile_screenshot'    => $gutenberg_mobile_shot,
			'css_result_draft'               => isset( $existing_workspace['css_result_draft'] ) ? (string) $existing_workspace['css_result_draft'] : '',
			'gutenberg_result_draft'         => isset( $existing_workspace['gutenberg_result_draft'] ) ? (string) $existing_workspace['gutenberg_result_draft'] : '',
		);
		AI_Workspace_Repository::save( $target_id, $workspace_to_save );

		$notice_code = isset( $_GET['ele2gb_ai_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ele2gb_ai_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->render_notice( $notice_code, $target_id );
		$this->render_form( $target_post, $source_post, AI_Workspace_Repository::get( $target_id ) );
	}

	/**
	 * Handle automated AI improvement via Claude API.
	 */
	public function handle_auto_improve(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ) );
		}

		check_admin_referer( self::NONCE_AUTO_IMPROVE );

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'elementor-to-gutenberg' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'elementor-to-gutenberg' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_ele2gb_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		$result = self::run_improvement( $source_id, $target_id );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'ai_failed';
			if ( 'ai_failed' === $notice ) {
				set_transient( 'ele2gb_ai_error_' . $target_id, $result['error'], 60 );
			}
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
			return array( 'success' => false, 'error' => $error, 'notice' => $notice );
		};

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

		$elementor_shot        = AI_Remediation_Screenshot_Meta_Service::get_elementor_url( $target_id );
		$gutenberg_shot        = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_url( $target_id );
		$elementor_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_url( $target_id );
		$gutenberg_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_url( $target_id );

		$api_result = Claude_Api_Service::send(
			$prompt,
			$elementor_shot,
			$gutenberg_shot,
			'',
			$elementor_mobile_shot,
			$gutenberg_mobile_shot
		);

		if ( ! $api_result['success'] ) {
			self::log_improvement( array(
				'step'      => 'api_failed',
				'target_id' => $target_id,
				'error'     => $api_result['error'],
			) );
			return $failure( $api_result['error'], 'ai_failed' );
		}

		$parsed           = Claude_Api_Service::parse_response( $api_result['content'] );
		$css_result       = self::fix_css_namespace( $parsed['css'], $source_id, $target_id );
		$gutenberg_result = $parsed['gutenberg'];

		self::log_improvement( array(
			'step'              => 'parse_complete',
			'target_id'         => $target_id,
			'css_length'        => strlen( $css_result ),
			'gutenberg_length'  => strlen( $gutenberg_result ),
			'gutenberg_preview' => substr( $gutenberg_result, 0, 120 ),
		) );

		if ( '' === trim( $gutenberg_result ) ) {
			self::log_improvement( array(
				'step'      => 'parse_failed_empty_gutenberg',
				'target_id' => $target_id,
			) );
			return $failure(
				__( 'No valid Gutenberg content could be parsed from the AI response.', 'elementor-to-gutenberg' ),
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
			self::log_improvement( array(
				'step'      => 'wp_update_post_failed',
				'target_id' => $target_id,
				'error'     => $update_result->get_error_message(),
			) );
			return $failure( $update_result->get_error_message(), 'update_failed' );
		}

		self::log_improvement( array(
			'step'          => 'wp_update_post_success',
			'target_id'     => $target_id,
			'returned_id'   => $update_result,
		) );

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

		update_post_meta( $target_id, '_ele2gb_last_ai_improved', current_time( 'mysql' ) );

		return array( 'success' => true, 'error' => '', 'notice' => 'updated' );
	}

	/**
	 * Append a diagnostic log entry to the same log file used by Claude_Api_Service.
	 *
	 * @param array $data Associative array of fields to log.
	 */
	private static function log_improvement( array $data ): void {
		$log_file = WP_CONTENT_DIR . '/ele2gb-claude-api.log';

		$entry = array_merge(
			array( 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'source' => 'run_improvement' ),
			$data
		);

		$line = json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false !== $line ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, $line . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Handle the "Refine with AI" action (Round 2+).
	 *
	 * Takes fresh screenshots, then sends the current page state plus the user's
	 * focus instruction to Claude for a targeted refinement pass.
	 */
	public function handle_refine(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ) );
		}

		check_admin_referer( self::NONCE_REFINE );

		$target_id         = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id         = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$focus_instruction = isset( $_POST['focus_instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['focus_instruction'] ) ) : '';

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'elementor-to-gutenberg' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'elementor-to-gutenberg' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_ele2gb_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		// Take fresh screenshots right before sending to Claude.
		AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );

		$result = self::run_refinement( $source_id, $target_id, $focus_instruction );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'ai_failed';
			if ( 'ai_failed' === $notice ) {
				set_transient( 'ele2gb_ai_error_' . $target_id, $result['error'], 60 );
			}
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
			return array( 'success' => false, 'error' => $error, 'notice' => $notice );
		};

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

		$elementor_shot        = AI_Remediation_Screenshot_Meta_Service::get_elementor_url( $target_id );
		$gutenberg_shot        = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_url( $target_id );
		$elementor_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_url( $target_id );
		$gutenberg_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_url( $target_id );

		$api_result = Claude_Api_Service::send(
			$prompt,
			$elementor_shot,
			$gutenberg_shot,
			Claude_Api_Service::get_refinement_system_prompt(),
			$elementor_mobile_shot,
			$gutenberg_mobile_shot
		);

		if ( ! $api_result['success'] ) {
			self::log_improvement( array(
				'step'      => 'refine_api_failed',
				'target_id' => $target_id,
				'error'     => $api_result['error'],
			) );
			return $failure( $api_result['error'], 'ai_failed' );
		}

		$parsed           = Claude_Api_Service::parse_response( $api_result['content'] );
		$css_result       = self::fix_css_namespace( $parsed['css'], $source_id, $target_id );
		$gutenberg_result = $parsed['gutenberg'];

		self::log_improvement( array(
			'step'              => 'refine_parse_complete',
			'target_id'         => $target_id,
			'css_length'        => strlen( $css_result ),
			'gutenberg_length'  => strlen( $gutenberg_result ),
			'gutenberg_preview' => substr( $gutenberg_result, 0, 120 ),
		) );

		if ( '' === trim( $gutenberg_result ) ) {
			self::log_improvement( array(
				'step'      => 'refine_parse_failed_empty_gutenberg',
				'target_id' => $target_id,
			) );
			return $failure(
				__( 'No valid Gutenberg content could be parsed from the AI refinement response.', 'elementor-to-gutenberg' ),
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
			self::log_improvement( array(
				'step'      => 'refine_wp_update_post_failed',
				'target_id' => $target_id,
				'error'     => $update_result->get_error_message(),
			) );
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

		update_post_meta( $target_id, '_ele2gb_last_ai_improved', current_time( 'mysql' ) );

		return array( 'success' => true, 'error' => '', 'notice' => 'refined' );
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ) );
		}

		check_admin_referer( self::NONCE_MOBILE_IMPROVE );

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'elementor-to-gutenberg' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'elementor-to-gutenberg' ) );
		}

		$stored_source_id = (int) get_post_meta( $target_id, '_ele2gb_source_id', true );
		if ( $stored_source_id > 0 && $stored_source_id !== $source_id ) {
			$this->redirect_with_notice( $source_id, $target_id, 'invalid_mapping' );
		}

		// Take fresh screenshots so the mobile pass works against the current rendering.
		AI_Remediation_Screenshot_Meta_Service::generate_and_store( $source_id, $target_id, true );

		$result = self::run_mobile_improvement( $source_id, $target_id );

		if ( ! $result['success'] ) {
			$notice = $result['notice'] ?? 'mobile_failed';
			if ( 'mobile_failed' === $notice ) {
				set_transient( 'ele2gb_ai_error_' . $target_id, $result['error'], 60 );
			}
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
			return array( 'success' => false, 'error' => $error, 'notice' => $notice );
		};

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

		// Mobile pass: send only mobile screenshots, no desktop screenshots.
		$elementor_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_mobile_url( $target_id );
		$gutenberg_mobile_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_mobile_url( $target_id );

		$api_result = Claude_Api_Service::send(
			$prompt,
			'',
			'',
			Claude_Api_Service::get_mobile_improvement_system_prompt(),
			$elementor_mobile_shot,
			$gutenberg_mobile_shot
		);

		if ( ! $api_result['success'] ) {
			self::log_improvement( array(
				'step'      => 'mobile_api_failed',
				'target_id' => $target_id,
				'error'     => $api_result['error'],
			) );
			return $failure( $api_result['error'], 'mobile_failed' );
		}

		$mobile_css = self::fix_css_namespace(
			Claude_Api_Service::parse_css_only_response( $api_result['content'] ),
			$source_id,
			$target_id
		);

		self::log_improvement( array(
			'step'        => 'mobile_parse_complete',
			'target_id'   => $target_id,
			'css_length'  => strlen( $mobile_css ),
			'css_preview' => substr( $mobile_css, 0, 200 ),
		) );

		$merged_css = self::merge_mobile_css( $current_css, $mobile_css );

		if ( '' !== trim( $merged_css ) ) {
			External_CSS_Service::save_post_css( $target_id, $merged_css );
		}

		if ( 'elementor_library' === get_post_type( $source_id ) ) {
			External_CSS_Service::register_global_css_post( $target_id );
		}

		update_post_meta( $target_id, '_ele2gb_last_ai_mobile_improved', current_time( 'mysql' ) );

		return array( 'success' => true, 'error' => '', 'notice' => 'mobile_improved' );
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
	 * The Gutenberg page HTML wrapper always uses etg-page-{source_id} as its class,
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
			'etg-page-' . $target_id,
			'etg-page-' . $source_id,
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ) );
		}

		$target_id = isset( $_POST['target_id'] ) ? absint( wp_unslash( $_POST['target_id'] ) ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;

		check_admin_referer( 'ele2gb_ai_regenerate_screenshots_' . $target_id );

		if ( $target_id <= 0 || $source_id <= 0 ) {
			wp_die( esc_html__( 'Source or target page is missing.', 'elementor-to-gutenberg' ) );
		}

		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page.', 'elementor-to-gutenberg' ) );
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
				'page'             => self::MENU_SLUG,
				'source_id'        => $source_id,
				'target_id'        => $target_id,
				'ele2gb_ai_notice' => $notice_code,
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

		if ( 'ai_failed' === $notice_code || 'mobile_failed' === $notice_code ) {
			$ai_error = '';
			if ( $target_id > 0 ) {
				$ai_error = (string) get_transient( 'ele2gb_ai_error_' . $target_id );
				delete_transient( 'ele2gb_ai_error_' . $target_id );
			}
			$prefix = 'mobile_failed' === $notice_code
				? esc_html__( 'Mobile improvement failed', 'elementor-to-gutenberg' )
				: esc_html__( 'Claude API call failed', 'elementor-to-gutenberg' );
			$msg = '' !== $ai_error
				/* translators: 1: failure prefix, 2: error message returned by Claude API */
				? sprintf( esc_html__( '%1$s: %2$s', 'elementor-to-gutenberg' ), $prefix, esc_html( $ai_error ) )
				: $prefix . '.';
			?>
			<div class="notice notice-error is-dismissible"><p><?php echo $msg; // Already escaped above. ?></p></div>
			<?php
			return;
		}

		$messages = array(
			'updated'                 => array( 'success', esc_html__( 'Page updated and AI CSS appended successfully.', 'elementor-to-gutenberg' ) ),
			'missing_gutenberg'       => array( 'error', esc_html__( 'Gutenberg result is required before updating.', 'elementor-to-gutenberg' ) ),
			'css_append_failed'       => array( 'error', esc_html__( 'Could not append CSS because the external CSS file for this page could not be resolved.', 'elementor-to-gutenberg' ) ),
			'update_failed'           => array( 'error', esc_html__( 'Failed to update Gutenberg page content.', 'elementor-to-gutenberg' ) ),
			'invalid_mapping'         => array( 'error', esc_html__( 'Source and target mapping validation failed.', 'elementor-to-gutenberg' ) ),
			'screenshots_regenerated' => array( 'success', esc_html__( 'Screenshots regenerated successfully.', 'elementor-to-gutenberg' ) ),
			'screenshots_failed'      => array( 'error', esc_html__( 'Screenshot regeneration failed. Check the screenshot service settings and connectivity.', 'elementor-to-gutenberg' ) ),
			'ai_parse_failed'         => array( 'error', esc_html__( 'Claude returned a response but no valid Gutenberg content could be parsed.', 'elementor-to-gutenberg' ) ),
			'refined'                 => array( 'success', esc_html__( 'Page refined successfully. Fresh screenshots were captured before this run.', 'elementor-to-gutenberg' ) ),
			'mobile_improved'         => array( 'success', esc_html__( 'Mobile CSS improved successfully. Desktop styles were not modified.', 'elementor-to-gutenberg' ) ),
			'mobile_failed'           => array( 'error', esc_html__( 'Mobile improvement failed. Check the screenshot service and Claude API settings.', 'elementor-to-gutenberg' ) ),
		);

		if ( ! isset( $messages[ $notice_code ] ) ) {
			return;
		}

		$notice_type = $messages[ $notice_code ][0];
		$message     = $messages[ $notice_code ][1];
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo $message; // Already escaped via esc_html__. ?></p></div>
		<?php
	}

	/**
	 * Render workflow page.
	 *
	 * The Screenshots section is rendered as a separate, self-contained form so
	 * it can contain its own submit button without nesting HTML forms. The
	 * "Improve with AI" form follows immediately after as a separate form.
	 */
	private function render_form( WP_Post $target_post, WP_Post $source_post, array $workspace ): void {
		$target_id    = (int) $target_post->ID;
		$source_id    = (int) $source_post->ID;
		$target_title = get_the_title( $target_id );
		$source_title = get_the_title( $source_id );

		$elementor_shot        = isset( $workspace['elementor_screenshot'] ) ? (string) $workspace['elementor_screenshot'] : '';
		$gutenberg_shot        = isset( $workspace['gutenberg_screenshot'] ) ? (string) $workspace['gutenberg_screenshot'] : '';
		$elementor_mobile_shot = isset( $workspace['elementor_mobile_screenshot'] ) ? (string) $workspace['elementor_mobile_screenshot'] : '';
		$gutenberg_mobile_shot = isset( $workspace['gutenberg_mobile_screenshot'] ) ? (string) $workspace['gutenberg_mobile_screenshot'] : '';

		$screenshot_status       = AI_Remediation_Screenshot_Meta_Service::get_status( $target_id );
		$screenshot_generated_at = (string) get_post_meta( $target_id, AI_Remediation_Screenshot_Meta_Service::META_GENERATED_AT, true );
		$service_configured      = '' !== AI_Remediation_Screenshot_Api_Service::get_endpoint_url();

		$status_labels = array(
			AI_Remediation_Screenshot_Meta_Service::STATUS_SUCCESS       => esc_html__( 'Generated', 'elementor-to-gutenberg' ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_FAILED        => esc_html__( 'Generation failed', 'elementor-to-gutenberg' ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_PENDING       => esc_html__( 'Pending', 'elementor-to-gutenberg' ),
			AI_Remediation_Screenshot_Meta_Service::STATUS_NOT_GENERATED => esc_html__( 'Not yet generated', 'elementor-to-gutenberg' ),
		);
		$status_label = isset( $status_labels[ $screenshot_status ] ) ? $status_labels[ $screenshot_status ] : esc_html( $screenshot_status );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Improve Page with AI', 'elementor-to-gutenberg' ); ?></h1>

			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Target Gutenberg Page ID', 'elementor-to-gutenberg' ); ?></th>
					<td><?php echo esc_html( (string) $target_id ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Source Elementor Page ID', 'elementor-to-gutenberg' ); ?></th>
					<td><?php echo esc_html( (string) $source_id ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Target Gutenberg Title', 'elementor-to-gutenberg' ); ?></th>
					<td><?php echo esc_html( $target_title ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Source Elementor Title', 'elementor-to-gutenberg' ); ?></th>
					<td><?php echo esc_html( $source_title ); ?></td>
				</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Screenshots', 'elementor-to-gutenberg' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Elementor Screenshot (Desktop)', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $elementor_shot ) : ?>
							<p><img src="<?php echo esc_url( $elementor_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Elementor desktop screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Gutenberg Screenshot (Desktop)', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $gutenberg_shot ) : ?>
							<p><img src="<?php echo esc_url( $gutenberg_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Gutenberg desktop screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Elementor Screenshot (Mobile)', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $elementor_mobile_shot ) : ?>
							<p><img src="<?php echo esc_url( $elementor_mobile_shot ); ?>" alt="" style="max-width:240px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Elementor mobile screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Gutenberg Screenshot (Mobile)', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $gutenberg_mobile_shot ) : ?>
							<p><img src="<?php echo esc_url( $gutenberg_mobile_shot ); ?>" alt="" style="max-width:240px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Gutenberg mobile screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Screenshot Status', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php
						echo $status_label; // Already escaped via esc_html__ or esc_html above.
						if ( '' !== $screenshot_generated_at ) {
							echo ' &mdash; ' . esc_html( $screenshot_generated_at );
						}
						if ( ! $service_configured ) {
							echo ' <span style="color:#b32d2e;">(' . esc_html__( 'Screenshot service not configured. See plugin settings.', 'elementor-to-gutenberg' ) . ')</span>';
						}
						?>
					</td>
				</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1.5em;">
				<?php wp_nonce_field( 'ele2gb_ai_regenerate_screenshots_' . $target_id ); ?>
				<input type="hidden" name="action" value="ele2gb_ai_regenerate_screenshots" />
				<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
				<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
				<?php submit_button( esc_html__( 'Regenerate Screenshots', 'elementor-to-gutenberg' ), 'secondary', 'ele2gb_regenerate_screenshots_submit', false ); ?>
			</form>

			<?php
			$last_improved = (string) get_post_meta( $target_id, '_ele2gb_last_ai_improved', true );
			if ( '' === $last_improved ) :
				?>
				<h2><?php echo esc_html__( 'AI Improvement', 'elementor-to-gutenberg' ); ?></h2>
				<p><?php echo esc_html__( 'Analyse and improve the converted page using AI. The page content and CSS will be updated automatically.', 'elementor-to-gutenberg' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ele2gb-ai-improve-form">
					<?php wp_nonce_field( self::NONCE_AUTO_IMPROVE ); ?>
					<input type="hidden" name="action" value="ele2gb_ai_auto_improve" />
					<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
					<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
					<?php submit_button( esc_html__( 'Improve with AI', 'elementor-to-gutenberg' ), 'primary', 'ele2gb_auto_improve_submit', false ); ?>
				</form>
			<?php else :
				// ── Round 2+: page has been improved at least once ────────────
				$suggestions = array(
					__( 'Fix hero section spacing and alignment', 'elementor-to-gutenberg' ),
					__( 'Match typography — font sizes and weights', 'elementor-to-gutenberg' ),
					__( 'Improve button styles and colors', 'elementor-to-gutenberg' ),
					__( 'Fix colors and contrast', 'elementor-to-gutenberg' ),
					__( 'Fix image sizing and alignment', 'elementor-to-gutenberg' ),
					__( 'Fix section padding and spacing', 'elementor-to-gutenberg' ),
					__( 'Improve heading styles', 'elementor-to-gutenberg' ),
					__( 'Fix navigation menu styling', 'elementor-to-gutenberg' ),
				);
				?>
				<h2><?php echo esc_html__( 'Refine with AI', 'elementor-to-gutenberg' ); ?></h2>
				<p><?php echo esc_html__( 'The page has been improved. Tell the AI exactly what to focus on next. Fresh screenshots will be captured automatically before this run.', 'elementor-to-gutenberg' ); ?></p>

				<div class="ele2gb-refine-suggestions">
					<span class="ele2gb-refine-suggestions-label"><?php echo esc_html__( 'Quick suggestions:', 'elementor-to-gutenberg' ); ?></span>
					<?php foreach ( $suggestions as $suggestion ) : ?>
						<button type="button" class="ele2gb-suggestion-chip" data-suggestion="<?php echo esc_attr( $suggestion ); ?>">
							<?php echo esc_html( $suggestion ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ele2gb-ai-refine-form">
					<?php wp_nonce_field( self::NONCE_REFINE ); ?>
					<input type="hidden" name="action" value="ele2gb_ai_refine" />
					<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
					<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
					<textarea
						name="focus_instruction"
						id="ele2gb-focus-instruction"
						rows="4"
						placeholder="<?php echo esc_attr__( 'Describe what needs fixing, e.g. "The hero section spacing is too tight and the heading font is too small"', 'elementor-to-gutenberg' ); ?>"
						style="width:100%;max-width:800px;margin-bottom:1em;font-size:14px;"
					></textarea>
					<br />
					<?php submit_button( esc_html__( 'Refine with AI', 'elementor-to-gutenberg' ), 'primary', 'ele2gb_refine_submit', false ); ?>
				</form>
			<?php endif; ?>

			<?php
			$last_mobile_improved = (string) get_post_meta( $target_id, '_ele2gb_last_ai_mobile_improved', true );
			$has_mobile_shots     = '' !== $elementor_mobile_shot && '' !== $gutenberg_mobile_shot;
			?>
			<hr style="margin:2em 0;" />
			<h2><?php echo esc_html__( 'Improve Mobile with AI', 'elementor-to-gutenberg' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Run a separate AI pass that compares the MOBILE screenshots and produces mobile-only @media query CSS. Desktop styles and Gutenberg block content are NOT changed.', 'elementor-to-gutenberg' ); ?>
			</p>
			<?php if ( '' !== $last_mobile_improved ) : ?>
				<p class="description">
					<?php
					/* translators: %s: MySQL datetime of last mobile improvement run */
					printf( esc_html__( 'Last mobile improvement: %s', 'elementor-to-gutenberg' ), esc_html( $last_mobile_improved ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! $has_mobile_shots ) : ?>
				<p class="description" style="color:#b32d2e;">
					<?php echo esc_html__( 'Mobile screenshots are missing. Click "Regenerate Screenshots" above before running this pass.', 'elementor-to-gutenberg' ); ?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ele2gb-ai-mobile-improve-form">
				<?php wp_nonce_field( self::NONCE_MOBILE_IMPROVE ); ?>
				<input type="hidden" name="action" value="ele2gb_ai_mobile_improve" />
				<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
				<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
				<?php submit_button( esc_html__( 'Improve Mobile with AI', 'elementor-to-gutenberg' ), 'secondary', 'ele2gb_mobile_improve_submit', false ); ?>
			</form>

			<div id="ele2gb-ai-loader" hidden>
				<div class="ele2gb-ai-loader-card">
					<svg class="ele2gb-ai-loader-spinner" viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<circle class="track" cx="22" cy="22" r="20" fill="none" stroke="#2271b1" stroke-width="3" />
						<circle class="arc"   cx="22" cy="22" r="20" fill="none" stroke="#2271b1" stroke-width="3" />
					</svg>
					<div>
						<strong class="ele2gb-ai-loader-title"><?php echo esc_html__( 'Improving with AI…', 'elementor-to-gutenberg' ); ?></strong>
						<span class="ele2gb-ai-loader-message"><?php echo esc_html__( 'Analysing page structure and generating improvements. This may take up to 2 minutes.', 'elementor-to-gutenberg' ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
