<?php
/**
 * Manual AI prompt builder for converted pages.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

class AI_Prompt_Builder {

	/**
	 * Build the ready-to-copy prompt.
	 *
	 * @param array $context Prompt context.
	 */
	public static function build( array $context ): string {
		$source_id         = isset( $context['source_id'] ) ? (int) $context['source_id'] : 0;
		$target_id         = isset( $context['target_id'] ) ? (int) $context['target_id'] : 0;
		$source_title      = isset( $context['source_title'] ) ? (string) $context['source_title'] : '';
		$target_title      = isset( $context['target_title'] ) ? (string) $context['target_title'] : '';
		$elementor_json    = isset( $context['elementor_json'] ) ? self::normalize_json_text( $context['elementor_json'] ) : '';
		$gutenberg_content = isset( $context['gutenberg_content'] ) ? (string) $context['gutenberg_content'] : '';

		// NOTE: All instructions live in the system prompt (Claude_Api_Service::get_system_prompt()).
		// This user message contains only the data Claude needs to work with.
		$sections = array(
			"PAGE CONTEXT\nSource Elementor page ID: {$source_id}\nSource Elementor title: {$source_title}\nTarget Gutenberg page ID: {$target_id}\nTarget Gutenberg title: {$target_title}",
			"ELEMENTOR_JSON\n{$elementor_json}",
			"GUTENBERG_CONTENT\n{$gutenberg_content}",
		);

		return implode( "\n\n", $sections );
	}

	/**
	 * Normalize Elementor JSON into readable text.
	 *
	 * @param mixed $raw_json Raw json value.
	 */
	private static function normalize_json_text( $raw_json ): string {
		if ( is_array( $raw_json ) ) {
			$encoded = wp_json_encode( $raw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			return is_string( $encoded ) ? $encoded : '';
		}

		return (string) $raw_json;
	}
}
