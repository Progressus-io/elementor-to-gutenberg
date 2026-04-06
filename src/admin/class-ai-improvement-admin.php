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
use function get_the_title;
use function get_transient;
use function sanitize_text_field;
use function set_transient;
use function sprintf;
use function update_post_meta;
use function wp_die;
use function wp_json_encode;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_update_post;

defined( 'ABSPATH' ) || exit;

class AI_Improvement_Admin {

	public const MENU_SLUG = 'ele2gb-ai-improvement';

	private const NONCE_AUTO_IMPROVE = 'ele2gb_ai_auto_improve';

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
		add_action( 'admin_post_ele2gb_ai_auto_improve', array( $this, 'handle_auto_improve' ) );
		add_action( 'admin_post_ele2gb_ai_regenerate_screenshots', array( $this, 'handle_regenerate_screenshots' ) );
	}

	/**
	 * Register hidden submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'tools.php',
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
		$elementor_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_url( $target_id );
		$gutenberg_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_url( $target_id );
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
			'target_post_id'          => $target_id,
			'source_post_id'          => $source_id,
			'prepared_prompt'         => $prompt,
			'elementor_json_snapshot' => $elementor_json,
			'gutenberg_snapshot'      => $gutenberg_content,
			'elementor_screenshot'    => $elementor_shot,
			'gutenberg_screenshot'    => $gutenberg_shot,
			'css_result_draft'        => isset( $existing_workspace['css_result_draft'] ) ? (string) $existing_workspace['css_result_draft'] : '',
			'gutenberg_result_draft'  => isset( $existing_workspace['gutenberg_result_draft'] ) ? (string) $existing_workspace['gutenberg_result_draft'] : '',
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

		$gutenberg_content = (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = get_post_meta( $source_id, '_elementor_data', true );
		if ( is_array( $elementor_json ) ) {
			$elementor_json = wp_json_encode( $elementor_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		$elementor_json = (string) $elementor_json;

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

		$elementor_shot = AI_Remediation_Screenshot_Meta_Service::get_elementor_url( $target_id );
		$gutenberg_shot = AI_Remediation_Screenshot_Meta_Service::get_gutenberg_url( $target_id );

		$api_result = Claude_Api_Service::send( $prompt, $elementor_shot, $gutenberg_shot );

		if ( ! $api_result['success'] ) {
			set_transient( 'ele2gb_ai_error_' . $target_id, $api_result['error'], 60 );
			$this->redirect_with_notice( $source_id, $target_id, 'ai_failed' );
		}

		$parsed          = Claude_Api_Service::parse_response( $api_result['content'] );
		$css_result      = $parsed['css'];
		$gutenberg_result = $parsed['gutenberg'];

		if ( '' === trim( $gutenberg_result ) ) {
			$this->redirect_with_notice( $source_id, $target_id, 'ai_parse_failed' );
		}

		$update_result = wp_update_post(
			array(
				'ID'           => $target_id,
				'post_content' => $gutenberg_result,
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			$this->redirect_with_notice( $source_id, $target_id, 'update_failed' );
		}

		if ( '' !== trim( $css_result ) ) {
			$css_append = External_CSS_Service::append_post_css( $target_id, $css_result );
			if ( $css_append instanceof WP_Error ) {
				$this->redirect_with_notice( $source_id, $target_id, 'css_append_failed' );
			}
		}

		$workspace                           = AI_Workspace_Repository::get( $target_id );
		$workspace['target_post_id']         = $target_id;
		$workspace['source_post_id']         = $source_id;
		$workspace['css_result_draft']       = $css_result;
		$workspace['gutenberg_result_draft'] = $gutenberg_result;
		$workspace['updated_at']             = current_time( 'mysql' );
		AI_Workspace_Repository::save( $target_id, $workspace );

		update_post_meta( $target_id, '_ele2gb_last_ai_improved', current_time( 'mysql' ) );
		$this->redirect_with_notice( $source_id, $target_id, 'updated' );
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

		if ( 'ai_failed' === $notice_code ) {
			$ai_error = '';
			if ( $target_id > 0 ) {
				$ai_error = (string) get_transient( 'ele2gb_ai_error_' . $target_id );
				delete_transient( 'ele2gb_ai_error_' . $target_id );
			}
			$msg = '' !== $ai_error
				/* translators: %s: error message returned by Claude API */
				? sprintf( esc_html__( 'Claude API call failed: %s', 'elementor-to-gutenberg' ), esc_html( $ai_error ) )
				: esc_html__( 'Claude API call failed.', 'elementor-to-gutenberg' );
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

		$elementor_shot = isset( $workspace['elementor_screenshot'] ) ? (string) $workspace['elementor_screenshot'] : '';
		$gutenberg_shot = isset( $workspace['gutenberg_screenshot'] ) ? (string) $workspace['gutenberg_screenshot'] : '';

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
			<p><?php echo esc_html__( 'Click "Improve with AI" to automatically improve this converted page using the Claude API.', 'elementor-to-gutenberg' ); ?></p>

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
					<th scope="row"><?php echo esc_html__( 'Elementor Screenshot', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $elementor_shot ) : ?>
							<p><img src="<?php echo esc_url( $elementor_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Elementor screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Gutenberg Screenshot', 'elementor-to-gutenberg' ); ?></th>
					<td>
						<?php if ( '' !== $gutenberg_shot ) : ?>
							<p><img src="<?php echo esc_url( $gutenberg_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'No Gutenberg screenshot available yet.', 'elementor-to-gutenberg' ); ?></p>
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

			<h2><?php echo esc_html__( 'Automated AI Improvement', 'elementor-to-gutenberg' ); ?></h2>
			<p><?php echo esc_html__( 'This will call the Claude API to improve the Gutenberg content of this page. The page content and CSS will be updated automatically.', 'elementor-to-gutenberg' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_AUTO_IMPROVE ); ?>
				<input type="hidden" name="action" value="ele2gb_ai_auto_improve" />
				<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
				<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />
				<?php submit_button( esc_html__( 'Improve with AI', 'elementor-to-gutenberg' ), 'primary', 'ele2gb_auto_improve_submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
