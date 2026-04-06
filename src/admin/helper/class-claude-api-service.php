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
				'model'      => self::MODEL,
				'max_tokens' => 8000,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $content ),
				),
			)
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 120,
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
			return array( 'success' => false, 'content' => '', 'error' => $error );
		}

		return array( 'success' => true, 'content' => (string) $data['content'][0]['text'], 'error' => '' );
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
