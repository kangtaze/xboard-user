#!/bin/sh
# caddy-start.sh — Inject SECURE_PATH from Laravel DB then exec caddy
#
# Why: AdminRoute::map() uses admin_setting('secure_path', hash('crc32b', APP_KEY))
# as the admin prefix, which can be overridden via v2_settings table. The Caddy
# rewrite rule for /<prefix>/edgetunnel and /<prefix>/cf-worker must match that
# prefix, otherwise Octane returns 404 for admin pages after a secure_path
# change.
#
# Flow:
#   1. Query the DB through artisan tinker (preferred path, matches AdminRoute).
#   2. Fall back to hash(crc32b, APP_KEY) if the DB is unavailable — this is the
#      exact same default AdminRoute uses, so admin pages still resolve.
#   3. export SECURE_PATH and exec caddy (Caddy reads {$VAR} from OS env).

set -e

cd /www

# Read SECURE_PATH from DB; suppress stderr so tinker noise doesn't leak into caddy logs.
SECURE_PATH=$(php artisan tinker --execute="echo admin_setting('secure_path', hash('crc32b', config('app.key')));" 2>/dev/null | tr -d '[:space:]')

# Fallback: recompute the default the same way AdminRoute does.
if [ -z "$SECURE_PATH" ]; then
    echo "[caddy-start] WARN: could not read SECURE_PATH from DB, falling back to hash(crc32b, APP_KEY)" >&2
    SECURE_PATH=$(php -r 'echo hash("crc32b", getenv("APP_KEY"));')
fi

if [ -z "$SECURE_PATH" ]; then
    echo "[caddy-start] FATAL: SECURE_PATH is empty, refusing to start caddy" >&2
    exit 1
fi

export SECURE_PATH
echo "[caddy-start] SECURE_PATH=$SECURE_PATH" >&2

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
