<?php
/**
 * Railway template defaults, loaded from wp-config.php (after WORDPRESS_CONFIG_EXTRA).
 *
 * Every constant is defined only if it is not defined yet, so you can override any of them by
 * setting WORDPRESS_CONFIG_EXTRA on the WordPress service, e.g. define('WP_POST_REVISIONS', 50);
 * Values come from service variables; see the template README for the list.
 */

if ( ! function_exists( 'railway_env' ) ) {
	function railway_env( $name, $default = null ) {
		$value = getenv( $name );
		return ( false === $value || '' === $value ) ? $default : $value;
	}
}

if ( ! function_exists( 'railway_env_bool' ) ) {
	function railway_env_bool( $name, $default = false ) {
		$value = railway_env( $name );
		if ( null === $value ) {
			return $default;
		}
		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}
}

// --- Site address -----------------------------------------------------------------------------
// WP_HOME defaults to https://<RAILWAY_PUBLIC_DOMAIN> (set by the template). Pinning it here means the
// site never redirect-loops between http/https or the internal host, and a custom domain is a single
// variable change (set WP_HOME=https://www.example.com and redeploy).
$railway_home = railway_env( 'WP_HOME' );
if ( null === $railway_home && railway_env( 'RAILWAY_PUBLIC_DOMAIN' ) ) {
	$railway_home = 'https://' . railway_env( 'RAILWAY_PUBLIC_DOMAIN' );
}
if ( null !== $railway_home ) {
	$railway_home = rtrim( $railway_home, '/' );
	defined( 'WP_HOME' ) || define( 'WP_HOME', $railway_home );
	defined( 'WP_SITEURL' ) || define( 'WP_SITEURL', rtrim( railway_env( 'WP_SITEURL', $railway_home ), '/' ) );
}
unset( $railway_home );

// Railway terminates TLS at its edge and forwards X-Forwarded-Proto: https. The official wp-config
// already maps that to $_SERVER['HTTPS']; also cover the rare proxies that only send X-Forwarded-SSL.
if ( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) {
	$_SERVER['HTTPS'] = 'on';
}
if ( defined( 'WP_HOME' ) && 0 === strpos( WP_HOME, 'https://' ) ) {
	defined( 'FORCE_SSL_ADMIN' ) || define( 'FORCE_SSL_ADMIN', true );
}

// --- Persistent object cache (Valkey/Redis via phpredis) ------------------------------------------
// Read by the object-cache.php drop-in of the bundled Redis Object Cache plugin.
defined( 'WP_REDIS_CLIENT' ) || define( 'WP_REDIS_CLIENT', 'phpredis' );
defined( 'WP_REDIS_HOST' ) || define( 'WP_REDIS_HOST', railway_env( 'WP_REDIS_HOST', '127.0.0.1' ) );
defined( 'WP_REDIS_PORT' ) || define( 'WP_REDIS_PORT', (int) railway_env( 'WP_REDIS_PORT', 6379 ) );
if ( ! defined( 'WP_REDIS_PASSWORD' ) && null !== railway_env( 'WP_REDIS_PASSWORD' ) ) {
	define( 'WP_REDIS_PASSWORD', railway_env( 'WP_REDIS_PASSWORD' ) );
}
defined( 'WP_REDIS_DATABASE' ) || define( 'WP_REDIS_DATABASE', (int) railway_env( 'WP_REDIS_DATABASE', 0 ) );
defined( 'WP_REDIS_PREFIX' ) || define( 'WP_REDIS_PREFIX', railway_env( 'WP_REDIS_PREFIX', 'wp:' ) );
defined( 'WP_REDIS_TIMEOUT' ) || define( 'WP_REDIS_TIMEOUT', 1 );
defined( 'WP_REDIS_READ_TIMEOUT' ) || define( 'WP_REDIS_READ_TIMEOUT', 1 );
defined( 'WP_REDIS_RETRY_INTERVAL' ) || define( 'WP_REDIS_RETRY_INTERVAL', 100 );
// If Valkey is down, WordPress keeps serving from the database instead of erroring out.
defined( 'WP_REDIS_GRACEFUL' ) || define( 'WP_REDIS_GRACEFUL', true );
if ( ! defined( 'WP_REDIS_IGBINARY' ) && extension_loaded( 'igbinary' ) && defined( 'Redis::SERIALIZER_IGBINARY' ) ) {
	define( 'WP_REDIS_IGBINARY', true );
}
if ( ! defined( 'WP_REDIS_DISABLED' ) && railway_env_bool( 'WP_REDIS_DISABLED' ) ) {
	define( 'WP_REDIS_DISABLED', true );
}

// --- Limits and housekeeping ----------------------------------------------------------------------
defined( 'WP_MEMORY_LIMIT' ) || define( 'WP_MEMORY_LIMIT', railway_env( 'WP_MEMORY_LIMIT', '256M' ) );
defined( 'WP_MAX_MEMORY_LIMIT' ) || define( 'WP_MAX_MEMORY_LIMIT', railway_env( 'PHP_MEMORY_LIMIT', '512M' ) );
defined( 'WP_POST_REVISIONS' ) || define( 'WP_POST_REVISIONS', (int) railway_env( 'WP_POST_REVISIONS', 20 ) );
defined( 'WP_ENVIRONMENT_TYPE' ) || define( 'WP_ENVIRONMENT_TYPE', railway_env( 'WP_ENVIRONMENT_TYPE', 'production' ) );
// The theme/plugin code editor is a classic attack path; plugin/theme installs and updates still work.
defined( 'DISALLOW_FILE_EDIT' ) || define( 'DISALLOW_FILE_EDIT', railway_env_bool( 'WP_DISALLOW_FILE_EDIT', true ) );
