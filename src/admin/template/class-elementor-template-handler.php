<?php
/**
 * Elementor Template Handler
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Template;

use Progressus\Gutenberg\Admin\Helper\Elementor_Elements_Parser;
use Progressus\Gutenberg\Admin\Template\Template_Handler_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Handles conversion of Elementor templates to Gutenberg.
 */
class Elementor_Template_Handler implements Template_Handler_Interface {

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Convert Elementor template to Gutenberg template.
	 *
	 * @param int $template_id The Elementor template ID.
	 * @return array The converted template data.
	 */
	public function convert( int $template_id ): array {
		// Get Elementor template data
		$elementor_data = get_post_meta( $template_id, '_elementor_data', true );
		
		if ( empty( $elementor_data ) ) {
			return array();
		}

		// Decode JSON data
		$elements = json_decode( $elementor_data, true );
		if ( ! is_array( $elements ) ) {
			return array();
		}

		// Convert elements to Gutenberg blocks
		$gutenberg_blocks = Elementor_Elements_Parser::parse( $elements );

		// Get template metadata
		$template_type = $this->get_template_type( $template_id );
		$conditions = $this->get_template_conditions( $template_id );

		return array(
			'id'              => $template_id,
			'title'           => get_the_title( $template_id ),
			'type'            => $template_type,
			'conditions'      => $conditions,
			'blocks'          => $gutenberg_blocks,
			'original_data'   => $elementor_data,
		);
	}

	/**
	 * Get template type (header, footer, single, archive, etc.).
	 *
	 * @param int $template_id The template ID.
	 * @return string The template type.
	 */
	public function get_template_type( int $template_id ): string {
		$template_type = get_post_meta( $template_id, '_elementor_template_type', true );
		
		// Map Elementor template types to WordPress/Gutenberg equivalents.
		$type_mapping = array(
			'header'          => 'wp_template_part',
			'footer'          => 'wp_template_part', 
			'single'          => 'wp_template',
			'archive'         => 'wp_template',
			'search-results'  => 'wp_template',
			'error-404'       => 'wp_template',
			'section'         => 'wp_template_part',
		);

		return $type_mapping[ $template_type ] ?? 'wp_template_part';
	}

	/**
	 * Get template conditions (where the template is applied).
	 *
	 * @param int $template_id The template ID.
	 * @return array The template conditions.
	 */
	public function get_template_conditions( int $template_id ): array {
		// Get Elementor Pro conditions if available
		$conditions = get_post_meta( $template_id, '_elementor_conditions', true );
		
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return array();
		}

		$converted_conditions = array();
		
		foreach ( $conditions as $condition ) {
			$converted_conditions[] = array(
				'type'     => $condition['type'] ?? '',
				'name'     => $condition['name'] ?? '',
				'sub'      => $condition['sub'] ?? '',
				'sub_name' => $condition['sub_name'] ?? '',
			);
		}

		return $converted_conditions;
	}

	/**
	 * Create Gutenberg template from converted data.
	 *
	 * @param array $template_data The converted template data.
	 * @param string $template_type The template type.
	 * @return int|false The created template ID or false on failure.
	 */
	public function create_gutenberg_template( array $template_data, string $template_type ) {
		$original_id   = $template_data['id'];
		$original_post = get_post( $original_id );
		
		if ( ! $original_post ) {
			return false;
		}

		// Determine the post type based on template type
		$post_type = $this->get_gutenberg_post_type( $template_data['type'], $template_data );
		
		// Get the Elementor template type for proper area assignment
		$elementor_type = get_post_meta( $template_data['id'], '_elementor_template_type', true );
		
		// Create the new template post
		$tax_input = array(
			'wp_theme' => array( get_stylesheet() ),
		);
		
		if ( $post_type === 'wp_template_part' && in_array( $elementor_type, array( 'header', 'footer' ), true ) ) {
			$tax_input['wp_template_part_area'] = array( $elementor_type );
		}
		
		$new_post_data = array(
			'post_title'   => $template_data['title'] . ' (Gutenberg)',
			'post_content' => $template_data['blocks'],
			'post_status'  => 'publish',
			'post_type'    => $post_type,
			'post_author'  => get_current_user_id(),
			'tax_input'    => $tax_input,
			'meta_input'   => array(
				'area' => $elementor_type,
			),
		);

		if ( in_array( $post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			$new_post_data['post_name'] = $this->generate_template_slug( $template_data );
		}

		$new_template_id = wp_insert_post( $new_post_data );

		if ( is_wp_error( $new_template_id ) || ! $new_template_id ) {
			return false;
		}

		$this->add_template_metadata( $new_template_id, $template_data );

		if ( $template_data['type'] === 'wp_template_part' ) {
			update_post_meta( $new_template_id, '_intended_template_type', 'wp_template_part' );
			$this->setup_theme_integration( $new_template_id, $template_data );
		}

		return $new_template_id;
	}

	/**
	 * Get the appropriate Gutenberg post type.
	 *
	 * @param string $template_type The template type.
	 * @param array  $template_data The template data.
	 * @return string The post type.
	 */
	private function get_gutenberg_post_type( string $template_type, array $template_data ): string {
		// Check if it's a header or footer specifically.
		$elementor_type = get_post_meta( $template_data['id'], '_elementor_template_type', true );
		
		if ( in_array( $elementor_type, array( 'header', 'footer' ), true ) ) {
			return 'wp_template_part';
		}

		return $template_type;
	}



	/**
	 * Generate template slug for theme templates.
	 *
	 * @param array $template_data The template data.
	 * @return string The template slug.
	 */
	private function generate_template_slug( array $template_data ): string {
		$elementor_type = get_post_meta( $template_data['id'], '_elementor_template_type', true );
		$theme_slug     = get_option( 'stylesheet' );
		
		switch ( $elementor_type ) {
			case 'header':
				return $theme_slug . '//header';
			case 'footer':
				return $theme_slug . '//footer';
			case 'single':
				return $theme_slug . '//single-elementor-' . $template_data['id'];
			case 'archive':
				return $theme_slug . '//archive-elementor-' . $template_data['id'];
			default:
				$base_slug = $theme_slug . '//' . sanitize_title( $template_data['title'] ) . '-' . $template_data['id'];
				return $base_slug;
		}
	}

	/**
	 * Add template metadata.
	 *
	 * @param int   $template_id The template ID.
	 * @param array $template_data The template data.
	 */
	private function add_template_metadata( int $template_id, array $template_data ): void {
		$elementor_type = get_post_meta( $template_data['id'], '_elementor_template_type', true );
		$theme_slug     = get_option( 'stylesheet' );
		
		if ( in_array( $elementor_type, array( 'header', 'footer' ), true ) ) {
			update_post_meta( $template_id, 'area', $elementor_type );
			$this->assign_template_part_area_taxonomy( $template_id, $elementor_type );
		}

		update_post_meta( $template_id, 'theme', $theme_slug );
		update_post_meta( $template_id, 'source', 'plugin' );
		update_post_meta( $template_id, '_original_elementor_id', $template_data['id'] );
		update_post_meta( $template_id, '_original_elementor_type', $elementor_type );
		update_post_meta( $template_id, '_conversion_date', current_time( 'mysql' ) );

		if ( ! empty( $template_data['conditions'] ) ) {
			update_post_meta( $template_id, '_elementor_conditions_converted', $template_data['conditions'] );
		}
	}

	/**
	 * Assign template part area taxonomy to a template.
	 *
	 * @param int    $template_id The template ID.
	 * @param string $area The template area (header, footer, etc.).
	 */
	private function assign_template_part_area_taxonomy( int $template_id, string $area ): void {
		// Check if this is a wp_template_part.
		$post_type = get_post_type( $template_id );
		if ( $post_type !== 'wp_template_part' ) {
			return;
		}

		// Ensure the taxonomy exists.
		if ( ! taxonomy_exists( 'wp_template_part_area' ) ) {
			return;
		}

		// Check if the term exists, if not create it.
		$term = get_term_by( 'slug', $area, 'wp_template_part_area' );
		if ( ! $term ) {
			$term_result = wp_insert_term( ucfirst( $area ), 'wp_template_part_area', array( 'slug' => $area ) );
			if ( is_wp_error( $term_result ) ) {
				return;
			}
			$term_id = $term_result['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		// Assign the term to the template.
		wp_set_object_terms( $template_id, array( $term_id ), 'wp_template_part_area' );
	}

	/**
	 * Setup theme integration for template parts.
	 *
	 * @param int   $template_id The template ID.
	 * @param array $template_data The template data.
	 */
	private function setup_theme_integration( int $template_id, array $template_data ): void {
		$elementor_type = get_post_meta( $template_data['id'], '_elementor_template_type', true );
		
		if ( in_array( $elementor_type, array( 'header', 'footer' ), true ) ) {
			$option_name = "active_gutenberg_{$elementor_type}_template";
			update_option( $option_name, $template_id );
			$this->add_template_hooks( $elementor_type, $template_id );
		}
	}

	/**
	 * Add WordPress hooks to inject template parts.
	 *
	 * @param string $type The template type (header/footer).
	 * @param int    $template_id The template ID.
	 */
	public function add_template_hooks( string $type, int $template_id ): void {
		add_filter( 'pre_get_block_template', array( $this, 'override_block_template' ), 10, 3 );
		add_filter( 'render_block_core/template-part', array( $this, 'render_custom_template_part' ), 10, 2 );
	}

	/**
	 * Render header template.
	 */
	public function render_header_template(): void {
		$template_id = get_option( 'active_gutenberg_header_template' );
		if ( $template_id ) {
			$this->render_template_part( $template_id );
		}
	}

	/**
	 * Render footer template.
	 */
	public function render_footer_template(): void {
		$template_id = get_option( 'active_gutenberg_footer_template' );
		if ( $template_id ) {
			$this->render_template_part( $template_id );
		}
	}

	/**
	 * Override block template with our converted template.
	 *
	 * @param WP_Block_Template|null $block_template The block template object, or null.
	 * @param string                 $id             Template unique identifier (example: theme_slug//template_slug).
	 * @param string                 $template_type  Template type: 'wp_template' or 'wp_template_part'.
	 * @return WP_Block_Template|null
	 */
	public function override_block_template( $block_template, string $id, string $template_type ) {
		if ( $template_type !== 'wp_template_part' ) {
			return $block_template;
		}

		$theme_slug = get_option( 'stylesheet' );
		$expected_header_id = $theme_slug . '//header';
		$expected_footer_id = $theme_slug . '//footer';

		if ( $id === $expected_header_id || $id === $expected_footer_id ) {
			// Find our converted template.
			$converted_template = $this->get_converted_template_by_slug( $id );
			if ( $converted_template ) {
				return $this->create_block_template_object( $converted_template, $id, $template_type );
			}
		}

		return $block_template;
	}

	/**
	 * Render custom template part for block themes.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string
	 */
	public function render_custom_template_part( string $block_content, array $block ): string {
		if ( ! isset( $block['attrs']['slug'] ) ) {
			return $block_content;
		}

		$slug = $block['attrs']['slug'];
		
		// Check if we have a converted template for this slug.
		if ( $slug === 'header' ) {
			ob_start();
			$this->render_header_template();
			$rendered_content = ob_get_clean();
			
			if ( ! empty( $rendered_content ) ) {
				return $rendered_content;
			}
		} elseif ( $slug === 'footer' ) {
			ob_start();
			$this->render_footer_template();
			$rendered_content = ob_get_clean();
			
			if ( ! empty( $rendered_content ) ) {
				return $rendered_content;
			}
		}

		return $block_content;
	}



	/**
	 * Get converted template by slug.
	 *
	 * @param string $slug The template slug.
	 * @return WP_Post|null
	 */
	private function get_converted_template_by_slug( string $slug ): ?WP_Post {
		$query = new WP_Query( array(
			'post_type'      => 'wp_template_part',
			'post_status'    => 'publish',
			'post_name'      => $slug,
			'posts_per_page' => 1,
		) );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return null;
	}

	/**
	 * Create a block template object from our converted template.
	 *
	 * @param WP_Post $template_post The template post.
	 * @param string  $id            Template ID.
	 * @param string  $template_type Template type.
	 * @return WP_Block_Template
	 */
	private function create_block_template_object( WP_Post $template_post, string $id, string $template_type ): WP_Block_Template {
		$template = new WP_Block_Template();

		$template->id             = $id;
		$template->theme          = get_option( 'stylesheet' );
		$template->content        = $template_post->post_content;
		$template->slug           = $template_post->post_name;
		$template->source         = 'plugin';
		$template->type           = $template_type;
		$template->title          = $template_post->post_title;
		$template->status         = $template_post->post_status;
		$template->has_theme_file = false;
		$template->is_custom      = true;
		$template->modified       = $template_post->post_modified;

		// Add area for template parts.
		if ( $template_type === 'wp_template_part' ) {
			$area = get_post_meta( $template_post->ID, 'area', true );
			$template->area = $area ?: 'uncategorized';
		}

		return $template;
	}

	/**
	 * Render a template part.
	 *
	 * @param int $template_id The template ID.
	 */
	private function render_template_part( int $template_id ): void {
		$template_post = get_post( $template_id );
		if ( $template_post && $template_post->post_status === 'publish' ) {
			echo do_blocks( $template_post->post_content );
		}
	}
}
