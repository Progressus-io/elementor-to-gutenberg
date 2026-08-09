<?php
/**
 * External CSS file service.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin\Helper;

use function absint;
use function current_time;
use function delete_post_meta;
use function get_option;
use function file_exists;
use function filemtime;
use function get_post_meta;
use function in_array;
use function is_array;
use function is_readable;
use function is_string;
use function json_decode;
use function maybe_unserialize;
use function md5;
use function update_option;
use function wp_json_encode;
use function update_post_meta;
use function wp_normalize_path;
use function wp_unslash;

defined( 'ABSPATH' ) || exit;

class External_CSS_Service {

	const META_KEY = '_blockshift_external_css';

	const CUSTOM_CSS_OPTION = 'blockshift_custom_css';

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
	 * @param int    $post_id Post ID.
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

		$dir_rel  = 'blockshift';
		$base_dir = trailingslashit( (string) $upload['basedir'] );
		$base_url = trailingslashit( (string) $upload['baseurl'] );

		$target_dir = $base_dir . $dir_rel;
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return null;
		}

		$hash     = substr( md5( $css ), 0, 12 );
		$filename = 'blockshift-page-' . (string) $post_id . '.css';

		$path = trailingslashit( $target_dir ) . $filename;
		$url  = trailingslashit( $base_url . $dir_rel ) . $filename;

		if ( ! self::write_file( $path, $css ) ) {
			return null;
		}

		$meta      = array(
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

		if ( ! is_array( $meta ) && is_string( $meta ) && '' !== $meta ) {
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
				$base_dir  = trailingslashit( wp_normalize_path( (string) $upload['basedir'] ) );
				$base_dir  = str_replace( '/', DIRECTORY_SEPARATOR, $base_dir );
				$filename  = basename( wp_normalize_path( $path ) );
				$candidate = $base_dir . 'blockshift' . DIRECTORY_SEPARATOR . $filename;

				if ( file_exists( $candidate ) ) {
					$path = $candidate;
				}
			}
		}

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return;
		}

		$hash = isset( $meta['hash'] ) ? (string) $meta['hash'] : '';
		$ver  = '' !== $hash ? $hash : (string) filemtime( $path );
		$hdl  = 'blockshift-page-css-' . (string) $post_id;

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
	 * Append migrated custom CSS to the plugin's own global stylesheet.
	 *
	 * Widget custom CSS carried over during conversion is untrusted author input.
	 * Rather than writing it to the site's Additional CSS (which replicates the
	 * Customizer's CSS editor), we sanitize it and store it in a plugin-owned CSS
	 * file that is enqueued site-wide by enqueue_global_custom_css().
	 *
	 * @param string $css CSS content.
	 *
	 * @return void
	 */
	public static function append_global_custom_css( string $css ): void {
		$css = self::normalize_css( $css );
		if ( '' === $css ) {
			return;
		}

		$existing = (string) get_option( self::CUSTOM_CSS_OPTION, '' );

		// Skip snippets already stored so repeated conversions don't grow the file.
		if ( '' !== $existing && false !== strpos( $existing, $css ) ) {
			return;
		}

		$combined = '' === $existing ? $css : rtrim( $existing ) . "\n" . $css;
		update_option( self::CUSTOM_CSS_OPTION, $combined, false );

		$paths = self::global_custom_css_paths();
		if ( null !== $paths ) {
			self::write_file( $paths['path'], $combined );
		}
	}

	/**
	 * Enqueue the plugin's global migrated-custom-CSS stylesheet on the front end.
	 *
	 * @return void
	 */
	public static function enqueue_global_custom_css(): void {
		$css = (string) get_option( self::CUSTOM_CSS_OPTION, '' );
		if ( '' === $css ) {
			return;
		}

		$paths = self::global_custom_css_paths();
		if ( null === $paths ) {
			return;
		}

		$path = self::normalize_fs_path( $paths['path'] );

		// Rebuild the file from the stored option if it was removed.
		if ( ! file_exists( $path ) ) {
			self::write_file( $paths['path'], $css );
			$path = self::normalize_fs_path( $paths['path'] );
		}

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style( 'blockshift-custom-css', $paths['url'], array(), substr( md5( $css ), 0, 12 ) );
	}

	/**
	 * Resolve the filesystem path and URL for the global custom-CSS file.
	 *
	 * @return array{path:string,url:string}|null
	 */
	private static function global_custom_css_paths(): ?array {
		$upload = wp_get_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return null;
		}

		$dir = trailingslashit( (string) $upload['basedir'] ) . 'blockshift';
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		return array(
			'path' => trailingslashit( $dir ) . 'blockshift-custom.css',
			'url'  => trailingslashit( (string) $upload['baseurl'] ) . 'blockshift/blockshift-custom.css',
		);
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

		return self::sanitize_css( $css );
	}

	/**
	 * Strip constructs that could turn a saved stylesheet into an attack vector.
	 *
	 * The generated CSS is written to a file in uploads and enqueued, so it never
	 * passes through WordPress content sanitization. This removes the pieces that
	 * do not belong in a plain stylesheet: markup, remote @import rules, and the
	 * legacy script-in-CSS vectors (expression(), behavior, -moz-binding,
	 * javascript:/vbscript: URIs).
	 *
	 * @param string $css CSS content.
	 *
	 * @return string Sanitized CSS.
	 */
	private static function sanitize_css( string $css ): string {
		if ( '' === $css ) {
			return '';
		}

		$patterns = array(
			// Control characters that have no place in a stylesheet.
			'/[\x00-\x08\x0B\x0C\x0E-\x1F]/'   => '',
			// Opening angle bracket: never valid CSS, enables </style> / <script> breakouts.
			// The ">" child combinator is left intact.
			'/</'                              => '',
			// Remote/arbitrary style inclusion. Bounded to a single line so a missing
			// semicolon cannot swallow the following rule.
			'/@import\b[^;\n]*;?/i'            => '',
			// Legacy IE CSS expressions (execute JS).
			'/expression\s*\(/i'               => '',
			// Script protocols anywhere (e.g. inside url()).
			'/(?:javascript|vbscript)\s*:/i'   => '',
			// Legacy data-binding / behavior declarations. The lookbehind keeps the
			// standard scroll-behavior and overscroll-behavior properties intact.
			'/-moz-binding\s*:[^;]*;?/i'       => '',
			'/(?<![-\w])behavior\s*:[^;]*;?/i' => '',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$result = preg_replace( $pattern, $replacement, $css );
			if ( null !== $result ) {
				$css = $result;
			}
		}

		return trim( $css );
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

		return false;
	}

	/**
	 * Register a post ID whose CSS should be enqueued on every frontend page.
	 *
	 * Used for converted header/footer template posts whose CSS must load
	 * globally, not just when viewing that specific post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function register_global_css_post( int $post_id ): void {
		$post_id = self::resolve_post_id( $post_id );
		$ids     = (array) get_option( '_blockshift_global_css_post_ids', array() );

		if ( ! in_array( $post_id, $ids, true ) ) {
			$ids[] = $post_id;
			update_option( '_blockshift_global_css_post_ids', $ids, false );
		}
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

		$global_ids = (array) get_option( '_blockshift_global_css_post_ids', array() );
		foreach ( $global_ids as $global_id ) {
			$global_id = (int) $global_id;
			if ( $global_id > 0 ) {
				self::enqueue_post_css( $global_id );
			}
		}
	}
}
