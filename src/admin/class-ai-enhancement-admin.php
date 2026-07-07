<?php
/**
 * AI Enhancement admin page.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use Progressus\BlockShift\Admin\Helper\Claude_Api_Service;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function get_post_meta;
use function get_the_title;
use function in_array;
use function sanitize_key;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_unslash;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Enhancement_Admin
 */
class AI_Enhancement_Admin {

	public const MENU_SLUG = 'blockshift-ai-enhancement';

	/**
	 * @var AI_Enhancement_Admin|null
	 */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public const FEEDBACK_NONCE = 'blockshift_ai_enhancement_feedback_nonce';

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_blockshift_submit_ai_enhancement_feedback', array( $this, 'ajax_submit_ai_enhancement_feedback' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'blockshift-settings',
			esc_html__( 'AI Enhancement', 'layoutbridge-block-migration' ),
			esc_html__( 'AI Enhancement', 'layoutbridge-block-migration' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Asset cache-busting version: file mtime while debugging, plugin version otherwise.
	 *
	 * @param string $rel Plugin-relative asset path.
	 */
	private static function asset_ver( string $rel ): string {
		$path = BLOCKSHIFT_DIR_PATH . '/' . ltrim( $rel, '/' );
		if ( defined( 'BLOCKSHIFT_DEBUG' ) && BLOCKSHIFT_DEBUG && file_exists( $path ) ) {
			return (string) filemtime( $path );
		}
		return BLOCKSHIFT_VERSION;
	}

	public function enqueue_assets(): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'blockshift-batch-wizard',
			BLOCKSHIFT_CSS_DIR_URL . '/batch-wizard.css',
			array(),
			self::asset_ver( 'assets/css/batch-wizard.css' )
		);

		wp_enqueue_script(
			'blockshift-pgs-icons',
			BLOCKSHIFT_JS_DIR_URL . '/pgs-icons.js',
			array(),
			self::asset_ver( 'assets/js/pgs-icons.js' ),
			true
		);

		wp_enqueue_script(
			'blockshift-ai-enhancement',
			BLOCKSHIFT_JS_DIR_URL . '/ai-enhancement.js',
			array( 'blockshift-pgs-icons' ),
			self::asset_ver( 'assets/js/ai-enhancement.js' ),
			true
		);

		wp_localize_script(
			'blockshift-ai-enhancement',
			'blockshiftAiEnhancement',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'aiImproveNonce'   => wp_create_nonce( 'blockshift_ai_improve' ),
				'feedbackNonce'    => wp_create_nonce( self::FEEDBACK_NONCE ),
				'feedbackEnabled'  => true,
				'aiConfigured'     => '' !== Claude_Api_Service::get_api_key(),
				'settingsUrl'      => admin_url( 'admin.php?page=blockshift-settings' ),
				'editBaseUrl'      => admin_url( 'post.php?post=' ),
				'aiImproveBaseUrl' => admin_url( 'admin.php?page=' . AI_Improvement_Admin::MENU_SLUG ),
				'pages'            => $this->get_converted_pages_data(),
				'strings'          => $this->get_strings(),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'layoutbridge-block-migration' ) );
		}

		$has_pages = ! empty( $this->get_converted_pages() );
		?>
		<div class="wrap pgs blockshift-wizard-wrap">
			<header class="pgs-pluginhead">
				<span class="pgs-pluginhead__brand"><span class="pgs-pluginhead__name"><?php esc_html_e( 'BlockShift – Migrate from Elementor', 'layoutbridge-block-migration' ); ?></span></span>
			</header>
			<hr class="wp-header-end" style="margin:0;border:0;">
			<?php if ( ! $has_pages ) : ?>
				<div class="pgs-col">
					<div class="pgs-pagetitle">
						<div>
							<h1><?php esc_html_e( 'AI Enhancement', 'layoutbridge-block-migration' ); ?></h1>
							<p><?php esc_html_e( 'Refine converted pages until they visually match the original.', 'layoutbridge-block-migration' ); ?></p>
						</div>
					</div>
					<div class="pgs-banner pgs-banner--neutral" role="status">
						<span class="pgs-banner__icon"><i data-icon="info"></i></span>
						<div class="pgs-banner__body"><span class="pgs-banner__text"><?php esc_html_e( 'No converted pages found. Use the Conversion Wizard to convert Elementor pages first.', 'layoutbridge-block-migration' ); ?></span></div>
					</div>
				</div>
			<?php else : ?>
				<div id="blockshift-ai-enhancement-app"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_converted_pages_data(): array {
		$data = array();
		foreach ( $this->get_converted_pages() as $page ) {
			$source_id = (int) get_post_meta( $page->ID, '_blockshift_source_id', true );

			if ( 'wp_template_part' === $page->post_type ) {
				$kind = (string) get_post_meta( $page->ID, '_blockshift_template_kind', true );
				$type = in_array( $kind, array( 'header', 'footer' ), true ) ? $kind : 'template';
			} else {
				$type = 'page';
			}

			$data[] = array(
				'id'           => $page->ID,
				'title'        => $page->post_title,
				'sourceId'     => $source_id,
				'sourceTitle'  => $source_id > 0 ? (string) get_the_title( $source_id ) : '',
				'type'         => $type,
				'lastImproved' => (string) get_post_meta( $page->ID, '_blockshift_last_ai_improved', true ),
			);
		}

		return $data;
	}

	private function get_converted_pages(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'page', 'wp_template_part' ),
				'post_status'    => 'publish',
				'posts_per_page' => 50, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'         => array(
				array(
					'key'     => '_blockshift_source_id',
					'compare' => 'EXISTS',
				),
			),
			'orderby'            => 'modified',
			'order'              => 'DESC',
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}

	private function get_strings(): array {
		return array(
			'colPage'                  => __( 'Converted Page', 'layoutbridge-block-migration' ),
			'colSource'                => __( 'Source Page', 'layoutbridge-block-migration' ),
			'colActions'               => __( 'Actions', 'layoutbridge-block-migration' ),
			'enhanceSingle'            => __( 'Enhance with AI', 'layoutbridge-block-migration' ),
			'enhanceSelected'          => __( 'Bulk Enhance with AI', 'layoutbridge-block-migration' ),
			/* translators: %1$d: number of selected items */
			'enhanceSelectedCount'     => __( 'Enhance %1$d items with AI', 'layoutbridge-block-migration' ),
			'noApiMessage'             => __( 'To enhance pages with AI, you need to enter your Claude API key.', 'layoutbridge-block-migration' ),
			'addApiLink'               => __( 'Add your API key in Settings', 'layoutbridge-block-migration' ),
			'back'                     => __( 'Back', 'layoutbridge-block-migration' ),
			'backToList'               => __( 'Back to list', 'layoutbridge-block-migration' ),
			'skip'                     => __( 'Skip', 'layoutbridge-block-migration' ),
			'retry'                    => __( 'Retry', 'layoutbridge-block-migration' ),
			'aiReadinessTitle'         => __( 'Pre-flight checklist', 'layoutbridge-block-migration' ),
			'aiReadinessApiValid'      => __( 'API key configured', 'layoutbridge-block-migration' ),
			'aiReadinessApiInvalid'    => __( 'API key not configured', 'layoutbridge-block-migration' ),
			/* translators: %1$d: estimated number of API calls */
			'aiReadinessCredits'       => __( 'Estimated: ~%1$d API call(s), ~1–2 minutes per item', 'layoutbridge-block-migration' ),
			'aiImproveWarningTitle'    => __( 'AI credits will be used', 'layoutbridge-block-migration' ),
			'aiImproveWarning'         => __( 'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.', 'layoutbridge-block-migration' ),
			'aiImproveStart'           => __( 'Start AI Enhancement', 'layoutbridge-block-migration' ),
			'aiImproveError'           => __( 'An unexpected error occurred.', 'layoutbridge-block-migration' ),
			'aiImproveType'            => __( 'Type', 'layoutbridge-block-migration' ),
			'aiImprovePaused'          => __( 'Paused — an item failed. Review the error below, then skip or retry to continue.', 'layoutbridge-block-migration' ),
			'aiImproveFinishedOk'      => __( 'All items improved successfully.', 'layoutbridge-block-migration' ),
			/* translators: 1: number of items done, 2: number failed, 3: number skipped */
			'aiImproveFinishedErr'     => __( 'Finished — %1$d done, %2$d failed, %3$d skipped.', 'layoutbridge-block-migration' ),
			'aiStatusPending'          => __( 'Pending', 'layoutbridge-block-migration' ),
			'aiStatusProcessing'       => __( 'Processing…', 'layoutbridge-block-migration' ),
			'aiStatusDone'             => __( 'Done', 'layoutbridge-block-migration' ),
			'aiStatusFailed'           => __( 'Failed', 'layoutbridge-block-migration' ),
			'aiStatusSkipped'          => __( 'Skipped', 'layoutbridge-block-migration' ),
			'aiLoaderTitle'            => __( 'Improving with AI…', 'layoutbridge-block-migration' ),
			'aiStageAnalyzing'         => __( 'Analyzing…', 'layoutbridge-block-migration' ),
			'aiStageGenerating'        => __( 'Generating…', 'layoutbridge-block-migration' ),
			'aiStageSaving'            => __( 'Saving…', 'layoutbridge-block-migration' ),
			'statTotalPages'           => __( 'Converted Items', 'layoutbridge-block-migration' ),
			'statAiEnhanced'           => __( 'AI-Enhanced', 'layoutbridge-block-migration' ),

			// Feedback strings
			'feedbackBtn'              => __( 'Send Feedback', 'layoutbridge-block-migration' ),
			'feedbackModalTitle'       => __( 'How did AI Enhancement go?', 'layoutbridge-block-migration' ),
			'feedbackIssueLabel'       => __( 'Issue type', 'layoutbridge-block-migration' ),
			'feedbackIssueDetailLabel' => __( 'Describe the issue', 'layoutbridge-block-migration' ),
			'feedbackNoteLabel'        => __( 'Additional notes', 'layoutbridge-block-migration' ),
			'feedbackConsentLabel'     => __( 'I consent to sending this anonymised AI enhancement report to the plugin developer for quality improvement. No passwords, API keys, or user data are included.', 'layoutbridge-block-migration' ),
			'feedbackSubmit'           => __( 'Send Feedback', 'layoutbridge-block-migration' ),
			'feedbackCancel'           => __( 'Cancel', 'layoutbridge-block-migration' ),
			'feedbackSending'          => __( 'Sending…', 'layoutbridge-block-migration' ),
			/* translators: %1$s: feedback submission ID */
			'feedbackSuccess'          => __( 'Thank you! Feedback submitted (ID: %1$s).', 'layoutbridge-block-migration' ),
			/* translators: %s: error message describing why feedback could not be sent */
			'feedbackError'            => __( 'Could not send feedback: %s', 'layoutbridge-block-migration' ),
			'feedbackNoIssue'          => __( 'No issue', 'layoutbridge-block-migration' ),
			'feedbackIssueLayout'      => __( 'Layout issues after AI', 'layoutbridge-block-migration' ),
			'feedbackIssueMissing'     => __( 'Wrong or missing content', 'layoutbridge-block-migration' ),
			'feedbackIssueCss'         => __( 'CSS / styling problems', 'layoutbridge-block-migration' ),
			'feedbackIssueQuality'     => __( 'AI output quality', 'layoutbridge-block-migration' ),
			'feedbackIssueOther'       => __( 'Other', 'layoutbridge-block-migration' ),
		);
	}

	/**
	 * AJAX handler: submit AI enhancement feedback for a single page.
	 */
	public function ajax_submit_ai_enhancement_feedback(): void {
		check_ajax_referer( self::FEEDBACK_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Unauthorized.', 'layoutbridge-block-migration' ) ) );
			return;
		}

		$consent_raw = isset( $_POST['consent_given'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_given'] ) ) : '';
		if ( 'true' !== $consent_raw ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Consent is required to submit feedback.', 'layoutbridge-block-migration' ) ) );
			return;
		}

		$target_id    = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$source_id    = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$issue_type   = isset( $_POST['issue_type'] ) ? sanitize_key( wp_unslash( $_POST['issue_type'] ) ) : '';
		$issue_detail = isset( $_POST['issue_detail'] ) ? sanitize_textarea_field( wp_unslash( $_POST['issue_detail'] ) ) : '';
		$issue_detail = substr( $issue_detail, 0, 500 );
		$user_note    = isset( $_POST['user_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['user_note'] ) ) : '';
		$user_note    = substr( $user_note, 0, 2000 );

		if ( $target_id <= 0 ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Invalid page ID.', 'layoutbridge-block-migration' ) ) );
			return;
		}

		$target_post = get_post( $target_id );
		if ( ! $target_post instanceof \WP_Post ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Page not found.', 'layoutbridge-block-migration' ) ) );
			return;
		}

		$theme = wp_get_theme();

		$manifest = array(
			'schema_version' => '1.0.0',
			'feedback_type'  => 'ai_enhancement',
			'feedback_id'    => 'aefbk_' . gmdate( 'YmdHis' ) . '_' . substr( md5( uniqid( '', true ) ), 0, 8 ),
			'submitted_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),

			'site'           => array(
				'site_url_hash'               => hash( 'sha256', (string) home_url() ),
				'site_domain'                 => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'plugin_version'              => BLOCKSHIFT_VERSION,
				'wordpress_version'           => get_bloginfo( 'version' ),
				'php_version'                 => PHP_VERSION,
				'active_theme'                => (string) $theme->get( 'Name' ),
				'active_theme_is_block_theme' => wp_is_block_theme(),
				'locale'                      => get_locale(),
			),

			'page'           => array(
				'target_id'     => $target_id,
				'source_id'     => $source_id > 0 ? $source_id : null,
				'title'         => $target_post->post_title,
				'last_improved' => (string) get_post_meta( $target_id, '_blockshift_last_ai_improved', true ),
				'screenshots'   => array(
					'elementor_desktop' => $this->get_first_screenshot( $target_id, '_blockshift_ai_elementor_screenshot_url' ),
					'gutenberg_desktop' => $this->get_first_screenshot( $target_id, '_blockshift_ai_gutenberg_screenshot_url' ),
					'elementor_mobile'  => $this->get_first_screenshot( $target_id, '_blockshift_ai_elementor_screenshot_mobile_url' ),
					'gutenberg_mobile'  => $this->get_first_screenshot( $target_id, '_blockshift_ai_gutenberg_screenshot_mobile_url' ),
				),
			),

			'user_feedback'  => array(
				'issue_type'      => $issue_type,
				'issue_detail'    => $issue_detail,
				'user_note'       => $user_note,
				'consent_given'   => true,
				'consent_version' => Feedback_Builder::CONSENT_VERSION,
				'consent_text'    => Feedback_Builder::CONSENT_TEXT,
			),
		);

		$result = Feedback_Sender::send( $manifest );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'error' => $result->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'feedback_id' => $manifest['feedback_id'] ) );
	}

	private function get_first_screenshot( int $post_id, string $meta_key ): ?string {
		$raw     = get_post_meta( $post_id, $meta_key, true );
		$decoded = json_decode( (string) $raw, true );
		if ( is_array( $decoded ) && ! empty( $decoded[0] ) ) {
			return (string) $decoded[0];
		}
		return is_string( $raw ) && '' !== $raw ? $raw : null;
	}
}
