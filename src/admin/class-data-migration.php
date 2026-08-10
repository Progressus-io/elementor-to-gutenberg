<?php
/**
 * One-time data migration for the rename to "Migrate Off Elementor".
 *
 * Earlier versions of this plugin stored data under the `ele2gb`, `etg` and
 * `progressus_gutenberg` prefixes and the `etg-*` CSS scope. This migration
 * renames that data to the unified `blockshift` scheme so pages converted
 * before the rename keep rendering and the admin tools keep finding their
 * settings.
 *
 * Scope rule, and it is the important part of this file: the migration only
 * ever touches names it can positively attribute to this plugin. Every option
 * name, meta key and file name it rewrites is spelled out in an allowlist
 * below, taken from the plugin's own history. It never matches a bare `LIKE`
 * against a short prefix such as `etg_`, because that prefix belongs to nobody
 * and a site can hold third-party rows that merely start with it.
 *
 * Shapes that are deliberately left alone, because they cannot be attributed:
 *
 * - `gutenberg_json_data`, the pre-rename name of `blockshift_json_data`. The
 *   name is generic enough that another plugin could legitimately own it on
 *   the same site.
 * - `etg_claude_settings`, `etg_screenshot_settings`, `_etg_hf_preview_page_id`
 *   and the `_ele2gb_ai_*` / `_etg_ai_screenshot_*` meta, which belonged to
 *   features that no longer ship. Nothing reads a renamed equivalent, so
 *   renaming them would achieve nothing.
 *
 * The pass runs as a sequence of steps, each of which records its own
 * completion the moment it finishes. An interrupted request therefore resumes
 * where it stopped instead of re-running everything from the top.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use function add_option;
use function basename;
use function clean_post_cache;
use function current_user_can;
use function delete_option;
use function get_option;
use function glob;
use function is_admin;
use function is_dir;
use function str_replace;
use function strtr;
use function trailingslashit;
use function update_option;
use function wp_cache_delete;
use function wp_cache_set_posts_last_changed;
use function wp_cache_set_users_last_changed;
use function wp_get_upload_dir;
use function WP_Filesystem;

defined( 'ABSPATH' ) || exit;

/**
 * Renames pre-rename data to the unified `blockshift` scheme.
 */
class Data_Migration {

	/**
	 * Option used to record that the migration has completed.
	 */
	const VERSION_OPTION = 'blockshift_data_version';

	/**
	 * Marker stored once every step below has run.
	 */
	const TARGET_VERSION = '2026-08-scope-and-log';

	/**
	 * Marker written by the original all-or-nothing rename pass. A site
	 * carrying it has already had the six rename steps applied.
	 */
	const LEGACY_RENAME_VERSION = '2024-rename-blockshift';

	/**
	 * Option holding the steps that have completed so far, so an interrupted
	 * run resumes rather than restarting. Removed once the pass finishes.
	 */
	const STEPS_OPTION = 'blockshift_data_migration_steps';

	/**
	 * Steps, in order. Each is a private method on this class.
	 *
	 * @var string[]
	 */
	private const STEPS = array(
		'migrate_postmeta_keys',
		'migrate_usermeta_keys',
		'migrate_option_names',
		'migrate_page_template_assignments',
		'migrate_post_content',
		'migrate_external_css',
		'secure_diagnostic_log',
	);

	/**
	 * The steps that the original rename pass already performed. A site holding
	 * LEGACY_RENAME_VERSION does not need them run again.
	 *
	 * @var string[]
	 */
	private const LEGACY_RENAME_STEPS = array(
		'migrate_postmeta_keys',
		'migrate_usermeta_keys',
		'migrate_option_names',
		'migrate_page_template_assignments',
		'migrate_post_content',
		'migrate_external_css',
	);

	/**
	 * Option names this plugin created before the rename, mapped to the names
	 * it uses now. Every one of these is verifiable in the plugin's own source
	 * history; nothing here is a guess.
	 *
	 * @var array<string,string>
	 */
	private const OPTION_MAP = array(
		'etg_conversion_preferences'             => 'blockshift_conversion_preferences',
		'etg_conversion_logging'                 => 'blockshift_conversion_logging',
		'etg_conversion_log'                     => 'blockshift_conversion_log',
		'etg_font_alias_map'                     => 'blockshift_font_alias_map',
		'etg_font_alias_map_version'             => 'blockshift_font_alias_map_version',
		'_etg_global_css_post_ids'               => '_blockshift_global_css_post_ids',
		'ele2gb_section_content_width'           => 'blockshift_section_content_width',
		'progressus_gutenberg_font_requirements' => 'blockshift_font_requirements',
	);

	/**
	 * Post meta keys this plugin wrote before the rename, mapped to the keys it
	 * writes now.
	 *
	 * @var array<string,string>
	 */
	private const POSTMETA_MAP = array(
		'_ele2gb_footer_part'                     => '_blockshift_footer_part',
		'_ele2gb_header_part'                     => '_blockshift_header_part',
		'_ele2gb_last_converted'                  => '_blockshift_last_converted',
		'_ele2gb_last_result'                     => '_blockshift_last_result',
		'_ele2gb_linked_pages'                    => '_blockshift_linked_pages',
		'_ele2gb_page_id'                         => '_blockshift_page_id',
		'_ele2gb_source_id'                       => '_blockshift_source_id',
		'_ele2gb_source_type'                     => '_blockshift_source_type',
		'_ele2gb_template_kind'                   => '_blockshift_template_kind',
		'_ele2gb_template_role'                   => '_blockshift_template_role',
		'_etg_used_fonts'                         => '_blockshift_used_fonts',
		'_etg_used_fonts_hash'                    => '_blockshift_used_fonts_hash',
		'_etg_ai_elementor_screenshot_url'        => '_blockshift_ai_elementor_screenshot_url',
		'_etg_ai_elementor_screenshot_mobile_url' => '_blockshift_ai_elementor_screenshot_mobile_url',
		'_etg_ai_gutenberg_screenshot_url'        => '_blockshift_ai_gutenberg_screenshot_url',
		'_etg_ai_gutenberg_screenshot_mobile_url' => '_blockshift_ai_gutenberg_screenshot_mobile_url',
		'_progressus_gutenberg_external_css'      => '_blockshift_external_css',
		'etg_source_menu_term_id'                 => 'blockshift_source_menu_term_id',
	);

	/**
	 * User meta keys this plugin wrote before the rename.
	 *
	 * @var array<string,string>
	 */
	private const USERMETA_MAP = array(
		'_ele2gb_job' => '_blockshift_job',
	);

	/**
	 * Meta keys that mark a post as one this plugin created or manages. Only
	 * posts carrying one of these have their `post_content` rewritten - the
	 * scope classes the migration is looking for also appear in text that
	 * belongs to other software, and a `LIKE '%etg-page-%'` over every row in
	 * `wp_posts` cannot tell the difference.
	 *
	 * These are the post-rename spellings because the meta-key step runs first.
	 *
	 * @var string[]
	 */
	private const OWNERSHIP_META_KEYS = array(
		'_blockshift_source_id',
		'_blockshift_page_id',
		'_blockshift_template_kind',
		'_blockshift_template_role',
		'_blockshift_header_part',
		'_blockshift_footer_part',
		'_blockshift_last_converted',
		'_blockshift_external_css',
		'_blockshift_used_fonts',
	);

	/**
	 * CSS scope replacements applied to the content of the plugin's own posts.
	 *
	 * strtr() does a single, non-overlapping pass (longest key wins), so the
	 * inserted `blockshift-*` values are never re-scanned by a shorter key.
	 *
	 * @var array<string,string>
	 */
	private const CONTENT_MAP = array(
		'etg-page-'           => 'blockshift-page-',
		'etg-widget-'         => 'blockshift-widget-',
		'etg-full-width-page' => 'blockshift-full-width-page',
		'progressus-etg//'    => 'progressus-blockshift//',
	);

	/**
	 * Run any outstanding migration steps, in the admin, for users who can
	 * manage options.
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

		$completed = self::get_completed_steps();

		foreach ( self::STEPS as $step ) {
			if ( in_array( $step, $completed, true ) ) {
				continue;
			}

			self::$step();

			// Recorded immediately, so a timeout or a fatal in a later step
			// cannot cause this one to run twice.
			$completed[] = $step;
			self::set_completed_steps( $completed );
		}

		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, self::TARGET_VERSION, '', false );
		} else {
			update_option( self::VERSION_OPTION, self::TARGET_VERSION, false );
		}

		delete_option( self::STEPS_OPTION );
	}

	/**
	 * The steps that have already run on this site.
	 *
	 * A site that completed the original all-or-nothing rename pass carries
	 * only its version marker, so the rename steps are treated as done and
	 * just the newer steps run.
	 *
	 * @return string[]
	 */
	private static function get_completed_steps(): array {
		$completed = get_option( self::STEPS_OPTION, array() );
		$completed = is_array( $completed ) ? array_values( array_filter( $completed, 'is_string' ) ) : array();

		if ( empty( $completed ) && self::LEGACY_RENAME_VERSION === get_option( self::VERSION_OPTION ) ) {
			$completed = self::LEGACY_RENAME_STEPS;
		}

		return $completed;
	}

	/**
	 * Persist the completed-step list.
	 *
	 * @param string[] $completed Completed step names.
	 *
	 * @return void
	 */
	private static function set_completed_steps( array $completed ): void {
		if ( false === get_option( self::STEPS_OPTION, false ) ) {
			add_option( self::STEPS_OPTION, $completed, '', false );
		} else {
			update_option( self::STEPS_OPTION, $completed, false );
		}
	}

	/**
	 * Rename the plugin's own legacy post meta keys.
	 *
	 * @return void
	 */
	private static function migrate_postmeta_keys(): void {
		global $wpdb;

		foreach ( self::POSTMETA_MAP as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Renaming a meta key has no core API, and the affected caches are cleared below.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new,
					$old
				)
			);
		}

		wp_cache_set_posts_last_changed();
	}

	/**
	 * Rename the plugin's own legacy user meta keys.
	 *
	 * @return void
	 */
	private static function migrate_usermeta_keys(): void {
		global $wpdb;

		foreach ( self::USERMETA_MAP as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Renaming a meta key has no core API; the user meta cache is invalidated below.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s",
					$new,
					$old
				)
			);
		}

		wp_cache_set_users_last_changed();
	}

	/**
	 * Rename the plugin's own legacy option names.
	 *
	 * @return void
	 */
	private static function migrate_option_names(): void {
		global $wpdb;

		foreach ( self::OPTION_MAP as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Renaming an option in place is the only way to preserve its value without a read/write round trip; the cache entries touched are deleted below.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s",
					$new,
					$old
				)
			);

			// Renaming option_name directly bypasses the options cache, so both
			// spellings are evicted. Only these entries - not the whole object
			// cache, which belongs to the rest of the site.
			wp_cache_delete( $old, 'options' );
			wp_cache_delete( $new, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}

		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Re-point converted pages to the renamed full-width page template.
	 *
	 * Both values are template slugs this plugin shipped, matched exactly.
	 *
	 * @return void
	 */
	private static function migrate_page_template_assignments(): void {
		global $wpdb;

		$slugs = array(
			'templates/etg-full-width-page.php'  => 'templates/blockshift-full-width-page.php',
			'progressus-etg//full-width-page'    => 'progressus-blockshift//full-width-page',
		);

		foreach ( $slugs as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk meta_value rewrite across an unbounded set of posts; post caches are invalidated below.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_wp_page_template' AND meta_value = %s",
					$new,
					$old
				)
			);
		}

		wp_cache_set_posts_last_changed();
	}

	/**
	 * Rewrite the CSS-scope classes baked into the plugin's own converted posts.
	 *
	 * Only posts this plugin marked as its own are considered. The scope
	 * classes are ordinary text that can appear in content belonging to other
	 * software, so ownership - not a substring match - decides what is rewritten.
	 *
	 * @return void
	 */
	private static function migrate_post_content(): void {
		global $wpdb;

		$ids = self::get_owned_post_ids();
		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated run of %d tokens; every value is passed through prepare() below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
				$ids
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$updated = strtr( (string) $row->post_content, self::CONTENT_MAP );
			if ( $updated === $row->post_content ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wp_update_post() would fire save hooks and re-run kses over already-stored content; the post cache is cleaned immediately after.
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $updated ),
				array( 'ID' => (int) $row->ID )
			);
			clean_post_cache( (int) $row->ID );
		}
	}

	/**
	 * IDs of posts this plugin created or manages.
	 *
	 * @return int[]
	 */
	private static function get_owned_post_ids(): array {
		global $wpdb;

		$keys         = self::OWNERSHIP_META_KEYS;
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated run of %s tokens; the key names themselves are class constants passed through prepare().
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
				$keys
			)
		);

		$ids = array_map( 'intval', (array) $ids );

		// Converted header/footer parts whose CSS is enqueued site-wide are
		// tracked in an option rather than by meta.
		$global_ids = get_option( '_blockshift_global_css_post_ids', array() );
		if ( is_array( $global_ids ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $global_ids ) );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Move the external CSS upload directory and files, and fix stored
	 * references inside the plugin's own meta.
	 *
	 * @return void
	 */
	private static function migrate_external_css(): void {
		$upload = wp_get_upload_dir();
		if ( ! empty( $upload['basedir'] ) ) {
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$base    = trailingslashit( (string) $upload['basedir'] );
			$old_dir = $base . 'etg';
			$new_dir = $base . 'blockshift';

			if ( $wp_filesystem && is_dir( $old_dir ) && ! is_dir( $new_dir ) ) {
				$wp_filesystem->move( $old_dir, $new_dir );
			}

			if ( $wp_filesystem && is_dir( $new_dir ) ) {
				foreach ( (array) glob( $new_dir . '/etg-page-*.css' ) as $old_file ) {
					$new_file = $new_dir . '/' . str_replace( 'etg-page-', 'blockshift-page-', basename( $old_file ) );
					if ( ! $wp_filesystem->exists( $new_file ) ) {
						$wp_filesystem->move( $old_file, $new_file );
					}
				}
			}
		}

		// Fix the stored url/path inside the (already renamed) external CSS
		// meta. Bounded to that one meta key, which only this plugin writes.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk rewrite of one plugin-owned meta key; the post meta cache is invalidated below.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				 SET meta_value = REPLACE( REPLACE( meta_value, '/etg/', '/blockshift/' ), 'etg-page-', 'blockshift-page-' )
				 WHERE meta_key = %s",
				'_blockshift_external_css'
			)
		);

		wp_cache_set_posts_last_changed();
	}

	/**
	 * Take the diagnostic log out of web reach.
	 *
	 * Before 1.0.1 the log was written to `uploads/blockshift/conversion-log.jsonl`,
	 * a directory the plugin serves generated stylesheets from, with no index
	 * file and no deny rule - so anyone could read it. This step creates the
	 * new protected directory and moves any existing log into it, keeping the
	 * administrator's history while removing the readable copy. If a log
	 * already exists at the new path the stale public one is deleted rather
	 * than merged, because leaving it behind would leave the exposure in place.
	 *
	 * @return void
	 */
	private static function secure_diagnostic_log(): void {
		Diagnostic_Logger::harden_log_dir();

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return;
		}

		$pairs = array(
			array( Diagnostic_Logger::legacy_log_path(), Diagnostic_Logger::log_path() ),
			array( Diagnostic_Logger::legacy_log_path() . '.1', Diagnostic_Logger::log_path() . '.1' ),
		);

		foreach ( $pairs as $pair ) {
			list( $legacy, $target ) = $pair;

			if ( ! $wp_filesystem->exists( $legacy ) ) {
				continue;
			}

			if ( $wp_filesystem->exists( $target ) ) {
				$wp_filesystem->delete( $legacy );
				continue;
			}

			if ( ! $wp_filesystem->move( $legacy, $target ) ) {
				// Moving failed for some reason; the exposure still has to go.
				$wp_filesystem->delete( $legacy );
			}
		}
	}
}
