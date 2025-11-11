<?php
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Template\Template_Manager;
use Progressus\Gutenberg\Admin\Template\Elementor_Template_Handler;

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
	 * Template manager instance.
	 *
	 * @var Template_Manager
	 */
	private Template_Manager $template_manager;

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
		$this->template_manager = new Template_Manager();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_filter( 'page_row_actions', array( $this, 'myplugin_add_convert_button' ), 10, 2 );
		add_action( 'admin_post_myplugin_convert_page', array( $this, 'myplugin_handle_convert_page' ) );
		add_action( 'init', array( $this, 'init_template_handler' ), 15 );
	}

	/**
	 * Initialize template activation system.
	 */
	public function init_template_activation(): void {
		// Initialize the Template Handler which will handle all template operations and hooks
		add_action( 'init', array( $this, 'init_template_handler' ), 15 );
	}

	/**
	 * Initialize the Template Handler to manage template activation and hooks.
	 */
	public function init_template_handler(): void {
		// Create template handler instance
		$template_handler = new Elementor_Template_Handler();
		
		// Find and activate all converted templates
		$this->activate_converted_templates( $template_handler );
	}

	/**
	 * Find and activate converted templates.
	 *
	 * @param Elementor_Template_Handler $handler Template handler instance.
	 */
	private function activate_converted_templates( $handler ): void {
		$theme_slug = get_option( 'stylesheet' );
		
		// Find template parts with the standard header/footer slugs.
		$args = array(
			'post_type'      => 'wp_template_part',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post_name__in'  => array(
				$theme_slug . '//header',
				$theme_slug . '//footer',
			),
			'meta_query'     => array(
				array(
					'key'     => '_original_elementor_id',
					'compare' => 'EXISTS',
				),
			),
		);

		$active_templates = get_posts( $args );
		
		foreach ( $active_templates as $template ) {
			$elementor_type = get_post_meta( $template->ID, '_original_elementor_type', true );
			
			if ( in_array( $elementor_type, array( 'header', 'footer' ), true ) ) {
				// Store as active template
				$option_name = "active_gutenberg_{$elementor_type}_template";
				update_option( $option_name, $template->ID );
				
				// Let the handler add its hooks for this template type
				$handler->add_template_hooks( $elementor_type, $template->ID );
			}
		}
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
			<h1><?php esc_html_e( 'Elementor to Gutenberg Converter', 'elementor-to-gutenberg' ); ?></h1>
			<?php settings_errors( 'gutenberg_json_data' ); ?>
			<!-- Template Management Section -->
			<?php $this->render_template_management_section(); ?>
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
					var form    = document.getElementById( 'json-upload-form' );
					var button  = document.getElementById( 'json-upload-btn' );
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
		if ( ! isset( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
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
		$block_content = '';
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'container' === $element['elType'] ) {
				$inner = ! empty( $element['elements'] ) ? $this->parse_elementor_elements( $element['elements'] ) : '';
				$block_content .= sprintf(
					'<!-- wp:group --><div class="wp-block-group">%s</div><!-- /wp:group -->' . "\n",
					$inner
				);
			} elseif ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {

				$handler = Widget_Handler_Factory::get_handler( $element['widgetType'] );
				if ( null !== $handler ) {
					$block_content .= $handler->handle( $element );
				} else {
					$block_content .= sprintf(
						'<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
						esc_html( $element['widgetType'] )
					);
				}

			} else {
				$block_content .= sprintf(
					'<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
					esc_html__( 'Unknown element', 'elementor-to-gutenberg' )
				);
			}
		}
		return $block_content;
	}

	/**
	 * Render the template management section.
	 */
	private function render_template_management_section(): void {
		$migration_status = $this->template_manager->get_migration_status();
		$convertible_templates = $this->template_manager->get_convertible_templates();
		
		?>
		<div class="template-management-section">
			<h2><?php esc_html_e( 'Elementor Template Migration', 'elementor-to-gutenberg' ); ?></h2>
			
			<?php if ( empty( $convertible_templates ) ): ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No Elementor templates found that can be converted to Gutenberg.', 'elementor-to-gutenberg' ); ?></p>
				</div>
			<?php else: ?>
				
				<!-- Migration Status -->
				<div class="migration-status-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
					<h3><?php esc_html_e( 'Migration Status', 'elementor-to-gutenberg' ); ?></h3>
					<div style="display: flex; gap: 20px; margin-bottom: 15px;">
						<div class="status-item">
							<strong><?php echo esc_html( $migration_status['total'] ); ?></strong>
							<span><?php esc_html_e( 'Total Templates', 'elementor-to-gutenberg' ); ?></span>
						</div>
						<div class="status-item">
							<strong style="color: #46b450;"><?php echo esc_html( $migration_status['converted'] ); ?></strong>
							<span><?php esc_html_e( 'Converted', 'elementor-to-gutenberg' ); ?></span>
						</div>
						<div class="status-item">
							<strong style="color: #dc3232;"><?php echo esc_html( $migration_status['needs_conversion'] ); ?></strong>
							<span><?php esc_html_e( 'Need Conversion', 'elementor-to-gutenberg' ); ?></span>
						</div>
					</div>
					
					<?php if ( $migration_status['needs_conversion'] > 0 ): ?>
						<div style="margin-top: 15px;">
							<?php
							$convert_all_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'      => 'convert_all_elementor_templates',
										'template_id' => 0, // Not used for bulk conversion
									),
									admin_url( 'admin.php' )
								),
								'convert_all_elementor_templates'
							);
							?>
							<a href="<?php echo esc_url( $convert_all_url ); ?>" 
							   class="button button-primary" 
							   onclick="return confirm('<?php esc_attr_e( 'This will convert all compatible Elementor templates to Gutenberg. Continue?', 'elementor-to-gutenberg' ); ?>');">
								<?php esc_html_e( 'Convert All Templates', 'elementor-to-gutenberg' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<!-- Templates List -->
				<div class="templates-list">
					<h3><?php esc_html_e( 'Available Templates', 'elementor-to-gutenberg' ); ?></h3>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Template Name', 'elementor-to-gutenberg' ); ?></th>
								<th><?php esc_html_e( 'Type', 'elementor-to-gutenberg' ); ?></th>
								<th><?php esc_html_e( 'Status', 'elementor-to-gutenberg' ); ?></th>
								<th><?php esc_html_e( 'Conditions', 'elementor-to-gutenberg' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'elementor-to-gutenberg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $convertible_templates as $template ): ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $template['title'] ); ?></strong>
										<div class="template-meta">
											<small>ID: <?php echo esc_html( $template['id'] ); ?></small>
										</div>
									</td>
									<td>
										<span class="template-type-badge" style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
											<?php echo esc_html( $template['type'] ); ?>
										</span>
									</td>
									<td>
										<?php if ( $template['existing_conversion'] ): ?>
											<span style="color: #46b450;">✓ <?php esc_html_e( 'Converted', 'elementor-to-gutenberg' ); ?></span>
											<div style="font-size: 11px; color: #666;">
												<?php 
												echo esc_html( 
													sprintf( 
														__( 'Converted on %s', 'elementor-to-gutenberg' ),
														$template['existing_conversion']['conversion_date'] 
													) 
												); 
												?>
											</div>
										<?php else: ?>
											<span style="color: #dc3232;"><?php esc_html_e( 'Not Converted', 'elementor-to-gutenberg' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $template['conditions'] ) ): ?>
											<?php foreach ( $template['conditions'] as $condition ): ?>
												<div style="font-size: 11px;">
													<?php echo esc_html( $condition['name'] ?? 'General' ); ?>
													<?php if ( ! empty( $condition['sub_name'] ) ): ?>
														→ <?php echo esc_html( $condition['sub_name'] ); ?>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										<?php else: ?>
											<em style="color: #666;"><?php esc_html_e( 'No conditions', 'elementor-to-gutenberg' ); ?></em>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $template['existing_conversion'] ): ?>
											<a href="<?php echo esc_url( get_edit_post_link( $template['existing_conversion']['id'] ) ); ?>" 
											   class="button button-small">
												<?php esc_html_e( 'Edit Gutenberg Version', 'elementor-to-gutenberg' ); ?>
											</a>
										<?php else: ?>
											<?php
											$convert_url = wp_nonce_url(
												add_query_arg(
													array(
														'action'      => 'convert_elementor_template',
														'template_id' => $template['id'],
													),
													admin_url( 'admin.php' )
												),
												'convert_elementor_template'
											);
											?>
											<a href="<?php echo esc_url( $convert_url ); ?>" 
											   class="button button-primary button-small">
												<?php esc_html_e( 'Convert', 'elementor-to-gutenberg' ); ?>
											</a>
										<?php endif; ?>
										
										<a href="<?php echo esc_url( get_edit_post_link( $template['id'] ) ); ?>" 
										   class="button button-small">
											<?php esc_html_e( 'View Original', 'elementor-to-gutenberg' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Important Note -->
				<div class="notice notice-warning" style="margin-top: 20px;">
					<p><strong><?php esc_html_e( 'Important:', 'elementor-to-gutenberg' ); ?></strong></p>
					<ul style="margin-left: 20px;">
						<li><?php esc_html_e( 'Converting templates will create new Gutenberg templates alongside your existing Elementor templates.', 'elementor-to-gutenberg' ); ?></li>
						<li><?php esc_html_e( 'For headers and footers, the Gutenberg versions will automatically take over when Elementor is deactivated.', 'elementor-to-gutenberg' ); ?></li>
						<li><?php esc_html_e( 'Always test your converted templates before deactivating Elementor.', 'elementor-to-gutenberg' ); ?></li>
						<li><?php esc_html_e( 'Keep backups of your original Elementor templates.', 'elementor-to-gutenberg' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		
		<style>
			.migration-status-card .status-item {
				text-align: center;
				padding: 10px;
				background: #f9f9f9;
				border-radius: 4px;
				min-width: 120px;
			}
			.migration-status-card .status-item strong {
				display: block;
				font-size: 24px;
				line-height: 1;
				margin-bottom: 5px;
			}
			.template-meta {
				margin-top: 5px;
				color: #666;
			}
		</style>
		<?php
	}

}