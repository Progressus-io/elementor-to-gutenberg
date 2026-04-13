<?php
/**
 * Claude API service.
 *
 * Sends prompts to the Anthropic Claude API and parses the response.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

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
	const MODEL = 'claude-opus-4-5';

	/**
	 * Send a prompt to Claude and return the text response.
	 *
	 * When screenshot URLs are provided they are included as vision image blocks
	 * so Claude can visually compare the Elementor and Gutenberg pages.
	 *
	 * @param string $prompt           The full prompt text.
	 * @param string $elementor_shot   Optional public URL of the Elementor screenshot.
	 * @param string $gutenberg_shot   Optional public URL of the Gutenberg screenshot.
	 * @return array{success: bool, content: string, error: string}
	 */
	public static function send( string $prompt, string $elementor_shot = '', string $gutenberg_shot = '' ): array {
		$api_key = self::get_api_key();

		if ( '' === $api_key ) {
			return array( 'success' => false, 'content' => '', 'error' => 'Claude API key is not configured.' );
		}

		$content = self::build_message_content( $prompt, $elementor_shot, $gutenberg_shot );

		$body = wp_json_encode(
			array(
				'model'       => self::MODEL,
				'max_tokens'  => 64000,
				'temperature' => 0,
				'system'      => self::get_system_prompt(),
				'messages'    => array(
					array( 'role' => 'user', 'content' => $content ),
				),
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
			return array( 'success' => false, 'content' => '', 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== $code || empty( $data['content'][0]['text'] ) ) {
			$error = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : "Unexpected response (HTTP $code).";
			self::log_response( array(
				'success'       => false,
				'http_code'     => $code,
				'stop_reason'   => '',
				'input_tokens'  => $data['usage']['input_tokens'] ?? null,
				'output_tokens' => $data['usage']['output_tokens'] ?? null,
				'error'         => $error,
				'raw_response'  => '',
			) );
			return array( 'success' => false, 'content' => '', 'error' => $error );
		}

		$response_text = (string) $data['content'][0]['text'];
		$stop_reason   = isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '';

		self::log_response( array(
			'success'       => 'max_tokens' !== $stop_reason,
			'http_code'     => $code,
			'stop_reason'   => $stop_reason,
			'input_tokens'  => $data['usage']['input_tokens'] ?? null,
			'output_tokens' => $data['usage']['output_tokens'] ?? null,
			'error'         => 'max_tokens' === $stop_reason ? 'Response truncated — max_tokens limit reached.' : '',
			'raw_response'  => $response_text,
		) );

		if ( 'max_tokens' === $stop_reason ) {
			return array(
				'success' => false,
				'content' => $response_text,
				'error'   => 'Claude response was truncated (max_tokens limit reached). Increase max_tokens or reduce input size.',
			);
		}

		return array( 'success' => true, 'content' => $response_text, 'error' => '' );
	}

	/**
	 * Build the message content array for the API request.
	 *
	 * If screenshot URLs are provided, prepends them as vision image blocks so
	 * Claude can see both pages before reading the text prompt. Images without a
	 * valid URL are silently skipped.
	 *
	 * @param string $prompt         Full text prompt.
	 * @param string $elementor_shot Public URL of the Elementor screenshot (optional).
	 * @param string $gutenberg_shot Public URL of the Gutenberg screenshot (optional).
	 * @return string|array A plain string when no images are present, a structured
	 *                      content array when at least one image URL is provided.
	 */
	private static function build_message_content( string $prompt, string $elementor_shot, string $gutenberg_shot ) {
		$image_blocks = array();

		if ( '' !== $elementor_shot ) {
			$image_blocks[] = array(
				'type'   => 'text',
				'text'   => 'This is a screenshot of the ORIGINAL Elementor page:',
			);
			$image_blocks[] = array(
				'type'   => 'image',
				'source' => array( 'type' => 'url', 'url' => $elementor_shot ),
			);
		}

		if ( '' !== $gutenberg_shot ) {
			$image_blocks[] = array(
				'type'   => 'text',
				'text'   => 'This is a screenshot of the CONVERTED Gutenberg page:',
			);
			$image_blocks[] = array(
				'type'   => 'image',
				'source' => array( 'type' => 'url', 'url' => $gutenberg_shot ),
			);
		}

		if ( empty( $image_blocks ) ) {
			return $prompt;
		}

		$image_blocks[] = array( 'type' => 'text', 'text' => $prompt );

		return $image_blocks;
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

		return array( 'css' => $css, 'gutenberg' => $gutenberg );
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

REQUIRED OUTPUT FORMAT (exactly this structure, no deviations):
CSS_RESULT:
<your css here>

GUTENBERG_RESULT:
<full gutenberg post_content here>
SYSTEM;
	}

	/**
	 * Append a structured log entry for a Claude API call to a file in wp-content.
	 *
	 * Each entry is one JSON object followed by a blank separator line.
	 * File: wp-content/ele2gb-claude-api.log
	 *
	 * Fields logged:
	 *   timestamp     — MySQL-style datetime of the call.
	 *   model         — Model name used.
	 *   success       — Whether the call succeeded.
	 *   http_code     — HTTP status code returned.
	 *   stop_reason   — Claude's stop_reason (end_turn / max_tokens / stop_sequence).
	 *   input_tokens  — Tokens consumed by the prompt (from usage object).
	 *   output_tokens — Tokens consumed by the response (from usage object).
	 *   error         — Error message if any.
	 *   raw_response  — Full raw text returned by Claude.
	 *
	 * @param array $data Log entry fields.
	 */
	private static function log_response( array $data ): void {
		$log_file = WP_CONTENT_DIR . '/ele2gb-claude-api.log';

		$entry = array(
			'timestamp'     => gmdate( 'Y-m-d H:i:s' ),
			'model'         => self::MODEL,
			'success'       => $data['success'] ?? false,
			'http_code'     => $data['http_code'] ?? 0,
			'stop_reason'   => $data['stop_reason'] ?? '',
			'input_tokens'  => $data['input_tokens'] ?? null,
			'output_tokens' => $data['output_tokens'] ?? null,
			'error'         => $data['error'] ?? '',
			'raw_response'  => $data['raw_response'] ?? '',
		);

		$line = json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

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
		$settings = get_option( 'etg_claude_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		return sanitize_text_field( $settings['api_key'] ?? '' );
	}
}
