# Módulo 19 — Auditoría y respaldos

## Alcance

Este módulo amplía la bitácora existente y protege la continuidad del sistema mediante respaldos completos semanales.

### Auditoría por liga

- Consulta limitada en el servidor a la liga activa.
- Filtros por acción, usuario, fecha y dirección IP.
- Detalle de valores anteriores y posteriores.
- Exportación CSV respetando los mismos filtros y el aislamiento por liga.
- Protección automática de contraseñas, tokens, secretos y claves cuando se registran valores nuevos.

### Respaldos del sistema

- Copia de PostgreSQL en formato nativo `pg_dump`.
- Inclusión de documentos privados, fotografías, logotipos y demás archivos cargados.
- Ejecución semanal mediante el contenedor `scheduler`.
- Configuración inicial: domingo a las 03:00 y retención de ocho respaldos.
- Creación manual, historial y descarga únicamente para el administrador del sistema.
- Creación manual en segundo plano mediante la cola Redis, sin bloquear la pantalla.
- Archivos fuera de la carpeta pública.
- Verificación de integridad SHA-256 antes de restaurar.
- Restauración solo desde consola, con modo mantenimiento, respaldo preventivo y confirmación `--force`.

## Actualización de base de datos

Las migraciones `2026_10_16_000022_create_backup_runs_table.php` y `2026_10_16_000023_create_failed_jobs_table.php` únicamente crean las tablas `backup_runs` y `failed_jobs`. No modifican ni eliminan tablas existentes. La segunda permite registrar de manera segura cualquier tarea de respaldo que no termine correctamente.

Desde PowerShell, en `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El instalador ejecuta las migraciones y actualiza el permiso `backups.manage`. No ejecutes `docker compose down -v`, porque esa opción elimina los volúmenes de PostgreSQL y Redis.

## Configuración

Los valores pueden cambiarse en `.env` sin modificar código:

```dotenv
BACKUP_DIRECTORY=backups
BACKUP_RETENTION_COUNT=8
BACKUP_SCHEDULE_DAY=sunday
BACKUP_SCHEDULE_TIME=03:00
```

Después de cambiar `.env`, ejecuta:

```powershell
docker compose exec app php artisan config:clear
docker compose restart scheduler
```

## Prueba manual

1. Ingresa con el administrador del sistema y selecciona **Administración del sistema**.
2. Abre **Administración > Respaldos**.
3. Pulsa **Crear respaldo ahora** y espera el mensaje de confirmación.
4. Comprueba que el estado sea **Completado**, que muestre tamaño y que permita descargar el ZIP.
5. Abre el ZIP solo para verificar que contiene `manifest.json` y `database.dump`; no edites el archivo.
6. Ingresa con un administrador de liga y verifica que **Respaldos** no aparezca y la URL directa responda 403.
7. Entra a una liga como administrador, abre **Control > Bitácora**, aplica filtros y descarga el CSV.
8. Cambia de liga y comprueba que la bitácora no muestre movimientos de la liga anterior.
9. Revisa la programación:

```powershell
docker compose exec app php artisan schedule:list
```

10. Prueba el comando manual:

```powershell
docker compose exec app php artisan liga:backup --trigger=manual
```

## Restauración

La restauración reemplaza información y no debe probarse sobre la instalación principal. Debe validarse primero sobre una copia del proyecto y la base de datos.

```powershell
docker compose exec app php artisan liga:backup-restore ID_DEL_RESPALDO --force
```

El comando comprueba la suma SHA-256, crea un respaldo preventivo, activa mantenimiento, restaura PostgreSQL y archivos y finalmente vuelve a habilitar el sistema.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run validate:module19
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
```

No se debe aprobar el módulo si alguna comprobación termina con error.
