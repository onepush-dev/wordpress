#!/bin/sh
# Railway entrypoint for the official WordPress image: fix the Apache MPM set, refresh the template's
# must-use plugin, log the object-cache status, then hand over to the official docker-entrypoint.sh
# (which copies WordPress onto an empty volume and writes wp-config.php on first boot).
set -e

if [ "${1:-}" = apache2-foreground ]; then
	# mod_php needs mpm_prefork. On Railway the extra MPMs can come back from lower image layers
	# ("AH00534: More than one MPM loaded"), so enforce exactly one at every boot.
	rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
	[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork

	# Keep the template's mu-plugin current on existing volumes (first boot copies it with core).
	if [ -d /var/www/html/wp-content ]; then
		mkdir -p /var/www/html/wp-content/mu-plugins
		cp -f /usr/local/share/railway/mu-plugins/*.php /var/www/html/wp-content/mu-plugins/
		chown -R www-data:www-data /var/www/html/wp-content/mu-plugins
	fi

	php /usr/local/share/railway/boot-check.php || true
	echo "[railway] PHP $(php -r 'echo PHP_VERSION;'), memory_limit=${PHP_MEMORY_LIMIT}, upload=${PHP_UPLOAD_LIMIT}, opcache=${PHP_OPCACHE_MEMORY}MB, site=${WP_HOME:-https://${RAILWAY_PUBLIC_DOMAIN:-?}}"
fi

exec docker-entrypoint.sh "$@"
