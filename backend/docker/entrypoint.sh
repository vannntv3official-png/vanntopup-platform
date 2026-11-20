#!/bin/sh
set -e

cd /var/www/html

# Ensure writable directories exist
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Ensure APP_KEY is a valid Laravel base64 32-byte key (handles Render generateValue safely)
if [ -z "$APP_KEY" ]; then
  echo "[VannTopUp] Generating ephemeral APP_KEY for staging..."
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
else
  case "$APP_KEY" in
    base64:*) ;;
    *)
      echo "[VannTopUp] Normalizing APP_KEY to Laravel base64 32-byte key..."
      export APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY"), true));')"
      ;;
  esac
fi

# Run database migrations safely
echo "[VannTopUp] Running database migrations..."
php artisan migrate --force

# Optional idempotent seeding on first staging boot
if [ "$RUN_SEED_ON_BOOT" = "true" ]; then
  echo "[VannTopUp] Running idempotent DatabaseSeeder..."
  php artisan db:seed --force
fi

# Cache framework configuration & routes for fast response times
php artisan config:clear
php artisan route:cache
php artisan view:cache

PORT_TO_USE="${PORT:-8000}"
echo "[VannTopUp] Starting Laravel API on 0.0.0.0:${PORT_TO_USE}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT_TO_USE}"
