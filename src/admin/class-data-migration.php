<?php
/**
 * One-time data migration for the rename to "Migrate Elementor to Gutenberg".
 *
 * Earlier versions of the plugin stored data under the `ele2gb`/`etg`/
 * `progressus_gutenberg` prefixes and the `etg-*` CSS scope. This migration
 * renames that data to the unified `metg` scheme so pages converted before the
 * rename keep rendering and the admin tools keep finding their settings:
 *
 * - post meta keys      `_ele2gb_*`, `_etg_*`, `_progressus_gutenberg_external_css`
 * - user meta keys      `_ele2gb_*`
 * - option names        `etg_*`, `_etg_*`, `progressus_gutenberg_font_requirements`
 * - page template slug  `templates/etg-full-width-page.php`, `progressus-etg//full-width-page`
 * - post_content scope   `etg-page-*`, `etg-widget-*`, `etg-full-width-page`
 * - external CSS files   uploads/etg/etg-page-*.css -> uploads/metg/metg-page-*.css
 *
 * The whole pass is guarded by an option so it runs at most once per site.
 *
 * @package Progressus\MigrateElementorToGutenberg
 */

namespace Progressus\MigrateElementorToGutenberg\Admin;

use function add_option;
use function current_user_can;
use function get_option;
use function glob;
use function is_admin;
use function is_dir;
use function rename;
use function str_replace;
use function trailingslashit;
use function update_option;
use function wp_get_upload_dir;

defined( 'ABSPATH' ) || exit;

/**
 * Renames pre-rename data to the unified `metg` scheme.
 */
class Data_Migration {

	/**
	 * Option used to record that the rename migration has completed.
	 */
	const VERSION_OPTION = 'metg_data_version';

	/**
	 * Marker stored once the migration has run for the rename.
	 */
	const TARGET_VERSION = '2024-rename-metg';

	/**
	 * Run the migration once, in the admin, for users who can manage options.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( self::TARGET_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::migrate_postmeta_keys();
		self::migrate_usermeta_keys();
		self::migrate_option_names();
		self::migrate_page_template_assignments();
		self::migrate_post_content();
		self::migrate_external_css();

		// Record completion (add_option first run, update_option afterwards).
		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, self::TARGET_VERSION, '', false );
		} else {
			update_option( self::VERSION_OPTION, self::TARGET_VERSION, false );
		}
	}

	/**
	 * Rename legacy post meta key prefixes to `_metg_`.
	 *
	 * @return void
	 */
	private static function migrate_postmeta_keys(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE( meta_key, '_ele2gb_', '_metg_' ) WHERE meta_key LIKE '\_ele2gb\_%'" );
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE( meta_key, '_etg_', '_metg_' ) WHERE meta_key LIKE '\_etg\_%'" );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				'_metg_external_css',
				'_progressus_gutenberg_external_css'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rename legacy user meta key prefixes to `_metg_`.
	 *
	 * @return void
	 */
	private static function migrate_usermeta_keys(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_key = REPLACE( meta_key, '_ele2gb_', '_metg_' ) WHERE meta_key LIKE '\_ele2gb\_%'" );
	}

	/**
	 * Rename legacy option names to the `metg` scheme.
	 *
	 * @return void
	 */
	private static function migrate_option_names(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE( option_name, '_etg_', '_metg_' ) WHERE option_name LIKE '\_etg\_%'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE( option_name, 'etg_', 'metg_' ) WHERE option_name LIKE 'etg\_%'" );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s",
				'metg_font_requirements',
				'progressus_gutenberg_font_requirements'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Renaming option_name directly bypasses the options cache.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_flush();
	}

	/**
	 * Re-point converted pages to the renamed full-width page template.
	 *
	 * @return void
	 */
	private static function migrate_page_template_assignments(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_wp_page_template' AND meta_value = %s",
				'templates/metg-full-width-page.php',
				'templates/etg-full-width-page.php'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_wp_page_template' AND meta_value = %s",
				'progressus-metg//full-width-page',
				'progressus-etg//full-width-page'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rewrite the CSS-scope classes baked into already-converted page content.
	 *
	 * @return void
	 */
	private static function migrate_post_content(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			 WHERE post_content LIKE '%etg-page-%'
			    OR post_content LIKE '%etg-widget-%'
			    OR post_content LIKE '%etg-full-width-page%'
			    OR post_content LIKE '%progressus-etg//%'"
		);

		if ( empty( $rows ) ) {
			return;
		}

		// strtr() does a single, non-overlapping pass (longest key wins), so the
		// inserted `metg-*` values are never re-scanned by a shorter `etg-*` key.
		$map = array(
			'etg-page-'            => 'metg-page-',
			'etg-widget-'          => 'metg-widget-',
			'etg-full-width-page'  => 'metg-full-width-page',
			'progressus-etg//'     => 'progressus-metg//',
		);

		foreach ( $rows as $row ) {
			$updated = strtr( (string) $row->post_content, $map );
			if ( $updated === $row->post_content ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $updated ),
				array( 'ID' => (int) $row->ID )
			);
			clean_post_cache( (int) $row->ID );
		}
	}

	/**
	 * Move the external CSS upload directory/files and fix stored references.
	 *
	 * @return void
	 */
	private static function migrate_external_css(): void {
		$upload = wp_get_upload_dir();
		if ( ! empty( $upload['basedir'] ) ) {
			$base    = trailingslashit( (string) $upload['basedir'] );
			$old_dir = $base . 'etg';
			$new_dir = $base . 'metg';

			if ( is_dir( $old_dir ) && ! is_dir( $new_dir ) ) {
				// phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
				@rename( $old_dir, $new_dir );
			}

			if ( is_dir( $new_dir ) ) {
				foreach ( (array) glob( $new_dir . '/etg-page-*.css' ) as $old_file ) {
					$new_file = $new_dir . '/' . str_replace( 'etg-page-', 'metg-page-', basename( $old_file ) );
					if ( ! file_exists( $new_file ) ) {
						// phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
						@rename( $old_file, $new_file );
					}
				}
			}
		}

		// Fix the stored url/path inside the (already renamed) external CSS meta.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"UPDATE {$wpdb->postmeta}
			 SET meta_value = REPLACE( REPLACE( meta_value, '/etg/', '/metg/' ), 'etg-page-', 'metg-page-' )
			 WHERE meta_key = '_metg_external_css'"
		);
	}
}
