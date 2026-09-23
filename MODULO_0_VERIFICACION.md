# Verificación del Módulo 0

## Alcance entregado

- Proyecto base Laravel, Vue y TypeScript.
- Estilos con Tailwind CSS.
- Servicios Docker para PHP-FPM, Nginx, Node, PostgreSQL y Redis.
- Cliente Redis Predis en PHP, sin compilación de extensiones PECL.
- Entorno local sin recompilación de OPcache, para mantener compatibilidad con PHP 8.5.
- Volumen persistente configurado en la ubicación requerida por PostgreSQL 18.
- Dependencias de desarrollo mínimas y compatibles con Laravel 13.
- Instalador PowerShell ejecutado correctamente en modo desacoplado.
- Espera de salud del contenedor PHP antes de ejecutar Artisan.
- Carpetas escribibles de Laravel creadas antes de ejecutar Composer.
- Pruebas ejecutadas directamente con PHPUnit.
- Suites `Feature` y `Unit` presentes para PHPUnit.
- Clave de cifrado válida y aislada para el entorno de pruebas.
- Permisos locales de escritura para vistas, sesiones y registros de Laravel.
- Variables locales de ejemplo sin secretos de producción.
- Página inicial de diagnóstico.
- Endpoint JSON `GET /status/services`.
- Pruebas automáticas iniciales.
- Organización modular del dominio.

## Prueba obligatoria en Windows

Desde PowerShell, en la carpeta del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El script construye el entorno, ejecuta las migraciones y corre las pruebas.

## Resultado esperado

1. `docker compose ps` muestra los cinco servicios activos.
2. `docker compose exec app php artisan test` termina sin errores.
3. `docker compose exec frontend npm run type-check` termina sin errores.
4. `http://localhost:8080` muestra Laravel, PostgreSQL y Redis disponibles.
5. `http://localhost:8080/status/services` devuelve estado `ok`.

## Criterio para continuar

No se inicia el Módulo 1 hasta que esta verificación sea satisfactoria o se corrija cualquier incidencia encontrada.
