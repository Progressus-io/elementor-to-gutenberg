<?php
/**
 * Plugin Name: Migrate Off Elementor
 * Description: Professional migration tool to convert Elementor layouts into native WordPress blocks
 * Author: Progressus
 * Author URI: https://progressus.io/
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: migrate-off-elementor
 *
 * @package Progressus\BlockShift
 */

namespace Progressus\BlockShift;

use Progressus\BlockShift\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'BLOCKSHIFT_VERSION' ) ) {
	define( 'BLOCKSHIFT_VERSION', '1.0.0' );
}
if ( ! defined( 'BLOCKSHIFT_DEBUG' ) ) {
	define( 'BLOCKSHIFT_DEBUG', false );
}
if ( ! defined( 'BLOCKSHIFT_FILE' ) ) {
	define( 'BLOCKSHIFT_FILE', __FILE__ );
}
if ( ! defined( 'BLOCKSHIFT_MAIN_FILE' ) ) {
	define( 'BLOCKSHIFT_MAIN_FILE', BLOCKSHIFT_FILE );
}
if ( ! defined( 'BLOCKSHIFT_BASENAME' ) ) {
	define( 'BLOCKSHIFT_BASENAME', plugin_basename( BLOCKSHIFT_FILE ) );
}
if ( ! defined( 'BLOCKSHIFT_DIR_PATH' ) ) {
	define( 'BLOCKSHIFT_DIR_PATH', untrailingslashit( plugin_dir_path( BLOCKSHIFT_FILE ) ) );
}
if ( ! defined( 'BLOCKSHIFT_TEMPLATES_DIR_PATH' ) ) {
	define( 'BLOCKSHIFT_TEMPLATES_DIR_PATH', untrailingslashit( plugin_dir_path( BLOCKSHIFT_FILE ) ) . '/templates/' );
}
if ( ! defined( 'BLOCKSHIFT_DIR_URL' ) ) {
	define( 'BLOCKSHIFT_DIR_URL', untrailingslashit( plugins_url( '/', BLOCKSHIFT_FILE ) ) );
}
if ( ! defined( 'BLOCKSHIFT_JS_DIR_URL' ) ) {
	define( 'BLOCKSHIFT_JS_DIR_URL', untrailingslashit( plugins_url( '/assets/js/', BLOCKSHIFT_FILE ) ) );
}
if ( ! defined( 'BLOCKSHIFT_CSS_DIR_URL' ) ) {
	define( 'BLOCKSHIFT_CSS_DIR_URL', untrailingslashit( plugins_url( '/assets/css/', BLOCKSHIFT_FILE ) ) );
}

register_activation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'blockshift_activated' );
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
		do_action( 'blockshift_deactivated' );
	}
);

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';


// Boot the plugin singleton if you’re using it elsewhere.
Gutenberg::instance();
