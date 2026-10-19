# WordPress (high-performance) for Railway.
# The Docker Official Image (WordPress 7.1.3, PHP 8.4, Apache) plus:
#   - phpredis 6.3.0 (+ igbinary 3.2.16) so the object cache uses the native C client, not Predis
#   - Redis Object Cache 3.0.0 bundled and enabled out of the box (drop-in + auto-activation mu-plugin)
#   - tuned OPcache/realpath cache, raised memory/upload limits (all overridable with env vars)
#   - Apache: one MPM enforced at boot (fixes "AH00534: More than one MPM loaded" on Railway),
#     Railway proxy range trusted for client IPs, brotli, long-lived caching of static assets
#   - WP-CLI 2.12.0 (runs as www-data), a DB-aware /healthz endpoint and a boot-time cache check
# Every download is pinned and checksum-verified.
ARG WORDPRESS_IMAGE=wordpress:7.1.3-php8.4-apache@sha256:309307430abf80009378a63a79df73554a6449b700fac59a991b4959e6dda8ca
FROM ${WORDPRESS_IMAGE}

ARG IGBINARY_VERSION=3.2.16
ARG IGBINARY_SHA256=8bf25d465abc7973d9e2c9a3039a5f8eea635b23bc1477017ff3999ff95836da
ARG PHPREDIS_VERSION=6.3.0
ARG PHPREDIS_SHA256=0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5
ARG WPCLI_VERSION=2.12.0
ARG WPCLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832
ARG REDIS_CACHE_VERSION=3.0.0
ARG REDIS_CACHE_SHA256=7a03342d3defbe94cac8d0470b42dc1a1c34331b19c47205df20ceccd3c8f54a

# phpredis with igbinary serializer support, built from checksum-verified PECL tarballs.
RUN set -eux; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
	cd /tmp; \
	curl -fsSL -o igbinary.tgz "https://pecl.php.net/get/igbinary-${IGBINARY_VERSION}.tgz"; \
	echo "${IGBINARY_SHA256}  igbinary.tgz" | sha256sum -c -; \
	curl -fsSL -o redis.tgz "https://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz"; \
	echo "${PHPREDIS_SHA256}  redis.tgz" | sha256sum -c -; \
	mkdir igbinary redis; \
	tar -xzf igbinary.tgz --strip-components=1 -C igbinary; \
	tar -xzf redis.tgz --strip-components=1 -C redis; \
	( cd igbinary && phpize && ./configure && make -j"$(nproc)" && make install ); \
	docker-php-ext-enable igbinary; \
	( cd redis && phpize && ./configure --enable-redis-igbinary && make -j"$(nproc)" && make install ); \
	docker-php-ext-enable redis; \
	rm -rf /tmp/igbinary* /tmp/redis* /tmp/pear; \
	apt-mark auto '.*' > /dev/null; \
	[ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*; \
	php -m | grep -qx redis; \
	php -m | grep -qx igbinary; \
	php -r 'exit(defined("Redis::SERIALIZER_IGBINARY") ? 0 : 1);'

# WP-CLI (checksum-verified phar). /usr/local/bin/wp runs it as www-data against /var/www/html.
RUN set -eux; \
	curl -fsSL -o /usr/local/lib/wp-cli.phar "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"; \
	echo "${WPCLI_SHA512}  /usr/local/lib/wp-cli.phar" | sha512sum -c -; \
	chmod 0644 /usr/local/lib/wp-cli.phar

# Redis Object Cache plugin + its object-cache.php drop-in, placed in the WordPress source tree so the
# official entrypoint copies them onto the volume together with core on first boot.
RUN set -eux; \
	cd /tmp; \
	curl -fsSL -o redis-cache.zip "https://downloads.wordpress.org/plugin/redis-cache.${REDIS_CACHE_VERSION}.zip"; \
	echo "${REDIS_CACHE_SHA256}  redis-cache.zip" | sha256sum -c -; \
	php -r '$z = new ZipArchive(); if ($z->open("redis-cache.zip") !== true || !$z->extractTo("/usr/src/wordpress/wp-content/plugins/")) exit(1);'; \
	rm redis-cache.zip; \
	cp /usr/src/wordpress/wp-content/plugins/redis-cache/includes/object-cache.php /usr/src/wordpress/wp-content/object-cache.php; \
	mkdir -p /usr/src/wordpress/wp-content/mu-plugins

COPY rootfs/ /
RUN set -eux; \
	chmod 0755 /usr/local/bin/railway-entrypoint.sh /usr/local/bin/wp; \
	cp /usr/local/share/railway/mu-plugins/*.php /usr/src/wordpress/wp-content/mu-plugins/; \
	chown -R www-data:www-data /usr/src/wordpress/wp-content; \
	php /usr/local/share/railway/patch-wp-config-docker.php /usr/src/wordpress/wp-config-docker.php; \
	php -l /usr/src/wordpress/wp-config-docker.php; \
	php -l /usr/local/etc/wordpress/railway-config.php; \
	php -l /usr/local/share/railway/healthz.php; \
	php -l /usr/local/share/railway/boot-check.php; \
	a2enmod -q brotli headers; \
	a2enconf -q railway; \
	apache2ctl -t

# Defaults for the tunables (the template sets the same values as service variables).
ENV PHP_MEMORY_LIMIT=512M \
	PHP_UPLOAD_LIMIT=256M \
	PHP_MAX_EXECUTION_TIME=300 \
	PHP_OPCACHE_MEMORY=256 \
	WP_CLI_CACHE_DIR=/tmp/wp-cli-cache

ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
CMD ["apache2-foreground"]
