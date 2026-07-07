<?php
/**
 * Manual AI prompt builder for converted pages.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

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
		$current_css       = isset( $context['current_css'] ) ? trim( (string) $context['current_css'] ) : '';
		$template_type     = isset( $context['template_type'] ) ? trim( (string) $context['template_type'] ) : '';

		$css_namespace = 'blockshift-page-' . $source_id;

		$sections = array();

		if ( '' !== $template_type ) {
			$sections[] = "TEMPLATE_FOCUS\nThis is an Elementor {$template_type} template. The screenshot shows the full page. Focus only on the {$template_type} area. Do not touch anything outside the {$template_type}.";
		}

		$sections[] = "PAGE CONTEXT\nSource Elementor page ID: {$source_id}\nSource Elementor title: {$source_title}\nTarget Gutenberg page ID: {$target_id}\nTarget Gutenberg title: {$target_title}\nCSS Namespace: .{$css_namespace}";

		if ( '' !== $current_css ) {
			$sections[] = "CURRENT_CSS\n{$current_css}";
		}

		$sections[] = "ELEMENTOR_JSON\n{$elementor_json}";
		$sections[] = "GUTENBERG_CONTENT\n{$gutenberg_content}";

		return implode( "\n\n", $sections );
	}

	/**
	 * Build the mobile-only improvement prompt.
	 *
	 * Targets a separate AI pass that should produce mobile-only @media query
	 * CSS without altering desktop styles or the Gutenberg block structure.
	 *
	 * @param array $context Prompt context.
	 */
	public static function build_mobile( array $context ): string {
		$source_id         = isset( $context['source_id'] ) ? (int) $context['source_id'] : 0;
		$target_id         = isset( $context['target_id'] ) ? (int) $context['target_id'] : 0;
		$source_title      = isset( $context['source_title'] ) ? (string) $context['source_title'] : '';
		$target_title      = isset( $context['target_title'] ) ? (string) $context['target_title'] : '';
		$elementor_json    = isset( $context['elementor_json'] ) ? self::normalize_json_text( $context['elementor_json'] ) : '';
		$gutenberg_content = isset( $context['gutenberg_content'] ) ? (string) $context['gutenberg_content'] : '';
		$current_css       = isset( $context['current_css'] ) ? trim( (string) $context['current_css'] ) : '';
		$template_type     = isset( $context['template_type'] ) ? trim( (string) $context['template_type'] ) : '';

		$css_namespace = 'blockshift-page-' . $source_id;

		$sections = array();

		if ( '' !== $template_type ) {
			$sections[] = "TEMPLATE_FOCUS\nThis is an Elementor {$template_type} template. Focus only on the {$template_type} area on mobile. Do not touch anything outside the {$template_type}.";
		}

		$sections[] = "MOBILE_FOCUS\nYou are improving the MOBILE rendering only. The two screenshots provided show the Elementor original and the converted Gutenberg page on a mobile viewport. Compare them and produce CSS that fixes mobile-only differences (spacing, typography sizing, alignment, stacking, image sizing).";
		$sections[] = "PAGE CONTEXT\nSource Elementor page ID: {$source_id}\nSource Elementor title: {$source_title}\nTarget Gutenberg page ID: {$target_id}\nTarget Gutenberg title: {$target_title}\nCSS Namespace: .{$css_namespace}";

		if ( '' !== $current_css ) {
			$sections[] = "CURRENT_CSS\n{$current_css}";
		}

		$sections[] = "ELEMENTOR_JSON\n{$elementor_json}";
		$sections[] = "GUTENBERG_CONTENT\n{$gutenberg_content}";

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
