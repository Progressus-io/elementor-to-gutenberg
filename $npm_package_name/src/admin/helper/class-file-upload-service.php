<?php
/**
 * Service class for handling file uploads.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Service class for handling file uploads.
 */
class File_Upload_Service {
	/**
	 * Upload a file to the WordPress media library or process JSON.
	 *
	 * @param array  $file The uploaded file array from $_FILES.
	 * @param string $type The file type ('json' or 'image').
	 * @return string|null The file URL or JSON content on success, null on failure.
	 */
	public static function upload_file( array $file, string $type = 'image' ): ?string {
		$tmp_file = $file['tmp_name'];
		if ( ! file_exists( $tmp_file ) ) {
			return null;
		}

		$content = file_get_contents( $tmp_file );
		if ( 'json' === $type ) {
			$data = json_decode( $content, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				add_settings_error(
					'gutenberg_json_data',
					'json_upload_error',
					esc_html__( 'Invalid JSON file uploaded.', 'elementor-to-gutenberg' ),
					'error'
				);
				return null;
			}
			return $content;
		}

		$file_array = array(
			'name'     => sanitize_file_name( basename( $file['name'] ) ),
			'tmp_name' => $tmp_file,
		);
		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			add_settings_error(
				'gutenberg_json_data',
				'json_upload_error',
				esc_html__( 'Failed to upload file.', 'elementor-to-gutenberg' ),
				'error'
			);
			return null;
		}

		return wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Download a file from a URL and upload to media library.
	 *
	 * @param string $url The URL of the file to download.
	 * @return string|null The uploaded file URL or null on failure.
	 */
	public static function download_and_upload( string $url ): ?string {
		$tmp_file = download_url( $url );
		if ( is_wp_error( $tmp_file ) ) {
			return null;
		}

		$file_array = array(
			'name'     => sanitize_file_name( basename( $url ) ),
			'tmp_name' => $tmp_file,
		);
		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( file_exists( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		return wp_get_attachment_url( $attachment_id );
	}
}