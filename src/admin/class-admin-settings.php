<?php
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Layout\Container_Classifier;

use function esc_html;
use function esc_html__;
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
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_filter( 'page_row_actions', array( $this, 'myplugin_add_convert_button' ), 10, 2 );
		add_action( 'admin_post_myplugin_convert_page', array( $this, 'myplugin_handle_convert_page' ) );
	}

	public function myplugin_add_convert_button( $actions, $post ) {
		if ( $post->post_type === 'page' ) {
			$json_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( empty( $json_data ) ) {
				return $actions;
			}
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=myplugin_convert_page&page_id=' . $post->ID ),
				'myplugin_convert_page_' . $post->ID
			);
			$actions['convert_to_gutenberg'] = '<a href="' . esc_url( $url ) . '">Convert to Gutenberg</a>';
		}
		return $actions;
	}


	public function myplugin_handle_convert_page() {
		if ( ! isset( $_GET['page_id'] ) ) {
			wp_die( 'Page ID missing.' );
		}

		$page_id = absint( $_GET['page_id'] );

		// Verify nonce
		check_admin_referer( 'myplugin_convert_page_' . $page_id );

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
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_page_id . '&action=edit' ) );
			exit;
		}

		wp_die( 'Failed to create Gutenberg page.' );
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
				'post_content' =>  $blocks,
			)
		);

		return $new_page_id;
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			'manage_options',
			'gutenberg-settings',
			array( $this, 'settings_page_content' ),
			'dashicons-admin-generic',
			100
		);
	}

	/**
	 * Initialize settings.
	 */
	public function settings_init(): void {
		register_setting(
			'gutenberg_settings_group',
			'gutenberg_json_data',
			array(
				'sanitize_callback' => array( $this, 'handle_json_upload' ),
			)
		);

		add_settings_section(
			'gutenberg_settings_section',
			esc_html__( 'Upload JSON Data', 'elementor-to-gutenberg' ),
			null,
			'gutenberg-settings'
		);

		add_settings_field(
			'gutenberg_json_upload',
			esc_html__( 'JSON File', 'elementor-to-gutenberg' ),
			array( $this, 'json_upload_field_callback' ),
			'gutenberg-settings',
			'gutenberg_settings_section'
		);
	}

	/**
	 * Render JSON upload field.
	 */
	public function json_upload_field_callback(): void {
		?>
		<input type="file" name="json_upload" accept=".json" />
		<?php
	}

	/**
	 * Handle JSON file upload and conversion.
	 *
	 * @param mixed $option The option value.
	 * @return string The processed Gutenberg content or existing option.
	 */
	public function handle_json_upload( $option ): string {
		if ( empty( $_FILES['json_upload']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $option;
		}

		$json_content = File_Upload_Service::upload_file( $_FILES['json_upload'], 'json' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $json_content ) {
			return get_option( 'gutenberg_json_data', '' );
		}

		$data = json_decode( $json_content, true );
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
				esc_html__( 'Failed to create new page.', 'elementor-to-gutenberg' ),
				'error'
			);
			return get_option( 'gutenberg_json_data', '' );
		}

		add_settings_error(
			'gutenberg_json_data',
			'json_upload_success',
			esc_html__( 'JSON file uploaded and page created successfully!', 'elementor-to-gutenberg' ),
			'updated'
		);
		return $gutenberg_content;
	}

	/**
	 * Render settings page content.
	 */
	public function settings_page_content(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gutenberg Settings', 'elementor-to-gutenberg' ); ?></h1>
			<?php settings_errors( 'gutenberg_json_data' ); ?>
			<form method="post" action="options.php" enctype="multipart/form-data" id="json-upload-form">
				<?php
				settings_fields( 'gutenberg_settings_group' );
				do_settings_sections( 'gutenberg-settings' );
				submit_button( esc_html__( 'Upload JSON File', 'elementor-to-gutenberg' ), 'primary', 'json-upload-btn' );
				?>
				<span id="json-upload-spinner" style="display:none;margin-left:10px;">
					<img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>" alt="<?php esc_attr_e( 'Loading', 'elementor-to-gutenberg' ); ?>" /> <?php esc_html_e( 'Uploading...', 'elementor-to-gutenberg' ); ?>
				</span>
			</form>
			<script>
				( function() {
					'use strict';
					var form = document.getElementById( 'json-upload-form' );
					var button = document.getElementById( 'json-upload-btn' );
					var spinner = document.getElementById( 'json-upload-spinner' );
					if ( form && button && spinner ) {
						form.addEventListener( 'submit', function() {
							button.disabled = true;
							spinner.style.display = 'inline-block';
						} );
					}
				} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param array $json_data The JSON data to convert.
	 * @return string The converted Gutenberg content.
	 */
	public function convert_json_to_gutenberg_content( array $json_data ): string {
		if ( empty( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
			return '';
		}
		return $this->parse_elementor_elements( $json_data['content'] );
	}

	/**
	 * Parse Elementor elements to Gutenberg blocks.
	 *
	 * @param array $elements The Elementor elements array.
	 * @return string The converted Gutenberg block content.
	 */
	public function parse_elementor_elements( array $elements ): string {
		$blocks = '';
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$blocks .= $this->render_element( $element );
		}

		return $blocks;
	}

	/**
	 * Render an Elementor element into block markup.
	 *
	 * @param array $element Elementor element.
	 */
	private function render_element( array $element ): string {
		$el_type = $element['elType'] ?? '';
		if ( 'container' === $el_type ) {
			return $this->render_container( $element );
		}

		if ( 'widget' === $el_type ) {
			$widget_type = $element['widgetType'] ?? '';
			$handler     = Widget_Handler_Factory::get_handler( $widget_type );
			if ( null !== $handler ) {
				return $handler->handle( $element );
			}

			return $this->render_unknown_widget( $widget_type );
		}

		return $this->render_unknown_widget( $el_type ?: 'unknown' );
	}

	/**
	 * Render a container element based on layout classification.
	 *
	 * @param array $element Elementor container element.
	 */
	private function render_container( array $element ): string {
		$children     = is_array( $element['elements'] ?? null ) ? $element['elements'] : array();
		$child_blocks = array();
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$converted = $this->render_element( $child );
			if ( '' !== $converted ) {
				$child_blocks[] = $converted;
			}
		}

		$child_count    = count( $children );
		$container_attr = Style_Parser::parse_container_styles( is_array( $element['settings'] ?? null ) ? $element['settings'] : array() );

		if ( Container_Classifier::is_grid( $element ) ) {
			$columns = Container_Classifier::get_grid_column_count( $element, $child_count );

			return $this->render_grid_group( $container_attr, $child_blocks, $columns );
		}

		if ( Container_Classifier::is_row( $element, $child_count ) ) {
			return $this->render_row_group( $container_attr, $child_blocks );
		}

		return $this->render_group( $container_attr, $child_blocks );
	}

	/**
	 * Render a Gutenberg group with constrained layout.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_group( array $attributes, array $child_blocks ): string {
		$attributes['layout'] = array( 'type' => 'constrained' );
		$inner_html           = implode( '', $child_blocks );

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a Gutenberg group with flex layout for row containers.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 */
	private function render_row_group( array $attributes, array $child_blocks ): string {
		$attributes['layout'] = array(
			'type'           => 'flex',
			'justifyContent' => 'space-between',
			'flexWrap'       => 'wrap',
		);

		return Block_Builder::build( 'group', $attributes, implode( '', $child_blocks ) );
	}

	/**
	 * Render a Gutenberg grid layout group.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $child_blocks Rendered child blocks.
	 * @param int $columns Number of columns.
	 */
	private function render_grid_group( array $attributes, array $child_blocks, int $columns ): string {
		$attributes['layout'] = array(
			'type'        => 'grid',
			'columnCount' => max( 1, $columns ),
		);

		$inner_html = '';
		foreach ( $child_blocks as $child_block ) {
			$inner_html .= Block_Builder::build(
				'group',
				array( 'layout' => array( 'type' => 'constrained' ) ),
				$child_block
			);
		}

		return Block_Builder::build( 'group', $attributes, $inner_html );
	}

	/**
	 * Render a placeholder for unknown widgets.
	 *
	 * @param string $type Widget type.
	 */
	private function render_unknown_widget( string $type ): string {
		$message   = sprintf( /* translators: %s widget type */ esc_html__( 'Unknown widget: %s', 'elementor-to-gutenberg' ), esc_html( $type ) );
		$paragraph = sprintf( '<p>%s</p>', $message );

		return Block_Builder::build(
			'group',
			array( 'layout' => array( 'type' => 'constrained' ) ),
			'<!-- wp:paragraph -->' . $paragraph . '<!-- /wp:paragraph -->' . "\n"
		);
	}
}