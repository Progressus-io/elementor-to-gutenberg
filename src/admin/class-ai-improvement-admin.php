<?php
/**
 * Manual AI improvement admin workflow.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Helper\AI_Prompt_Builder;
use Progressus\Gutenberg\Admin\Helper\AI_Workspace_Repository;
use Progressus\Gutenberg\Admin\Helper\External_CSS_Service;
use WP_Error;
use WP_Post;

use function absint;
use function add_query_arg;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function get_post;
use function get_post_field;
use function get_post_meta;
use function get_the_title;
use function sanitize_text_field;
use function update_post_meta;
use function wp_die;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_update_post;

defined( 'ABSPATH' ) || exit;

class AI_Improvement_Admin {

	public const MENU_SLUG = 'ele2gb-ai-improvement';

	private const NONCE_ACTION = 'ele2gb_ai_improvement_update';

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
		add_action( 'admin_post_ele2gb_ai_update_page', array( $this, 'handle_update_page' ) );
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
		$elementor_shot     = isset( $existing_workspace['elementor_screenshot'] ) ? (string) $existing_workspace['elementor_screenshot'] : '';
		$gutenberg_shot     = isset( $existing_workspace['gutenberg_screenshot'] ) ? (string) $existing_workspace['gutenberg_screenshot'] : '';

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
			'target_post_id'         => $target_id,
			'source_post_id'         => $source_id,
			'prepared_prompt'        => $prompt,
			'elementor_json_snapshot'=> $elementor_json,
			'gutenberg_snapshot'     => $gutenberg_content,
			'elementor_screenshot'   => $elementor_shot,
			'gutenberg_screenshot'   => $gutenberg_shot,
			'css_result_draft'       => isset( $existing_workspace['css_result_draft'] ) ? (string) $existing_workspace['css_result_draft'] : '',
			'gutenberg_result_draft' => isset( $existing_workspace['gutenberg_result_draft'] ) ? (string) $existing_workspace['gutenberg_result_draft'] : '',
		);
		AI_Workspace_Repository::save( $target_id, $workspace_to_save );

		$notice_code = isset( $_GET['ele2gb_ai_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ele2gb_ai_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->render_notice( $notice_code );
		$this->render_form( $target_post, $source_post, AI_Workspace_Repository::get( $target_id ) );
	}

	/**
	 * Handle update action.
	 */
	public function handle_update_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

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

		$css_result       = isset( $_POST['css_result'] ) ? (string) wp_unslash( $_POST['css_result'] ) : '';
		$gutenberg_result = isset( $_POST['gutenberg_result'] ) ? (string) wp_unslash( $_POST['gutenberg_result'] ) : '';

		$elementor_shot = isset( $_POST['elementor_screenshot'] ) ? sanitize_text_field( wp_unslash( $_POST['elementor_screenshot'] ) ) : '';
		$gutenberg_shot = isset( $_POST['gutenberg_screenshot'] ) ? sanitize_text_field( wp_unslash( $_POST['gutenberg_screenshot'] ) ) : '';

		$workspace = AI_Workspace_Repository::get( $target_id );
		$workspace['target_post_id']         = $target_id;
		$workspace['source_post_id']         = $source_id;
		$workspace['css_result_draft']       = $css_result;
		$workspace['gutenberg_result_draft'] = $gutenberg_result;
		$workspace['elementor_screenshot']   = $elementor_shot;
		$workspace['gutenberg_screenshot']   = $gutenberg_shot;
		AI_Workspace_Repository::save( $target_id, $workspace );

		if ( '' === trim( $gutenberg_result ) ) {
			$this->redirect_with_notice( $source_id, $target_id, 'missing_gutenberg' );
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
			$css_result_append = External_CSS_Service::append_post_css( $target_id, $css_result );
			if ( $css_result_append instanceof WP_Error ) {
				$this->redirect_with_notice( $source_id, $target_id, 'css_append_failed' );
			}
		}

		update_post_meta( $target_id, '_ele2gb_last_ai_improved', current_time( 'mysql' ) );
		$this->redirect_with_notice( $source_id, $target_id, 'updated' );
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
	 */
	private function render_notice( string $notice_code ): void {
		if ( '' === $notice_code ) {
			return;
		}

		$messages = array(
			'updated'           => array( 'success', esc_html__( 'Page updated and AI CSS appended successfully.', 'elementor-to-gutenberg' ) ),
			'missing_gutenberg' => array( 'error', esc_html__( 'Gutenberg result is required before updating.', 'elementor-to-gutenberg' ) ),
			'css_append_failed' => array( 'error', esc_html__( 'Could not append CSS because the external CSS file for this page could not be resolved.', 'elementor-to-gutenberg' ) ),
			'update_failed'     => array( 'error', esc_html__( 'Failed to update Gutenberg page content.', 'elementor-to-gutenberg' ) ),
			'invalid_mapping'   => array( 'error', esc_html__( 'Source and target mapping validation failed.', 'elementor-to-gutenberg' ) ),
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
	 * Render workflow form.
	 */
	private function render_form( WP_Post $target_post, WP_Post $source_post, array $workspace ): void {
		$target_id  = (int) $target_post->ID;
		$source_id  = (int) $source_post->ID;
		$target_title = get_the_title( $target_id );
		$source_title = get_the_title( $source_id );

		$gutenberg_content = isset( $workspace['gutenberg_snapshot'] ) ? (string) $workspace['gutenberg_snapshot'] : (string) get_post_field( 'post_content', $target_id );
		$elementor_json    = isset( $workspace['elementor_json_snapshot'] ) ? (string) $workspace['elementor_json_snapshot'] : (string) get_post_meta( $source_id, '_elementor_data', true );
		$prompt            = isset( $workspace['prepared_prompt'] ) ? (string) $workspace['prepared_prompt'] : '';
		$css_result        = isset( $workspace['css_result_draft'] ) ? (string) $workspace['css_result_draft'] : '';
		$gutenberg_result  = isset( $workspace['gutenberg_result_draft'] ) ? (string) $workspace['gutenberg_result_draft'] : '';
		$elementor_shot    = isset( $workspace['elementor_screenshot'] ) ? (string) $workspace['elementor_screenshot'] : '';
		$gutenberg_shot    = isset( $workspace['gutenberg_screenshot'] ) ? (string) $workspace['gutenberg_screenshot'] : '';

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Improve Page with AI', 'elementor-to-gutenberg' ); ?></h1>
			<p><?php echo esc_html__( 'Manual workflow: copy the prompt and inputs below, run them in your external AI tool, then paste CSS and Gutenberg output back here.', 'elementor-to-gutenberg' ); ?></p>

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

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="ele2gb_ai_update_page" />
				<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
				<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source_id ); ?>" />

				<h2><?php echo esc_html__( 'Screenshots', 'elementor-to-gutenberg' ); ?></h2>
				<p><?php echo esc_html__( 'Add URL references for screenshots you want to share with the external AI tool.', 'elementor-to-gutenberg' ); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="ele2gb_elementor_screenshot"><?php echo esc_html__( 'Elementor Screenshot URL', 'elementor-to-gutenberg' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="ele2gb_elementor_screenshot" name="elementor_screenshot" value="<?php echo esc_attr( $elementor_shot ); ?>" />
							<?php if ( '' !== $elementor_shot ) : ?>
								<p><img src="<?php echo esc_url( $elementor_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'No Elementor screenshot attached yet.', 'elementor-to-gutenberg' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ele2gb_gutenberg_screenshot"><?php echo esc_html__( 'Gutenberg Screenshot URL', 'elementor-to-gutenberg' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="ele2gb_gutenberg_screenshot" name="gutenberg_screenshot" value="<?php echo esc_attr( $gutenberg_shot ); ?>" />
							<?php if ( '' !== $gutenberg_shot ) : ?>
								<p><img src="<?php echo esc_url( $gutenberg_shot ); ?>" alt="" style="max-width:480px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;" /></p>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'No Gutenberg screenshot attached yet.', 'elementor-to-gutenberg' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					</tbody>
				</table>

				<h2><?php echo esc_html__( 'Prepared Inputs', 'elementor-to-gutenberg' ); ?></h2>
				<p><label for="ele2gb_gutenberg_content"><strong><?php echo esc_html__( 'Gutenberg Content', 'elementor-to-gutenberg' ); ?></strong></label></p>
				<textarea id="ele2gb_gutenberg_content" class="large-text code" rows="14" readonly><?php echo esc_textarea( $gutenberg_content ); ?></textarea>

				<p><label for="ele2gb_elementor_json"><strong><?php echo esc_html__( 'Elementor JSON', 'elementor-to-gutenberg' ); ?></strong></label></p>
				<textarea id="ele2gb_elementor_json" class="large-text code" rows="14" readonly><?php echo esc_textarea( $elementor_json ); ?></textarea>

				<p><label for="ele2gb_ready_prompt"><strong><?php echo esc_html__( 'Ready AI Prompt', 'elementor-to-gutenberg' ); ?></strong></label></p>
				<textarea id="ele2gb_ready_prompt" class="large-text code" rows="18" readonly><?php echo esc_textarea( $prompt ); ?></textarea>

				<h2><?php echo esc_html__( 'Paste AI Results', 'elementor-to-gutenberg' ); ?></h2>
				<p><label for="ele2gb_css_result"><strong><?php echo esc_html__( 'CSS Result', 'elementor-to-gutenberg' ); ?></strong></label></p>
				<textarea id="ele2gb_css_result" class="large-text code" name="css_result" rows="10" placeholder="<?php echo esc_attr__( 'Paste returned CSS here (optional).', 'elementor-to-gutenberg' ); ?>"><?php echo esc_textarea( $css_result ); ?></textarea>

				<p><label for="ele2gb_gutenberg_result"><strong><?php echo esc_html__( 'Gutenberg Content Result', 'elementor-to-gutenberg' ); ?></strong></label></p>
				<textarea id="ele2gb_gutenberg_result" class="large-text code" name="gutenberg_result" rows="16" placeholder="<?php echo esc_attr__( 'Paste returned Gutenberg post_content here (required).', 'elementor-to-gutenberg' ); ?>"><?php echo esc_textarea( $gutenberg_result ); ?></textarea>

				<?php submit_button( esc_html__( 'Update Page', 'elementor-to-gutenberg' ) ); ?>
			</form>
		</div>
		<?php
	}
}
