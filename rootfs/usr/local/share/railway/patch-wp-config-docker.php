<?php
// Build-time helper: make the official wp-config-docker.php (the template the image's entrypoint turns
// into wp-config.php on first boot) load the Railway defaults. Inserted after the WORDPRESS_CONFIG_EXTRA
// eval, so anything set there wins, and before "stop editing", so it runs before wp-settings.php.
$file = $argv[1];
$src = file_get_contents($file);
$needle = "/* That's all, stop editing!";
if (strpos($src, 'railway-config.php') !== false) {
    exit(0);
}
if (strpos($src, 'eval($configExtra);') === false || strpos($src, $needle) === false) {
    fwrite(STDERR, "wp-config-docker.php layout changed, refusing to patch\n");
    exit(1);
}
$include = <<<'PHP'
// Railway template defaults: site URL, HTTPS, Valkey/Redis object cache, limits.
// Every constant there is only defined if not set already, so WORDPRESS_CONFIG_EXTRA can override it.
if ( is_readable( '/usr/local/etc/wordpress/railway-config.php' ) ) {
	require_once '/usr/local/etc/wordpress/railway-config.php';
}


PHP;
file_put_contents($file, str_replace($needle, $include . $needle, $src));
echo "patched $file\n";
