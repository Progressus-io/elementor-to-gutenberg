<?php
// phpcs:ignoreFile

/**
 * Sends a feedback manifest to the ETG Feedback Receiver.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the HTTP POST to the hardcoded receiver endpoint.
 * Authentication uses a per-site client_id + client_secret pair that is
 * auto-generated on first use and stored in the blockshift_feedback_client WP option.
 */
class Feedback_Sender {

	/**
	 * Hardcoded receiver endpoint — never changeable by site admins.
	 */
	const RECEIVER_URL = 'https://block-shift.com/wp-json/metg-feedback/v1/submit';

	/**
	 * WP option key that stores the auto-generated client credentials.
	 */
	const OPTION_CREDENTIALS = 'blockshift_feedback_client';

	/**
	 * Send the manifest array to the receiver.
	 *
	 * @param array<string,mixed> $manifest Assembled feedback manifest.
	 *
	 * @return array<string,mixed>|WP_Error Decoded receiver response or a WP_Error.
	 */
	public static function send( array $manifest ) {
		$creds = self::get_or_create_credentials();

		$json = wp_json_encode( $manifest );
		if ( false === $json ) {
			return new WP_Error( 'blockshift_feedback_encode', 'Failed to encode manifest as JSON.' );
		}

		$response = wp_remote_post(
			self::RECEIVER_URL,
			array(
				'body'        => $json,
				'headers'     => array(
					'Content-Type'    => 'application/json',
					'X-ETG-Client-ID' => $creds['client_id'],
					'Authorization'   => 'Bearer ' . $creds['client_secret'],
				),
				'timeout'     => 30,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( 200 === $code ) {
			return is_array( $decoded ) ? $decoded : array( 'success' => true );
		}

		$message = self::message_for_code( $code, $decoded );

		return new WP_Error(
			'blockshift_feedback_receiver_error',
			$message,
			array( 'http_code' => $code )
		);
	}

	/**
	 * Return a user-friendly message for a given HTTP status code.
	 *
	 * @param int                      $code    HTTP status code.
	 * @param array<string,mixed>|null $decoded Decoded response body, if any.
	 */
	private static function message_for_code( int $code, $decoded ): string {
		switch ( $code ) {
			case 401:
				return 'Authentication failed. Please contact the plugin developer.';
			case 403:
				$reason = is_array( $decoded ) ? ( (string) ( $decoded['error'] ?? '' ) ) : '';
				if ( in_array( $reason, array( 'client_blocked', 'client_revoked' ), true ) ) {
					return 'Feedback submission is unavailable for this site. Please contact the plugin developer.';
				}
				return 'Feedback submission is not available for this site.';
			case 409:
				return 'This feedback has already been submitted.';
			case 413:
				return 'The feedback package is too large to send. Please try with fewer items.';
			case 422:
				return 'Required diagnostic data is missing from the feedback package.';
			case 429:
				if ( is_array( $decoded ) && isset( $decoded['retry_after_seconds'] ) ) {
					$minutes = (int) ceil( (int) $decoded['retry_after_seconds'] / 60 );
					return sprintf( 'Feedback limit reached. Please try again in %d minute(s).', $minutes );
				}
				return 'Feedback limit reached. Please try again later.';
			default:
				return sprintf( 'Receiver returned HTTP %d.', $code );
		}
	}

	/**
	 * Get existing client credentials or generate new ones on first call.
	 *
	 * @return array{client_id: string, client_secret: string}
	 */
	public static function get_or_create_credentials(): array {
		$stored = get_option( self::OPTION_CREDENTIALS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( ! empty( $stored['client_id'] ) && ! empty( $stored['client_secret'] ) ) {
			return array(
				'client_id'     => (string) $stored['client_id'],
				'client_secret' => (string) $stored['client_secret'],
			);
		}

		$credentials = array(
			'client_id'     => 'etgc_' . substr( hash( 'sha256', home_url() . microtime() . wp_rand() ), 0, 20 ),
			'client_secret' => wp_generate_password( 40, false, false ),
		);

		update_option( self::OPTION_CREDENTIALS, $credentials, false );

		return $credentials;
	}

	/**
	 * Always true — URL is hardcoded and credentials are auto-generated.
	 */
	public static function is_configured(): bool {
		return true;
	}
}
