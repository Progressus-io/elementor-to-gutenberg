<?php
/**
 * AI Enhancement admin page.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Helper\Claude_Api_Service;

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

	public const MENU_SLUG = 'ele2gb-ai-enhancement';

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

	public const FEEDBACK_NONCE = 'etg_ai_enhancement_feedback_nonce';

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_etg_submit_ai_enhancement_feedback', array( $this, 'ajax_submit_ai_enhancement_feedback' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'AI Enhancement', 'elementor-to-gutenberg' ),
			esc_html__( 'AI Enhancement', 'elementor-to-gutenberg' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets(): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'ele2gb-batch-wizard',
			GUTENBERG_PLUGIN_CSS_DIR_URL . '/batch-wizard.css',
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'etg-ai-enhancement',
			GUTENBERG_PLUGIN_JS_DIR_URL . '/ai-enhancement.js',
			array(),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'etg-ai-enhancement',
			'etgAiEnhancement',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'aiImproveNonce'   => wp_create_nonce( 'ele2gb_ai_improve' ),
				'feedbackNonce'    => wp_create_nonce( self::FEEDBACK_NONCE ),
				'feedbackEnabled'  => true,
				'aiConfigured'     => '' !== Claude_Api_Service::get_api_key(),
				'settingsUrl'      => admin_url( 'admin.php?page=gutenberg-settings' ),
				'editBaseUrl'      => admin_url( 'post.php?post=' ),
				'aiImproveBaseUrl' => admin_url( 'admin.php?page=' . AI_Improvement_Admin::MENU_SLUG ),
				'pages'            => $this->get_converted_pages_data(),
				'strings'          => $this->get_strings(),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
		}

		$has_pages = ! empty( $this->get_converted_pages() );
		?>
        <div class="wrap ele2gb-wizard-wrap">
            <h1><?php esc_html_e( 'AI Enhancement', 'elementor-to-gutenberg' ); ?></h1>
            <?php if ( ! $has_pages ) : ?>
                <p><?php esc_html_e( 'No converted pages found. Use the Conversion Wizard to convert Elementor pages first.', 'elementor-to-gutenberg' ); ?></p>
            <?php else : ?>
                <div id="etg-ai-enhancement-app"></div>
            <?php endif; ?>
        </div>
		<?php
	}

	private function get_converted_pages_data(): array {
		$data = array();
		foreach ( $this->get_converted_pages() as $page ) {
			$source_id = (int) get_post_meta( $page->ID, '_ele2gb_source_id', true );

			if ( 'wp_template_part' === $page->post_type ) {
				$kind = (string) get_post_meta( $page->ID, '_ele2gb_template_kind', true );
				$type = in_array( $kind, array( 'header', 'footer' ), true ) ? $kind : 'template';
			} else {
				$type = 'page';
			}

			$data[] = array(
				'id'          => $page->ID,
				'title'       => $page->post_title,
				'sourceId'    => $source_id,
				'sourceTitle' => $source_id > 0 ? (string) get_the_title( $source_id ) : '',
				'type'        => $type,
				'lastImproved' => (string) get_post_meta( $page->ID, '_ele2gb_last_ai_improved', true ),
			);
		}

		return $data;
	}

	private function get_converted_pages(): array {
		$query = new \WP_Query( array(
			'post_type'      => array( 'page', 'wp_template_part' ),
			'post_status'    => 'publish',
			'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_ele2gb_source_id',
					'compare' => 'EXISTS',
				),
			),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		return is_array( $query->posts ) ? $query->posts : array();
	}

	private function get_strings(): array {
		return array(
			'colPage'               => __( 'Converted Page', 'elementor-to-gutenberg' ),
			'colSource'             => __( 'Source Page', 'elementor-to-gutenberg' ),
			'colActions'            => __( 'Actions', 'elementor-to-gutenberg' ),
			'enhanceSingle'         => __( 'Enhance with AI', 'elementor-to-gutenberg' ),
			'enhanceSelected'       => __( 'Bulk Enhance with AI', 'elementor-to-gutenberg' ),
			/* translators: %1$d: number of selected items */
			'enhanceSelectedCount'  => __( 'Enhance %1$d items with AI', 'elementor-to-gutenberg' ),
			'noApiMessage'          => __( 'To enhance pages with AI, you need to enter your Claude API key.', 'elementor-to-gutenberg' ),
			'addApiLink'            => __( 'Add your API key in Settings', 'elementor-to-gutenberg' ),
			'back'                  => __( 'Back', 'elementor-to-gutenberg' ),
			'backToList'            => __( 'Back to list', 'elementor-to-gutenberg' ),
			'skip'                  => __( 'Skip', 'elementor-to-gutenberg' ),
			'retry'                 => __( 'Retry', 'elementor-to-gutenberg' ),
			'aiReadinessTitle'      => __( 'Pre-flight checklist', 'elementor-to-gutenberg' ),
			'aiReadinessApiValid'   => __( 'API key configured', 'elementor-to-gutenberg' ),
			'aiReadinessApiInvalid' => __( 'API key not configured', 'elementor-to-gutenberg' ),
			/* translators: %1$d: estimated number of API calls */
			'aiReadinessCredits'    => __( 'Estimated: ~%1$d API call(s), ~1–2 minutes per item', 'elementor-to-gutenberg' ),
			'aiImproveWarningTitle' => __( 'AI credits will be used', 'elementor-to-gutenberg' ),
			'aiImproveWarning'      => __( 'This will use AI credits once per selected item. Make sure your API key has sufficient credits before starting.', 'elementor-to-gutenberg' ),
			'aiImproveStart'        => __( 'Start AI Enhancement', 'elementor-to-gutenberg' ),
			'aiImproveError'        => __( 'An unexpected error occurred.', 'elementor-to-gutenberg' ),
			'aiImproveType'         => __( 'Type', 'elementor-to-gutenberg' ),
			'aiImprovePaused'       => __( 'Paused — an item failed. Review the error below, then skip or retry to continue.', 'elementor-to-gutenberg' ),
			'aiImproveFinishedOk'   => __( 'All items improved successfully.', 'elementor-to-gutenberg' ),
			/* translators: 1: number of items done, 2: number failed, 3: number skipped */
			'aiImproveFinishedErr'  => __( 'Finished — %1$d done, %2$d failed, %3$d skipped.', 'elementor-to-gutenberg' ),
			'aiStatusPending'       => __( 'Pending', 'elementor-to-gutenberg' ),
			'aiStatusProcessing'    => __( 'Processing…', 'elementor-to-gutenberg' ),
			'aiStatusDone'          => __( 'Done', 'elementor-to-gutenberg' ),
			'aiStatusFailed'        => __( 'Failed', 'elementor-to-gutenberg' ),
			'aiStatusSkipped'       => __( 'Skipped', 'elementor-to-gutenberg' ),
			'aiLoaderTitle'         => __( 'Improving with AI…', 'elementor-to-gutenberg' ),
			'aiStageAnalyzing'      => __( 'Analyzing…', 'elementor-to-gutenberg' ),
			'aiStageGenerating'     => __( 'Generating…', 'elementor-to-gutenberg' ),
			'aiStageSaving'         => __( 'Saving…', 'elementor-to-gutenberg' ),
			'statTotalPages'        => __( 'Converted Items', 'elementor-to-gutenberg' ),
			'statAiEnhanced'        => __( 'AI-Enhanced', 'elementor-to-gutenberg' ),

			// Feedback strings
			'feedbackBtn'              => __( 'Send Feedback', 'elementor-to-gutenberg' ),
			'feedbackModalTitle'       => __( 'How did AI Enhancement go?', 'elementor-to-gutenberg' ),
			'feedbackIssueLabel'       => __( 'Issue type', 'elementor-to-gutenberg' ),
			'feedbackIssueDetailLabel' => __( 'Describe the issue', 'elementor-to-gutenberg' ),
			'feedbackNoteLabel'        => __( 'Additional notes', 'elementor-to-gutenberg' ),
			'feedbackConsentLabel'     => __( 'I consent to sending this anonymised AI enhancement report to the plugin developer for quality improvement. No passwords, API keys, or user data are included.', 'elementor-to-gutenberg' ),
			'feedbackSubmit'           => __( 'Send Feedback', 'elementor-to-gutenberg' ),
			'feedbackCancel'           => __( 'Cancel', 'elementor-to-gutenberg' ),
			'feedbackSending'          => __( 'Sending…', 'elementor-to-gutenberg' ),
			/* translators: %1$s: feedback submission ID */
			'feedbackSuccess'          => __( 'Thank you! Feedback submitted (ID: %1$s).', 'elementor-to-gutenberg' ),
			/* translators: %s: error message describing why feedback could not be sent */
			'feedbackError'            => __( 'Could not send feedback: %s', 'elementor-to-gutenberg' ),
			'feedbackNoIssue'          => __( 'No issue', 'elementor-to-gutenberg' ),
			'feedbackIssueLayout'      => __( 'Layout issues after AI', 'elementor-to-gutenberg' ),
			'feedbackIssueMissing'     => __( 'Wrong or missing content', 'elementor-to-gutenberg' ),
			'feedbackIssueCss'         => __( 'CSS / styling problems', 'elementor-to-gutenberg' ),
			'feedbackIssueQuality'     => __( 'AI output quality', 'elementor-to-gutenberg' ),
			'feedbackIssueOther'       => __( 'Other', 'elementor-to-gutenberg' ),
		);
	}

	/**
	 * AJAX handler: submit AI enhancement feedback for a single page.
	 */
	public function ajax_submit_ai_enhancement_feedback(): void {
		check_ajax_referer( self::FEEDBACK_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Unauthorized.', 'elementor-to-gutenberg' ) ) );
			return;
		}

		$consent_raw = isset( $_POST['consent_given'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_given'] ) ) : '';
		if ( 'true' !== $consent_raw ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Consent is required to submit feedback.', 'elementor-to-gutenberg' ) ) );
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
			wp_send_json_error( array( 'error' => esc_html__( 'Invalid page ID.', 'elementor-to-gutenberg' ) ) );
			return;
		}

		$target_post = get_post( $target_id );
		if ( ! $target_post instanceof \WP_Post ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Page not found.', 'elementor-to-gutenberg' ) ) );
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
				'plugin_version'              => GUTENBERG_PLUGIN_VERSION,
				'wordpress_version'           => get_bloginfo( 'version' ),
				'php_version'                 => PHP_VERSION,
				'active_theme'                => (string) $theme->get( 'Name' ),
				'active_theme_is_block_theme' => wp_is_block_theme(),
				'locale'                      => get_locale(),
			),

			'page'           => array(
				'target_id'    => $target_id,
				'source_id'    => $source_id > 0 ? $source_id : null,
				'title'        => $target_post->post_title,
				'last_improved' => (string) get_post_meta( $target_id, '_ele2gb_last_ai_improved', true ),
				'screenshots'  => array(
					'elementor_desktop' => $this->get_first_screenshot( $target_id, '_etg_ai_elementor_screenshot_url' ),
					'gutenberg_desktop' => $this->get_first_screenshot( $target_id, '_etg_ai_gutenberg_screenshot_url' ),
					'elementor_mobile'  => $this->get_first_screenshot( $target_id, '_etg_ai_elementor_screenshot_mobile_url' ),
					'gutenberg_mobile'  => $this->get_first_screenshot( $target_id, '_etg_ai_gutenberg_screenshot_mobile_url' ),
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
