<?php
/**
 * Plugin Name: Elementor To Gutenberg
 * Plugin URI: https://progressus.io/
 * Description: Elementor To Gutenberg plugin.
 * Author: Progressus
 * Author URI: https://progressus.io/
 * Version: 1.0.0
 * Text Domain: elementor-to-gutenberg
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg;

use Progressus\Gutenberg\Gutenberg;

if (! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

if (! defined('GUTENBERG_PLUGIN_VERSION') ) {
    define('GUTENBERG_PLUGIN_VERSION', '1.0.0');
}
if (! defined('GUTENBERG_PLUGIN_DEBUG') ) {
    define('GUTENBERG_PLUGIN_DEBUG', true);
}
if (! defined('GUTENBERG_PLUGIN_FILE') ) {
    define('GUTENBERG_PLUGIN_FILE', __FILE__);
}
if (! defined('GUTENBERG_PLUGIN_BASENAME') ) {
    define('GUTENBERG_PLUGIN_BASENAME', plugin_basename(GUTENBERG_PLUGIN_FILE));
}
if (! defined('GUTENBERG_PLUGIN_DIR_PATH') ) {
    define('GUTENBERG_PLUGIN_DIR_PATH', untrailingslashit(plugin_dir_path(GUTENBERG_PLUGIN_FILE)));
}
if (! defined('GUTENBERG_PLUGIN_TEMPLATES_DIR_PATH') ) {
    define('GUTENBERG_PLUGIN_TEMPLATES_DIR_PATH', untrailingslashit(plugin_dir_path(GUTENBERG_PLUGIN_FILE)) . '/templates/');
}
if (! defined('GUTENBERG_PLUGIN_DIR_URL') ) {
    define('GUTENBERG_PLUGIN_DIR_URL', untrailingslashit(plugins_url('/', GUTENBERG_PLUGIN_FILE)));
}
if (! defined('GUTENBERG_PLUGIN_JS_DIR_URL') ) {
    define('GUTENBERG_PLUGIN_JS_DIR_URL', untrailingslashit(plugins_url('/assets/js/', GUTENBERG_PLUGIN_FILE)));
}
if (! defined('GUTENBERG_PLUGIN_CSS_DIR_URL') ) {
    define('GUTENBERG_PLUGIN_CSS_DIR_URL', untrailingslashit(plugins_url('/assets/css/', GUTENBERG_PLUGIN_FILE)));
}

register_activation_hook(
    __FILE__,
    function () {
        /**
         * Fires when the plugin is activated.
         *
         * @since 1.0.0
         */
        do_action('gutenberg_plugin_activated');
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
        do_action('gutenberg_plugin_deactivated');
    }
);

require_once plugin_dir_path(__FILE__) . '/vendor/autoload_packages.php';


Gutenberg::instance();
