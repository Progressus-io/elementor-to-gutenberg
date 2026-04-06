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
	 * @param string $prompt The full prompt text.
	 * @return array{success: bool, content: string, error: string}
	 */
	public static function send( string $prompt ): array {
		$api_key = self::get_api_key();

		if ( '' === $api_key ) {
			return array( 'success' => false, 'content' => '', 'error' => 'Claude API key is not configured.' );
		}

		$body = wp_json_encode(
			array(
				'model'      => self::MODEL,
				'max_tokens' => 8000,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
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
