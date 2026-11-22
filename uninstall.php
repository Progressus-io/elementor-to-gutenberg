<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the working data the plugin keeps for its own use: the list of
 * converted forms and the short-lived caches. Converted pages, their
 * stylesheets and the conversion settings are left in place, because the
 * pages keep working after the plugin is removed and belong to the site.
 *
 * @package Progressus\BlockShift
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'blockshift_form_registry' );
delete_option( 'blockshift_form_fields' );

delete_transient( 'blockshift_theme_screenshot_twentytwentyfive' );
delete_transient( 'blockshift_theme_screenshot_twentytwentyfour' );

global $wpdb;

// The form throttle keeps one transient per sender address, so they are removed by their shared prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients have no delete-by-prefix API.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_blockshift_form_rl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_blockshift_form_rl_' ) . '%'
	)
);
