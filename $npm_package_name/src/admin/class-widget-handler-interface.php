<?php
/**
 * Widget Handler Interface
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for widget handlers to ensure consistent processing.
 */
interface Widget_Handler_Interface {
	/**
	 * Handle the conversion of an Elementor element to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
	public function handle( array $element ): string;
}