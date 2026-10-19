# WordPress (high-performance) — WordPress 7.1 on PHP 8.4 with a Valkey object cache, tuned OPcache and MariaDB 12.3. Deploy on Railway with one click.

[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/deploy/TEMPLATE_CODE)

This template runs the **Docker Official WordPress image** (7.1.3, PHP 8.4, Apache) with a few additions that make it noticeably faster than a stock install, plus MariaDB and Valkey. All three services come pre-wired: every password and salt is generated for you, so you can deploy without filling anything in.

## Why it's faster

| What | Stock `wordpress` image | This template |
| --- | --- | --- |
| Persistent object cache | none (every request rebuilds options, menus, queries from MySQL) | **Valkey 9.1** via the native **phpredis 6.3** C extension + igbinary, persistent connections, Redis Object Cache 3.0 drop-in enabled from first boot |
| OPcache | 128 MB, re-checks files every 2 s | 256 MB, 32k files, 32 MB interned strings, re-checks every 60 s |
| realpath cache | 4 MB / 120 s | 4 MB / 600 s (fewer `stat()` calls) |
| PHP limits | 128 MB memory, 2 MB uploads | 512 MB memory, 256 MB uploads, 300 s execution (all variables) |
| Compression | gzip | **brotli** (gzip fallback) |
| Static assets | no cache headers | `Cache-Control: public, max-age=2592000` |
| Database | — | MariaDB 12.3 LTS with a sized InnoDB buffer pool |

Measured on Railway (same region, same WordPress version and content, 15 interleaved requests per page; server time = time to first byte minus the network floor of ~435 ms from the test machine):

| Page | Stock | This template |
| --- | --- | --- |
| Home page | ~92 ms | **~53 ms** |
| Single post | ~102 ms | **~63 ms** |
| REST API `/wp-json/wp/v2/posts` | ~61 ms | **~30 ms** |
| Search `/?s=hello` | ~94 ms | **~55 ms** |
| Home page bytes on the wire (brotli) | 70.6 KB (uncompressed for `br`) | **11.5 KB** |

The gap grows with plugins and traffic: the object cache saves the most on sites with WooCommerce, page builders and lots of options.

It also fixes the usual Railway pain points of the official image: exactly one Apache MPM is enforced at every boot (no `AH00534: More than one MPM loaded` crash), the site URL is pinned to your Railway domain over HTTPS (no redirect loops), Railway's proxy range is trusted so WordPress sees real visitor IPs, and `/healthz` gives Railway a DB-aware health check that works before and after installation.

## Services

| Service | Image | Port | Volume | Notes |
| --- | --- | --- | --- | --- |
| **WordPress** | `ghcr.io/onepush-dev/wordpress:7.1.3-php8.4` (digest-pinned; built from this repo) | 80 (public) | `/var/www/html` | Health check `/healthz` |
| **MariaDB** | `mariadb:12.3.3` (digest-pinned) | 3306 (private) | `/var/lib/mysql` | `--innodb-buffer-pool-size`, `--max-allowed-packet=64M`, `--skip-name-resolve` |
| **Valkey** | `valkey/valkey:9.1.2-alpine` (digest-pinned) | 6379 (private) | none (cache only) | password-protected, `allkeys-lru`, no persistence |

## Variables

All of these have defaults; nothing needs to be filled in at deploy time.

**WordPress**

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `80` | Apache port (public domain routes here) |
| `WP_HOME` | `https://${{RAILWAY_PUBLIC_DOMAIN}}` | Site address. Change to `https://your-domain.com` after adding a custom domain |
| `WORDPRESS_DB_HOST` | `${{MariaDB.RAILWAY_PRIVATE_DOMAIN}}:3306` | MariaDB over the private network |
| `WORDPRESS_DB_NAME` | `${{MariaDB.MARIADB_DATABASE}}` | Database name |
| `WORDPRESS_DB_USER` | `${{MariaDB.MARIADB_USER}}` | Database user |
| `WORDPRESS_DB_PASSWORD` | `${{MariaDB.MARIADB_PASSWORD}}` | Database password |
| `WORDPRESS_TABLE_PREFIX` | `wp_` | Table prefix |
| `WORDPRESS_AUTH_KEY`, `WORDPRESS_SECURE_AUTH_KEY`, `WORDPRESS_LOGGED_IN_KEY`, `WORDPRESS_NONCE_KEY`, `WORDPRESS_AUTH_SALT`, `WORDPRESS_SECURE_AUTH_SALT`, `WORDPRESS_LOGGED_IN_SALT`, `WORDPRESS_NONCE_SALT` | `${{secret(64)}}` each | Cookie/nonce keys and salts, generated per deploy |
| `WP_REDIS_HOST` | `${{Valkey.RAILWAY_PRIVATE_DOMAIN}}` | Object cache host |
| `WP_REDIS_PORT` | `6379` | Object cache port |
| `WP_REDIS_PASSWORD` | `${{Valkey.VALKEY_PASSWORD}}` | Object cache password |
| `WP_REDIS_DISABLED` | `false` | Set `true` to turn the object cache off |
| `PHP_MEMORY_LIMIT` | `512M` | PHP `memory_limit` (also WordPress admin memory) |
| `PHP_UPLOAD_LIMIT` | `256M` | Max upload / POST size |
| `PHP_MAX_EXECUTION_TIME` | `300` | Max script time in seconds (imports, backups) |
| `PHP_OPCACHE_MEMORY` | `256` | OPcache size in MB |

Optional (not set by default): `WORDPRESS_CONFIG_EXTRA` (extra PHP for `wp-config.php`, e.g. `define('WP_POST_REVISIONS', 50);`), `WP_MEMORY_LIMIT` (`256M`), `WP_POST_REVISIONS` (`20`), `WP_DISALLOW_FILE_EDIT` (`true`), `WP_REDIS_PREFIX` (`wp:`), `WP_REDIS_DISABLE_METRICS` (`true`).

**MariaDB**

| Variable | Default | Purpose |
| --- | --- | --- |
| `MARIADB_DATABASE` | `wordpress` | Database created on first boot |
| `MARIADB_USER` | `wordpress` | App user created on first boot |
| `MARIADB_PASSWORD` | `${{secret(32)}}` | App user password |
| `MARIADB_ROOT_PASSWORD` | `${{secret(32)}}` | Root password |
| `MARIADB_AUTO_UPGRADE` | `1` | Runs `mariadb-upgrade` automatically after image upgrades |
| `MARIADB_INNODB_BUFFER_POOL_SIZE` | `256M` | InnoDB buffer pool; raise it on bigger plans |

**Valkey**

| Variable | Default | Purpose |
| --- | --- | --- |
| `VALKEY_PASSWORD` | `${{secret(32)}}` | Password required by Valkey |
| `VALKEY_MAXMEMORY` | `256mb` | Memory cap; least-recently-used keys are evicted past it |
| `VALKEY_URL` | `redis://default:${{VALKEY_PASSWORD}}@${{RAILWAY_PRIVATE_DOMAIN}}:6379` | Connection URL for other services |

## First run

1. Click **Deploy on Railway** and wait until all three services are green (WordPress turns healthy once MariaDB accepts connections).
2. Open the WordPress service's public domain. You'll see the WordPress installer: pick a language, site title, admin user and password.
3. Log in at `/wp-admin`. The **Redis Object Cache** plugin is already active; **Settings → Redis** should say *Connected* (drop-in valid, phpredis client).
4. Optional: add a custom domain to the WordPress service, then set `WP_HOME=https://your-domain.com` and redeploy.

WP-CLI is included and runs as `www-data`: in the WordPress service shell, run e.g. `wp plugin list` or `wp redis status`.

## Notes

- **Persistence:** WordPress core, plugins, themes and uploads live on the `/var/www/html` volume; the database lives on the MariaDB volume. Valkey is a pure cache and can be restarted or wiped at any time.
- **Updates:** WordPress updates itself from the dashboard as usual (files are on the volume). Bumping the image only changes PHP/Apache/extensions.
- **Email:** WordPress can't send mail without SMTP. Install an SMTP plugin (e.g. WP Mail SMTP) if you need password resets or notifications.
- **Security defaults:** the theme/plugin file editor is disabled (`DISALLOW_FILE_EDIT`), `readme.html`/`license.txt` are blocked, PHP and Apache versions are hidden, admin is HTTPS-only.

## Building the image

`.github/workflows/image.yml` builds `ghcr.io/onepush-dev/wordpress` from the `Dockerfile` on every push to `main` (official image pinned by digest; phpredis, igbinary, WP-CLI and Redis Object Cache downloads are checksum-verified) and runs a smoke test.

## License

WordPress is GPL-2.0-or-later. The files in this repo are provided under the same license.
