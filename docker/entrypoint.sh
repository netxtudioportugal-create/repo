#!/bin/sh
set -e
cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run once per release. On platforms with a release phase, move these there.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --class=TaxonomySeeder --force
  php artisan items:import --no-interaction
fi

exec "$@"
