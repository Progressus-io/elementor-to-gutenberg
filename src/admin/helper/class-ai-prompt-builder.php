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

		$sections = array(
			"You are improving a converted WordPress page from Elementor to Gutenberg.",
			"\nGOAL\nReturn improved Gutenberg block markup and CSS remediation that make the Gutenberg page more visually faithful to the original Elementor page.",
			"\nREMEDIATION INSTRUCTIONS\n1) Preserve semantic structure and content meaning.\n2) Improve spacing, typography, alignment, and responsive behavior where needed.\n3) Keep Gutenberg block comment delimiters valid.\n4) Return two outputs only:\n   - CSS_RESULT: plain CSS only\n   - GUTENBERG_RESULT: full Gutenberg post_content only\n5) Do not include explanations before or after the two outputs.",
			"\nPAGE CONTEXT\nSource Elementor page ID: {$source_id}\nSource Elementor title: {$source_title}\nTarget Gutenberg page ID: {$target_id}\nTarget Gutenberg title: {$target_title}",
			"\nELEMENTOR_JSON\n{$elementor_json}",
			"\nGUTENBERG_CONTENT\n{$gutenberg_content}",
			"\nOUTPUT FORMAT\nCSS_RESULT:\n<css here>\n\nGUTENBERG_RESULT:\n<full gutenberg content here>",
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
