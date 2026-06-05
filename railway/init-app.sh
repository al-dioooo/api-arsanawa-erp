#!/usr/bin/env sh
set -eu

php artisan migrate --force
php artisan db:seed --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
