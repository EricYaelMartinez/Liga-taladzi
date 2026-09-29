$ErrorActionPreference = "Stop"

function Assert-DockerSucceeded {
    param([int]$ExitCode)

    if ($ExitCode -ne 0) {
        throw "Docker finalizo con el codigo de error $ExitCode. Revisa el mensaje mostrado arriba."
    }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker no está disponible. Instala Docker Desktop y vuelve a ejecutar este archivo."
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
}

Write-Host "Preparando las dependencias PHP..."
docker compose build app
Assert-DockerSucceeded $LASTEXITCODE

if (-not (Test-Path "composer.lock")) {
    docker compose run --rm --no-deps --entrypoint composer app install --no-interaction --prefer-dist --no-progress
    Assert-DockerSucceeded $LASTEXITCODE
} elseif (-not (Select-String -Path "composer.lock" -Pattern '"name": "barryvdh/laravel-dompdf"' -Quiet)) {
    docker compose run --rm --no-deps --entrypoint composer app update barryvdh/laravel-dompdf --with-all-dependencies --no-interaction --prefer-dist --no-progress
    Assert-DockerSucceeded $LASTEXITCODE
}

Write-Host "Construyendo servicios y esperando a que estén disponibles..."
docker compose up -d --build --wait --wait-timeout 600
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app composer install --no-interaction --prefer-dist --no-progress
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan optimize:clear
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan migrate --force
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan db:seed --class=IdentitySeeder --force
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan storage:link --force
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php vendor/bin/phpunit
Assert-DockerSucceeded $LASTEXITCODE

Write-Host ""
Write-Host "Entorno listo: http://localhost:8080" -ForegroundColor Green
Write-Host "Si todavía no existe un administrador, ejecuta: docker compose exec app php artisan liga:create-admin" -ForegroundColor Yellow
