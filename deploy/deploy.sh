#!/usr/bin/env bash
# Builds a new release next to the live one and switches over atomically.
#
#   sudo -u www-data bash deploy/deploy.sh <git-ref>
#
# Layout:
#   /var/www/it-helpdesk/releases/<timestamp>/   (backend/ + pwa/)
#   /var/www/it-helpdesk/shared/.env             backend .env (production values)
#   /var/www/it-helpdesk/shared/pwa.env          pwa build values (VITE_*)
#   /var/www/it-helpdesk/shared/storage/         uploads, logs, cache (kept across releases)
#   /var/www/it-helpdesk/current -> releases/<timestamp>
set -euo pipefail

BASE=/var/www/it-helpdesk
REPO=${REPO:-"$BASE/repo"}
REF=${1:-main}
RELEASE="$BASE/releases/$(date +%Y%m%d%H%M%S)"
KEEP=5

echo "→ Fetching $REF"
git -C "$REPO" fetch --quiet origin
mkdir -p "$RELEASE"
git -C "$REPO" archive "origin/$REF" | tar -x -C "$RELEASE"

echo "→ Backend"
cd "$RELEASE/backend"
ln -sfn "$BASE/shared/.env" .env
rm -rf storage && ln -sfn "$BASE/shared/storage" storage
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet
php artisan migrate --force
php artisan optimize
php artisan filament:optimize

echo "→ PWA"
cd "$RELEASE/pwa"
cp "$BASE/shared/pwa.env" .env.production.local
npm ci --no-audit --no-fund --silent
npm run build --silent

echo "→ Switching to $RELEASE"
ln -sfn "$RELEASE" "$BASE/current.tmp" && mv -Tf "$BASE/current.tmp" "$BASE/current"

echo "→ Restarting background processes"
php "$BASE/current/backend/artisan" queue:restart
php "$BASE/current/backend/artisan" reverb:restart || true
sudo /usr/sbin/service php8.4-fpm reload || true

echo "→ Cleaning old releases"
ls -1dt "$BASE"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "✓ Deployed $REF"
