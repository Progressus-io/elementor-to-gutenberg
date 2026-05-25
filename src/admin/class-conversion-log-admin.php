<?php
// phpcs:ignoreFile

/**
 * Conversion Log admin UI page.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use function add_submenu_page;
use function admin_url;
use function add_query_arg;
use function check_admin_referer;
use function current_user_can;
use function delete_option;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_js;
use function esc_url;
use function get_edit_post_link;
use function get_option;
use function get_the_title;
use function update_option;
use function wp_die;
use function wp_kses;
use function wp_nonce_field;
use function wp_safe_redirect;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the "Conversion Log" submenu, global UI log (DB option),
 * and the rolling text log file on disk.
 */
class Conversion_Log_Admin {

	const MENU_SLUG    = 'etg-conversion-log';
	const OPTION_LOG   = 'etg_conversion_log';
	const MAX_ENTRIES  = 300;

	/** Max lines shown in the text log viewer on the page. */
	const TEXT_LOG_TAIL = 200;

	/** Max file size before the log rolls over (2 MB). */
	const TEXT_LOG_MAX_BYTES = 2097152;

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_etg_clear_conversion_log', array( $this, 'handle_clear_log' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Conversion Log', 'elementor-to-gutenberg' ),
			esc_html__( 'Conversion Log', 'elementor-to-gutenberg' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** Clear both the DB log and the text log file, then redirect back. */
	public function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'elementor-to-gutenberg' ) );
		}
		check_admin_referer( 'etg_clear_conversion_log' );

		delete_option( self::OPTION_LOG );
		self::truncate_text_log();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'etg_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TEXT LOG — file on disk
	// ─────────────────────────────────────────────────────────────────────────

	/** Full path to the on-disk text log. */
	public static function text_log_path(): string {
		return WP_CONTENT_DIR . '/ele2gb-conversion.log';
	}

	/**
	 * Write one line to the text log.
	 * Format (pipe-delimited, fixed-width columns, easy to grep/copy):
	 *
	 *   TIMESTAMP           | STATUS  | TITLE (IDs)                             | TYPE    | W.STAT | DUR   | ISSUES
	 *   2026-05-25 10:00:01 | SUCCESS | About Us (42→87)                        | page    |  14/18 | 1.23s | —
	 *   2026-05-25 10:00:03 | PARTIAL | Contact Form (55→91)                    | page    |   8/10 | 0.87s | form-pro×1, cta×1
	 *   2026-05-25 10:00:05 | ERROR   | Services Page (61)                      | page    |      — |     — | Failed: invalid JSON
	 */
	private static function write_text_log( array $entry ): void {
		$file = self::text_log_path();

		// Rotate if over size limit — rename to .1 and start fresh.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		if ( file_exists( $file ) && filesize( $file ) > self::TEXT_LOG_MAX_BYTES ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $file, $file . '.1' );
		}

		// Write header once when file is new.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		$is_new = ! file_exists( $file );

		// ── Build each column ──────────────────────────────────────────────

		$time = $entry['time'] ?? gmdate( 'Y-m-d H:i:s' );

		// Determine display status.
		$status      = strtoupper( $entry['status'] ?? 'unknown' );
		$unsp_total  = 0;
		$unsp_types  = is_array( $entry['unsupported_by_type'] ?? null ) ? $entry['unsupported_by_type'] : array();
		foreach ( $unsp_types as $c ) {
			$unsp_total += (int) $c;
		}
		if ( 'SUCCESS' === $status && $unsp_total > 0 ) {
			$status = 'PARTIAL';
		}

		// Title + IDs, capped at 40 chars.
		$post_id   = (int) ( $entry['post_id'] ?? 0 );
		$target_id = (int) ( $entry['target_id'] ?? 0 );
		$title_raw = (string) ( $entry['post_title'] ?? '' );
		if ( '' === $title_raw && $post_id > 0 ) {
			$title_raw = get_the_title( $post_id );
		}
		$ids = $post_id > 0
			? ( $target_id > 0 ? "({$post_id}\xe2\x86\x92{$target_id})" : "({$post_id})" )
			: '';
		$name_full = $ids !== '' ? "{$title_raw} {$ids}" : $title_raw;
		if ( mb_strlen( $name_full ) > 40 ) {
			$name_full = mb_substr( $name_full, 0, 37 ) . '...';
		}

		$type = strtolower( (string) ( $entry['item_type'] ?? 'page' ) );

		// Widget stat.
		$stats    = is_array( $entry['stats'] ?? null ) ? $entry['stats'] : null;
		$w_stat   = $stats
			? sprintf( '%d/%d', $stats['converted'] ?? 0, $stats['total'] ?? 0 )
			: '—';

		// Duration.
		$dur     = (float) ( $entry['duration'] ?? 0 );
		$dur_str = $dur > 0.0 ? round( $dur, 2 ) . 's' : '—';

		// Issues column: unsupported list, or fall back to the message.
		if ( ! empty( $unsp_types ) ) {
			$parts = array();
			foreach ( $unsp_types as $wtype => $cnt ) {
				$parts[] = $wtype . "\xc3\x97" . $cnt;   // ×
			}
			$empty_w = (int) ( $stats['empty_output'] ?? 0 );
			if ( $empty_w > 0 ) {
				$parts[] = "empty\xc3\x97{$empty_w}";
			}
			$issues = implode( ', ', $parts );
		} else {
			$msg    = trim( (string) ( $entry['message'] ?? '' ) );
			$issues = $msg !== '' ? $msg : '—';
		}

		// ── Compose the line ───────────────────────────────────────────────
		$line = sprintf(
			"%-19s | %-7s | %-40s | %-8s | %6s | %5s | %s\n",
			$time,
			$status,
			$name_full,
			$type,
			$w_stat,
			$dur_str,
			$issues
		);

		if ( $is_new ) {
			$header  = "# Elementor to Gutenberg — Conversion Log\n";
			$header .= "# " . str_repeat( '-', 110 ) . "\n";
			$header .= sprintf(
				"# %-19s | %-7s | %-40s | %-8s | %6s | %5s | %s\n",
				'TIMESTAMP', 'STATUS', 'TITLE (IDs)', 'TYPE', 'W.STAT', 'DUR', 'ISSUES'
			);
			$header .= "# " . str_repeat( '-', 110 ) . "\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, $header, LOCK_EX );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}

	/** Truncate the on-disk text log (leave the header intact). */
	private static function truncate_text_log(): void {
		$file = self::text_log_path();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, '' );
		}
		// Also remove the rolled-over backup.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		if ( file_exists( $file . '.1' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file . '.1' );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// DB LOG — WordPress option
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Append one entry to both the DB log and the text log.
	 * No-op when logging is disabled.
	 */
	public static function append_entry( array $entry ): void {
		if ( ! Admin_Settings::is_logging_enabled() ) {
			return;
		}

		// ── UI (DB) log ────────────────────────────────────────────────────
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = $entry;
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_ENTRIES );
		}
		update_option( self::OPTION_LOG, $log, false );

		// ── Text log ───────────────────────────────────────────────────────
		self::write_text_log( $entry );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// ADMIN PAGE
	// ─────────────────────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Newest first for the table.
		$log = array_reverse( $log );

		$filter     = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logging_on = Admin_Settings::is_logging_enabled();
		$cleared    = isset( $_GET['etg_cleared'] ) && '1' === $_GET['etg_cleared']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Summary counts (computed before filter is applied).
		$total_count = count( $log );
		$cnt_success = 0;
		$cnt_partial = 0;
		$cnt_error   = 0;
		$cnt_skipped = 0;

		foreach ( $log as $entry ) {
			$s    = $entry['status'] ?? '';
			$unsp = (int) ( $entry['stats']['unsupported'] ?? 0 );
			if ( 'success' === $s ) {
				if ( $unsp > 0 ) {
					$cnt_partial++;
				} else {
					$cnt_success++;
				}
			} elseif ( 'error' === $s ) {
				$cnt_error++;
			} elseif ( 'skipped' === $s ) {
				$cnt_skipped++;
			}
		}

		// Apply status filter.
		if ( 'all' !== $filter ) {
			$log = array_values(
				array_filter(
					$log,
					static function ( $entry ) use ( $filter ) {
						$s    = $entry['status'] ?? '';
						$unsp = (int) ( $entry['stats']['unsupported'] ?? 0 );
						if ( 'partial' === $filter ) {
							return 'success' === $s && $unsp > 0;
						}
						if ( 'success' === $filter ) {
							return 'success' === $s && 0 === $unsp;
						}
						return $s === $filter;
					}
				)
			);
		}

		// Text log state.
		$text_log_path   = self::text_log_path();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		$text_log_exists = file_exists( $text_log_path );
		$text_log_size   = $text_log_exists ? (int) filesize( $text_log_path ) : 0;
		$text_log_lines  = array();
		if ( $text_log_exists && $text_log_size > 0 ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$all_lines      = file( $text_log_path, FILE_IGNORE_NEW_LINES );
			$all_lines      = is_array( $all_lines ) ? $all_lines : array();
			$text_log_lines = array_slice( $all_lines, -self::TEXT_LOG_TAIL );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Conversion Log', 'elementor-to-gutenberg' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( $cleared ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Conversion log cleared.', 'elementor-to-gutenberg' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $logging_on ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							wp_kses(
								/* translators: %s: URL to Settings page */
								__( 'Conversion logging is <strong>disabled</strong>. Enable it in <a href="%s">Settings</a> to start capturing conversion events.', 'elementor-to-gutenberg' ),
								array(
									'strong' => array(),
									'a'      => array( 'href' => array() ),
								)
							),
							esc_url( admin_url( 'admin.php?page=gutenberg-settings' ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php /* ── Summary cards row ─────────────────────────────────────── */ ?>
			<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:16px 0 20px;">
				<?php
				$cards = array(
					array( 'label' => __( 'Total',   'elementor-to-gutenberg' ), 'count' => $total_count, 'color' => '#2271b1', 'filter' => 'all'     ),
					array( 'label' => __( 'Success', 'elementor-to-gutenberg' ), 'count' => $cnt_success, 'color' => '#00a32a', 'filter' => 'success' ),
					array( 'label' => __( 'Partial', 'elementor-to-gutenberg' ), 'count' => $cnt_partial, 'color' => '#dba617', 'filter' => 'partial' ),
					array( 'label' => __( 'Errors',  'elementor-to-gutenberg' ), 'count' => $cnt_error,   'color' => '#b32d2e', 'filter' => 'error'   ),
					array( 'label' => __( 'Skipped', 'elementor-to-gutenberg' ), 'count' => $cnt_skipped, 'color' => '#757575', 'filter' => 'skipped' ),
				);
				foreach ( $cards as $card ) :
					$active = ( $filter === $card['filter'] );
					$url    = add_query_arg(
						array( 'page' => self::MENU_SLUG, 'status' => $card['filter'] ),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" style="text-decoration:none;">
						<div style="background:#fff;border:2px solid <?php echo esc_attr( $active ? $card['color'] : '#ddd' ); ?>;border-radius:6px;padding:10px 18px;min-width:80px;text-align:center;">
							<div style="font-size:26px;font-weight:700;line-height:1;color:<?php echo esc_attr( $card['color'] ); ?>;"><?php echo (int) $card['count']; ?></div>
							<div style="font-size:11px;color:#555;margin-top:4px;text-transform:uppercase;letter-spacing:.04em;"><?php echo esc_html( $card['label'] ); ?></div>
						</div>
					</a>
				<?php endforeach; ?>

				<?php if ( $total_count > 0 || $text_log_exists ) : ?>
					<div style="margin-left:auto;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							  onsubmit="return confirm('<?php echo esc_js( __( 'Clear all log entries? This cannot be undone.', 'elementor-to-gutenberg' ) ); ?>');">
							<?php wp_nonce_field( 'etg_clear_conversion_log' ); ?>
							<input type="hidden" name="action" value="etg_clear_conversion_log" />
							<button type="submit" class="button button-secondary">
								<?php esc_html_e( 'Clear All Logs', 'elementor-to-gutenberg' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<?php /* ── UI table ───────────────────────────────────────────────── */ ?>
			<?php if ( empty( $log ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:48px 24px;text-align:center;color:#757575;">
					<?php if ( 'all' === $filter ) : ?>
						<p style="font-size:15px;margin:0;">
							<?php esc_html_e( 'No conversion events recorded yet. Run a conversion to see results here.', 'elementor-to-gutenberg' ); ?>
						</p>
					<?php else : ?>
						<p style="font-size:15px;margin:0;">
							<?php esc_html_e( 'No entries match this filter.', 'elementor-to-gutenberg' ); ?>
						</p>
					<?php endif; ?>
				</div>

			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" style="border-radius:6px;overflow:hidden;">
					<thead>
						<tr>
							<th style="width:24%;"><?php esc_html_e( 'Page / Template', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:8%;"><?php esc_html_e( 'Type', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:10%;"><?php esc_html_e( 'Status', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:14%;"><?php esc_html_e( 'Widgets', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:26%;"><?php esc_html_e( 'Issues', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:7%;"><?php esc_html_e( 'Duration', 'elementor-to-gutenberg' ); ?></th>
							<th style="width:11%;"><?php esc_html_e( 'Date', 'elementor-to-gutenberg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) :
							$status      = $entry['status'] ?? 'skipped';
							$unsupported = (int) ( $entry['stats']['unsupported'] ?? 0 );
							$is_partial  = 'success' === $status && $unsupported > 0;
							$badge       = $is_partial ? 'partial' : $status;

							$badge_styles = array(
								'success' => array( 'bg' => '#d7f0dd', 'fg' => '#1a7431' ),
								'partial' => array( 'bg' => '#fef8e6', 'fg' => '#896109' ),
								'error'   => array( 'bg' => '#fde8e8', 'fg' => '#8b0000' ),
								'skipped' => array( 'bg' => '#f0f0f0', 'fg' => '#555555' ),
							);
							$bs = $badge_styles[ $badge ] ?? $badge_styles['skipped'];

							$stats       = is_array( $entry['stats'] ?? null ) ? $entry['stats'] : array();
							$total_w     = (int) ( $stats['total'] ?? 0 );
							$converted_w = (int) ( $stats['converted'] ?? 0 );
							$empty_w     = (int) ( $stats['empty_output'] ?? 0 );
							$unsp_types  = is_array( $entry['unsupported_by_type'] ?? null ) ? $entry['unsupported_by_type'] : array();

							$pct         = $total_w > 0 ? min( 100, (int) round( ( $converted_w / $total_w ) * 100 ) ) : 0;
							$duration    = (float) ( $entry['duration'] ?? 0 );
							$dur_str     = $duration > 0 ? round( $duration, 2 ) . 's' : '—';

							$post_id     = (int) ( $entry['post_id'] ?? 0 );
							$post_title  = (string) ( $entry['post_title'] ?? '' );
							if ( '' === $post_title && $post_id > 0 ) {
								$post_title = get_the_title( $post_id );
							}
							if ( '' === $post_title ) {
								$post_title = esc_html__( '(unknown)', 'elementor-to-gutenberg' );
							}

							$target_id  = (int) ( $entry['target_id'] ?? 0 );
							$item_type  = (string) ( $entry['item_type'] ?? 'page' );
							$edit_link  = $post_id > 0 ? get_edit_post_link( $post_id ) : '';
							$tgt_link   = $target_id > 0 ? get_edit_post_link( $target_id ) : '';
						?>
						<tr>
							<td>
								<?php if ( $edit_link ) : ?>
									<strong><a href="<?php echo esc_url( (string) $edit_link ); ?>"><?php echo esc_html( $post_title ); ?></a></strong>
								<?php else : ?>
									<strong><?php echo esc_html( $post_title ); ?></strong>
								<?php endif; ?>
								<?php if ( $target_id > 0 && $tgt_link ) : ?>
									<br><small style="color:#777;">
										<?php esc_html_e( 'Target:', 'elementor-to-gutenberg' ); ?>
										<a href="<?php echo esc_url( (string) $tgt_link ); ?>">#<?php echo $target_id; ?></a>
									</small>
								<?php endif; ?>
							</td>
							<td style="text-transform:capitalize;font-size:12px;"><?php echo esc_html( $item_type ); ?></td>
							<td>
								<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:600;background:<?php echo esc_attr( $bs['bg'] ); ?>;color:<?php echo esc_attr( $bs['fg'] ); ?>;">
									<?php echo esc_html( ucfirst( $badge ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $total_w > 0 ) : ?>
									<span style="font-size:12px;white-space:nowrap;">
										<?php printf( esc_html__( '%1$d / %2$d', 'elementor-to-gutenberg' ), $converted_w, $total_w ); ?>
									</span>
									<div style="background:#e0e0e0;border-radius:3px;height:4px;margin-top:4px;overflow:hidden;">
										<div style="background:#00a32a;height:4px;width:<?php echo $pct; ?>%;"></div>
									</div>
								<?php else : ?>
									<span style="color:#aaa;font-size:12px;">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $unsp_types ) ) : ?>
									<details>
										<summary style="cursor:pointer;color:#896109;font-size:12px;list-style:none;display:flex;align-items:center;gap:4px;">
											<span>&#9888;</span>
											<?php
											$nt = count( $unsp_types );
											printf( esc_html( _n( '%d unsupported type', '%d unsupported types', $nt, 'elementor-to-gutenberg' ) ), $nt );
											?>
										</summary>
										<ul style="margin:4px 0 0 16px;padding:0;font-size:11px;color:#555;list-style:disc;">
											<?php foreach ( $unsp_types as $wtype => $wcount ) : ?>
												<li><code><?php echo esc_html( (string) $wtype ); ?></code> &times; <?php echo (int) $wcount; ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
									<?php if ( $empty_w > 0 ) : ?>
										<div style="font-size:11px;color:#888;margin-top:2px;">
											<?php printf( esc_html( _n( '+%d empty output', '+%d empty outputs', $empty_w, 'elementor-to-gutenberg' ) ), $empty_w ); ?>
										</div>
									<?php endif; ?>
								<?php elseif ( $empty_w > 0 ) : ?>
									<span style="font-size:12px;color:#888;">
										&#8505; <?php printf( esc_html( _n( '%d empty output', '%d empty outputs', $empty_w, 'elementor-to-gutenberg' ) ), $empty_w ); ?>
									</span>
								<?php else : ?>
									<span style="color:#1a7431;font-size:12px;">&#10003; <?php esc_html_e( 'None', 'elementor-to-gutenberg' ); ?></span>
								<?php endif; ?>
							</td>
							<td style="font-size:12px;"><?php echo esc_html( $dur_str ); ?></td>
							<td style="font-size:11px;color:#666;"><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p style="color:#888;font-size:12px;margin-top:8px;">
					<?php
					printf(
						esc_html__( 'Showing %1$d entries. Log retains the last %2$d entries; oldest are discarded automatically.', 'elementor-to-gutenberg' ),
						count( $log ),
						self::MAX_ENTRIES
					);
					?>
				</p>
			<?php endif; ?>

			<?php /* ── Text log viewer ──────────────────────────────────────────── */ ?>
			<hr style="margin:32px 0 24px;">
			<h2 style="margin-bottom:6px;"><?php esc_html_e( 'Text Log File', 'elementor-to-gutenberg' ); ?></h2>
			<p style="color:#555;margin-top:0;">
				<?php esc_html_e( 'Plain-text log written to disk on every conversion. One line per event — copy it all, grep it, or attach it to a support request.', 'elementor-to-gutenberg' ); ?>
			</p>

			<table class="form-table" role="presentation" style="margin-bottom:0;">
				<tr>
					<th style="width:160px;padding-bottom:4px;"><?php esc_html_e( 'File path', 'elementor-to-gutenberg' ); ?></th>
					<td style="padding-bottom:4px;">
						<code style="background:#f6f7f7;padding:3px 6px;border-radius:3px;font-size:13px;user-select:all;cursor:text;"><?php echo esc_html( $text_log_path ); ?></code>
					</td>
				</tr>
				<tr>
					<th style="padding-top:0;"><?php esc_html_e( 'File size', 'elementor-to-gutenberg' ); ?></th>
					<td style="padding-top:0;">
						<?php
						if ( $text_log_exists && $text_log_size > 0 ) {
							echo esc_html( size_format( $text_log_size ) );
							echo ' &nbsp;';
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
							if ( file_exists( $text_log_path . '.1' ) ) {
								esc_html_e( '(rotated backup: .log.1 also present)', 'elementor-to-gutenberg' );
							}
						} else {
							echo '<span style="color:#aaa;">' . esc_html__( 'File not yet created', 'elementor-to-gutenberg' ) . '</span>';
						}
						?>
					</td>
				</tr>
			</table>

			<?php if ( $text_log_exists && ! empty( $text_log_lines ) ) : ?>
				<p style="font-size:12px;color:#888;margin:10px 0 4px;">
					<?php
					printf(
						esc_html__( 'Showing last %1$d lines. Click inside to select all, then copy.', 'elementor-to-gutenberg' ),
						count( $text_log_lines )
					);
					?>
				</p>
				<textarea
					id="etg-text-log"
					readonly
					onclick="this.select();"
					spellcheck="false"
					style="width:100%;height:340px;font-family:monospace;font-size:12px;line-height:1.6;background:#1e1e2e;color:#cdd6f4;border:1px solid #333;border-radius:6px;padding:12px 14px;resize:vertical;white-space:pre;overflow-x:auto;tab-size:4;box-sizing:border-box;"
				><?php
					// Output lines already HTML-escaped, joined with newlines.
					echo esc_textarea( implode( "\n", $text_log_lines ) );
				?></textarea>
				<p style="font-size:12px;color:#888;margin-top:6px;">
					<?php esc_html_e( 'Tip: the file rolls over automatically once it exceeds 2 MB. The previous file is kept as .log.1.', 'elementor-to-gutenberg' ); ?>
				</p>
			<?php elseif ( $logging_on ) : ?>
				<p style="color:#aaa;font-style:italic;">
					<?php esc_html_e( 'No text log entries yet. Run a conversion and refresh this page.', 'elementor-to-gutenberg' ); ?>
				</p>
			<?php endif; ?>

		</div>
		<?php
	}
}
