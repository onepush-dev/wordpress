<?php
// Railway healthcheck (served at /healthz by Apache, outside the document root, without loading
// WordPress): 200 once PHP runs and the database accepts the WordPress credentials, 503 otherwise.
// It works before and after the WordPress installer has run and never redirects.
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Cache-Control: no-store' );

$host   = getenv( 'WORDPRESS_DB_HOST' ) ?: 'mysql';
$port   = 3306;
$socket = null;
if ( preg_match( '/^\[(.+)\](?::(\d+))?$/', $host, $m ) ) { // [ipv6]:port
	$host = $m[1];
	$port = isset( $m[2] ) ? (int) $m[2] : 3306;
} elseif ( substr_count( $host, ':' ) === 1 ) {               // host:port or host:/socket
	list( $h, $p ) = explode( ':', $host, 2 );
	$host = $h;
	if ( ctype_digit( $p ) ) {
		$port = (int) $p;
	} else {
		$socket = $p;
	}
}

mysqli_report( MYSQLI_REPORT_OFF );
$db = mysqli_init();
$db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 );
$ok = @$db->real_connect(
	$host,
	getenv( 'WORDPRESS_DB_USER' ) ?: '',
	getenv( 'WORDPRESS_DB_PASSWORD' ) ?: '',
	getenv( 'WORDPRESS_DB_NAME' ) ?: '',
	$port,
	$socket
);
if ( ! $ok ) {
	http_response_code( 503 );
	echo "database: unreachable\n";
	exit;
}
$db->close();
echo "ok\n";
