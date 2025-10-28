<?php
/**
 * Batch conversion wizard for Elementor to Gutenberg.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Layout\Batch_Convert_List_Table;

use function absint;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function get_post_status;
use function get_post_thumbnail_id;
use function get_post_type;
use function get_the_title;
use function get_transient;
use function maybe_unserialize;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function set_post_thumbnail;
use function set_transient;
use function submit_button;
use function update_post_meta;
use function is_wp_error;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_insert_post;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_update_post;
use function wp_verify_nonce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Convert_Wizard
 */
class Batch_Convert_Wizard {
	public const MENU_SLUG = 'ele2gb-batch-convert';

	private const NONCE_ACTION = 'ele2gb_batch_convert_action';

	private const NONCE_NAME = 'ele2gb_batch_convert_nonce';

	private const RESULTS_TRANSIENT_TTL = 600;

	private const TEMPLATE_SLUG = 'elementor-to-gutenberg-full-width.php';

	/**
	 * Singleton instance.
	 *
	 * @var Batch_Convert_Wizard|null
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
		add_action( 'admin_post_ele2gb_batch_convert', array( $this, 'handle_batch_convert' ) );
		
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Batch Convert Wizard', 'elementor-to-gutenberg' ),
			esc_html__( 'Batch Convert Wizard', 'elementor-to-gutenberg' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'ele2gb-batch-wizard',
			plugins_url( 'assets/css/batch-wizard.css', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'ele2gb-batch-wizard',
			plugins_url( 'assets/js/batch-wizard.js', GUTENBERG_PLUGIN_MAIN_FILE ),
			array( 'jquery' ),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ele2gb-batch-wizard',
			'ele2gbBatchWizard',
			array(
				'noSelection' => esc_html__( 'Select at least one page before converting.', 'elementor-to-gutenberg' ),
				'selectAll'   => esc_html__( 'Select all', 'elementor-to-gutenberg' ),
			)
		);
	}

	/**
	 * Render wizard page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
		}

		$list_table = new Batch_Convert_List_Table();
		$list_table->prepare_items();

		$results = $this->get_stored_results();
		?>
		<div class="wrap ele2gb-batch-wizard">
			<h1><?php esc_html_e( 'Batch Convert Wizard', 'elementor-to-gutenberg' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Convert multiple Elementor pages into Gutenberg blocks in one go.', 'elementor-to-gutenberg' ); ?></p>
			<?php $this->render_results_notices( $results ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ele2gb-batch-convert-form">
				<input type="hidden" name="action" value="ele2gb_batch_convert" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<?php $list_table->search_box( esc_html__( 'Search Pages', 'elementor-to-gutenberg' ), 'ele2gb-search' ); ?>
				<?php $list_table->display(); ?>

				<fieldset class="ele2gb-wizard-options">
					<legend><?php esc_html_e( 'Conversion Options', 'elementor-to-gutenberg' ); ?></legend>
					<div class="ele2gb-wizard-option">
						<span class="ele2gb-option-label"><?php esc_html_e( 'Mode', 'elementor-to-gutenberg' ); ?></span>
						<label><input type="radio" name="mode" value="create" checked="checked" /> <?php esc_html_e( 'Create new Gutenberg pages', 'elementor-to-gutenberg' ); ?></label>
						<label><input type="radio" name="mode" value="update" /> <?php esc_html_e( 'Update existing pages in place', 'elementor-to-gutenberg' ); ?></label>
					</div>
					<div class="ele2gb-wizard-option">
						<label><input type="checkbox" name="wrap_full_width" value="1" /> <?php esc_html_e( 'Wrap converted content in a full-width group block', 'elementor-to-gutenberg' ); ?></label>
					</div>
					<div class="ele2gb-wizard-option">
						<label><input type="checkbox" name="assign_template" value="1" /> <?php esc_html_e( 'Assign the Elementor to Gutenberg full-width template', 'elementor-to-gutenberg' ); ?></label>
					</div>
					<div class="ele2gb-wizard-option">
						<label><input type="checkbox" name="keep_meta" value="1" /> <?php esc_html_e( 'Copy non-Elementor meta fields and featured image', 'elementor-to-gutenberg' ); ?></label>
					</div>
					<div class="ele2gb-wizard-option">
						<label><input type="checkbox" name="skip_converted" value="1" checked="checked" /> <?php esc_html_e( 'Skip pages that were already converted', 'elementor-to-gutenberg' ); ?></label>
					</div>
					<div class="ele2gb-wizard-option">
						<label class="ele2gb-select-all-wrapper"><input type="checkbox" class="ele2gb-select-all" /> <?php esc_html_e( 'Select all on this page', 'elementor-to-gutenberg' ); ?></label>
					</div>
				</fieldset>
				<?php submit_button( esc_html__( 'Convert Selected Pages', 'elementor-to-gutenberg' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle batch conversion submission.
	 */
	public function handle_batch_convert(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform batch conversions.', 'elementor-to-gutenberg' ) );
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'elementor-to-gutenberg' ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['post_ids'] ) ) : array();
		$post_ids = array_filter( $post_ids );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create';
		if ( 'update' !== $mode ) {
			$mode = 'create';
		}

		$options = array(
			'mode'            => $mode,
			'wrap_full_width' => ! empty( $_POST['wrap_full_width'] ),
			'assign_template' => ! empty( $_POST['assign_template'] ),
			'keep_meta'       => ! empty( $_POST['keep_meta'] ),
			'skip_converted'  => ! empty( $_POST['skip_converted'] ),
		);

		$results = array();

		if ( empty( $post_ids ) ) {
			$message   = esc_html__( 'No pages were selected for conversion.', 'elementor-to-gutenberg' );
			$results[] = array(
				'source'  => 0,
				'status'  => 'error',
				'message' => $message,
				'target'  => 0,
			);
		} else {
			foreach ( $post_ids as $post_id ) {
				$results[] = $this->process_single_post( $post_id, $options );
			}
		}

		$this->store_results( $results );

		$redirect = add_query_arg( array( 'page' => self::MENU_SLUG, 'results' => 1 ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process a single post conversion.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $options Conversion options.
	 */
	public function process_single_post( int $post_id, array $options ): array {
		$post   = get_post( $post_id );
		$result = array(
			'source'  => $post_id,
			'status'  => 'skipped',
			'message' => '',
			'target'  => 0,
		);

		if ( ! $post || 'page' !== $post->post_type ) {
			$message = esc_html__( 'Skipped: only pages can be converted.', 'elementor-to-gutenberg' );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'skipped', $message );
			$result['message'] = $message;
			return $result;
		}

		$target_id = $this->get_existing_target_id( $post_id );
		if ( ! empty( $options['skip_converted'] ) && $this->has_been_converted( $post_id, $target_id ) ) {
			$title   = get_the_title( $post_id );
			$message = sprintf( esc_html__( 'Skipped: “%s” is already converted.', 'elementor-to-gutenberg' ), $title );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'skipped', $message, $target_id );
			$result['message'] = $message;
			$result['target']  = $target_id;
			return $result;
		}

		$json_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $json_data ) ) {
			$message = esc_html__( 'Skipped: Elementor data not found.', 'elementor-to-gutenberg' );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'skipped', $message );
			$result['message'] = $message;
			return $result;
		}

		$decoded = json_decode( $json_data, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			$message = esc_html__( 'Failed: invalid Elementor JSON data.', 'elementor-to-gutenberg' );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'error', $message );
			$result['status']  = 'error';
			$result['message'] = $message;
			return $result;
		}

		$content = Admin_Settings::instance()->convert_json_to_gutenberg_content( array( 'content' => $decoded ) );
		if ( '' === trim( $content ) ) {
			$message = esc_html__( 'Failed: conversion produced no Gutenberg content.', 'elementor-to-gutenberg' );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'error', $message );
			$result['status']  = 'error';
			$result['message'] = $message;
			return $result;
		}

		if ( ! empty( $options['wrap_full_width'] ) ) {
			$content = Admin_Settings::instance()->wrap_content_full_width( $content );
		}

		if ( 'update' === $options['mode'] ) {
			$save = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				),
				true
			);
			$target_id = is_wp_error( $save ) ? 0 : (int) $save;
		} else {
			$save = wp_insert_post(
				array(
					'post_title'   => get_the_title( $post_id ) . ' (Gutenberg)',
					'post_type'    => get_post_type( $post_id ),
					'post_status'  => get_post_status( $post_id ),
					'post_author'  => (int) $post->post_author,
					'post_parent'  => (int) $post->post_parent,
					'post_content' => $content,
				),
				true
			);
			$target_id = is_wp_error( $save ) ? 0 : (int) $save;

			if ( $target_id && ! empty( $options['keep_meta'] ) ) {
				$this->copy_post_meta( $post_id, $target_id );
			}
		}

		if ( empty( $target_id ) ) {
			$message = esc_html__( 'Failed: could not save Gutenberg content.', 'elementor-to-gutenberg' );
			Admin_Settings::instance()->record_conversion_result( $post_id, 'error', $message );
			$result['status']  = 'error';
			$result['message'] = $message;
			return $result;
		}

		if ( ! empty( $options['assign_template'] ) ) {
			update_post_meta( $target_id, '_wp_page_template', self::TEMPLATE_SLUG );
		}

		if ( 'update' === $options['mode'] && ! empty( $options['keep_meta'] ) ) {
			$this->copy_post_meta( $post_id, $target_id, true );
		}

		$title   = get_the_title( $post_id );
		$message = sprintf( esc_html__( 'Converted “%s” to Gutenberg blocks.', 'elementor-to-gutenberg' ), $title );
		Admin_Settings::instance()->record_conversion_result( $post_id, 'success', $message, $target_id );

		$result['status']  = 'success';
		$result['message'] = $message;
		$result['target']  = $target_id;

		return $result;
	}

	/**
	 * Copy non-Elementor meta values to the target post.
	 *
	 * @param int  $source_id Source post ID.
	 * @param int  $target_id Target post ID.
	 * @param bool $update_mode Whether we are updating the same post.
	 */
	private function copy_post_meta( int $source_id, int $target_id, bool $update_mode = false ): void {
		if ( $update_mode ) {
			$thumbnail_id = get_post_thumbnail_id( $source_id );
			if ( $thumbnail_id ) {
				set_post_thumbnail( $target_id, $thumbnail_id );
			}

			return;
		}

		$meta = get_post_meta( $source_id );
		if ( empty( $meta ) ) {
			$thumbnail_id = get_post_thumbnail_id( $source_id );
			if ( $thumbnail_id ) {
				set_post_thumbnail( $target_id, $thumbnail_id );
			}
			return;
		}

		$skip_keys = array( '_edit_lock', '_edit_last', '_elementor_data' );

		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, '_elementor_' ) ) {
				continue;
			}
			if ( 0 === strpos( $key, '_ele2gb_' ) ) {
				continue;
			}
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			if ( '_thumbnail_id' === $key ) {
				continue;
			}

			if ( ! $update_mode ) {
				delete_post_meta( $target_id, $key );
			}

			foreach ( $values as $value ) {
				add_post_meta( $target_id, $key, maybe_unserialize( $value ) );
			}
		}

		$thumbnail_id = get_post_thumbnail_id( $source_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $target_id, $thumbnail_id );
		}
	}

	/**
	 * Determine whether a post already has a converted target.
	 *
	 * @param int $post_id Source post ID.
	 * @param int $target_id Target post ID.
	 */
	private function has_been_converted( int $post_id, int $target_id ): bool {
		$converted_time = '';

		if ( $target_id > 0 ) {
			$converted_time = get_post_meta( $target_id, '_ele2gb_last_converted', true );
		}

		if ( empty( $converted_time ) ) {
			$converted_time = get_post_meta( $post_id, '_ele2gb_last_converted', true );
		}

		return ! empty( $converted_time );
	}

	/**
	 * Attempt to locate a previously converted target ID.
	 *
	 * @param int $post_id Source post ID.
	 */
	private function get_existing_target_id( int $post_id ): int {
		$last_result = get_post_meta( $post_id, '_ele2gb_last_result', true );
		if ( is_array( $last_result ) && ! empty( $last_result['target'] ) ) {
			return absint( $last_result['target'] );
		}

		return 0;
	}

	/**
	 * Render stored result notices.
	 *
	 * @param array $results Results array.
	 */
	private function render_results_notices( array $results ): void {
		if ( empty( $results ) ) {
			return;
		}

		$grouped = array();
		foreach ( $results as $result ) {
			$status = $result['status'];
			if ( ! isset( $grouped[ $status ] ) ) {
				$grouped[ $status ] = array();
			}
			$grouped[ $status ][] = $result;
		}

		foreach ( $grouped as $status => $items ) {
			$class = 'notice notice-info';
			if ( 'success' === $status ) {
				$class = 'notice notice-success';
			} elseif ( 'error' === $status ) {
				$class = 'notice notice-error';
			}

			echo '<div class="' . esc_attr( $class ) . '"><ul>';
			foreach ( $items as $item ) {
				$source = absint( $item['source'] );
				$label  = $source > 0 ? sprintf( esc_html__( 'Page #%d: ', 'elementor-to-gutenberg' ), $source ) : '';
				echo '<li>' . esc_html( $label . $item['message'] ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	/**
	 * Store results for the next page load.
	 *
	 * @param array $results Results array.
	 */
	private function store_results( array $results ): void {
		set_transient( $this->get_results_transient_name(), $results, self::RESULTS_TRANSIENT_TTL );
	}

	/**
	 * Get stored results and clear transient.
	 */
	private function get_stored_results(): array {
		$results = get_transient( $this->get_results_transient_name() );
		if ( false !== $results ) {
			delete_transient( $this->get_results_transient_name() );
			return is_array( $results ) ? $results : array();
		}

		return array();
	}

	/**
	 * Transient key for storing results per user.
	 */
	private function get_results_transient_name(): string {
		return 'ele2gb_batch_results_' . get_current_user_id();
	}
}