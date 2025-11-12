<?php
/**
 * Modern batch conversion wizard for Elementor to Gutenberg.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Admin_Settings;
use WP_Post;
use WP_Query;

use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function plugins_url;
use function sanitize_key;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_unslash;


use const HOUR_IN_SECONDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Convert_Wizard
 */
class Batch_Convert_Wizard {
	public const MENU_SLUG = 'ele2gb-batch-convert-v2';

	private const NONCE_ACTION = 'ele2gb_batch_convert';


	/**
	 * Singleton instance.
	 *
	 * @var Batch_Convert_Wizard|null
	 */
	private static $instance = null;

	/**
	 * Cached header/footer template detection.
	 *
	 * @var array|null
	 */
	private $cached_templates = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets for the wizard page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'ele2gb-batch-wizard-v2',
			plugins_url( 'assets/css/batch-wizard-v2.css', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'ele2gb-batch-wizard-v2',
			plugins_url( 'assets/js/batch-convert-wizard-v2.js', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ele2gb-batch-wizard-v2',
			'ele2gbBatchWizardV2',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'userCanEdit'  => current_user_can( 'edit_pages' ),
				'maxBatchSize' => 1,
			)
		);
	}

	/**
	 * Render wizard page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
		}

		?>
		<div class="wrap ele2gb-wizard-v2-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Gutenberg Conversion Wizard', 'elementor-to-gutenberg' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Convert Elementor pages to Gutenberg blocks.', 'elementor-to-gutenberg' ); ?></p>
			<div id="ele2gb-batch-convert-v2-root" class="ele2gb-wizard-v2-root" aria-live="polite"></div>
		</div>
		<?php
	}

}