<?php
/**
 * Template Manager
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Template;

use Progressus\Gutenberg\Admin\Template\Elementor_Template_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Manages template conversion from Elementor to Gutenberg.
 */
class Template_Manager {

	/**
	 * The template handler.
	 *
	 * @var Elementor_Template_Handler
	 */
	private Elementor_Template_Handler $handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->handler = new Elementor_Template_Handler();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'admin_init', array( $this, 'handle_bulk_conversion' ) );
		add_action( 'admin_notices', array( $this, 'show_conversion_notices' ) );
		add_filter( 'post_row_actions', array( $this, 'add_conversion_action' ), 10, 2 );
	}

	/**
	 * Get all Elementor templates that can be converted.
	 *
	 * @return array Array of template data.
	 */
	public function get_convertible_templates(): array {
		$args = array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_elementor_template_type',
					'value'   => array( 'header', 'footer', 'single', 'archive' ),
					'compare' => 'IN',
				),
			),
		);

		$templates = get_posts( $args );
		$convertible = array();

		foreach ( $templates as $template ) {
			$template_type = get_post_meta( $template->ID, '_elementor_template_type', true );
			$conditions = $this->handler->get_template_conditions( $template->ID );
			
			// Check if already converted.
			$existing_conversion = $this->get_existing_conversion( $template->ID );
			
			$convertible[] = array(
				'id'                  => $template->ID,
				'title'               => $template->post_title,
				'type'                => $template_type,
				'conditions'          => $conditions,
				'status'              => $template->post_status,
				'existing_conversion' => $existing_conversion,
				'can_convert'         => empty( $existing_conversion ),
			);
		}

		return $convertible;
	}

	/**
	 * Check if template has already been converted.
	 *
	 * @param int $elementor_template_id The Elementor template ID.
	 * @return array|null Existing conversion data or null.
	 */
	private function get_existing_conversion( int $elementor_template_id ): ?array {
		$args = array(
			'post_type'      => array( 'wp_template', 'wp_template_part' ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_original_elementor_id',
					'value' => $elementor_template_id,
				),
			),
		);

		$converted = get_posts( $args );
		
		if ( empty( $converted ) ) {
			return null;
		}

		$converted_template = $converted[0];
		
		return array(
			'id'              => $converted_template->ID,
			'title'           => $converted_template->post_title,
			'status'          => $converted_template->post_status,
			'conversion_date' => get_post_meta( $converted_template->ID, '_conversion_date', true ),
		);
	}

	/**
	 * Convert a single template.
	 *
	 * @param int $template_id The Elementor template ID.
	 * @return array Result of conversion.
	 */
	public function convert_template( int $template_id ): array {
		// Check if template exists and is convertible
		$template = get_post( $template_id );
		if ( ! $template || $template->post_type !== 'elementor_library' ) {
			return array(
				'success' => false,
				'message' => 'Template not found or not an Elementor template.',
			);
		}

		// Check if already converted
		$existing = $this->get_existing_conversion( $template_id );
		if ( $existing ) {
			return array(
				'success'  => false,
				'message'  => 'Template has already been converted.',
				'existing' => $existing,
			);
		}

		// Perform conversion
		try {
			$template_data = $this->handler->convert( $template_id );
			
			if ( empty( $template_data ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to parse Elementor template data.',
				);
			}

			$new_template_id = $this->handler->create_gutenberg_template( 
				$template_data, 
				$template_data['type'] 
			);

			if ( ! $new_template_id ) {
				return array(
					'success' => false,
					'message' => 'Failed to create Gutenberg template.',
				);
			}

			return array(
				'success'         => true,
				'message'         => 'Template converted successfully.',
				'original_id'     => $template_id,
				'new_template_id' => $new_template_id,
				'template_data'   => $template_data,
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Conversion failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Convert all compatible templates.
	 *
	 * @return array Results of bulk conversion.
	 */
	public function convert_all_templates(): array {
		$templates = $this->get_convertible_templates();
		$results = array(
			'converted'  => array(),
			'failed'     => array(),
			'skipped'    => array(),
		);

		foreach ( $templates as $template_data ) {
			if ( ! $template_data['can_convert'] ) {
				$results['skipped'][] = $template_data;
				continue;
			}

			$result = $this->convert_template( $template_data['id'] );
			
			if ( $result['success'] ) {
				$results['converted'][] = array_merge( $template_data, $result );
			} else {
				$results['failed'][] = array_merge( $template_data, $result );
			}
		}

		return $results;
	}

	/**
	 * Handle bulk conversion via admin interface.
	 */
	public function handle_bulk_conversion(): void {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['template_id'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['action'] );
		
		if ( ! in_array( $action, array( 'convert_elementor_template', 'convert_all_elementor_templates' ), true ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], $action ) ) {
			wp_die( 'Security check failed.' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		if ( $action === 'convert_elementor_template' ) {
			$template_id = absint( $_GET['template_id'] );
			$result      = $this->convert_template( $template_id );
			
			$redirect_url = add_query_arg( 
				array(
					'page'              => 'gutenberg-settings',
					'conversion_result' => $result['success'] ? 'success' : 'error',
					'message'           => urlencode( $result['message'] ),
				),
				admin_url( 'admin.php' )
			);

			if ( $result['success'] ) {
				$redirect_url = add_query_arg( 'new_template_id', $result['new_template_id'], $redirect_url );
			}

		} elseif ( $action === 'convert_all_elementor_templates' ) {
			$results = $this->convert_all_templates();
			
			$message = sprintf(
				'Conversion complete. Converted: %d, Failed: %d, Skipped: %d',
				count( $results['converted'] ),
				count( $results['failed'] ),
				count( $results['skipped'] )
			);

			$redirect_url = add_query_arg(
				array(
					'page'              => 'gutenberg-settings',
					'conversion_result' => 'bulk_complete',
					'message'           => urlencode( $message ),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show conversion notices in admin.
	 */
	public function show_conversion_notices(): void {
		if ( ! isset( $_GET['conversion_result'] ) ) {
			return;
		}

		$result = sanitize_text_field( $_GET['conversion_result'] );
		$message = isset( $_GET['message'] ) ? urldecode( sanitize_text_field( $_GET['message'] ) ) : '';

		$class = in_array( $result, array( 'success', 'bulk_complete' ), true ) ? 'notice-success' : 'notice-error';

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);

		// If conversion was successful, show link to edit new template
		if ( $result === 'success' && isset( $_GET['new_template_id'] ) ) {
			$new_template_id = absint( $_GET['new_template_id'] );
			$edit_link = get_edit_post_link( $new_template_id );
			
			if ( $edit_link ) {
				printf(
					'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
					esc_html__( 'Template converted successfully!', 'progressus-gutenberg' ),
					esc_url( $edit_link ),
					esc_html__( 'Edit Gutenberg Template', 'progressus-gutenberg' )
				);
			}
		}
	}

	/**
	 * Add conversion action to post row actions.
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post The post object.
	 * @return array  Modified actions array.
	 */
	public function add_conversion_action( array $actions, $post ): array {
		// Only add for Elementor library posts
		if ( $post->post_type !== 'elementor_library' ) {
			return $actions;
		}

		$template_type = get_post_meta( $post->ID, '_elementor_template_type', true );
		
		// Only for convertible template types
		if ( ! in_array( $template_type, array( 'header', 'footer', 'single', 'archive' ), true ) ) {
			return $actions;
		}

		// Check if already converted
		$existing = $this->get_existing_conversion( $post->ID );
		
		if ( $existing ) {
			$actions['view_gutenberg'] = sprintf(
				'<a href="%s">%s</a>',
				get_edit_post_link( $existing['id'] ),
				__( 'View Gutenberg Version', 'progressus-gutenberg' )
			);
		} else {
			$convert_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'convert_elementor_template',
						'template_id' => $post->ID,
					),
					admin_url( 'admin.php' )
				),
				'convert_elementor_template'
			);

			$actions['convert_to_gutenberg'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $convert_url ),
				__( 'Convert to Gutenberg', 'progressus-gutenberg' )
			);
		}

		return $actions;
	}

	/**
	 * Get template migration status for admin display.
	 *
	 * @return array Migration status information.
	 */
	public function get_migration_status(): array {
		$templates = $this->get_convertible_templates();
		
		$status = array(
			'total'          => count( $templates ),
			'converted'      => 0,
			'needs_conversion' => 0,
			'active_headers' => array(),
			'active_footers' => array(),
		);

		foreach ( $templates as $template ) {
			if ( $template['existing_conversion'] ) {
				$status['converted']++;
			} else {
				$status['needs_conversion']++;
			}

			// Track active templates
			if ( $template['type'] === 'header' && ! empty( $template['conditions'] ) ) {
				$status['active_headers'][] = $template;
			} elseif ( $template['type'] === 'footer' && ! empty( $template['conditions'] ) ) {
				$status['active_footers'][] = $template;
			}
		}

		return $status;
	}

	/**
	 * Cleanup after Elementor deactivation.
	 * Ensures Gutenberg templates take over seamlessly.
	 */
	public function handle_elementor_deactivation(): void {
		// Get all converted templates
		$converted_templates = $this->get_all_converted_templates();
		
		foreach ( $converted_templates as $template ) {
			$original_type = get_post_meta( $template->ID, '_original_elementor_type', true );
			
			// Activate converted headers/footers
			if ( in_array( $original_type, array( 'header', 'footer' ), true ) ) {
				$this->activate_template_replacement( $template->ID, $original_type );
			}
		}

		// Set flag that conversion is complete
		update_option( 'elementor_to_gutenberg_migration_complete', true );
	}

	/**
	 * Get all converted templates.
	 *
	 * @return array Array of converted template posts.
	 */
	private function get_all_converted_templates(): array {
		$args = array(
			'post_type'      => array( 'wp_template', 'wp_template_part' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_original_elementor_id',
					'compare' => 'EXISTS',
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Activate template replacement.
	 *
	 * @param int    $template_id The Gutenberg template ID.
	 * @param string $type The template type (header/footer).
	 */
	private function activate_template_replacement( int $template_id, string $type ): void {
		// Ensure the template is published
		wp_update_post( array(
			'ID'          => $template_id,
			'post_status' => 'publish',
		) );

		// Set as active template
		update_option( "active_gutenberg_{$type}_template", $template_id );
		
		// Add to theme.json if possible
		$this->add_to_theme_json( $template_id, $type );
	}

	/**
	 * Add template to theme.json if theme supports it.
	 *
	 * @param int    $template_id The template ID.
	 * @param string $type The template type.
	 */
	private function add_to_theme_json( int $template_id, string $type ): void {
		// This would integrate with the active theme's template system
		// Implementation depends on the specific theme architecture
		
		// For now, we'll store the information for theme integration hooks
		$active_templates = get_option( 'gutenberg_active_templates', array() );
		$active_templates[ $type ] = $template_id;
		update_option( 'gutenberg_active_templates', $active_templates );
	}
}