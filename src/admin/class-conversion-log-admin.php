<?php
/**
 * Conversion Log admin UI page.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

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
use function wp_delete_file;
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

	const MENU_SLUG    = 'blockshift-conversion-log';
	const OPTION_LOG   = 'blockshift_conversion_log';
	const MAX_ENTRIES  = 300;

	/** Max JSONL lines shown in the log viewer on the page. */
	const JSONL_LOG_TAIL = 50;

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_blockshift_clear_conversion_log', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the Progressus design-system stylesheet + inline icon engine
	 * on the Conversion Log screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature is fixed by the admin_enqueue_scripts hook; the screen is identified from the page query var instead.
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check on an enqueue hook; nothing is written, so a nonce would be meaningless here.
			return;
		}

		$css_path   = BLOCKSHIFT_DIR_PATH . '/assets/css/batch-wizard.css';
		$icons_path = BLOCKSHIFT_DIR_PATH . '/assets/js/pgs-icons.js';

		wp_enqueue_style(
			'blockshift-pgs-admin',
			plugins_url( 'assets/css/batch-wizard.css', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $css_path ) ? (string) filemtime( $css_path ) : BLOCKSHIFT_VERSION
		);

		wp_enqueue_script(
			'blockshift-pgs-icons',
			plugins_url( 'assets/js/pgs-icons.js', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $icons_path ) ? (string) filemtime( $icons_path ) : BLOCKSHIFT_VERSION,
			true
		);

		$log_js_path = BLOCKSHIFT_DIR_PATH . '/assets/js/conversion-log.js';

		wp_enqueue_script(
			'blockshift-conversion-log',
			plugins_url( 'assets/js/conversion-log.js', BLOCKSHIFT_MAIN_FILE ),
			array(),
			BLOCKSHIFT_DEBUG && file_exists( $log_js_path ) ? (string) filemtime( $log_js_path ) : BLOCKSHIFT_VERSION,
			true
		);

		wp_localize_script(
			'blockshift-conversion-log',
			'blockshiftConversionLog',
			array(
				'copy'   => __( 'Copy', 'migrate-off-elementor' ),
				'copied' => __( 'Copied', 'migrate-off-elementor' ),
			)
		);
	}

	public function register_menu(): void {
		add_submenu_page(
			'blockshift-settings',
			esc_html__( 'Conversion Log', 'migrate-off-elementor' ),
			esc_html__( 'Conversion Log', 'migrate-off-elementor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** Clear both the DB log and the text log file, then redirect back. */
	public function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'migrate-off-elementor' ) );
		}
		check_admin_referer( 'blockshift_clear_conversion_log' );

		delete_option( self::OPTION_LOG );

		$jsonl = Diagnostic_Logger::log_path();

		// The legacy path is included so "Clear log" also removes a file left
		// in the old, publicly readable location by a pre-1.0.1 install.
		$targets = array(
			$jsonl,
			$jsonl . '.1',
			Diagnostic_Logger::legacy_log_path(),
			Diagnostic_Logger::legacy_log_path() . '.1',
		);

		foreach ( $targets as $target ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- wp_delete_file() below is the WordPress API for the deletion itself; there is no wrapped existence test, and a missing file must not raise a notice.
			if ( file_exists( $target ) ) {
				wp_delete_file( $target );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'blockshift_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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

		$filter     = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only table filter behind a manage_options check; it changes no state.
		$logging_on = Admin_Settings::is_logging_enabled();
		$cleared    = isset( $_GET['blockshift_cleared'] ) && '1' === $_GET['blockshift_cleared']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect flag that only decides whether a notice is shown; the clear action itself is nonce-checked in handle_clear_log().

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

		// JSONL diagnostic log state.
		$jsonl_log_path  = Diagnostic_Logger::log_path();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Read-only probe of the plugin's own log path while rendering a screen; initialising WP_Filesystem here would prompt for credentials on non-direct hosts.
		$jsonl_log_exists = file_exists( $jsonl_log_path );
		$jsonl_log_size   = $jsonl_log_exists ? (int) filesize( $jsonl_log_path ) : 0;
		$jsonl_log_lines  = array();
		if ( $jsonl_log_exists && $jsonl_log_size > 0 ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file the plugin wrote itself, not a remote resource; WP_Filesystem has no line-wise reader and would prompt for credentials.
			$_all_lines      = file( $jsonl_log_path, FILE_IGNORE_NEW_LINES );
			$_all_lines      = is_array( $_all_lines ) ? $_all_lines : array();
			$jsonl_log_lines = array_slice( $_all_lines, -self::JSONL_LOG_TAIL );
		}
		?>
		<div class="wrap pgs">
		<div class="pgs-screen" data-screen-label="Conversion Log">

			<header class="pgs-pluginhead">
				<span class="pgs-pluginhead__brand"><span class="pgs-pluginhead__name"><?php esc_html_e( 'Migrate Off Elementor', 'migrate-off-elementor' ); ?></span></span>
			</header>
			<hr class="wp-header-end" style="margin:0;border:0;">

			<div class="pgs-col">
				<div class="pgs-pagetitle">
					<div>
						<h1><?php esc_html_e( 'Conversion Log', 'migrate-off-elementor' ); ?></h1>
						<p><?php esc_html_e( 'Every widget conversion is recorded here — converted, skipped, or unsupported.', 'migrate-off-elementor' ); ?></p>
					</div>
					<?php if ( $total_count > 0 || $jsonl_log_exists ) : ?>
						<div class="pgs-pagetitle__actions">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								  onsubmit="return confirm('<?php echo esc_js( __( 'Clear all log entries? This cannot be undone.', 'migrate-off-elementor' ) ); ?>');">
								<?php wp_nonce_field( 'blockshift_clear_conversion_log' ); ?>
								<input type="hidden" name="action" value="blockshift_clear_conversion_log" />
								<button type="submit" class="pgs-btn pgs-btn--secondary pgs-btn--sm"><span class="pgs-btn__icon"><i data-icon="trash-2"></i></span><span><?php esc_html_e( 'Clear All Logs', 'migrate-off-elementor' ); ?></span></button>
							</form>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $cleared ) : ?>
					<div class="pgs-banner pgs-banner--success" role="status">
						<span class="pgs-banner__icon"><i data-icon="check-circle-2"></i></span>
						<div class="pgs-banner__body"><span class="pgs-banner__text"><?php esc_html_e( 'Conversion log cleared.', 'migrate-off-elementor' ); ?></span></div>
					</div>
				<?php endif; ?>

				<?php if ( ! $logging_on ) : ?>
					<div class="pgs-banner pgs-banner--warning" role="status">
						<span class="pgs-banner__icon"><i data-icon="alert-triangle"></i></span>
						<div class="pgs-banner__body"><span class="pgs-banner__text">
							<?php
							printf(
								wp_kses(
									/* translators: %s: URL to Settings page */
									__( 'Conversion logging is <strong>disabled</strong>. Enable it in <a href="%s">Settings</a> to start capturing conversion events.', 'migrate-off-elementor' ),
									array(
										'strong' => array(),
										'a'      => array( 'href' => array() ),
									)
								),
								esc_url( admin_url( 'admin.php?page=blockshift-settings' ) )
							);
							?>
						</span></div>
					</div>
				<?php endif; ?>

				<?php /* ── Summary stat cards (status filter links) ─────────────── */ ?>
				<div class="pgs-grid5">
					<?php
					$cards = array(
						array( 'label' => __( 'Total',   'migrate-off-elementor' ), 'count' => $total_count, 'filter' => 'all',     'variant' => '',                              'icon' => '' ),
						array( 'label' => __( 'Success', 'migrate-off-elementor' ), 'count' => $cnt_success, 'filter' => 'success', 'variant' => 'pgs-stat--tinted pgs-stat--success', 'icon' => 'check-circle-2' ),
						array( 'label' => __( 'Partial', 'migrate-off-elementor' ), 'count' => $cnt_partial, 'filter' => 'partial', 'variant' => 'pgs-stat--tinted pgs-stat--warning', 'icon' => 'alert-triangle' ),
						array( 'label' => __( 'Errors',  'migrate-off-elementor' ), 'count' => $cnt_error,   'filter' => 'error',   'variant' => '',                              'icon' => '' ),
						array( 'label' => __( 'Skipped', 'migrate-off-elementor' ), 'count' => $cnt_skipped, 'filter' => 'skipped', 'variant' => '',                              'icon' => '' ),
					);
					foreach ( $cards as $card ) :
						$active = ( $filter === $card['filter'] );
						$url    = add_query_arg(
							array( 'page' => self::MENU_SLUG, 'status' => $card['filter'] ),
							admin_url( 'admin.php' )
						);
						$classes = trim( 'pgs-stat ' . $card['variant'] . ( $active ? ' pgs-stat--selected' : '' ) );
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>">
							<div class="pgs-stat__top">
								<span class="pgs-stat__value"><?php echo (int) $card['count']; ?></span>
								<?php if ( '' !== $card['icon'] ) : ?>
									<span class="pgs-stat__icon"><i data-icon="<?php echo esc_attr( $card['icon'] ); ?>"></i></span>
								<?php endif; ?>
							</div>
							<span class="pgs-stat__label"><?php echo esc_html( $card['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<?php /* ── UI table ───────────────────────────────────────────────── */ ?>
				<?php if ( empty( $log ) ) : ?>
					<div class="pgs-card pgs-card--sunken">
						<div class="pgs-card__body" style="text-align:center;padding:48px 24px;">
							<?php if ( 'all' === $filter ) : ?>
								<p class="pgs-muted" style="font-size:var(--text-md);">
									<?php esc_html_e( 'No conversion events recorded yet. Run a conversion to see results here.', 'migrate-off-elementor' ); ?>
								</p>
							<?php else : ?>
								<p class="pgs-muted" style="font-size:var(--text-md);">
									<?php esc_html_e( 'No entries match this filter.', 'migrate-off-elementor' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

				<?php else : ?>
					<div class="pgs-card pgs-card--flat">
						<table class="pgs-table pgs-table--log">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page / template', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Type', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Status', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Widgets', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Issues', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Duration', 'migrate-off-elementor' ); ?></th>
									<th><?php esc_html_e( 'Date', 'migrate-off-elementor' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $log as $entry ) :
									$status      = $entry['status'] ?? 'skipped';
									$unsupported = (int) ( $entry['stats']['unsupported'] ?? 0 );
									$is_partial  = 'success' === $status && $unsupported > 0;
									$badge       = $is_partial ? 'partial' : $status;

									$pill_variants = array(
										'success' => 'pgs-pill--success',
										'partial' => 'pgs-pill--warning',
										'error'   => 'pgs-pill--error',
										'skipped' => 'pgs-pill--neutral',
									);
									$pill_class = $pill_variants[ $badge ] ?? 'pgs-pill--neutral';

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
										$post_title = esc_html__( '(unknown)', 'migrate-off-elementor' );
									}

									$target_id  = (int) ( $entry['target_id'] ?? 0 );
									$item_type  = (string) ( $entry['item_type'] ?? 'page' );
									$edit_link  = $post_id > 0 ? get_edit_post_link( $post_id ) : '';
									$tgt_link   = $target_id > 0 ? get_edit_post_link( $target_id ) : '';
								?>
								<tr>
									<td>
										<?php if ( $edit_link ) : ?>
											<div class="pgs-table__linkstrong"><a href="<?php echo esc_url( (string) $edit_link ); ?>"><?php echo esc_html( $post_title ); ?></a></div>
										<?php else : ?>
											<div class="pgs-table__linkstrong"><?php echo esc_html( $post_title ); ?></div>
										<?php endif; ?>
										<?php if ( $target_id > 0 && $tgt_link ) : ?>
											<div class="pgs-table__meta">
												<?php esc_html_e( 'Target:', 'migrate-off-elementor' ); ?>
												<a href="<?php echo esc_url( (string) $tgt_link ); ?>">#<?php echo (int) $target_id; ?></a>
											</div>
										<?php endif; ?>
									</td>
									<td class="pgs-table__muted" style="text-transform:capitalize;"><?php echo esc_html( $item_type ); ?></td>
									<td>
										<span class="pgs-pill <?php echo esc_attr( $pill_class ); ?>"><span class="pgs-pill__dot" aria-hidden="true"></span><?php echo esc_html( ucfirst( $badge ) ); ?></span>
									</td>
									<td style="min-width:140px;">
										<?php if ( $total_w > 0 ) : ?>
											<div class="pgs-progress pgs-progress--sm"><div class="pgs-progress__track"><div class="pgs-progress__fill<?php echo $is_partial ? ' pgs-progress__fill--warning' : ''; ?>" style="width:<?php echo esc_attr( $pct ); ?>%;"></div></div></div>
											<div class="pgs-table__count">
												<?php
												/* translators: 1: number of Elementor widgets on this page that were converted, 2: total number of Elementor widgets found on this page. */
												printf( esc_html__( '%1$d / %2$d', 'migrate-off-elementor' ), (int) $converted_w, (int) $total_w );
												?>
											</div>
										<?php else : ?>
											<span class="pgs-table__muted">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $unsp_types ) ) : ?>
											<details>
												<summary class="pgs-issue" style="cursor:pointer;list-style:none;">
													<i data-icon="alert-triangle"></i>
													<?php
													$nt = count( $unsp_types );
													/* translators: %d: number of distinct Elementor widget types on this page that have no block equivalent. */
													printf( esc_html( _n( '%d unsupported type', '%d unsupported types', $nt, 'migrate-off-elementor' ) ), (int) $nt );
													?>
												</summary>
												<ul style="margin:6px 0 0 16px;padding:0;font-size:var(--text-xs);color:var(--text-muted);list-style:disc;">
													<?php foreach ( $unsp_types as $wtype => $wcount ) : ?>
														<li><code><?php echo esc_html( (string) $wtype ); ?></code> &times; <?php echo (int) $wcount; ?></li>
													<?php endforeach; ?>
												</ul>
											</details>
											<?php if ( $empty_w > 0 ) : ?>
												<div class="pgs-table__meta">
													<?php
													/* translators: %d: number of Elementor widgets on this page that converted to empty block output, in addition to the unsupported types listed above. */
													printf( esc_html( _n( '+%d empty output', '+%d empty outputs', $empty_w, 'migrate-off-elementor' ) ), (int) $empty_w );
													?>
												</div>
											<?php endif; ?>
										<?php elseif ( $empty_w > 0 ) : ?>
											<span class="pgs-issue">
												<i data-icon="info"></i>
												<?php
												/* translators: %d: number of Elementor widgets on this page that converted to empty block output. */
												printf( esc_html( _n( '%d empty output', '%d empty outputs', $empty_w, 'migrate-off-elementor' ) ), (int) $empty_w );
												?>
											</span>
										<?php else : ?>
											<span class="pgs-issue pgs-issue--ok"><i data-icon="check"></i> <?php esc_html_e( 'None', 'migrate-off-elementor' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="pgs-table__muted"><?php echo esc_html( $dur_str ); ?></td>
									<td class="pgs-table__muted"><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<div class="pgs-table__foot">
							<?php
							printf(
								/* translators: 1: number of conversion log entries currently listed in the table, 2: maximum number of entries the log keeps before the oldest are discarded. */
								esc_html__( 'Showing %1$d entries. Log retains the last %2$d entries; oldest are discarded automatically.', 'migrate-off-elementor' ),
								(int) count( $log ),
								(int) self::MAX_ENTRIES
							);
							?>
						</div>
					</div>
				<?php endif; ?>

				<?php /* ── JSONL diagnostic log viewer ─────────────────────────────── */ ?>
				<div class="pgs-card">
					<div class="pgs-card__header">
						<div>
							<div class="pgs-card__eyebrow"><?php esc_html_e( 'Machine-readable', 'migrate-off-elementor' ); ?></div>
							<div class="pgs-card__title"><?php esc_html_e( 'Diagnostic Log (JSONL)', 'migrate-off-elementor' ); ?></div>
						</div>
					</div>
					<div class="pgs-card__body">
						<p class="pgs-muted" style="margin-bottom:12px;">
							<?php esc_html_e( 'Structured machine-readable log. Every conversion run appends JSON events — one per line. Attach this file to a feedback report or import it into the Feedback Hub for analysis.', 'migrate-off-elementor' ); ?>
						</p>

						<div class="pgs-muted" style="margin-bottom:12px;line-height:var(--leading-relaxed);">
							<div>
								<strong><?php esc_html_e( 'File path:', 'migrate-off-elementor' ); ?></strong>
								<code style="background:var(--surface-sunken);padding:3px 6px;border-radius:var(--radius-xs);user-select:all;cursor:text;"><?php echo esc_html( $jsonl_log_path ); ?></code>
							</div>
							<div>
								<strong><?php esc_html_e( 'File size:', 'migrate-off-elementor' ); ?></strong>
								<?php
								if ( $jsonl_log_exists && $jsonl_log_size > 0 ) {
									echo esc_html( size_format( $jsonl_log_size ) );
									// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Read-only probe of the plugin's own rotated log while rendering a screen.
									if ( file_exists( $jsonl_log_path . '.1' ) ) {
										echo ' &nbsp;';
										esc_html_e( '(rotated backup: .jsonl.1 also present)', 'migrate-off-elementor' );
									}
								} else {
									esc_html_e( 'File not yet created — run a conversion first.', 'migrate-off-elementor' );
								}
								?>
							</div>
						</div>

						<?php if ( $jsonl_log_exists && ! empty( $jsonl_log_lines ) ) : ?>
							<div class="pgs-code">
								<div class="pgs-code__bar">
									<span class="pgs-code__name"><i data-icon="braces"></i>conversion-log.jsonl</span>
									<button type="button" class="pgs-code__copy" id="blockshift-jsonl-copy"><i data-icon="copy"></i><span><?php esc_html_e( 'Copy', 'migrate-off-elementor' ); ?></span></button>
								</div>
								<pre class="pgs-code__pre" id="blockshift-jsonl-log" style="--_maxh:340px;"><?php echo esc_html( implode( "\n", $jsonl_log_lines ) ); ?></pre>
							</div>
							<p class="pgs-muted" style="margin-top:10px;">
								<?php
								printf(
									/* translators: %1$d: number of most recent diagnostic log events shown from conversion-log.jsonl. */
									esc_html__( 'Showing last %1$d events. The file rotates automatically at 5 MB — the previous file is kept as .jsonl.1.', 'migrate-off-elementor' ),
									count( $jsonl_log_lines )
								);
								?>
							</p>
						<?php elseif ( $logging_on ) : ?>
							<p class="pgs-muted" style="font-style:italic;">
								<?php esc_html_e( 'No diagnostic log events yet. Run a conversion and refresh this page.', 'migrate-off-elementor' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>

			</div>

		</div>
		</div>
		<?php
	}
}
