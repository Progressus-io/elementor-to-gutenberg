<?php
/**
 * Plugin Name: BlockShift – Migrate from Elementor
 * Description: Professional migration tool to convert Elementor layouts into native Gutenberg blocks
 * Author: Progressus
 * Author URI: https://progressus.io/
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blockshift-migrate-from-elementor
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift;

use Progressus\BlockShift\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'METG_VERSION' ) ) {
	define( 'METG_VERSION', '1.0.0' );
}
if ( ! defined( 'METG_DEBUG' ) ) {
	define( 'METG_DEBUG', false );
}
if ( ! defined( 'METG_FILE' ) ) {
	define( 'METG_FILE', __FILE__ );
}
if ( ! defined( 'METG_MAIN_FILE' ) ) {
	define( 'METG_MAIN_FILE', METG_FILE );
}
if ( ! defined( 'METG_BASENAME' ) ) {
	define( 'METG_BASENAME', plugin_basename( METG_FILE ) );
}
if ( ! defined( 'METG_DIR_PATH' ) ) {
	define( 'METG_DIR_PATH', untrailingslashit( plugin_dir_path( METG_FILE ) ) );
}
if ( ! defined( 'METG_TEMPLATES_DIR_PATH' ) ) {
	define( 'METG_TEMPLATES_DIR_PATH', untrailingslashit( plugin_dir_path( METG_FILE ) ) . '/templates/' );
}
if ( ! defined( 'METG_DIR_URL' ) ) {
	define( 'METG_DIR_URL', untrailingslashit( plugins_url( '/', METG_FILE ) ) );
}
if ( ! defined( 'METG_JS_DIR_URL' ) ) {
	define( 'METG_JS_DIR_URL', untrailingslashit( plugins_url( '/assets/js/', METG_FILE ) ) );
}
if ( ! defined( 'METG_CSS_DIR_URL' ) ) {
	define( 'METG_CSS_DIR_URL', untrailingslashit( plugins_url( '/assets/css/', METG_FILE ) ) );
}

register_activation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'metg_activated' );
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'metg_deactivated' );
	}
);

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';


// Boot the plugin singleton if you’re using it elsewhere.
Gutenberg::instance();
