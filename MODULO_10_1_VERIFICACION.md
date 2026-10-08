# Módulo 10.1 — Programación automática avanzada

## Objetivo

Configurar horarios estándar y capacidad diaria por cancha, generar una o todas las jornadas pendientes, programar automáticamente los encuentros y asignar árbitros en un paso posterior.

## Funcionalidades

- Horarios generales de la liga por día de la semana.
- Excepciones de horarios por cancha; cuando existen, sustituyen a los horarios generales de ese día.
- Máximo general de partidos por cancha y día.
- Máximo particular opcional para cada cancha.
- Generación de la siguiente jornada o de todas las jornadas restantes.
- Prevención de partidos repetidos dentro de la misma vuelta.
- Inversión de local y visitante en torneos de ida y vuelta.
- Asignación automática de fecha, hora y cancha respetando disponibilidad, bloqueos, márgenes y capacidad.
- Asignación arbitral independiente después de definir el horario.
- Cambio posterior de horario o campo con validación de equipos, campo y árbitros ya asignados.
- Historial y bitácora de todos los cambios.
- Presentación visual agrupada por jornada.

## Instalación

Desde PowerShell en `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración es aditiva y conserva jornadas y partidos existentes. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Ingresa como administrador y abre **Calendario**.
2. Selecciona **Horarios y capacidad**.
3. Registra horarios generales para el día en que se disputará la jornada.
4. Configura, si se requiere, horarios diferentes para una cancha.
5. Define el máximo general y una excepción para alguna cancha.
6. Pulsa **Generar jornadas** y elige **Siguiente jornada** con programación automática.
7. Confirma que solo se cree una jornada y que sus partidos tengan horario y cancha.
8. Genera nuevamente la siguiente jornada y comprueba que no se repitan enfrentamientos.
9. Selecciona **Todas las restantes** y confirma que se complete el rol.
10. En cada encuentro utiliza **Asignar árbitros**.
11. Utiliza **Cambiar horario/campo** e intenta provocar un cruce: debe rechazarse.
12. Intenta superar el máximo diario de una cancha: debe rechazarse.
13. Publica la jornada; debe requerir árbitro central en todos los encuentros.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module10.1
```

Todos los comandos deben finalizar sin errores. Si PHPUnit reporta un fallo, conserva la salida completa para diagnosticarlo antes de continuar al Módulo 11.
