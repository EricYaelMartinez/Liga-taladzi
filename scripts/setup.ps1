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

Write-Host "Construyendo servicios y esperando a que PHP termine de instalar sus dependencias..."
docker compose up -d --build --wait --wait-timeout 600
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan migrate --force
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php artisan db:seed --class=IdentitySeeder --force
Assert-DockerSucceeded $LASTEXITCODE

docker compose exec app php vendor/bin/phpunit
Assert-DockerSucceeded $LASTEXITCODE

Write-Host ""
Write-Host "Entorno listo: http://localhost:8080" -ForegroundColor Green
Write-Host "Si todavía no existe un administrador, ejecuta: docker compose exec app php artisan liga:create-admin" -ForegroundColor Yellow
