# Módulo 7 — Documentos y credenciales

## Objetivo

Controlar la emisión y revocación de credenciales de jugadores, así como el acceso, conservación y eliminación segura de documentos privados.

## Reglas implementadas

- Cada credencial emitida recibe un folio único por liga y conserva una fotografía de sus datos en formato JSON.
- Solo se emiten credenciales para altas activas de jugadores activos y aprobados.
- La aprobación o reactivación emite el folio automáticamente.
- La baja revoca la credencial activa; una reincorporación futura genera un folio nuevo.
- El PDF por equipo incluye únicamente credenciales activas emitidas.
- Se conserva el diseño personalizado de 9 × 6 cm y los cuatro logotipos configurables de la liga.
- Los documentos admitidos son PDF, JPG y PNG, con un máximo de 5 MB.
- Los archivos se guardan en almacenamiento privado y nunca se exponen mediante una URL pública directa.
- Solo el administrador de liga puede consultar o administrar documentos privados.
- La descarga de fotografías y documentos, así como la emisión, revocación y eliminación, queda en la bitácora.
- La carta responsiva es obligatoria para registrar menores y se conserva hasta el final de la temporada asociada.
- El archivo solo puede eliminarse después de la fecha de retención, exige un motivo y conserva un registro lógico de la eliminación.

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
git pull
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El instalador aplicará la migración y actualizará los permisos. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Ingresa como administrador de liga y abre **Jugadores**.
2. Aprueba un jugador pendiente y confirma que aparezca un folio en su historial.
3. En **Credenciales**, usa **Emitir faltantes** para jugadores aprobados antes de instalar este módulo.
4. Abre la vista previa y descarga el PDF: debe mostrar el folio y conservar el diseño personalizado.
5. Revoca una credencial manualmente y confirma que deje de aparecer en el PDF.
6. Aprueba o emite nuevamente cuando corresponda y verifica que el nuevo folio sea distinto.
7. En un jugador menor, sube una carta PDF/JPG/PNG menor o igual a 5 MB.
8. Descárgala como administrador y revisa la acción en **Bitácora**.
9. Ingresa como representante: la descarga directa del documento debe responder con acceso denegado.
10. Antes de concluir la retención no debe aparecer la opción de eliminación ni permitirse por URL.
11. Después de la fecha de retención, elimina con motivo y comprueba el registro en bitácora.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module7
```

## Recuperación

La migración es aditiva y no borra expedientes existentes. Antes de revertirla en un ambiente con datos reales, realiza un respaldo: la reversión elimina el historial de folios y los metadatos documentales agregados por este módulo.
