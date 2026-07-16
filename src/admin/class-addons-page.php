<?php
// phpcs:ignoreFile

/**
 * Add-ons admin page.
 *
 * Promotes BlockShift add-ons (currently the AI Enhancement add-on) and links
 * out to the external product site. The free plugin only links to the add-on;
 * it never downloads or installs off-wordpress.org code itself, per the
 * wordpress.org plugin guidelines.
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift\Admin;

use function add_submenu_page;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function file_exists;
use function filemtime;
use function plugins_url;
use function sanitize_key;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_unslash;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the "Add-ons" submenu page.
 */
class Addons_Page {

	const MENU_SLUG = 'blockshift-addons';

	/**
	 * External marketing/product page for the AI Enhancement add-on.
	 */
	const AI_ADDON_URL = 'https://block-shift.com/ai-enhancement';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the "Add-ons" submenu under the BlockShift top-level menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'blockshift-settings',
			esc_html__( 'Add-ons', 'blockshift' ),
			esc_html__( 'Add-ons', 'blockshift' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the Progressus design-system stylesheet + inline icon engine on
	 * the Add-ons screen so its .pgs markup is styled and its <i data-icon>
	 * placeholders are replaced with inline SVG.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	}

	/**
	 * Render the Add-ons page.
	 *
	 * This is a pure promo page: it only links out to the add-on's product page
	 * on block-shift.com. The main plugin intentionally has no knowledge of
	 * whether any add-on is installed — an active add-on removes this page and
	 * registers its own screens (see the add-on's admin bootstrap).
	 */
	public function render_page(): void {
		?>
		<div class="wrap pgs">
		<div class="pgs-screen" data-screen-label="Add-ons">

			<header class="pgs-pluginhead">
				<span class="pgs-pluginhead__brand"><span class="pgs-pluginhead__name"><?php esc_html_e( 'BlockShift – Migrate from Elementor', 'blockshift' ); ?></span></span>
			</header>
			<hr class="wp-header-end" style="margin:0;border:0;">

			<div class="pgs-col">
				<div class="pgs-pagetitle">
					<div>
						<h1><?php esc_html_e( 'Add-ons', 'blockshift' ); ?></h1>
						<p><?php esc_html_e( 'Extend BlockShift with optional add-ons. Add-ons are separate plugins hosted on block-shift.com.', 'blockshift' ); ?></p>
					</div>
				</div>

				<div class="pgs-stack" style="gap:var(--gap-section);">

					<div class="pgs-card">
						<div class="pgs-card__header">
							<div>
								<div class="pgs-card__eyebrow"><?php esc_html_e( 'Add-on', 'blockshift' ); ?></div>
								<div class="pgs-card__title"><?php esc_html_e( 'BlockShift AI Enhancement', 'blockshift' ); ?></div>
							</div>
						</div>
						<div class="pgs-card__body">
							<p style="margin-top:0;">
								<?php esc_html_e( 'AI-powered design enhancement that uses your own Anthropic (Claude) API key to nudge converted pages closer to the original Elementor design.', 'blockshift' ); ?>
							</p>

							<ul class="pgs-featurelist">
								<li><span class="pgs-featurelist__icon"><i data-icon="check"></i></span><?php esc_html_e( 'Refines spacing, alignment, and typography after conversion', 'blockshift' ); ?></li>
								<li><span class="pgs-featurelist__icon"><i data-icon="check"></i></span><?php esc_html_e( 'Uses your own Claude API key — you stay in control of usage and cost', 'blockshift' ); ?></li>
								<li><span class="pgs-featurelist__icon"><i data-icon="check"></i></span><?php esc_html_e( 'Installs alongside BlockShift as a separate add-on plugin', 'blockshift' ); ?></li>
							</ul>

							<div class="pgs-actions-end">
								<a class="pgs-btn pgs-btn--primary pgs-btn--md" href="<?php echo esc_url( self::AI_ADDON_URL ); ?>" target="_blank" rel="noopener noreferrer">
									<span><?php esc_html_e( 'Get the AI Enhancement add-on', 'blockshift' ); ?></span>
									<span class="pgs-btn__icon"><i data-icon="arrow-right"></i></span>
								</a>
							</div>
						</div>
					</div>

				</div>
			</div>

		</div>
		</div>
		<?php
	}
}
