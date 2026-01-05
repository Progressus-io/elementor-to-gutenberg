<?php
/**
 * External CSS file service.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function absint;
use function current_time;
use function delete_post_meta;
use function file_exists;
use function filemtime;
use function get_post_meta;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function maybe_unserialize;
use function wp_json_encode;
use function wp_normalize_path;
use function wp_unslash;

defined( 'ABSPATH' ) || exit;

class External_CSS_Service {

	const META_KEY = '_progressus_gutenberg_external_css';

	private static function resolve_post_id( int $post_id ): int {
		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			return absint( $parent_id );
		}

		$parent_id = wp_is_post_autosave( $post_id );
		if ( $parent_id ) {
			return absint( $parent_id );
		}

		return $post_id;
	}
	/**
	 * Save a CSS string as an external file in uploads and store reference in post meta.
	 *
	 * @param int $post_id Post ID.
	 * @param string $css CSS content.
	 *
	 * @return array|null Meta payload on success, null on failure or empty CSS.
	 */
	public static function save_post_css( int $post_id, string $css ): ?array {
		$post_id = self::resolve_post_id( $post_id );

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
		$meta_json = wp_json_encode( $meta, JSON_UNESCAPED_SLASHES );
		update_post_meta( $post_id, self::META_KEY, $meta_json );

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
		$post_id = self::resolve_post_id( $post_id );

		$meta = get_post_meta( $post_id, self::META_KEY, true );

		// If meta is already an array (serialized storage), use it directly.
		if ( is_array( $meta ) ) {
			// continue
		} elseif ( is_string( $meta ) && '' !== $meta ) {
			// Most common case: JSON string (sometimes slashed).
			$raw = trim( wp_unslash( $meta ) );

			$maybe = maybe_unserialize( $raw );
			if ( is_array( $maybe ) ) {
				$meta = $maybe;
			} else {
				$decoded = json_decode( $raw, true );

				// If the JSON decodes into a string (quoted JSON), decode again.
				if ( is_string( $decoded ) && '' !== $decoded ) {
					$decoded = json_decode( $decoded, true );
				}

				if ( is_array( $decoded ) ) {
					$meta = $decoded;
				}
			}
		}

		if ( ! is_array( $meta ) ) {
			return null;
		}

		$url  = ( isset( $meta['url'] ) && is_string( $meta['url'] ) ) ? $meta['url'] : '';
		$path = ( isset( $meta['path'] ) && is_string( $meta['path'] ) ) ? $meta['path'] : '';

		if ( '' === $url || '' === $path ) {
			return null;
		}

		// Normalize Windows paths to avoid file_exists/is_readable edge cases.
		$meta['path'] = wp_normalize_path( $path );

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
		$post_id = self::resolve_post_id( $post_id );
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
		$post_id = self::resolve_post_id( $post_id );

		static $enqueued = array();
		if ( isset( $enqueued[ $post_id ] ) ) {
			return;
		}
		$enqueued[ $post_id ] = true;

		$meta = self::get_post_css_meta( $post_id );
		if ( null === $meta ) {
			return;
		}

		$path = ( isset( $meta['path'] ) && is_string( $meta['path'] ) ) ? $meta['path'] : '';
		$url  = ( isset( $meta['url'] ) && is_string( $meta['url'] ) ) ? $meta['url'] : '';

		if ( '' === $path || '' === $url ) {
			return;
		}

		// Normalize/fix filesystem path.
		$path = self::normalize_fs_path( $path );

		// Fallback: rebuild path from uploads basedir using the filename.
		if ( ! file_exists( $path ) ) {
			$upload = wp_get_upload_dir();
			if ( ! empty( $upload['basedir'] ) ) {
				$base_dir   = trailingslashit( wp_normalize_path( (string) $upload['basedir'] ) );
				$base_dir   = str_replace( '/', DIRECTORY_SEPARATOR, $base_dir );
				$filename   = basename( wp_normalize_path( $path ) );
				$candidate  = $base_dir . 'progressus-gutenberg' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . $filename;

				if ( file_exists( $candidate ) ) {
					$path = $candidate;
				}
			}
		}

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return;
		}

		$ver = (string) filemtime( $path );
		$hdl = 'progressus-gutenberg-page-css-' . (string) $post_id;

		wp_enqueue_style( $hdl, $url, array(), $ver );
	}

	private static function normalize_fs_path( string $path ): string {
		$path = trim( $path );

		// Normalize slashes.
		$path = wp_normalize_path( $path );

		// Fix Windows drive path missing slash: "C:xampp/..." => "C:/xampp/..."
		if ( preg_match( '/^[A-Za-z]:(?!\/)/', $path ) ) {
			$path = substr( $path, 0, 2 ) . '/' . substr( $path, 2 );
		}

		// Convert to OS separator for filesystem calls (Windows likes backslashes).
		$path = str_replace( '/', DIRECTORY_SEPARATOR, $path );

		return $path;
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

	/**
	 * Enqueue CSS for the "current" post context (frontend or editor).
	 *
	 * @return void
	 */
	public static function enqueue_current_post_css(): void {
		$post_id = 0;

		// Frontend: use queried object.
		if ( ! is_admin() ) {
			$post_id = (int) get_queried_object_id();
		} else {
			// Editor/admin: try global $post first.
			global $post;
			if ( $post && isset( $post->ID ) ) {
				$post_id = (int) $post->ID;
			}

			// Fallback: classic editor / block editor edit screen usually provides ?post=123
			if ( 0 === $post_id && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( $post_id > 0 ) {
			self::enqueue_post_css( $post_id );
		}
	}

}
