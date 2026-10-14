# Estabilización del ciclo competitivo

## Objetivo

Corregir el ciclo de vida de partidos, jornadas, competencias y temporadas antes de continuar con el siguiente módulo.

## Reglas implementadas

- Un partido programado cambia automáticamente a **En curso** cuando llega su fecha y hora.
- El cambio automático queda registrado en el historial de programación.
- La cédula no puede capturarse ni enviarse antes del inicio programado, incluso mediante una petición directa al servidor.
- Una jornada cambia a **Completada** cuando todos sus partidos están finalizados o cancelados.
- Una jornada cambia a **Cancelada** cuando todos sus partidos fueron cancelados.
- Las jornadas completadas y canceladas se ocultan inicialmente y se consultan mediante **Mostrar jornadas finalizadas y canceladas**.
- Al finalizar, archivar o cancelar una temporada, el estado se propaga a sus torneos y competencias.
- Las estructuras concluidas conservan su historial, pero ya no aceptan nuevos torneos, competencias, equipos, jornadas ni cambios de programación.

## Automatización

Docker inicia el servicio `scheduler`, que ejecuta el programador de Laravel. La tarea `liga:sync-match-lifecycle` se ejecuta cada minuto.

Como respaldo, la sincronización también se ejecuta al abrir las pantallas de Jornadas, Alineaciones y Cédulas.

En producción sin Docker debe configurarse el cron estándar de Laravel:

```bash
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## Instalación segura

Desde PowerShell, dentro del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración es aditiva: amplía los estados permitidos y corrige los estados derivados. No elimina temporadas, torneos, competencias, jornadas, partidos, alineaciones, cédulas ni resultados. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Programa y publica un partido para unos minutos después.
2. Antes de la hora, abre **Cédulas** y comprueba que la captura esté bloqueada.
3. Al llegar la hora, recarga **Jornadas** y comprueba que el partido aparezca **En curso**.
4. Abre **Cédulas** y verifica que ya permita capturar el borrador.
5. Finaliza o cancela todos los encuentros de una jornada y recarga el calendario.
6. Comprueba que la jornada desaparezca del listado inicial.
7. Activa **Mostrar jornadas finalizadas y canceladas** y verifica que reaparezca.
8. Finaliza una temporada y comprueba que sus torneos y competencias también indiquen **Finalizada**.
9. Archiva la temporada y verifica que sus estructuras indiquen **Archivada** y no aparezcan en formularios de alta.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:lifecycle
```
