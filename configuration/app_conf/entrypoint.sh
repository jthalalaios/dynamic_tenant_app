#!/bin/sh
# Laravel container boot: permissions → vendor → Passport keys → wait for DB →
# migrate (every boot) → sync permissions → seed (first boot) → caches → workers → FPM.
#
# Every artisan call runs as www-data so files it writes (compiled views,
# caches, logs) stay writable by PHP-FPM. Steps that must succeed fail the
# container instead of being silently skipped.
set -eu

APP_DIR="/var/www/html"
cd "$APP_DIR"

log() { echo "[entrypoint] $*"; }

is_root() { [ "$(id -u)" -eq 0 ]; }

# Run a command as www-data when we are root (bind mounts keep host ownership otherwise)
as_app() {
  if is_root; then
    setpriv --reuid=www-data --regid=www-data --init-groups "$@"
  else
    "$@"
  fi
}

artisan() { as_app php artisan "$@"; }

# Compose does not pass APP_ENV; fall back to the app's .env, then "production"
if [ -z "${APP_ENV:-}" ] && [ -f "$APP_DIR/.env" ]; then
  APP_ENV=$(sed -n 's/^APP_ENV=//p' "$APP_DIR/.env" | tail -n 1 | tr -d '"'"'"'\r')
fi
APP_ENV=${APP_ENV:-production}
export APP_ENV
log "APP_ENV=$APP_ENV"

# ---------------------------------------------------------------- permissions
if is_root; then
  chmod 1777 /tmp
  mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions \
           storage/logs storage/app/public bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
fi

# --------------------------------------------------------------------- vendor
# The bind mount hides the image's vendor/ when the host has none
if [ ! -f vendor/autoload.php ]; then
  log "vendor/ missing, installing composer dependencies..."
  if [ "$APP_ENV" = "production" ]; then
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  else
    # non-production environments need dev packages such as pail / pest
    composer install --prefer-dist --optimize-autoloader --no-interaction
  fi
fi

# Stale caches from the host or a previous boot would hide config/route changes
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php bootstrap/cache/events.php

# --------------------------------------------------------------- passport keys
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
  log "Generating Passport keys..."
  artisan passport:keys --force
fi
# Passport rejects overly permissive key files
for key in storage/oauth-private.key storage/oauth-public.key; do
  chmod 600 "$key"
  if is_root; then chown www-data:www-data "$key"; fi
done

# ------------------------------------------------------------ wait for the DB
# depends_on: service_healthy covers compose, but not restarts or external databases
db_php='require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'

tries=0
until as_app php -r "$db_php Illuminate\Support\Facades\DB::connection()->getPdo();" > /dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    log "Database still unreachable after $tries attempts, giving up:"
    as_app php -r "$db_php Illuminate\Support\Facades\DB::connection()->getPdo();" || true
    exit 1
  fi
  log "Waiting for the database ($tries/30)..."
  sleep 2
done

# ----------------------------------------------------------------- migrations
# Remember whether this is the very first boot of this database, before migrating
FIRST_BOOT=$(as_app php -r "$db_php echo Illuminate\Support\Facades\Schema::hasTable('migrations') ? 0 : 1;")

# Always migrate: applies every migration added since the last boot (no-op when up to date)
log "Running migrations..."
artisan migrate --force --no-interaction

# Idempotent; picks up permissions added to config/permissions.php
log "Syncing permissions..."
artisan permissions:sync --no-interaction

if [ "$FIRST_BOOT" = "1" ] || [ "${RUN_SEEDERS:-false}" = "true" ]; then
  log "Seeding database..."
  artisan db:seed --force --no-interaction
else
  log "Database already seeded (set RUN_SEEDERS=true to seed again)."
fi

# Passport personal access client (needs the oauth_clients table)
if [ "$(as_app php -r "$db_php try { app(Laravel\Passport\ClientRepository::class)->personalAccessClient('users'); echo 1; } catch (Throwable \$e) { echo 0; }")" != "1" ]; then
  log "Creating Passport personal access client..."
  artisan passport:client --personal --name="SPA Personal Access Client" --provider=users --no-interaction
fi

# --------------------------------------------------------------------- caches
if [ "$APP_ENV" = "production" ]; then
  log "Caching config, routes, events and views..."
  artisan optimize
else
  # Development: no caches, so edits to config/routes/.env apply immediately
  artisan optimize:clear > /dev/null
fi

if [ ! -L public/storage ]; then
  rm -rf public/storage
  artisan storage:link
fi

# -------------------------------------------------------------------- workers
log "Starting queue worker and scheduler..."
as_app php artisan queue:work --sleep=3 --tries=3 &
# Runs routes/console.php (presence:close-stale, passport:purge)
as_app php artisan schedule:work &

log "Starting PHP-FPM..."
exec php-fpm -F
