<?php
/**
 * Claude API service.
 *
 * Sends prompts to the Anthropic Claude API and parses the response.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function get_option;
use function is_wp_error;
use function json_decode;
use function preg_match;
use function sanitize_text_field;
use function trim;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

defined( 'ABSPATH' ) || exit;

/**
 * Class Claude_Api_Service
 *
 * Handles communication with the Anthropic Claude API.
 */
class Claude_Api_Service {

	/**
	 * Claude API endpoint.
	 */
	const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Model to use for requests.
	 */
	const MODEL = 'claude-opus-4-6';

	/**
	 * Send a prompt to Claude and return the text response.
	 *
	 * Screenshot sets are arrays of chunk URLs — one element for single-screen
	 * pages, multiple for tall pages split by the screenshot service. Each chunk
	 * is sent as a separate vision image block labelled "part X of Y".
	 * Only pass the screenshot sets that are relevant to the AI pass being run:
	 * desktop passes receive desktop chunks; the mobile pass receives mobile chunks.
	 *
	 * @param string   $prompt                The full prompt text.
	 * @param string[] $elementor_shots        Elementor desktop screenshot chunk URLs.
	 * @param string[] $gutenberg_shots        Gutenberg desktop screenshot chunk URLs.
	 * @param string   $system_prompt         Optional system prompt override.
	 * @param string[] $elementor_mobile_shots Elementor mobile screenshot chunk URLs.
	 * @param string[] $gutenberg_mobile_shots Gutenberg mobile screenshot chunk URLs.
	 * @return array{success: bool, content: string, error: string}
	 */
	public static function send(
		string $prompt,
		array $elementor_shots = array(),
		array $gutenberg_shots = array(),
		string $system_prompt = '',
		array $elementor_mobile_shots = array(),
		array $gutenberg_mobile_shots = array()
	): array {
		$api_key = self::get_api_key();

		if ( '' === $api_key ) {
			return array(
				'success' => false,
				'content' => '',
				'error'   => 'Claude API key is not configured.',
			);
		}

		$resolved_system = '' !== $system_prompt ? $system_prompt : self::get_system_prompt();
		$content         = self::build_message_content(
			$prompt,
			$elementor_shots,
			$gutenberg_shots,
			$elementor_mobile_shots,
			$gutenberg_mobile_shots
		);

		$body = wp_json_encode(
			array(
				'model'       => self::MODEL,
				'max_tokens'  => 64000,
				'temperature' => 0,
				'system'      => $resolved_system,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => $content,
					),
				),
			)
		);

		// Log the full request before sending.
		self::log_entry(
			array(
				'event'                    => 'api_request',
				'system_prompt'            => $resolved_system,
				'user_prompt'              => $prompt,
				'elementor_desktop_chunks' => count( $elementor_shots ),
				'gutenberg_desktop_chunks' => count( $gutenberg_shots ),
				'elementor_mobile_chunks'  => count( $elementor_mobile_shots ),
				'gutenberg_mobile_chunks'  => count( $gutenberg_mobile_shots ),
				'user_prompt_length'       => strlen( $prompt ),
			)
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 1020,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_entry(
				array(
					'event'   => 'api_response',
					'success' => false,
					'error'   => $response->get_error_message(),
				)
			);
			return array(
				'success' => false,
				'content' => '',
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== $code || empty( $data['content'][0]['text'] ) ) {
			$error = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : "Unexpected response (HTTP $code).";
			self::log_entry(
				array(
					'event'         => 'api_response',
					'success'       => false,
					'http_code'     => $code,
					'stop_reason'   => '',
					'input_tokens'  => $data['usage']['input_tokens'] ?? null,
					'output_tokens' => $data['usage']['output_tokens'] ?? null,
					'error'         => $error,
					'raw_response'  => '',
				)
			);
			return array(
				'success' => false,
				'content' => '',
				'error'   => $error,
			);
		}

		$response_text = (string) $data['content'][0]['text'];
		$stop_reason   = isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '';

		self::log_entry(
			array(
				'event'         => 'api_response',
				'success'       => 'max_tokens' !== $stop_reason,
				'http_code'     => $code,
				'stop_reason'   => $stop_reason,
				'input_tokens'  => $data['usage']['input_tokens'] ?? null,
				'output_tokens' => $data['usage']['output_tokens'] ?? null,
				'error'         => 'max_tokens' === $stop_reason ? 'Response truncated — max_tokens limit reached.' : '',
				'raw_response'  => $response_text,
			)
		);

		if ( 'max_tokens' === $stop_reason ) {
			return array(
				'success' => false,
				'content' => $response_text,
				'error'   => 'Claude response was truncated (max_tokens limit reached). Increase max_tokens or reduce input size.',
			);
		}

		return array(
			'success' => true,
			'content' => $response_text,
			'error'   => '',
		);
	}

	/**
	 * Build the message content array for the API request.
	 *
	 * Each screenshot set is an array of chunk URLs produced by the screenshot
	 * service when a page exceeds the 7500 px height limit. Every chunk becomes
	 * its own vision image block. Labels include "part X of Y" when there is
	 * more than one chunk so Claude understands the vertical ordering.
	 *
	 * @param string   $prompt                Full text prompt.
	 * @param string[] $elementor_shots        Elementor desktop chunk URLs.
	 * @param string[] $gutenberg_shots        Gutenberg desktop chunk URLs.
	 * @param string[] $elementor_mobile_shots Elementor mobile chunk URLs.
	 * @param string[] $gutenberg_mobile_shots Gutenberg mobile chunk URLs.
	 * @return string|array Plain string when no valid images are present; structured
	 *                      content array when at least one image URL is provided.
	 */
	private static function build_message_content(
		string $prompt,
		array $elementor_shots,
		array $gutenberg_shots,
		array $elementor_mobile_shots,
		array $gutenberg_mobile_shots
	) {
		$image_blocks = array();

		$sets = array(
			array(
				'urls'  => $elementor_shots,
				'label' => 'DESKTOP screenshot of the ORIGINAL Elementor page',
			),
			array(
				'urls'  => $gutenberg_shots,
				'label' => 'DESKTOP screenshot of the CONVERTED Gutenberg page',
			),
			array(
				'urls'  => $elementor_mobile_shots,
				'label' => 'MOBILE screenshot of the ORIGINAL Elementor page',
			),
			array(
				'urls'  => $gutenberg_mobile_shots,
				'label' => 'MOBILE screenshot of the CONVERTED Gutenberg page',
			),
		);

		foreach ( $sets as $set ) {
			$urls  = array_values( array_filter( $set['urls'], 'is_string' ) );
			$total = count( $urls );
			if ( 0 === $total ) {
				continue;
			}

			foreach ( $urls as $i => $url ) {
				if ( '' === $url ) {
					continue;
				}
				$part_suffix    = $total > 1 ? sprintf( ' (part %d of %d)', $i + 1, $total ) : '';
				$image_blocks[] = array(
					'type' => 'text',
					'text' => 'This is a ' . $set['label'] . $part_suffix . ':',
				);
				$image_blocks[] = array(
					'type'   => 'image',
					'source' => array(
						'type' => 'url',
						'url'  => $url,
					),
				);
			}
		}

		if ( empty( $image_blocks ) ) {
			return $prompt;
		}

		$image_blocks[] = array(
			'type' => 'text',
			'text' => $prompt,
		);

		return $image_blocks;
	}

	/**
	 * Parse Claude's response text for the mobile-only improvement pass.
	 *
	 * The mobile pass should return only a CSS_RESULT block (no GUTENBERG_RESULT).
	 * If the marker is missing the entire response is returned as the CSS body so
	 * the caller can still recover something usable.
	 *
	 * @param string $text Raw Claude response text.
	 * @return string CSS body without the CSS_RESULT label.
	 */
	public static function parse_css_only_response( string $text ): string {
		if ( preg_match( '/CSS_RESULT:\s*(.*)/s', $text, $m ) ) {
			return trim( $m[1] );
		}

		return trim( $text );
	}

	/**
	 * Parse Claude's response text into CSS and Gutenberg content blocks.
	 *
	 * Expected format:
	 *   CSS_RESULT:
	 *   <css here>
	 *
	 *   GUTENBERG_RESULT:
	 *   <gutenberg content here>
	 *
	 * @param string $text Raw Claude response text.
	 * @return array{css: string, gutenberg: string}
	 */
	public static function parse_response( string $text ): array {
		$css       = '';
		$gutenberg = '';

		if ( preg_match( '/CSS_RESULT:\s*(.*?)\s*GUTENBERG_RESULT:/s', $text, $m ) ) {
			$css = trim( $m[1] );
		}

		if ( preg_match( '/GUTENBERG_RESULT:\s*(.*)/s', $text, $m ) ) {
			$gutenberg = trim( $m[1] );
		}

		return array(
			'css'       => $css,
			'gutenberg' => $gutenberg,
		);
	}

	/**
	 * Return the system prompt used for all AI improvement requests.
	 *
	 * Keeping instructions in the system role (not the user message) makes them
	 * authoritative and prevents them from being overwhelmed by the large data payload.
	 *
	 * @return string
	 */
	private static function get_system_prompt(): string {
		return <<<'SYSTEM'
You are a WordPress developer expert in both Elementor and Gutenberg (Block Editor).

Your job is to improve a Gutenberg page that was auto-converted from an Elementor page, making it as visually faithful as possible to the original Elementor design.

STRICT RULES:
1. Return ONLY two labeled sections — nothing else before, between, or after them.
2. CSS_RESULT must contain plain CSS only (no explanation, no markdown fences).
3. GUTENBERG_RESULT must contain the complete, valid Gutenberg post_content only (no explanation, no markdown fences).
4. Preserve all original text content exactly — never remove, rewrite, or paraphrase any text.
5. Keep all Gutenberg block comment delimiters syntactically valid (<!-- wp:block-name --> ... <!-- /wp:block-name -->).
6. Fix spacing, typography, alignment, and responsive behavior to match the Elementor original as closely as possible.
7. If no CSS changes are needed, output CSS_RESULT: with an empty body.
8. Output the full Gutenberg content — never truncate or abbreviate it.
9. CRITICAL — CSS scoping: The PAGE CONTEXT includes a "CSS Namespace" class (e.g. .metg-page-97). You MUST use that exact class as the root selector for ALL CSS rules. Never use the Target Gutenberg page ID in any CSS class name.
10. NEVER use <!-- wp:html --> blocks. All content MUST use proper Gutenberg blocks (wp:group, wp:columns, wp:column, wp:heading, wp:paragraph, wp:buttons, wp:button, wp:image, wp:separator, wp:list, wp:navigation, wp:site-logo, etc.). Preserve the block structure from GUTENBERG_CONTENT — add CSS classes or modify block attributes, but never replace blocks with raw HTML. The output must remain fully editable in the WordPress Block Editor.
11. Put all visual styling in CSS_RESULT using the CSS Namespace. Do not use inline styles in the Gutenberg HTML unless the original GUTENBERG_CONTENT already had them for block-level attributes (padding, margin, background-color).

REQUIRED OUTPUT FORMAT (exactly this structure, no deviations):
CSS_RESULT:
<your css here>

GUTENBERG_RESULT:
<full gutenberg post_content here>
SYSTEM;
	}

	/**
	 * Return the system prompt used for refinement (Round 2+) requests.
	 *
	 * Refinement is a targeted pass on an already-improved page. The user has
	 * identified a specific area to fix. Claude must return the COMPLETE CSS and
	 * Gutenberg content — not just the delta — so the stylesheet can be fully replaced.
	 *
	 * @return string
	 */
	public static function get_refinement_system_prompt(): string {
		return <<<'SYSTEM'
You are a WordPress developer expert in both Elementor and Gutenberg (Block Editor).

A Gutenberg page has already been improved once to match its original Elementor design.
You are now performing a targeted refinement pass based on specific user feedback.

YOUR JOB:
Carefully compare the two screenshots (Elementor original vs Gutenberg converted). Identify every visual difference — colors, typography, spacing, layout, alignment — and fix them all so the Gutenberg page matches the Elementor original as closely as possible.
The USER_FOCUS note gives you a starting hint or priority area, but do not limit yourself to it. Fix everything you can see is wrong by comparing the screenshots.

COLOR RULES:
- When the Elementor JSON defines a color via `"__globals__": {"title_color": "globals/colors?id=..."}`, that element has NO explicit color — it inherits the theme. Do NOT guess or invent a color for it. Read the actual color from the Elementor screenshot instead.
- Only apply an explicit `color` value when the Elementor JSON has a hardcoded color (e.g. `"title_color": "#ffffff"`) OR when you can clearly see it in the Elementor screenshot.
- Never apply `color: #ffffff` to text that sits over a light/white background area.

STRICT RULES:
1. Return ONLY two labeled sections — nothing else before, between, or after them.
2. CSS_RESULT must contain the COMPLETE updated CSS — this fully replaces the existing stylesheet. Preserve all existing rules unless changing them to fix a visual difference.
3. GUTENBERG_RESULT must contain the complete, valid Gutenberg post_content only (no explanation, no markdown fences).
4. Preserve all original text content exactly — never remove, rewrite, or paraphrase any text.
5. Keep all Gutenberg block comment delimiters syntactically valid (<!-- wp:block-name --> ... <!-- /wp:block-name -->).
6. Do NOT change responsive/mobile breakpoints unless the user explicitly asks.
7. Do NOT modify anything outside the page content — site header and footer are out of scope.
8. If no CSS changes are needed, output CSS_RESULT: followed by the existing CSS unchanged.
9. Output the full Gutenberg content — never truncate or abbreviate it.
10. CRITICAL — CSS scoping: use the "CSS Namespace" class from PAGE CONTEXT (e.g. .metg-page-97) as the root selector for ALL CSS rules. Never use the Target Gutenberg page ID in any CSS class name.
11. NEVER use <!-- wp:html --> blocks. All content MUST use proper Gutenberg blocks (wp:group, wp:columns, wp:column, wp:heading, wp:paragraph, wp:buttons, wp:button, wp:image, wp:separator, wp:list, wp:navigation, wp:site-logo, etc.). Preserve the block structure from GUTENBERG_CONTENT — add CSS classes or modify block attributes, but never replace blocks with raw HTML. The output must remain fully editable in the WordPress Block Editor.
12. Put all visual styling in CSS_RESULT using the CSS Namespace. Do not use inline styles in the Gutenberg HTML unless the original GUTENBERG_CONTENT already had them for block-level attributes (padding, margin, background-color).

REQUIRED OUTPUT FORMAT (exactly this structure, no deviations):
CSS_RESULT:
<complete css here>

GUTENBERG_RESULT:
<full gutenberg post_content here>
SYSTEM;
	}

	/**
	 * Return the system prompt used for the mobile-only improvement pass.
	 *
	 * The mobile pass must NOT modify desktop styles or the Gutenberg post_content.
	 * It returns only mobile-scoped @media query rules that the caller wraps into
	 * a marker block and merges into the existing CSS file.
	 *
	 * @return string
	 */
	public static function get_mobile_improvement_system_prompt(): string {
		return <<<'SYSTEM'
You are a WordPress developer expert in both Elementor and Gutenberg (Block Editor).

You are running a MOBILE-ONLY improvement pass on a Gutenberg page that was auto-converted from an Elementor page. The user has already improved desktop layout. You must now make the page render correctly on mobile WITHOUT affecting desktop styles.

YOUR JOB:
Carefully compare the two MOBILE screenshots (Elementor original mobile vs Gutenberg converted mobile). Identify every mobile-only visual difference — spacing, typography sizing, alignment, stacking, image sizing — and fix them by writing CSS rules that only apply on mobile viewports.

STRICT RULES:
1. Output a SINGLE labeled section: CSS_RESULT — nothing else before or after.
2. CSS_RESULT must contain plain CSS only (no explanation, no markdown fences, no GUTENBERG_RESULT).
3. EVERY rule you output MUST be wrapped in an `@media` query that only matches mobile (e.g. `@media (max-width: 781px) { ... }` or `@media (max-width: 600px) { ... }`). NEVER output any rule that applies on desktop.
4. Do NOT modify or duplicate existing desktop CSS rules. Only ADD mobile-scoped overrides.
5. Do NOT propose changes to the Gutenberg post_content. The block structure is fixed for this pass.
6. Use the "CSS Namespace" class from PAGE CONTEXT (e.g. .metg-page-97) as the root selector for ALL CSS rules. Never use the Target Gutenberg page ID in any CSS class name.
7. If no mobile changes are needed, output an empty CSS_RESULT body.
8. Do NOT include `<style>` tags, HTML, or markdown — plain CSS only.

REQUIRED OUTPUT FORMAT (exactly this structure, no deviations):
CSS_RESULT:
@media (max-width: 781px) {
  .metg-page-XX .some-block { ... }
}
SYSTEM;
	}

	/**
	 * Append a structured log entry to wp-content/metg-claude-api.log.
	 *
	 * Used for both api_request and api_response events. Each entry is one JSON
	 * object followed by a blank separator line so the file is easy to tail/grep.
	 *
	 * @param array $data Log entry fields (merged with timestamp + model).
	 */
	private static function log_entry( array $data ): void {
		$upload_dir = wp_upload_dir();
		$log_file   = trailingslashit( $upload_dir['basedir'] ) . 'metg-claude-api.log';

		$entry = array_merge(
			array(
				'timestamp' => gmdate( 'Y-m-d H:i:s' ),
				'model'     => self::MODEL,
			),
			$data
		);

		$line = wp_json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $line ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $line . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get the configured Claude API key.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		$settings = get_option( 'metg_claude_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		return sanitize_text_field( $settings['api_key'] ?? '' );
	}
}
