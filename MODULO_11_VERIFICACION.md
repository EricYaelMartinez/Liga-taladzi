# Módulo 11 — Alineaciones

## Objetivo

Permitir la captura opcional de titulares, suplentes y capitán para cada equipo de un partido publicado, conservar la información histórica y cerrar definitivamente la alineación cuando sea enviada.

## Alcance implementado

- Alineación independiente para local y visitante.
- Selección exclusiva de jugadores activos y aprobados en la plantilla del equipo.
- Clasificación como titular o suplente.
- Capitán opcional y único por equipo.
- Copia histórica del dorsal y la posición al momento de la captura.
- Guardado como borrador con modificaciones ilimitadas antes del envío.
- Envío irreversible; no existe reapertura ni edición posterior.
- El representante administra únicamente la alineación de su equipo.
- El administrador puede administrar las alineaciones de cualquier partido de su liga.
- El árbitro consulta las alineaciones enviadas únicamente en partidos asignados.
- Bitácora de guardado y envío con usuario, fecha, valores y motivo.
- Interfaz adaptable a computadora y teléfono.

La captura es opcional: la ausencia de una alineación no impide iniciar el partido ni sustituye la revisión de credenciales físicas. Las cédulas arbitrales pertenecen al Módulo 12.

## Reglas aplicadas

- El partido debe pertenecer a la liga activa y a una jornada publicada.
- Solo se captura mientras el partido esté programado o aplazado.
- El equipo debe ser local o visitante del partido.
- Un jugador no puede repetirse en la misma alineación.
- No se admiten jugadores pendientes, suspendidos, liberados o pertenecientes al rival.
- Solo puede existir una alineación por partido y equipo.
- Puede haber como máximo un capitán.
- Debe guardarse al menos un jugador para crear el borrador.
- Una alineación enviada no puede modificarse ni enviarse nuevamente.

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración es aditiva. No elimina jornadas, partidos, jugadores ni alineaciones existentes. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Confirma que exista una jornada publicada con un partido programado.
2. Confirma que ambos equipos tengan jugadores activos y aprobados.
3. Ingresa como representante y abre **Alineaciones**.
4. Comprueba que solamente aparezcan partidos de tu equipo.
5. Selecciona jugadores, marca titulares y suplentes, y designa un capitán.
6. Guarda el borrador y vuelve a editarlo.
7. Intenta seleccionar dos capitanes: la interfaz debe conservar solo uno.
8. Pulsa **Enviar y cerrar** y confirma la advertencia.
9. Verifica que desaparezcan los controles de edición.
10. Ingresa como árbitro asignado y comprueba que pueda consultar la alineación enviada.
11. Ingresa como administrador y comprueba que pueda administrar borradores de ambos equipos.
12. Revisa la bitácora para localizar `lineup.saved` y `lineup.submitted`.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module11
```

Todos los comandos deben finalizar sin errores antes de iniciar el Módulo 12.

## Problemas comunes

- **No aparece Alineaciones:** ejecuta nuevamente `setup.ps1` para sincronizar permisos.
- **No aparecen partidos:** la jornada debe estar publicada y el usuario debe ser administrador, representante de uno de los equipos o árbitro asignado.
- **No aparecen jugadores:** deben estar activos y aprobados en la plantilla de esa participación.
- **No puedo editar:** la alineación ya fue enviada o el partido dejó de estar programado/aplazado.
- **El árbitro no ve la alineación:** debe estar asignado al partido y la alineación debe haber sido enviada.
