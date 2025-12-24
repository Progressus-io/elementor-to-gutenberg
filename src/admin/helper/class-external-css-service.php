<?php
/**
 * External CSS file service.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function current_time;
use function get_post_meta;
use function is_array;
use function is_string;
use function trailingslashit;
use function update_post_meta;
use function wp_get_upload_dir;
use function wp_mkdir_p;

defined( 'ABSPATH' ) || exit;

class External_CSS_Service {

	const META_KEY = '_progressus_gutenberg_external_css';

	/**
	 * Save a CSS string as an external file in uploads and store reference in post meta.
	 *
	 * @param int $post_id Post ID.
	 * @param string $css CSS content.
	 *
	 * @return array|null Meta payload on success, null on failure or empty CSS.
	 */
	public static function save_post_css( int $post_id, string $css ): ?array {
		$css = self::normalize_css( $css );
		if ( '' === $css ) {
			self::delete_post_css_meta( $post_id );

			return null;
		}

		$upload = wp_get_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return null;
		}

		$dir_rel  = 'progressus-gutenberg/css';
		$base_dir = trailingslashit( (string) $upload['basedir'] );
		$base_url = trailingslashit( (string) $upload['baseurl'] );

		$target_dir = $base_dir . $dir_rel;
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return null;
		}

		$hash     = substr( md5( $css ), 0, 12 );
		$filename = 'page-' . (string) $post_id . '-' . $hash . '.css';

		$path = trailingslashit( $target_dir ) . $filename;
		$url  = trailingslashit( $base_url . $dir_rel ) . $filename;

		if ( ! self::write_file( $path, $css ) ) {
			return null;
		}

		$meta = array(
			'url'      => $url,
			'path'     => $path,
			'hash'     => $hash,
			'saved_at' => current_time( 'mysql' ),
		);

		update_post_meta( $post_id, self::META_KEY, $meta );

		return $meta;
	}

	/**
	 * Get stored CSS meta for a post.
	 *
	 * @param int $post_id
	 *
	 * @return array|null
	 */
	public static function get_post_css_meta( int $post_id ): ?array {
		$meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $meta ) ) {
			return null;
		}

		if ( empty( $meta['url'] ) || empty( $meta['path'] ) ) {
			return null;
		}

		return $meta;
	}

	/**
	 * Delete CSS meta reference.
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	public static function delete_post_css_meta( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}

	/**
	 * Enqueue a post CSS file if stored and readable.
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	public static function enqueue_post_css( int $post_id ): void {
		$meta = self::get_post_css_meta( $post_id );
		if ( null === $meta ) {
			return;
		}

		$path = is_string( $meta['path'] ) ? $meta['path'] : '';
		$url  = is_string( $meta['url'] ) ? $meta['url'] : '';

		if ( '' === $path || '' === $url || ! file_exists( $path ) ) {
			self::delete_post_css_meta( $post_id );

			return;
		}

		$ver = (string) filemtime( $path );
		$hdl = 'progressus-gutenberg-page-css-' . (string) $post_id;

		wp_enqueue_style( $hdl, $url, array(), $ver );
	}

	/**
	 * Normalize CSS content.
	 *
	 * @param string $css
	 *
	 * @return string
	 */
	private static function normalize_css( string $css ): string {
		$css = str_replace( "\r\n", "\n", $css );
		$css = str_replace( "\r", "\n", $css );
		$css = trim( $css );

		return $css;
	}

	/**
	 * Write a file safely via WP_Filesystem when available.
	 *
	 * @param string $path
	 * @param string $contents
	 *
	 * @return bool
	 */
	private static function write_file( string $path, string $contents ): bool {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$filesystem_ready = WP_Filesystem();
		if ( $filesystem_ready ) {
			global $wp_filesystem;
			if ( $wp_filesystem && method_exists( $wp_filesystem, 'put_contents' ) ) {
				return (bool) $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
			}
		}

		return false !== file_put_contents( $path, $contents );
	}
}
