<?php
/**
 * Template Handler Interface
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for template handlers.
 */
interface Template_Handler_Interface {

	/**
	 * Convert Elementor template to Gutenberg template.
	 *
	 * @param int $template_id The Elementor template ID.
	 * @return array The converted template data.
	 */
	public function convert( int $template_id ): array;

	/**
	 * Get template type (header, footer, single, archive, etc.).
	 *
	 * @param int $template_id The template ID.
	 * @return string The template type.
	 */
	public function get_template_type( int $template_id ): string;

	/**
	 * Get template conditions (where the template is applied).
	 *
	 * @param int $template_id The template ID.
	 * @return array The template conditions.
	 */
	public function get_template_conditions( int $template_id ): array;

	/**
	 * Create Gutenberg template from converted data.
	 *
	 * @param array $template_data The converted template data.
	 * @param string $template_type The template type.
	 * @return int|false The created template ID or false on failure.
	 */
	public function create_gutenberg_template( array $template_data, string $template_type );
}
