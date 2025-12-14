#!/bin/bash

# Laravel Forge Deployment Script for Domain Monitor
# This script is optimized for Laravel 12 and includes all necessary steps

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Install PHP dependencies
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install and build frontend assets
npm ci || npm install
npm run build

# Create storage symlink (before caching)
$FORGE_PHP artisan storage:link

# Run database migrations
$FORGE_PHP artisan migrate --force

# Cache configuration for performance
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

# Activate the new release
$ACTIVATE_RELEASE()

# Restart queue workers (if configured)
$RESTART_QUEUES()

