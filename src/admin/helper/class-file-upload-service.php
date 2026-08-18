<?php
/**
 * Service class for handling file uploads.
 *
 * @package Progressus\BlockShift
 */
namespace Progressus\BlockShift\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Service class for handling file uploads.
 */
class File_Upload_Service {
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

		$file_array    = array(
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
