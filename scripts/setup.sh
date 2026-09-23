#!/usr/bin/env sh
set -eu

command -v docker >/dev/null 2>&1 || { echo "Docker no está instalado."; exit 1; }

[ -f .env ] || cp .env.example .env

docker compose up -d --build --wait --wait-timeout 600
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=IdentitySeeder --force
docker compose exec app php vendor/bin/phpunit

echo "Entorno listo: http://localhost:8080"
echo "Si todavía no existe un administrador, ejecuta: docker compose exec app php artisan liga:create-admin"
