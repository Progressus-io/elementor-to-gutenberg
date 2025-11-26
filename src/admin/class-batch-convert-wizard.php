<?php
/**
 * Modern batch conversion wizard for Elementor to Gutenberg.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Admin_Settings;
use WP_Error;
use WP_Post;
use WP_Query;

use function absint;
use function add_submenu_page;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function date_i18n;
use function delete_user_meta;
use function esc_html__;
use function get_current_user_id;
use function get_edit_post_link;
use function get_post_thumbnail_id;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_post_field;
use function get_post_status;
use function get_post_type;
use function get_the_title;
use function get_user_meta;
use function get_stylesheet;
use function wp_get_theme;
use function is_array;
use function maybe_unserialize;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_title;
use function set_post_thumbnail;
use function set_transient;
use function time;
use function uniqid;
use function update_user_meta;
use function update_post_meta;
use function add_post_meta;
use function wp_create_nonce;
use function wp_die;
use function delete_post_meta;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_custom_css;
use function wp_get_themes;
use function wp_localize_script;
use function wp_set_post_terms;
use function wp_reset_postdata;
use function wp_update_custom_css_post;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function strtotime;
use function switch_theme;
use function wp_insert_post;
use function wp_ajax_install_theme;
use function wp_update_post;
use function is_wp_error;
use function delete_transient;
use function wp_is_block_theme;

use const HOUR_IN_SECONDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Convert_Wizard
 */
class Batch_Convert_Wizard {
	public const MENU_SLUG = 'ele2gb-batch-convert';

	private const NONCE_ACTION = 'ele2gb_batch_convert';

	private const NONCE_NAME = 'nonce';

	private const JOB_TRANSIENT_PREFIX = 'ele2gb_job_';

	private const TEMPLATE_SLUG = 'elementor-to-gutenberg-full-width.php';

	private const JOB_TRANSIENT_TTL = 6 * HOUR_IN_SECONDS;

	private const ITEMS_PER_QUERY = 250;

	private const TEMPLATE_SOURCE_ELEMENTOR_PRO = 'elementor_pro';

	private const TEMPLATE_SOURCE_HEADER_FOOTER = 'header_footer_elementor';

	private const TEMPLATE_ROLE_DEFAULT_HEADER = 'default_header';

	private const TEMPLATE_ROLE_DEFAULT_FOOTER = 'default_footer';

	private const TEMPLATE_ROLE_EXTRA = 'extra';

	private const SUGGESTED_BLOCK_THEMES = array(
		array(
			'slug' => 'twentytwentyfive',
			'name' => 'Twenty Twenty-Five',
		),
		array(
			'slug' => 'twentytwentyfour',
			'name' => 'Twenty Twenty-Four',
		),
	);

	/**
	 * Singleton instance.
	 *
	 * @var Batch_Convert_Wizard|null
	 */
	private static $instance = null;

	/**
	 * Cached header/footer template detection.
	 *
	 * @var array|null
	 */
	private $cached_templates = null;

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
		add_action( 'wp_ajax_ele2gb_pages', array( $this, 'ajax_get_pages' ) );
		add_action( 'wp_ajax_ele2gb_start_job', array( $this, 'ajax_start_job' ) );
		add_action( 'wp_ajax_ele2gb_poll_job', array( $this, 'ajax_poll_job' ) );
		add_action( 'wp_ajax_ele2gb_cancel_job', array( $this, 'ajax_cancel_job' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets for the wizard page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			plugins_url( 'assets/js/batch-convert-wizard.js', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ele2gb-batch-wizard',
			'ele2gbBatchWizard',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'pages'        => $this->get_elementor_pages_data(),
				'strings'      => $this->get_strings(),
				'templates'    => $this->get_header_footer_templates_data(),
				'themes'       => $this->get_theme_compatibility_data(),
				'activeJob'    => $this->get_active_job_for_user(),
				'userCanEdit'  => current_user_can( 'edit_pages' ),
				'maxBatchSize' => 1,
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

		?>
        <div class="wrap ele2gb-wizard-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Gutenberg Conversion Wizard', 'elementor-to-gutenberg' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Convert Elementor pages to Gutenberg blocks.', 'elementor-to-gutenberg' ); ?></p>
            <div id="ele2gb-batch-convert-root" class="ele2gb-wizard-root" aria-live="polite"></div>
        </div>
		<?php
	}

	/**
	 * Handle AJAX request for Elementor pages data.
	 */
	public function ajax_get_pages(): void {
		$this->verify_ajax_request();

		wp_send_json_success(
			array(
				'pages'     => $this->get_elementor_pages_data(),
				'templates' => $this->get_header_footer_templates_data(),
			)
		);
	}

	/**
	 * Start a conversion job via AJAX.
	 */
	public function ajax_start_job(): void {
		$this->verify_ajax_request();

		$mode            = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
		$conflict_policy = isset( $_POST['conflictPolicy'] ) ? sanitize_key( wp_unslash( $_POST['conflictPolicy'] ) ) : 'skip';
		$skip_converted  = ! empty( $_POST['skipConverted'] );

		$raw_pages         = isset( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : array();
		$raw_disabled      = isset( $_POST['disabledMeta'] ) ? wp_unslash( $_POST['disabledMeta'] ) : array();
		$raw_headers       = isset( $_POST['headerTemplates'] ) ? wp_unslash( $_POST['headerTemplates'] ) : array();
		$raw_footers       = isset( $_POST['footerTemplates'] ) ? wp_unslash( $_POST['footerTemplates'] ) : array();
		$default_header    = isset( $_POST['defaultHeader'] ) ? absint( wp_unslash( $_POST['defaultHeader'] ) ) : 0;
		$default_footer    = isset( $_POST['defaultFooter'] ) ? absint( wp_unslash( $_POST['defaultFooter'] ) ) : 0;
		$change_theme      = ! empty( $_POST['changeTheme'] );
		$new_theme         = isset( $_POST['newTheme'] ) ? sanitize_text_field( wp_unslash( $_POST['newTheme'] ) ) : '';
		$copy_custom_css   = ! empty( $_POST['copyCustomCss'] );
		$selected_page_ids = array_map( 'absint', (array) $raw_pages );
		$disabled_meta_ids = array_map( 'absint', (array) $raw_disabled );
		$selected_headers  = $this->normalize_template_selection( $raw_headers );
		$selected_footers  = $this->normalize_template_selection( $raw_footers );

		if ( 'auto' === $mode ) {
			$all_pages         = $this->get_elementor_pages_data();
			$selected_page_ids = array_map(
				static function ( array $page ): int {
					return (int) $page['id'];
				},
				$all_pages
			);
			$skip_converted    = true;
		}

		$selected_page_ids = array_values( array_unique( array_filter( $selected_page_ids ) ) );

		if ( $change_theme && '' === $new_theme ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Select a theme before starting the conversion.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$theme_result = $this->maybe_switch_theme( $change_theme, $new_theme, $copy_custom_css, $mode );
		if ( is_wp_error( $theme_result ) ) {
			wp_send_json_error(
				array(
					'message' => $theme_result->get_error_message(),
				)
			);
		}

		$pages     = $this->prepare_job_pages( $selected_page_ids, $disabled_meta_ids );
		$templates = $this->prepare_job_templates(
			$mode,
			$selected_headers,
			$selected_footers,
			$default_header,
			$default_footer
		);

		if ( empty( $pages ) && empty( $templates['headers'] ) && empty( $templates['footers'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Select at least one page or template before starting a conversion.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$options = $this->build_job_options( $mode, $conflict_policy, $skip_converted );

		$job_id = uniqid( 'ele2gb_', true );

		$job = array(
			'id'              => $job_id,
			'user_id'         => get_current_user_id(),
			'mode'            => $mode,
			'conflict_policy' => $conflict_policy,
			'status'          => 'queued',
			'created_at'      => time(),
			'started_at'      => 0,
			'completed_at'    => 0,
			'processed'       => 0,
			'pages'           => $pages,
			'templates'       => $templates,
			'options'         => $options,
			'counts'          => array(
				'success' => 0,
				'skipped' => 0,
				'error'   => 0,
				'partial' => 0,
			),
			'results'         => array(),
		);

		$this->store_job( $job );

		update_user_meta( get_current_user_id(), '_ele2gb_job', $job_id );

		wp_send_json_success(
			array(
				'job' => $this->format_job_for_response( $job ),
			)
		);
	}

	/**
	 * Poll and process the next batch of a conversion job.
	 */
	public function ajax_poll_job(): void {
		$this->verify_ajax_request();

		$job_id = isset( $_POST['jobId'] ) ? sanitize_text_field( wp_unslash( $_POST['jobId'] ) ) : '';
		if ( '' === $job_id ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Missing job identifier.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$job = $this->get_job( $job_id );
		if ( empty( $job ) || (int) $job['user_id'] !== get_current_user_id() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Conversion job could not be found.', 'elementor-to-gutenberg' ),
				)
			);
		}

		if ( 'completed' === $job['status'] ) {
			wp_send_json_success(
				array(
					'job' => $this->format_job_for_response( $job ),
				)
			);
		}

		if ( empty( $job['started_at'] ) ) {
			$job['started_at'] = time();
		}

		$job['status'] = 'running';

		$batch_size = 1;

		$items       = $this->get_job_queue( $job );
		$total_items = count( $items );
		for ( $i = 0; $i < $batch_size; $i ++ ) {
			if ( $job['processed'] >= $total_items ) {
				break;
			}

			$index     = $job['processed'];
			$item      = $items[ $index ];
			$result    = array(
				'status'  => 'skipped',
				'message' => '',
				'target'  => 0,
			);
			$duration  = 0;
			$view_url  = '';
			$keep_meta = false;

			if ( 'page' === $item['type'] ) {
				$page_info = $item['data'];

				$options              = $job['options'];
				$options['keep_meta'] = ! empty( $page_info['keep_meta'] );

				$start_time = microtime( true );
				$result     = $this->process_single_post( (int) $page_info['id'], $options );
				$duration   = max( 0, microtime( true ) - $start_time );
				$view_url   = $result['target'] ? get_permalink( (int) $result['target'] ) : '';
				$keep_meta  = ! empty( $page_info['keep_meta'] );

				$result_entry = array(
					'id'        => (int) $page_info['id'],
					'title'     => $page_info['title'],
					'status'    => $result['status'],
					'message'   => $result['message'],
					'target'    => $result['target'],
					'duration'  => $duration,
					'view_url'  => $view_url,
					'keep_meta' => $keep_meta,
					'type'      => 'page',
				);

				$this->store_page_conversion_result( (int) $page_info['id'], $result_entry );

			} else {
				$template_info = $item['data'];

				$start_time      = microtime( true );
				$template_result = $this->process_template_conversion( $template_info, $job['options'] );
				$duration        = max( 0, microtime( true ) - $start_time );
				$result          = $template_result;
				$view_url        = $template_result['view_url'];

				$result_entry = array(
					'id'        => (int) $template_info['id'],
					'title'     => $template_info['title'],
					'status'    => $template_result['status'],
					'message'   => $template_result['message'],
					'target'    => $template_result['target'],
					'duration'  => $duration,
					'view_url'  => $view_url,
					'keep_meta' => false,
					'type'      => $template_info['type'],
					'role'      => $template_info['role'],
					'source'    => $template_info['source'],
				);

				$this->store_template_conversion_result( (int) $template_info['id'], $result_entry );
			}

			$job['results'][] = $result_entry;

			if ( isset( $job['counts'][ $result['status'] ] ) ) {
				$job['counts'][ $result['status'] ] ++;
			}

			$job['processed'] ++;
		}

		if ( $job['processed'] >= $total_items ) {
			$job['status']       = 'completed';
			$job['completed_at'] = time();
			delete_user_meta( get_current_user_id(), '_ele2gb_job' );
		}

		$this->store_job( $job );

		wp_send_json_success(
			array(
				'job' => $this->format_job_for_response( $job ),
			)
		);
	}

	/**
	 * Verify AJAX request permissions and nonce.
	 */
	private function verify_ajax_request(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, self::NONCE_NAME );
	}

	/**
	 * Retrieve Elementor pages data for the wizard.
	 */
	private function get_elementor_pages_data(): array {
		$paged       = 1;
		$accumulated = array();

		do {
			$query = new WP_Query(
				array(
					'post_type'      => array( 'page' ),
					'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'posts_per_page' => self::ITEMS_PER_QUERY,
					'paged'          => $paged,
					'meta_query'     => array(
						array(
							'key'     => '_elementor_data',
							'value'   => '',
							'compare' => '!=',
						),
					),
				)
			);

			foreach ( $query->posts as $post ) {
				$accumulated[] = $this->map_page_to_array( $post );
			}

			$paged ++;
			$max_pages = (int) $query->max_num_pages;
			wp_reset_postdata();
		} while ( $paged <= $max_pages );

		return $accumulated;
	}

	/**
	 * Retrieve detected header and footer templates for UI consumption.
	 */
	private function get_header_footer_templates_data(): array {
		$raw = $this->get_header_footer_templates_raw();

		$format = function ( array $template ): array {
			return array(
				'id'                => (int) $template['id'],
				'title'             => $template['title'],
				'status'            => $template['status'],
				'conversionStatus'  => $template['conversion_status'],
				'lastResultStatus'  => $template['last_result_status'],
				'lastResultMessage' => $template['last_result_message'],
				'lastConverted'     => $template['last_converted'],
				'source'            => $template['source'],
				'sourceLabel'       => $this->get_template_source_label( $template['source'] ),
				'type'              => $template['type'],
				'postType'          => $template['post_type'],
				'isLikelyGlobal'    => ! empty( $template['is_global'] ),
			);
		};

		$defaults = array(
			'header' => $this->pick_default_template_id( $raw['headers'] ),
			'footer' => $this->pick_default_template_id( $raw['footers'] ),
		);

		return array(
			'headers'  => array_map( $format, $raw['headers'] ),
			'footers'  => array_map( $format, $raw['footers'] ),
			'defaults' => $defaults,
			'counts'   => array(
				'headers' => count( $raw['headers'] ),
				'footers' => count( $raw['footers'] ),
			),
		);
	}

	/**
	 * Retrieve theme compatibility information for the wizard.
	 */
	private function get_theme_compatibility_data(): array {
		$current_stylesheet = get_stylesheet();
		$current_theme      = wp_get_theme( $current_stylesheet );

		$current = array(
			'name'         => $current_theme->get( 'Name' ),
			'slug'         => $current_stylesheet,
			'isBlockTheme' => method_exists( $current_theme, 'is_block_theme' ) ? (bool) $current_theme->is_block_theme() : (bool) wp_is_block_theme(),
		);

		$installed = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme() ) {
				$installed[] = array(
					'name'     => $theme->get( 'Name' ),
					'slug'     => $slug,
					'isActive' => $slug === $current_stylesheet,
				);
			}
		}

		$suggested       = array();
		$installed_slugs = array_map(
			static function ( array $theme ): string {
				return (string) $theme['slug'];
			},
			$installed
		);

		foreach ( self::SUGGESTED_BLOCK_THEMES as $theme ) {
			if ( in_array( $theme['slug'], $installed_slugs, true ) ) {
				continue;
			}

			$suggested[] = $theme;
		}

		return array(
			'currentTheme'         => $current,
			'installedBlockThemes' => $installed,
			'suggestedCoreThemes'  => $suggested,
		);
	}

	/**
	 * Retrieve detected header/footer templates with metadata.
	 */
	private function get_header_footer_templates_raw(): array {
		if ( null !== $this->cached_templates ) {
			return $this->cached_templates;
		}

		$templates = array(
			'headers' => array(),
			'footers' => array(),
		);

		$elementor_pro = $this->find_elementor_pro_templates();
		$hfe           = $this->find_header_footer_elementor_templates();

		$templates['headers'] = array_merge( $templates['headers'], $elementor_pro['headers'], $hfe['headers'] );
		$templates['footers'] = array_merge( $templates['footers'], $elementor_pro['footers'], $hfe['footers'] );

		$this->cached_templates = $templates;

		return $templates;
	}

	/**
	 * Detect Elementor Pro theme builder templates.
	 */
	private function find_elementor_pro_templates(): array {
		$results = array(
			'headers' => array(),
			'footers' => array(),
		);

		$query = new WP_Query(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => - 1,
				'meta_query'     => array(
					array(
						'key'     => '_elementor_template_type',
						'value'   => array( 'header', 'footer' ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$type = get_post_meta( $post->ID, '_elementor_template_type', true );
			if ( 'header' !== $type && 'footer' !== $type ) {
				continue;
			}

			$is_global = $this->is_elementor_pro_template_global( (int) $post->ID );
			$mapped    = $this->map_template_post_to_array( $post, self::TEMPLATE_SOURCE_ELEMENTOR_PRO, $type, $is_global );

			if ( 'header' === $type ) {
				$results['headers'][] = $mapped;
			} else {
				$results['footers'][] = $mapped;
			}
		}

		wp_reset_postdata();

		return $results;
	}

	/**
	 * Detect templates from Header Footer Elementor plugin.
	 */
	private function find_header_footer_elementor_templates(): array {
		$results = array(
			'headers' => array(),
			'footers' => array(),
		);

		$query = new WP_Query(
			array(
				'post_type'      => 'elementor-hf',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => - 1,
				'meta_query'     => array(
					array(
						'key'     => 'ehf_template_type',
						'value'   => array( 'type_header', 'type_footer' ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$type_meta = get_post_meta( $post->ID, 'ehf_template_type', true );
			if ( 'type_header' !== $type_meta && 'type_footer' !== $type_meta ) {
				continue;
			}

			$type      = 'type_header' === $type_meta ? 'header' : 'footer';
			$is_global = $this->is_header_footer_elementor_global( (int) $post->ID );
			$mapped    = $this->map_template_post_to_array( $post, self::TEMPLATE_SOURCE_HEADER_FOOTER, $type, $is_global );

			if ( 'header' === $type ) {
				$results['headers'][] = $mapped;
			} else {
				$results['footers'][] = $mapped;
			}
		}

		wp_reset_postdata();

		return $results;
	}

	/**
	 * Map a template post to an internal array structure.
	 */
	private function map_template_post_to_array( WP_Post $post, string $source, string $type, bool $is_global ): array {
		$target_pages = array();

		if ( self::TEMPLATE_SOURCE_ELEMENTOR_PRO === $source ) {
			$targets      = $this->get_elementor_pro_template_targets( (int) $post->ID );
			$target_pages = $targets['pages'] ?? array();
		} elseif ( self::TEMPLATE_SOURCE_HEADER_FOOTER === $source ) {
			$targets      = $this->get_header_footer_elementor_template_targets( (int) $post->ID );
			$target_pages = $targets['pages'] ?? array();
		}

		$last_result = get_post_meta( $post->ID, '_ele2gb_last_result', true );
		$status_key  = is_array( $last_result ) && isset( $last_result['status'] ) ? (string) $last_result['status'] : '';
		$last_time   = is_array( $last_result ) && ! empty( $last_result['time'] ) ? (string) $last_result['time'] : '';

		$conversion_status = $this->map_status_to_badge( $status_key );

		$last_converted = '';
		if ( '' !== $last_time ) {
			$timestamp = strtotime( $last_time );
			if ( false !== $timestamp ) {
				$last_converted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
			}
		}

		return array(
			'id'                  => (int) $post->ID,
			'title'               => get_the_title( $post ),
			'status'              => get_post_status( $post ),
			'conversion_status'   => $conversion_status,
			'last_result_status'  => $status_key,
			'last_result_message' => is_array( $last_result ) && isset( $last_result['message'] ) ? (string) $last_result['message'] : '',
			'last_converted'      => $last_converted,
			'source'              => $source,
			'type'                => $type,
			'post_type'           => get_post_type( $post ),
			'is_global'           => $is_global,
			'target_pages'        => array_values( array_unique( array_filter( array_map( 'absint', $target_pages ) ) ) ),
			'modified'            => $this->get_post_modified_timestamp( $post ),
		);
	}

	/**
	 * Extract page-specific targets for an Elementor Pro template.
	 */
	private function get_elementor_pro_template_targets( int $post_id ): array {
		$conditions = get_post_meta( $post_id, '_elementor_conditions', true );

		if ( is_string( $conditions ) && '' !== $conditions ) {
			$decoded = json_decode( $conditions, true );
			if ( is_array( $decoded ) ) {
				$conditions = $decoded;
			}
		}

		if ( ! is_array( $conditions ) ) {
			return array( 'pages' => array() );
		}

		$pages = array();

		foreach ( $conditions as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $condition ) {
				if ( ! is_array( $condition ) ) {
					continue;
				}

				$type = isset( $condition['type'] ) ? (string) $condition['type'] : 'include';
				if ( 'include' !== $type ) {
					continue;
				}

				foreach ( array( 'id', 'post_id', 'sub_id' ) as $key ) {
					if ( ! empty( $condition[ $key ] ) ) {
						$pages[] = absint( $condition[ $key ] );
					}
				}

				if ( isset( $condition['value'] ) ) {
					$this->collect_page_ids_from_value( $condition['value'], $pages );
				}

				if ( isset( $condition['name'] ) ) {
					$this->collect_page_ids_from_value( $condition['name'], $pages );
				}

				if ( isset( $condition['sub_name'] ) ) {
					$this->collect_page_ids_from_value( $condition['sub_name'], $pages );
				}
			}
		}

		$pages = array_values( array_unique( array_filter( array_map( 'absint', $pages ) ) ) );

		return array(
			'pages' => $pages,
		);
	}

	/**
	 * Extract page-specific targets for a Header Footer Elementor template.
	 */
	private function get_header_footer_elementor_template_targets( int $post_id ): array {
		$include_locations = get_post_meta( $post_id, 'ehf_target_include_locations', true );
		$include_locations = maybe_unserialize( $include_locations );

		$pages = array();

		if ( is_array( $include_locations ) ) {
			foreach ( $include_locations as $location ) {
				$this->collect_page_ids_from_value( $location, $pages );
			}
		}

		$exclude_locations = get_post_meta( $post_id, 'ehf_target_exclude_locations', true );
		$exclude_locations = maybe_unserialize( $exclude_locations );

		if ( is_array( $exclude_locations ) && ! empty( $pages ) ) {
			$excluded = array();
			foreach ( $exclude_locations as $location ) {
				$this->collect_page_ids_from_value( $location, $excluded );
			}

			if ( ! empty( $excluded ) ) {
				$pages = array_diff( $pages, $excluded );
			}
		}

		$pages = array_values( array_unique( array_filter( array_map( 'absint', $pages ) ) ) );

		return array(
			'pages' => $pages,
		);
	}

	/**
	 * Collect page IDs from a mixed value.
	 *
	 * @param mixed $value Value to inspect.
	 * @param array $pages Accumulator for page IDs.
	 */
	private function collect_page_ids_from_value( $value, array &$pages ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->collect_page_ids_from_value( $item, $pages );
			}

			return;
		}

		if ( is_numeric( $value ) ) {
			$pages[] = absint( $value );

			return;
		}

		if ( is_string( $value ) ) {
			if ( preg_match_all( '/\d+/', $value, $matches ) && ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $match ) {
					$pages[] = absint( $match );
				}
			}
		}
	}

	/**
	 * Determine if an Elementor Pro template targets the entire site.
	 */
	private function is_elementor_pro_template_global( int $post_id ): bool {
		$conditions = get_post_meta( $post_id, '_elementor_conditions', true );

		if ( is_string( $conditions ) && '' !== $conditions ) {
			$decoded = json_decode( $conditions, true );
			if ( is_array( $decoded ) ) {
				$conditions = $decoded;
			}
		}

		if ( ! is_array( $conditions ) ) {
			return false;
		}

		foreach ( $conditions as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $condition ) {
				if ( ! is_array( $condition ) ) {
					continue;
				}

				$type = isset( $condition['type'] ) ? (string) $condition['type'] : 'include';
				if ( 'include' !== $type ) {
					continue;
				}

				$value = $condition['value'] ?? $condition['sub_name'] ?? $condition['name'] ?? '';
				if ( is_string( $value ) && $this->value_matches_entire_site( $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine if a Header Footer Elementor template targets the entire site.
	 */
	private function is_header_footer_elementor_global( int $post_id ): bool {
		$include_locations = get_post_meta( $post_id, 'ehf_target_include_locations', true );
		$include_locations = maybe_unserialize( $include_locations );

		if ( $this->contains_entire_site_flag( $include_locations ) ) {
			$exclude_locations = get_post_meta( $post_id, 'ehf_target_exclude_locations', true );
			$exclude_locations = maybe_unserialize( $exclude_locations );

			if ( ! $this->contains_entire_site_flag( $exclude_locations ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a value includes an entire site flag.
	 *
	 * @param mixed $value Value to inspect.
	 */
	private function contains_entire_site_flag( $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->contains_entire_site_flag( $item ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_string( $value ) ) {
			return $this->value_matches_entire_site( $value );
		}

		return false;
	}

	/**
	 * Determine whether a string indicates entire site coverage.
	 */
	private function value_matches_entire_site( string $value ): bool {
		$normalized = strtolower( $value );

		return false !== strpos( $normalized, 'entire_site' )
		       || false !== strpos( $normalized, 'entire-site' )
		       || false !== strpos( $normalized, 'entire site' )
		       || false !== strpos( $normalized, 'global' );
	}

	/**
	 * Retrieve a human readable label for template source.
	 */
	private function get_template_source_label( string $source ): string {
		switch ( $source ) {
			case self::TEMPLATE_SOURCE_HEADER_FOOTER:
				return esc_html__( 'Header Footer Elementor', 'elementor-to-gutenberg' );
			case self::TEMPLATE_SOURCE_ELEMENTOR_PRO:
			default:
				return esc_html__( 'Elementor Pro', 'elementor-to-gutenberg' );
		}
	}

	/**
	 * Safely retrieve the modified timestamp for a post.
	 */
	private function get_post_modified_timestamp( WP_Post $post ): int {
		$timestamp = strtotime( (string) $post->post_modified_gmt );
		if ( false === $timestamp || $timestamp <= 0 ) {
			$timestamp = strtotime( (string) $post->post_modified );
		}

		if ( false === $timestamp || $timestamp <= 0 ) {
			$timestamp = time();
		}

		return (int) $timestamp;
	}

	/**
	 * Map a WP_Post instance to the structure expected by the wizard.
	 *
	 * @param WP_Post $post Post object.
	 */
	private function map_page_to_array( WP_Post $post ): array {
		$last_result = get_post_meta( $post->ID, '_ele2gb_last_result', true );
		$status_key  = is_array( $last_result ) && isset( $last_result['status'] ) ? (string) $last_result['status'] : '';
		$last_time   = is_array( $last_result ) && ! empty( $last_result['time'] ) ? (string) $last_result['time'] : '';
		$target_id   = is_array( $last_result ) && ! empty( $last_result['target'] ) ? absint( $last_result['target'] ) : 0;

		$conversion_status = $this->map_status_to_badge( $status_key );

		$last_converted = '';
		if ( '' !== $last_time ) {
			$timestamp = strtotime( $last_time );
			if ( false !== $timestamp ) {
				$last_converted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
			}
		}

		$view_converted = $target_id ? get_permalink( $target_id ) : '';

		return array(
			'id'                => (int) $post->ID,
			'title'             => get_the_title( $post ),
			'status'            => get_post_status( $post ),
			'conversionStatus'  => $conversion_status,
			'lastResultStatus'  => $status_key,
			'lastResultMessage' => is_array( $last_result ) && isset( $last_result['message'] ) ? (string) $last_result['message'] : '',
			'lastConverted'     => $last_converted,
			'hasConflict'       => $target_id > 0,
			'targetId'          => $target_id,
			'viewConvertedUrl'  => $view_converted,
			'editUrl'           => get_edit_post_link( $post ),
			'slug'              => $post->post_name,
		);
	}

	/**
	 * Prepare job pages configuration.
	 *
	 * @param array $page_ids Selected page IDs.
	 * @param array $disabled_meta Page IDs where meta copy is disabled.
	 */
	private function prepare_job_pages( array $page_ids, array $disabled_meta ): array {
		$pages = array();

		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post instanceof WP_Post || 'page' !== get_post_type( $post ) ) {
				continue;
			}

			$pages[] = array(
				'id'        => (int) $page_id,
				'title'     => get_the_title( $post ),
				'keep_meta' => ! in_array( (int) $page_id, $disabled_meta, true ),
			);
		}

		return $pages;
	}

	/**
	 * Normalize template selection payload.
	 *
	 * @param mixed $raw Raw selection data from the request.
	 */
	private function normalize_template_selection( $raw ): array {
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				$raw = array( $raw );
			}
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();
		foreach ( $raw as $value ) {
			if ( is_array( $value ) && isset( $value['id'] ) ) {
				$ids[] = absint( $value['id'] );
			} else {
				$ids[] = absint( $value );
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		return $ids;
	}

	/**
	 * Prepare job templates configuration.
	 */
	private function prepare_job_templates( string $mode, array $selected_headers, array $selected_footers, int $default_header, int $default_footer ): array {
		$detected     = $this->get_header_footer_templates_raw();
		$header_index = array();
		foreach ( $detected['headers'] as $template ) {
			$header_index[ (int) $template['id'] ] = $template;
		}

		$footer_index = array();
		foreach ( $detected['footers'] as $template ) {
			$footer_index[ (int) $template['id'] ] = $template;
		}

		$headers           = array();
		$footers           = array();
		$header_default_id = 0;
		$footer_default_id = 0;

		if ( 'auto' === $mode ) {
			$header_default_id = $this->pick_default_template_id( $detected['headers'] );
			$footer_default_id = $this->pick_default_template_id( $detected['footers'] );

			if ( $header_default_id && isset( $header_index[ $header_default_id ] ) ) {
				$headers[] = $this->build_job_template_entry( $header_index[ $header_default_id ], true );
			}

			if ( $footer_default_id && isset( $footer_index[ $footer_default_id ] ) ) {
				$footers[] = $this->build_job_template_entry( $footer_index[ $footer_default_id ], true );
			}
		} else {
			$header_default_id = $this->determine_custom_default( $selected_headers, $header_index, $default_header );
			$footer_default_id = $this->determine_custom_default( $selected_footers, $footer_index, $default_footer );

			foreach ( $selected_headers as $header_id ) {
				if ( ! isset( $header_index[ $header_id ] ) ) {
					continue;
				}

				$headers[] = $this->build_job_template_entry( $header_index[ $header_id ], $header_default_id === $header_id );
			}

			foreach ( $selected_footers as $footer_id ) {
				if ( ! isset( $footer_index[ $footer_id ] ) ) {
					continue;
				}

				$footers[] = $this->build_job_template_entry( $footer_index[ $footer_id ], $footer_default_id === $footer_id );
			}
		}

		return array(
			'headers'        => $headers,
			'footers'        => $footers,
			'default_header' => $header_default_id,
			'default_footer' => $footer_default_id,
		);
	}

	/**
	 * Pick the default template ID prioritizing global matches.
	 */
	private function pick_default_template_id( array $templates ): int {
		if ( empty( $templates ) ) {
			return 0;
		}

		$global = array_filter(
			$templates,
			static function ( array $template ): bool {
				return ! empty( $template['is_global'] );
			}
		);

		if ( ! empty( $global ) ) {
			usort(
				$global,
				static function ( array $a, array $b ): int {
					return ( $b['modified'] ?? 0 ) <=> ( $a['modified'] ?? 0 );
				}
			);

			return (int) $global[0]['id'];
		}

		usort(
			$templates,
			static function ( array $a, array $b ): int {
				return ( $b['modified'] ?? 0 ) <=> ( $a['modified'] ?? 0 );
			}
		);

		return (int) $templates[0]['id'];
	}

	/**
	 * Build a job template entry for queue processing.
	 */
	private function build_job_template_entry( array $template, bool $is_default ): array {
		$type = 'header' === $template['type'] ? 'header' : 'footer';
		$role = $is_default
			? ( 'header' === $type ? self::TEMPLATE_ROLE_DEFAULT_HEADER : self::TEMPLATE_ROLE_DEFAULT_FOOTER )
			: self::TEMPLATE_ROLE_EXTRA;

		return array(
			'id'           => (int) $template['id'],
			'title'        => $template['title'],
			'source'       => $template['source'],
			'type'         => $type,
			'is_default'   => $is_default,
			'role'         => $role,
			'is_global'    => ! empty( $template['is_global'] ),
			'target_pages' => $template['target_pages'] ?? array(),
		);
	}

	/**
	 * Determine default selection for custom mode.
	 */
	private function determine_custom_default( array $selected_ids, array $index, int $preferred ): int {
		if ( $preferred && in_array( $preferred, $selected_ids, true ) && isset( $index[ $preferred ] ) ) {
			return $preferred;
		}

		foreach ( $selected_ids as $id ) {
			if ( isset( $index[ $id ] ) && ! empty( $index[ $id ]['is_global'] ) ) {
				return (int) $id;
			}
		}

		return $selected_ids[0] ?? 0;
	}

	/**
	 * Build job options based on mode and conflict policy.
	 */
	private function build_job_options( string $mode, string $conflict_policy, bool $skip_converted ): array {
		$options = array(
			'mode'            => 'create',
			'wrap_full_width' => false,
			'assign_template' => false,
			'keep_meta'       => true,
			'skip_converted'  => $skip_converted,
		);

		if ( 'overwrite' === $conflict_policy ) {
			$options['mode']           = 'update';
			$options['skip_converted'] = false;
		} elseif ( 'duplicate' === $conflict_policy ) {
			$options['mode']           = 'create';
			$options['skip_converted'] = false;
		}

		return $options;
	}

	/**
	 * Handle requested theme switch and optional Additional CSS migration.
	 *
	 * @param bool $change_theme Whether a theme switch was requested.
	 * @param string $new_theme Target theme slug/stylesheet.
	 * @param bool $copy_custom_css Whether to copy Additional CSS.
	 * @param string $mode Wizard mode (auto/custom).
	 */
	private function maybe_switch_theme( bool $change_theme, string $new_theme, bool $copy_custom_css, string $mode ) {
		if ( ! $change_theme || '' === $new_theme ) {
			return true;
		}

		$current_stylesheet = get_stylesheet();
		if ( $new_theme === $current_stylesheet ) {
			return true;
		}

		$should_copy_css = $copy_custom_css || 'auto' === $mode;
		$existing_css    = '';

		if ( $should_copy_css ) {
			$existing_css = (string) wp_get_custom_css( $current_stylesheet );
		}

		$themes = wp_get_themes();
		if ( ! isset( $themes[ $new_theme ] ) ) {
			$install = $this->install_theme_if_needed( $new_theme );
			if ( is_wp_error( $install ) ) {
				return $install;
			}
			$themes = wp_get_themes();
			if ( ! isset( $themes[ $new_theme ] ) ) {
				return new WP_Error( 'ele2gb-theme-missing', esc_html__( 'The selected theme is not available.', 'elementor-to-gutenberg' ) );
			}
		}

		switch_theme( $new_theme );

		if ( get_stylesheet() !== $new_theme ) {
			return new WP_Error( 'ele2gb-theme-switch-failed', esc_html__( 'Unable to switch to the selected theme.', 'elementor-to-gutenberg' ) );
		}

		if ( $should_copy_css && '' !== trim( $existing_css ) ) {
			$new_theme_css = (string) wp_get_custom_css( $new_theme );
			$comment       = '/* Migrated from ' . $current_stylesheet . ' */';
			$combined_css  = $new_theme_css;

			if ( '' !== trim( $combined_css ) ) {
				$combined_css .= "\n\n";
			}

			$combined_css .= $comment . "\n" . $existing_css;

			$result = wp_update_custom_css_post(
				$combined_css,
				array(
					'stylesheet' => $new_theme,
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Install a theme if it is not already available.
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return true|WP_Error
	 */
	private function install_theme_if_needed( string $slug ) {
		// If the theme is already installed, nothing to do.
		$themes = wp_get_themes();
		if ( isset( $themes[ $slug ] ) ) {
			return true;
		}

		if ( ! current_user_can( 'install_themes' ) ) {
			return new WP_Error(
				'ele2gb-theme-install-permissions',
				esc_html__( 'You do not have permission to install themes.', 'elementor-to-gutenberg' )
			);
		}

		// Load required admin helpers.
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! function_exists( 'themes_api' ) ) {
			return new WP_Error(
				'ele2gb-theme-install-missing-api',
				esc_html__( 'Theme installation API is not available on this site.', 'elementor-to-gutenberg' )
			);
		}

		// Fetch theme information from WordPress.org.
		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( empty( $api->download_link ) ) {
			return new WP_Error(
				'ele2gb-theme-install-no-download',
				esc_html__( 'Could not find a download link for the selected theme.', 'elementor-to-gutenberg' )
			);
		}

		// Use Theme_Upgrader to download and install the theme.
		$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );

		$result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error(
				'ele2gb-theme-install-failed',
				esc_html__( 'Theme installation failed.', 'elementor-to-gutenberg' )
			);
		}

		// Refresh the theme cache so wp_get_themes() can see the new theme.
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
		}

		return true;
	}

	/**
	 * Process a single page conversion.
	 *
	 * @param int $post_id Post ID.
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
			$message           = esc_html__( 'Skipped: only pages can be converted.', 'elementor-to-gutenberg' );
			$result['message'] = $message;

			return $result;
		}

		$target_id = $this->get_existing_target_id( $post_id );
		if ( ! empty( $options['skip_converted'] ) && $this->has_been_converted( $post_id, $target_id ) ) {
			$title             = get_the_title( $post_id );
			$message           = sprintf( esc_html__( 'Skipped: “%s” is already converted.', 'elementor-to-gutenberg' ), $title );
			$result['message'] = $message;
			$result['target']  = $target_id;

			return $result;
		}

		$json_data = get_post_meta( $post_id, '_elementor_data', true );
		$template  = (string) get_page_template_slug( $post_id );
		if ( empty( $json_data ) ) {
			$message           = esc_html__( 'Skipped: Elementor data not found.', 'elementor-to-gutenberg' );
			$result['message'] = $message;

			return $result;
		}

		$decoded = json_decode( $json_data, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			$message           = esc_html__( 'Failed: invalid Elementor JSON data.', 'elementor-to-gutenberg' );
			$result['status']  = 'error';
			$result['message'] = $message;

			return $result;
		}

		$content = Admin_Settings::instance()->convert_json_to_gutenberg_content( array( 'content' => $decoded ) );
		if ( '' === trim( $content ) ) {
			$message           = esc_html__( 'Failed: conversion produced no Gutenberg content.', 'elementor-to-gutenberg' );
			$result['status']  = 'error';
			$result['message'] = $message;

			return $result;
		}

		if ( ! empty( $options['wrap_full_width'] ) ) {
			$content = Admin_Settings::instance()->wrap_content_full_width( $content );
		}

		if ( 'update' === $options['mode'] ) {
			$save      = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				),
				true
			);
			$target_id = is_wp_error( $save ) ? 0 : (int) $save;
		} else {
			$save      = wp_insert_post(
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
			$message           = esc_html__( 'Failed: could not save Gutenberg content.', 'elementor-to-gutenberg' );
			$result['status']  = 'error';
			$result['message'] = $message;

			return $result;
		}

		if ( ! empty( $options['assign_template'] ) ) {
			update_post_meta( $target_id, '_wp_page_template', self::TEMPLATE_SLUG );
		}

		$this->normalize_page_template( $template, $target_id );

		if ( 'update' === $options['mode'] && ! empty( $options['keep_meta'] ) ) {
			$this->copy_post_meta( $post_id, $target_id, true );
		}

		$title   = get_the_title( $post_id );
		$message = sprintf( esc_html__( 'Converted “%s” to Gutenberg blocks.', 'elementor-to-gutenberg' ), $title );

		$result['status']  = 'success';
		$result['message'] = $message;
		$result['target']  = $target_id;

		return $result;
	}

	/**
	 * Copy non-Elementor meta values to the target post.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $target_id Target post ID.
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
	 * Convert a header/footer template into a Gutenberg template part.
	 */
	private function process_template_conversion( array $template_info, array $options ): array {
		$result = array(
			'status'   => 'skipped',
			'message'  => '',
			'target'   => 0,
			'view_url' => '',
		);

		$post = get_post( (int) $template_info['id'] );
		if ( ! $post instanceof WP_Post ) {
			$result['message'] = esc_html__( 'Skipped: template not found.', 'elementor-to-gutenberg' );

			return $result;
		}

		$existing_target = $this->find_existing_template_part( (int) $template_info['id'], (string) $template_info['source'] );

		$last_result = get_post_meta( $post->ID, '_ele2gb_last_result', true );
		if ( ! $existing_target && is_array( $last_result ) && ! empty( $last_result['target'] ) ) {
			$existing_target = absint( $last_result['target'] );
		}

		$last_status = is_array( $last_result ) && isset( $last_result['status'] ) ? (string) $last_result['status'] : '';

		if ( ! empty( $options['skip_converted'] ) && 'success' === $last_status && $existing_target ) {
			$this->update_template_part_role( $existing_target, (string) $template_info['role'], (string) $template_info['type'] );
			$message = esc_html__( 'Skipped: template already converted.', 'elementor-to-gutenberg' );

			$edit_link = $existing_target ? get_edit_post_link( $existing_target, '' ) : '';
			if ( $existing_target && ! $edit_link ) {
				$edit_link = admin_url( 'post.php?post=' . $existing_target . '&action=edit' );
			}

			$result['message']  = $message;
			$result['target']   = $existing_target;
			$result['view_url'] = (string) $edit_link;

			return $result;
		}

		$json_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( empty( $json_data ) ) {
			$message   = esc_html__( 'Skipped: Elementor data not found.', 'elementor-to-gutenberg' );
			$edit_link = $existing_target ? get_edit_post_link( $existing_target, '' ) : '';
			if ( $existing_target && ! $edit_link ) {
				$edit_link = admin_url( 'post.php?post=' . $existing_target . '&action=edit' );
			}

			$result['message']  = $message;
			$result['target']   = $existing_target;
			$result['view_url'] = (string) $edit_link;

			return $result;
		}

		$decoded = json_decode( $json_data, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			$message   = esc_html__( 'Failed: invalid Elementor JSON data.', 'elementor-to-gutenberg' );
			$edit_link = $existing_target ? get_edit_post_link( $existing_target, '' ) : '';
			if ( $existing_target && ! $edit_link ) {
				$edit_link = admin_url( 'post.php?post=' . $existing_target . '&action=edit' );
			}

			$result['status']   = 'error';
			$result['message']  = $message;
			$result['target']   = $existing_target;
			$result['view_url'] = (string) $edit_link;

			return $result;
		}

		$content = Admin_Settings::instance()->convert_json_to_gutenberg_content( array( 'content' => $decoded ) );
		if ( '' === trim( $content ) ) {
			$message   = esc_html__( 'Failed: conversion produced no Gutenberg content.', 'elementor-to-gutenberg' );
			$edit_link = $existing_target ? get_edit_post_link( $existing_target, '' ) : '';
			if ( $existing_target && ! $edit_link ) {
				$edit_link = admin_url( 'post.php?post=' . $existing_target . '&action=edit' );
			}

			$result['status']   = 'error';
			$result['message']  = $message;
			$result['target']   = $existing_target;
			$result['view_url'] = (string) $edit_link;

			return $result;
		}

		$target_id = $this->save_template_part( $template_info, $post, $content, $existing_target );
		if ( ! $target_id ) {
			$message           = esc_html__( 'Failed: could not save Gutenberg template.', 'elementor-to-gutenberg' );
			$result['status']  = 'error';
			$result['message'] = $message;
			$result['target']  = $existing_target;

			return $result;
		}

		$this->store_template_part_meta( $target_id, $template_info );
		$this->update_template_part_role( $target_id, (string) $template_info['role'], (string) $template_info['type'] );

		// force it to become the actual default template part in the active block theme.
		$is_global   = ! empty( $template_info['is_global'] );
		$has_targets = ! empty( $template_info['target_pages'] ) && is_array( $template_info['target_pages'] );

		if ( self::TEMPLATE_ROLE_DEFAULT_HEADER === $template_info['role'] && 'header' === $template_info['type'] ) {
			$this->force_block_theme_default_header( $target_id );
		}

		if ( self::TEMPLATE_ROLE_DEFAULT_FOOTER === $template_info['role'] && 'footer' === $template_info['type'] ) {
			$this->force_block_theme_default_footer( $target_id );
		}

		if ( $has_targets ) {

			$this->link_template_part_to_target_pages( $target_id, $template_info );
		}

		$label   = 'header' === $template_info['type'] ? esc_html__( 'header', 'elementor-to-gutenberg' ) : esc_html__( 'footer', 'elementor-to-gutenberg' );
		$title   = get_the_title( $post );
		$message = sprintf( esc_html__( 'Converted %1$s “%2$s”.', 'elementor-to-gutenberg' ), $label, $title );


		$edit_link = get_edit_post_link( $target_id, '' );
		if ( ! $edit_link ) {
			$edit_link = admin_url( 'post.php?post=' . $target_id . '&action=edit' );
		}

		$result['status']   = 'success';
		$result['message']  = $message;
		$result['target']   = $target_id;
		$result['view_url'] = (string) $edit_link;

		return $result;
	}

	/**
	 * Create or update a template part post for the converted template.
	 */
	private function save_template_part( array $template_info, WP_Post $source_post, string $content, int $existing_target ): int {
		$slug = sanitize_title( sprintf( 'converted-%s-%d', $template_info['type'], $source_post->ID ) );

		$title_format = esc_html__( 'Converted %1$s: %2$s', 'elementor-to-gutenberg' );
		$label        = 'header' === $template_info['type'] ? esc_html__( 'Header', 'elementor-to-gutenberg' ) : esc_html__( 'Footer', 'elementor-to-gutenberg' );
		$post_title   = sprintf( $title_format, $label, get_the_title( $source_post ) );

		$postarr = array(
			'post_title'   => $post_title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'wp_template_part',
			'post_author'  => (int) $source_post->post_author,
		);

		if ( $existing_target ) {
			$postarr['ID'] = $existing_target;
			$saved         = wp_update_post( $postarr, true );
		} else {
			$saved = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $saved ) || ! $saved ) {
			return 0;
		}

		$target_id = (int) $saved;

		$theme = get_stylesheet();
		if ( $theme ) {
			wp_set_post_terms( $target_id, array( $theme ), 'wp_theme', false );
		}

		wp_set_post_terms( $target_id, array( $template_info['type'] ), 'wp_template_part_area', false );

		return $target_id;
	}

	/**
	 * Persist meta for a converted template part.
	 */
	private function store_template_part_meta( int $target_id, array $template_info ): void {
		update_post_meta( $target_id, '_ele2gb_source_id', (int) $template_info['id'] );
		update_post_meta( $target_id, '_ele2gb_source_type', (string) $template_info['source'] );
		update_post_meta( $target_id, '_ele2gb_template_kind', (string) $template_info['type'] );
	}

	/**
	 * Update the role meta for a converted template part.
	 */
	private function update_template_part_role( int $target_id, string $role, string $type ): void {
		if ( ! $target_id ) {
			return;
		}

		if ( self::TEMPLATE_ROLE_DEFAULT_HEADER === $role || self::TEMPLATE_ROLE_DEFAULT_FOOTER === $role ) {
			$this->clear_existing_template_role( $target_id, $role, $type );
		}

		update_post_meta( $target_id, '_ele2gb_template_role', $role );
	}

	/**
	 * Force a converted template part to act as the default FSE header.
	 *
	 * @param int $target_id Template part post ID.
	 */
	private function force_block_theme_default_header( int $target_id ): void {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		$post = get_post( $target_id );
		if ( ! $post instanceof WP_Post || 'wp_template_part' !== $post->post_type ) {
			return;
		}

		$theme = get_stylesheet();

		// Attach the template part to the current theme and mark it as header area.
		if ( $theme ) {
			wp_set_post_terms( $target_id, array( $theme ), 'wp_theme', false );
		}
		wp_set_post_terms( $target_id, array( 'header' ), 'wp_template_part_area', false );

		// Find any existing "header" template parts.
		$existing_query = new WP_Query(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => - 1,
				'name'           => 'header',
				'tax_query'      => array(
					'relation' => 'AND',
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => array( $theme ),
					),
					array(
						'taxonomy' => 'wp_template_part_area',
						'field'    => 'slug',
						'terms'    => array( 'header' ),
					),
				),
			)
		);

		if ( $existing_query->have_posts() ) {
			foreach ( $existing_query->posts as $existing ) {
				$existing_id = (int) $existing->ID;
				if ( $existing_id === $target_id ) {
					continue;
				}

				update_post_meta( $existing_id, '_ele2gb_template_role', self::TEMPLATE_ROLE_EXTRA );
			}
		}

		wp_reset_postdata();

		wp_update_post(
			array(
				'ID'        => $target_id,
				'post_name' => 'header',
			)
		);
	}

	/**
	 * Force a converted template part to act as the default FSE footer.
	 *
	 * @param int $target_id Template part post ID.
	 */
	private function force_block_theme_default_footer( int $target_id ): void {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		$post = get_post( $target_id );
		if ( ! $post instanceof WP_Post || 'wp_template_part' !== $post->post_type ) {
			return;
		}

		$theme = get_stylesheet();

		if ( $theme ) {
			wp_set_post_terms( $target_id, array( $theme ), 'wp_theme', false );
		}
		wp_set_post_terms( $target_id, array( 'footer' ), 'wp_template_part_area', false );

		$existing_query = new WP_Query(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => - 1,
				'name'           => 'footer',
				'tax_query'      => array(
					'relation' => 'AND',
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => array( $theme ),
					),
					array(
						'taxonomy' => 'wp_template_part_area',
						'field'    => 'slug',
						'terms'    => array( 'footer' ),
					),
				),
			)
		);

		if ( $existing_query->have_posts() ) {
			foreach ( $existing_query->posts as $existing ) {
				$existing_id = (int) $existing->ID;
				if ( $existing_id === $target_id ) {
					continue;
				}

				update_post_meta( $existing_id, '_ele2gb_template_role', self::TEMPLATE_ROLE_EXTRA );
			}
		}

		wp_reset_postdata();

		wp_update_post(
			array(
				'ID'        => $target_id,
				'post_name' => 'footer',
			)
		);
	}

	/**
	 * Link a template part to specific converted pages via page templates.
	 *
	 * @param int $target_id Template part post ID.
	 * @param array $template_info Template info array.
	 */
	private function link_template_part_to_target_pages( int $target_id, array $template_info ): void {
		if ( ! $target_id || empty( $template_info['target_pages'] ) || ! is_array( $template_info['target_pages'] ) ) {
			return;
		}

		$linked_pages = array();

		foreach ( $template_info['target_pages'] as $source_page_id ) {
			$source_page_id = absint( $source_page_id );
			if ( ! $source_page_id ) {
				continue;
			}

			$converted_page_id = $this->get_existing_target_id( $source_page_id );
			if ( ! $converted_page_id ) {
				continue;
			}

			$template_id = $this->ensure_page_template_for_page( $converted_page_id, $template_info, $target_id );

			if ( $template_id ) {
				$linked_pages[] = $converted_page_id;
			}
		}

		if ( ! empty( $linked_pages ) ) {
			update_post_meta( $target_id, '_ele2gb_linked_pages', array_values( array_unique( $linked_pages ) ) );
		}
	}

	/**
	 * Create or update a page-specific template for a converted page.
	 *
	 * @param int $converted_page_id Converted Gutenberg page ID.
	 * @param array $template_info Template info array.
	 * @param int $target_id Template part ID to link.
	 */
	private function ensure_page_template_for_page( int $converted_page_id, array $template_info, int $target_id ): int {
		$theme = get_stylesheet();
		$slug  = sanitize_title( 'page-' . $converted_page_id );

		$existing_id = $this->find_page_template_for_page( $slug, $theme );

		$header_part_id = $existing_id ? (int) get_post_meta( $existing_id, '_ele2gb_header_part', true ) : 0;
		$footer_part_id = $existing_id ? (int) get_post_meta( $existing_id, '_ele2gb_footer_part', true ) : 0;

		if ( 'header' === $template_info['type'] ) {
			$header_part_id = $target_id;
		} elseif ( 'footer' === $template_info['type'] ) {
			$footer_part_id = $target_id;
		}

		$header_slug = $header_part_id ? (string) get_post_field( 'post_name', $header_part_id ) : '';
		$footer_slug = $footer_part_id ? (string) get_post_field( 'post_name', $footer_part_id ) : '';

		$content = $this->build_page_template_content( $header_slug, $footer_slug );

		$title_format = esc_html__( 'Page Template: %s', 'elementor-to-gutenberg' );
		$post_title   = sprintf( $title_format, get_the_title( $converted_page_id ) );

		$postarr = array(
			'post_title'   => $post_title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'wp_template',
			'post_author'  => (int) get_post_field( 'post_author', $converted_page_id ),
		);

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$saved         = wp_update_post( $postarr, true );
		} else {
			$saved = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $saved ) || ! $saved ) {
			return 0;
		}

		$template_id = (int) $saved;

		if ( $theme ) {
			wp_set_post_terms( $template_id, array( $theme ), 'wp_theme', false );
		}

		update_post_meta( $template_id, '_ele2gb_header_part', $header_part_id );
		update_post_meta( $template_id, '_ele2gb_footer_part', $footer_part_id );
		update_post_meta( $template_id, '_ele2gb_page_id', $converted_page_id );

		update_post_meta( $converted_page_id, '_wp_page_template', $slug );

		return $template_id;
	}

	/**
	 * Find an existing page-specific template for a page and theme.
	 *
	 * @param string $slug Template slug.
	 * @param string $theme Current theme slug.
	 */
	private function find_page_template_for_page( string $slug, string $theme ): int {
		$query_args = array(
			'post_type'      => 'wp_template',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'name'           => $slug,
		);

		if ( $theme ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => array( $theme ),
				),
			);
		}

		$query = new WP_Query( $query_args );
		$found = $query->have_posts() ? (int) $query->posts[0] : 0;

		wp_reset_postdata();

		return $found;
	}

	/**
	 * Build block content for a page template with optional header/footer parts.
	 *
	 * @param string $header_slug Header template part slug.
	 * @param string $footer_slug Footer template part slug.
	 */
	private function build_page_template_content( string $header_slug, string $footer_slug ): string {
		$theme  = get_stylesheet();
		$blocks = array();

		if ( '' !== $header_slug ) {
			$blocks[] = sprintf(
				'<!-- wp:template-part {"slug":"%1$s","theme":"%2$s","tagName":"header"} /-->',
				$header_slug,
				$theme
			);
		}

		$blocks[] = '<!-- wp:post-content /-->';

		if ( '' !== $footer_slug ) {
			$blocks[] = sprintf(
				'<!-- wp:template-part {"slug":"%1$s","theme":"%2$s","tagName":"footer"} /-->',
				$footer_slug,
				$theme
			);
		}

		return implode( "\n", $blocks );
	}

	/**
	 * Ensure only one template part keeps a default role.
	 */
	private function clear_existing_template_role( int $target_id, string $role, string $type ): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => - 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ele2gb_template_role',
						'value' => $role,
					),
					array(
						'key'   => '_ele2gb_template_kind',
						'value' => $type,
					),
				),
			)
		);

		foreach ( $query->posts as $other_id ) {
			$other_id = (int) $other_id;
			if ( $other_id === $target_id ) {
				continue;
			}

			update_post_meta( $other_id, '_ele2gb_template_role', self::TEMPLATE_ROLE_EXTRA );
		}

		wp_reset_postdata();
	}

	/**
	 * Locate an existing converted template part for a source template.
	 */
	private function find_existing_template_part( int $source_id, string $source_type ): int {
		$query = new WP_Query(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ele2gb_source_id',
						'value' => $source_id,
					),
					array(
						'key'   => '_ele2gb_source_type',
						'value' => $source_type,
					),
				),
			)
		);

		$existing = $query->have_posts() ? (int) $query->posts[0] : 0;

		wp_reset_postdata();

		return $existing;
	}

	/**
	 * Store job in a transient.
	 *
	 * @param array $job Job data.
	 */
	private function store_job( array $job ): void {
		set_transient( self::JOB_TRANSIENT_PREFIX . $job['id'], $job, self::JOB_TRANSIENT_TTL );
	}

	/**
	 * Retrieve a stored job.
	 */
	private function get_job( string $job_id ): array {
		$job = get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $job ) ) {
			return array();
		}

		return $job;
	}

	/**
	 * Count the total number of items (pages + templates) in a job.
	 */
	private function count_job_items( array $job ): int {
		return count( $this->get_job_queue( $job ) );
	}

	/**
	 * Flatten the job items into a sequential queue.
	 */
	private function get_job_queue( array $job ): array {
		$queue = array();

		foreach ( $job['pages'] as $page ) {
			$queue[] = array(
				'type' => 'page',
				'data' => $page,
			);
		}

		$templates = $job['templates'] ?? array();
		foreach ( $templates['headers'] ?? array() as $header ) {
			$queue[] = array(
				'type' => 'header',
				'data' => $header,
			);
		}

		foreach ( $templates['footers'] ?? array() as $footer ) {
			$queue[] = array(
				'type' => 'footer',
				'data' => $footer,
			);
		}

		return $queue;
	}

	/**
	 * Format job data for JSON responses.
	 *
	 * @param array $job Job data.
	 */
	private function format_job_for_response( array $job ): array {
		$total     = $this->count_job_items( $job );
		$processed = min( (int) $job['processed'], $total );

		$duration = 0;
		if ( ! empty( $job['started_at'] ) ) {
			$end_time = ! empty( $job['completed_at'] ) ? (int) $job['completed_at'] : time();
			$duration = max( 0, $end_time - (int) $job['started_at'] );
		}

		$results = array_map(
			static function ( array $item ): array {
				return array(
					'id'       => (int) $item['id'],
					'title'    => $item['title'],
					'status'   => $item['status'],
					'message'  => $item['message'],
					'target'   => $item['target'],
					'duration' => $item['duration'],
					'viewUrl'  => $item['view_url'],
					'keepMeta' => ! empty( $item['keep_meta'] ),
					'type'     => isset( $item['type'] ) ? (string) $item['type'] : 'page',
					'role'     => isset( $item['role'] ) ? (string) $item['role'] : '',
					'source'   => isset( $item['source'] ) ? (string) $item['source'] : '',
				);
			},
			$job['results']
		);

		$templates = array(
			'headers'  => array_map(
				static function ( array $item ): array {
					return array(
						'id'        => (int) $item['id'],
						'title'     => $item['title'],
						'source'    => $item['source'],
						'type'      => $item['type'],
						'isDefault' => ! empty( $item['is_default'] ),
						'role'      => $item['role'],
					);
				},
				$job['templates']['headers'] ?? array()
			),
			'footers'  => array_map(
				static function ( array $item ): array {
					return array(
						'id'        => (int) $item['id'],
						'title'     => $item['title'],
						'source'    => $item['source'],
						'type'      => $item['type'],
						'isDefault' => ! empty( $item['is_default'] ),
						'role'      => $item['role'],
					);
				},
				$job['templates']['footers'] ?? array()
			),
			'defaults' => array(
				'header' => isset( $job['templates']['default_header'] ) ? (int) $job['templates']['default_header'] : 0,
				'footer' => isset( $job['templates']['default_footer'] ) ? (int) $job['templates']['default_footer'] : 0,
			),
		);

		return array(
			'id'             => $job['id'],
			'status'         => $job['status'],
			'mode'           => $job['mode'],
			'conflictPolicy' => $job['conflict_policy'],
			'skipConverted'  => ! empty( $job['options']['skip_converted'] ),
			'total'          => $total,
			'processed'      => $processed,
			'counts'         => $job['counts'],
			'results'        => $results,
			'templates'      => $templates,
			'createdAt'      => (int) $job['created_at'],
			'startedAt'      => (int) $job['started_at'],
			'completedAt'    => (int) $job['completed_at'],
			'duration'       => $duration,
		);
	}

	/**
	 * Map status value to a badge slug.
	 */
	private function map_status_to_badge( string $status ): string {
		switch ( $status ) {
			case 'success':
				return 'converted';
			case 'partial':
				return 'partial';
			case 'error':
				return 'error';
			case 'skipped':
				return 'skipped';
			case 'cancelled':
				return 'cancelled';
			default:
				return 'not_converted';
		}
	}

	/**
	 * Retrieve localized strings for the UI.
	 */
	private function get_strings(): array {
		return array(
			'step'                   => esc_html__( 'Step %1$s of %2$s — %3$s', 'elementor-to-gutenberg' ),
			'modeTitle'              => esc_html__( 'Choose Mode', 'elementor-to-gutenberg' ),
			'modeAutoTitle'          => esc_html__( 'Convert all pages automatically', 'elementor-to-gutenberg' ),
			'modeAutoDesc'           => esc_html__( 'Run with smart defaults: copy meta & featured image, skip pages already converted.', 'elementor-to-gutenberg' ),
			'modeCustomTitle'        => esc_html__( 'Choose specific pages', 'elementor-to-gutenberg' ),
			'modeCustomDesc'         => esc_html__( 'Pick exactly which pages to convert and fine-tune options per page.', 'elementor-to-gutenberg' ),
			'continue'               => esc_html__( 'Continue', 'elementor-to-gutenberg' ),
			'back'                   => esc_html__( 'Back', 'elementor-to-gutenberg' ),
			'selectPagesTitle'       => esc_html__( 'Select Pages', 'elementor-to-gutenberg' ),
			'selectAll'              => esc_html__( 'Select all', 'elementor-to-gutenberg' ),
			'selectionSummary'       => esc_html__( '%1$d selected / %2$d total', 'elementor-to-gutenberg' ),
			'noPagesFound'           => esc_html__( 'No Elementor pages found for conversion.', 'elementor-to-gutenberg' ),
			'skipConverted'          => esc_html__( 'Skip pages that were already converted', 'elementor-to-gutenberg' ),
			'disableMeta'            => esc_html__( 'Don’t copy meta fields & featured image', 'elementor-to-gutenberg' ),
			'conflictsTitle'         => esc_html__( 'Resolve Conflicts', 'elementor-to-gutenberg' ),
			'conflictDetected'       => esc_html__( '%1$d selected pages already have a converted version.', 'elementor-to-gutenberg' ),
			'conflictOverwrite'      => esc_html__( 'Update existing pages in place (overwrite)', 'elementor-to-gutenberg' ),
			'conflictSkip'           => esc_html__( 'Skip those pages', 'elementor-to-gutenberg' ),
			'conflictDuplicate'      => esc_html__( 'Create duplicates with “(Converted)” suffix', 'elementor-to-gutenberg' ),
			'themeStepTitle'         => esc_html__( 'Theme compatibility', 'elementor-to-gutenberg' ),
			'themeStepDesc'          => esc_html__( 'Block themes work best with Gutenberg. You can keep your current theme or switch to a compatible one before conversion.', 'elementor-to-gutenberg' ),
			'themeCurrentGood'       => esc_html__( 'Your current theme already supports Gutenberg and block templates.', 'elementor-to-gutenberg' ),
			'themeSelectPrompt'      => esc_html__( 'Select a block theme for best compatibility.', 'elementor-to-gutenberg' ),
			'themeKeepCurrent'       => esc_html__( 'Keep current theme', 'elementor-to-gutenberg' ),
			'themeSuggestedCore'     => esc_html__( 'Suggested core block themes', 'elementor-to-gutenberg' ),
			'themeInstalledList'     => esc_html__( 'Installed block themes', 'elementor-to-gutenberg' ),
			'themeNoInstalled'       => esc_html__( 'No compatible block themes are installed.', 'elementor-to-gutenberg' ),
			'copyAdditionalCss'      => esc_html__( 'Copy Additional CSS from the current theme', 'elementor-to-gutenberg' ),
			'themeSwitchError'       => esc_html__( 'Unable to switch themes. Please try again or choose a different theme.', 'elementor-to-gutenberg' ),
			'themeActiveLabel'       => esc_html__( 'Active', 'elementor-to-gutenberg' ),
			'reviewTitle'            => esc_html__( 'Review & Confirm', 'elementor-to-gutenberg' ),
			'reviewSummary'          => esc_html__( '%1$d pages selected — %2$d will be converted, %3$d skipped.', 'elementor-to-gutenberg' ),
			'metaDisabled'           => esc_html__( '%1$d pages will be converted without copying meta fields or featured image.', 'elementor-to-gutenberg' ),
			'startConversion'        => esc_html__( 'Start Conversion', 'elementor-to-gutenberg' ),
			'backgroundInfo'         => esc_html__( 'Conversion runs in the background. You can safely close this page.', 'elementor-to-gutenberg' ),
			'progressTitle'          => esc_html__( 'Progress & Results', 'elementor-to-gutenberg' ),
			'converted'              => esc_html__( 'Converted', 'elementor-to-gutenberg' ),
			'skipped'                => esc_html__( 'Skipped', 'elementor-to-gutenberg' ),
			'errors'                 => esc_html__( 'Errors', 'elementor-to-gutenberg' ),
			'duration'               => esc_html__( 'Duration', 'elementor-to-gutenberg' ),
			'viewConverted'          => esc_html__( 'View converted', 'elementor-to-gutenberg' ),
			'retry'                  => esc_html__( 'Retry', 'elementor-to-gutenberg' ),
			'viewPages'              => esc_html__( 'View converted pages', 'elementor-to-gutenberg' ),
			'startNew'               => esc_html__( 'Start new conversion', 'elementor-to-gutenberg' ),
			'statusConverted'        => esc_html__( 'Converted', 'elementor-to-gutenberg' ),
			'statusNotConverted'     => esc_html__( 'Not converted', 'elementor-to-gutenberg' ),
			'statusPartial'          => esc_html__( 'Partial', 'elementor-to-gutenberg' ),
			'statusError'            => esc_html__( 'Error', 'elementor-to-gutenberg' ),
			'statusSkipped'          => esc_html__( 'Skipped', 'elementor-to-gutenberg' ),
			'statusUnknown'          => esc_html__( 'Unknown', 'elementor-to-gutenberg' ),
			'tableTitle'             => esc_html__( 'Title', 'elementor-to-gutenberg' ),
			'tableStatus'            => esc_html__( 'Status', 'elementor-to-gutenberg' ),
			'tableConversionStatus'  => esc_html__( 'Conversion status', 'elementor-to-gutenberg' ),
			'tableLastConverted'     => esc_html__( 'Last converted', 'elementor-to-gutenberg' ),
			'tableActions'           => esc_html__( 'Actions', 'elementor-to-gutenberg' ),
			'jobCompleted'           => esc_html__( 'Conversion completed successfully in %s.', 'elementor-to-gutenberg' ),
			'jobCompletedWithErrors' => esc_html__( 'Conversion finished with issues in %s.', 'elementor-to-gutenberg' ),
			'jobRunning'             => esc_html__( 'Conversion in progress…', 'elementor-to-gutenberg' ),
			'resumeJob'              => esc_html__( 'Resuming an active conversion job.', 'elementor-to-gutenberg' ),
			'processing'             => esc_html__( 'Processing…', 'elementor-to-gutenberg' ),
			'noSelectionError'       => esc_html__( 'Select at least one page or template before continuing.', 'elementor-to-gutenberg' ),
			'retryFailed'            => esc_html__( 'Unable to retry conversion. Please try again.', 'elementor-to-gutenberg' ),
			'headerFooterStepTitle'  => esc_html__( 'Header & Footer Templates', 'elementor-to-gutenberg' ),
			'headersLabel'           => esc_html__( 'Headers', 'elementor-to-gutenberg' ),
			'footersLabel'           => esc_html__( 'Footers', 'elementor-to-gutenberg' ),
			'defaultHeaderLabel'     => esc_html__( 'Default header after conversion', 'elementor-to-gutenberg' ),
			'defaultFooterLabel'     => esc_html__( 'Default footer after conversion', 'elementor-to-gutenberg' ),
			'headerFooterSummary'    => esc_html__( '%1$d headers and %2$d footers selected for conversion.', 'elementor-to-gutenberg' ),
			'headerFooterDefaults'   => esc_html__( 'Default header: %1$s — Default footer: %2$s', 'elementor-to-gutenberg' ),
			'cancel'                 => esc_html__( 'Cancel', 'elementor-to-gutenberg' ),
			'jobCancelled'           => esc_html__( 'Conversion was cancelled.', 'elementor-to-gutenberg' ),
		);
	}

	/**
	 * Retrieve active job info for current user if any.
	 */
	private function get_active_job_for_user(): array {
		$job_id = get_user_meta( get_current_user_id(), '_ele2gb_job', true );
		if ( empty( $job_id ) ) {
			return array();
		}

		$job = $this->get_job( (string) $job_id );
		if ( empty( $job ) ) {
			delete_user_meta( get_current_user_id(), '_ele2gb_job' );

			return array();
		}

		return $this->format_job_for_response( $job );
	}

	/**
	 * Delete a stored job.
	 *
	 * @param string $job_id Job ID.
	 */
	private function delete_job( string $job_id ): void {
		delete_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
	}

	/**
	 * Cancel an existing conversion job via AJAX.
	 */
	public function ajax_cancel_job(): void {
		$this->verify_ajax_request();

		$job_id = isset( $_POST['jobId'] ) ? sanitize_text_field( wp_unslash( $_POST['jobId'] ) ) : '';

		if ( '' === $job_id ) {
			$job_id = (string) get_user_meta( get_current_user_id(), '_ele2gb_job', true );
		}

		if ( '' === $job_id ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No active conversion job to cancel.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$job = $this->get_job( $job_id );
		if ( empty( $job ) || (int) $job['user_id'] !== get_current_user_id() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Conversion job could not be found.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$job['status']       = 'cancelled';
		$job['completed_at'] = time();

		$this->store_job( $job );
		delete_user_meta( get_current_user_id(), '_ele2gb_job' );
		$this->delete_job( $job_id );

		wp_send_json_success(
			array(
				'job' => $this->format_job_for_response( $job ),
			)
		);
	}

	/**
	 * Store conversion result meta for a normal page.
	 *
	 * @param int $source_id Source Elementor page ID.
	 * @param array $result_entry Result entry from the job.
	 */
	private function store_page_conversion_result( int $source_id, array $result_entry ): void {
		$time = gmdate( 'Y-m-d H:i:s' );

		$data = array(
			'status'  => $result_entry['status'],
			'message' => $result_entry['message'],
			'target'  => $result_entry['target'],
			'time'    => $time,
		);

		// Store on the original Elementor page.
		update_post_meta( $source_id, '_ele2gb_last_result', $data );

		// Store also on the converted page (if any).
		if ( ! empty( $result_entry['target'] ) ) {
			update_post_meta( $result_entry['target'], '_ele2gb_last_result', $data );
		}

		// Mark as "converted" only when success.
		if ( 'success' === $result_entry['status'] ) {
			update_post_meta( $source_id, '_ele2gb_last_converted', $time );

			if ( ! empty( $result_entry['target'] ) ) {
				update_post_meta( $result_entry['target'], '_ele2gb_last_converted', $time );
			}
		}
	}

	/**
	 * Ensure Elementor-specific templates are reset to the default template after conversion.
	 *
	 * @param string $source_template Template slug from the source page.
	 * @param int $target_id Converted page ID.
	 */
	private function normalize_page_template( string $source_template, int $target_id ): void {
		if ( ! $target_id ) {
			return;
		}

		if ( '' === $source_template || 'default' === $source_template ) {
			return;
		}

		if ( ! $this->is_elementor_template_slug( $source_template ) ) {
			return;
		}

		update_post_meta( $target_id, '_wp_page_template', 'default' );
	}

	/**
	 * Determine whether the template slug belongs to an Elementor template.
	 *
	 * @param string $template_slug Template slug to check.
	 */
	private function is_elementor_template_slug( string $template_slug ): bool {
		if ( '' === $template_slug ) {
			return false;
		}

		return 0 === strpos( $template_slug, 'elementor' );
	}

	/**
	 * Store conversion result meta for a header/footer template.
	 *
	 * @param int $template_id Source Elementor template ID.
	 * @param array $result_entry Result entry from the job.
	 */
	private function store_template_conversion_result( int $template_id, array $result_entry ): void {
		$time = gmdate( 'Y-m-d H:i:s' );

		$data = array(
			'status'  => $result_entry['status'],
			'message' => $result_entry['message'],
			'target'  => $result_entry['target'],
			'time'    => $time,
		);

		update_post_meta( $template_id, '_ele2gb_last_result', $data );

		if ( 'success' === $result_entry['status'] ) {
			update_post_meta( $template_id, '_ele2gb_last_converted', $time );
		}
	}

}