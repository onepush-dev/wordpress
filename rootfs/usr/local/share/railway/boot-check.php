<?php
// Boot-time status line for the deploy logs: is the persistent object cache reachable?
// Never fails the boot (WordPress falls back to the database if the cache is down).
$host = getenv( 'WP_REDIS_HOST' ) ?: '127.0.0.1';
$port = (int) ( getenv( 'WP_REDIS_PORT' ) ?: 6379 );
$pass = getenv( 'WP_REDIS_PASSWORD' );
$tag  = '[railway] object-cache:';

if ( filter_var( getenv( 'WP_REDIS_DISABLED' ), FILTER_VALIDATE_BOOLEAN ) ) {
	echo "$tag disabled by WP_REDIS_DISABLED\n";
	exit( 0 );
}
if ( ! class_exists( 'Redis' ) ) {
	echo "$tag phpredis extension missing\n";
	exit( 0 );
}
$dropin = is_file( '/var/www/html/wp-content/object-cache.php' ) ? 'drop-in installed' : 'drop-in not installed yet (copied on first boot)';
for ( $i = 1; $i <= 5; $i++ ) {
	try {
		$r = new Redis();
		$r->connect( $host, $port, 1.5 );
		if ( false !== $pass && '' !== $pass ) {
			$r->auth( $pass );
		}
		$r->ping();
		$info = $r->info( 'server' );
		$name = isset( $info['valkey_version'] ) ? 'Valkey ' . $info['valkey_version'] : 'Redis ' . ( $info['redis_version'] ?? '?' );
		printf( "%s connected to %s at %s:%d (phpredis %s, igbinary %s), %s\n", $tag, $name, $host, $port, phpversion( 'redis' ), extension_loaded( 'igbinary' ) ? 'on' : 'off', $dropin );
		exit( 0 );
	} catch ( Throwable $e ) {
		$err = $e->getMessage();
		sleep( 1 );
	}
}
printf( "%s NOT reachable at %s:%d (%s); WordPress will run without a persistent cache until it is\n", $tag, $host, $port, $err );
