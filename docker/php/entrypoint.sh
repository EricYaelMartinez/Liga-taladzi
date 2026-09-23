#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chmod -R a+rwX storage
chmod -R ug+rwX bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist --no-progress

# Docker Desktop comparte estos directorios con Windows. Reaplicamos permisos
# después de Composer por si creó nuevos archivos como usuario root.
chmod -R a+rwX storage
chmod -R ug+rwX bootstrap/cache

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

touch /tmp/liga-app-ready

exec "$@"
