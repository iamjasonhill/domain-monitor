#!/bin/bash

set -euo pipefail

# Laravel Forge deployment script for Domain Monitor.
# This release flow assumes Redis-backed queues with Horizon running as a Forge daemon.

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Validate production-only safeguards before deploying.
./scripts/check-production-env.sh

# Install PHP dependencies.
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install and build frontend assets.
npm ci --no-audit --no-fund || npm install --no-audit --no-fund
npm run build

# Ensure the public storage symlink exists for the new release.
$FORGE_PHP artisan storage:link

# Apply schema changes before the new release goes live.
$FORGE_PHP artisan migrate --force

# Rebuild framework caches for the new release.
$FORGE_PHP artisan optimize:clear
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

# Switch the current symlink to the new release.
$ACTIVATE_RELEASE()

# Tell the running Horizon master to terminate so Forge restarts it on the new release.
$FORGE_PHP artisan horizon:terminate || true
