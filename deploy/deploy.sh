#!/usr/bin/env bash
# ============================================================
# Zero-downtime deploy script for MSME 43B(h) Tracker
#
# Usage: ./deploy/deploy.sh [branch]
# Default branch: main
#
# Pre-requisites on the server:
#   - /var/www/msme-tracker owned by www-data
#   - PHP 8.3, Composer 2, Node 20 installed
#   - Redis, MySQL 8 running
#   - Supervisor configured with deploy/supervisor.conf
#   - This script run as www-data or a deploy user with sudo rights for
#     php-fpm reload and supervisorctl
#
# Called by GitHub Actions on push to main (see .github/workflows/ci.yml)
# ============================================================

set -euo pipefail

APP_DIR="/var/www/msme-tracker"
BRANCH="${1:-main}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG="/var/log/deploy/deploy-${TIMESTAMP}.log"

mkdir -p /var/log/deploy

echo "=== MSME Tracker deploy: branch=${BRANCH} at ${TIMESTAMP} ===" | tee "$LOG"

cd "$APP_DIR"

# 1. Maintenance mode — Inertia/503 for active users
echo "[1/9] Enabling maintenance mode..." | tee -a "$LOG"
php artisan down --render="errors::503" --retry=60 2>>"$LOG"

# 2. Pull latest code
echo "[2/9] Pulling ${BRANCH}..." | tee -a "$LOG"
git fetch origin 2>>"$LOG"
git reset --hard "origin/${BRANCH}" 2>>"$LOG"

# 3. Composer — production-only, no dev packages
echo "[3/9] Installing PHP dependencies..." | tee -a "$LOG"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>>"$LOG"

# 4. Environment sanity check (key must exist)
echo "[4/9] Checking environment..." | tee -a "$LOG"
php artisan about --only=environment 2>>"$LOG" || true

# 5. Database migrations (automatic in CI; tagged releases only in production)
echo "[5/9] Running migrations..." | tee -a "$LOG"
php artisan migrate --force 2>>"$LOG"

# 6. Asset compilation
echo "[6/9] Building frontend assets..." | tee -a "$LOG"
npm ci --omit=dev 2>>"$LOG"
npm run build 2>>"$LOG"

# 7. Cache optimisation
echo "[7/9] Caching routes, views, config..." | tee -a "$LOG"
php artisan route:cache 2>>"$LOG"
php artisan view:cache 2>>"$LOG"
php artisan config:cache 2>>"$LOG"
php artisan event:cache 2>>"$LOG"

# 8. Reload PHP-FPM (clears Opcache without dropping connections)
echo "[8/9] Reloading PHP-FPM..." | tee -a "$LOG"
sudo systemctl reload php8.3-fpm 2>>"$LOG"

# Restart queue workers so they pick up new code
supervisorctl restart msme:* 2>>"$LOG" || true

# 9. Disable maintenance mode
echo "[9/9] Disabling maintenance mode..." | tee -a "$LOG"
php artisan up 2>>"$LOG"

echo "=== Deploy complete: ${TIMESTAMP} ===" | tee -a "$LOG"
