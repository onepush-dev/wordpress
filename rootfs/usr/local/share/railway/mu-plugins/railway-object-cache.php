<?php
/**
 * Plugin Name: Railway object cache bootstrap
 * Description: Activates the bundled Redis Object Cache plugin once (right after install, or on the first admin visit), so Settings &rarr; Redis and `wp redis status` work out of the box. The object-cache.php drop-in itself is active from the first request. Deactivating Redis Object Cache later is respected. Shipped by the Railway template image.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'railway_bootstrap_redis_cache_plugin' ) ) {
	function railway_bootstrap_redis_cache_plugin() {
		if ( get_option( 'railway_redis_cache_bootstrapped' ) ) {
			return;
		}
		$plugin = 'redis-cache/redis-cache.php';
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			return;
		}
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( $plugin ) ) {
			// Silent: skips the plugin's "redirect to settings" activation hook.
			$result = activate_plugin( $plugin, '', false, true );
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		update_option( 'railway_redis_cache_bootstrapped', 1, true );
	}
}

// Fires at the end of the installer (/wp-admin/install.php or `wp core install`).
add_action( 'wp_install', 'railway_bootstrap_redis_cache_plugin', 20 );
// Fallback for sites installed another way.
add_action( 'admin_init', 'railway_bootstrap_redis_cache_plugin' );
