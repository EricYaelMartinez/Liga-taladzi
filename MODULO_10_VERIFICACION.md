# Módulo 10 — Jornadas y programación

## Objetivo

Crear jornadas y partidos, generar emparejamientos, asignar horarios, canchas y árbitros, publicar el calendario y controlar aplazamientos, suspensiones y cancelaciones sin producir conflictos de recursos.

## Alcance implementado

- Generación de todos-contra-todos a una o dos vueltas.
- Soporte para cantidad par o impar de equipos; los descansos no crean partidos ficticios.
- Jornadas y partidos manuales para eliminación, grupos o configuraciones especiales.
- Programación de fecha, hora, cancha y árbitro central.
- Asistentes y cuarto árbitro opcionales únicamente en fase eliminatoria.
- Publicación completa por jornada.
- Estados: borrador, programado, en curso, suspendido, aplazado y cancelado. El estado finalizado se conectará al módulo de resultados.
- Historial de programación, reprogramaciones y cambios de estado con motivo y responsable.
- Consulta del calendario publicado por administradores, árbitros, representantes y jugadores.

Las alineaciones pertenecen al Módulo 11; las cédulas al 12; los marcadores, finalización y estadísticas al 13.

## Reglas implementadas

- Los equipos de un partido deben ser participaciones activas de la misma competencia.
- Un equipo no puede aparecer dos veces en una jornada manual.
- La duración se obtiene de la copia histórica del reglamento de la competencia.
- La cancha debe estar activa, disponible y sin bloqueos durante el partido y el margen configurado.
- El árbitro debe estar activo y disponible durante el encuentro y el margen.
- Ningún equipo, cancha o árbitro puede tener otro partido en un horario incompatible.
- El margen opcional configurado por la liga se aplica a los conflictos.
- En fase regular y de grupos solo se asigna árbitro central.
- En fase eliminatoria pueden asignarse dos asistentes y cuarto árbitro.
- Un árbitro no puede ocupar dos funciones en el mismo partido.
- Después de iniciar y suspender un encuentro, no se permite sustituir a los árbitros asignados.
- Una jornada solo se publica si todos sus partidos tienen fecha, cancha y árbitro central.
- Los borradores no se muestran a roles de consulta.
- Reprogramaciones, aplazamientos, suspensiones y cancelaciones requieren una causa.
- Al suspender una participación, sus partidos futuros se cancelan; el marcador administrativo se aplicará en el módulo de resultados.
- Todas las operaciones validan la liga activa en el servidor y se registran en la bitácora.

## Instalación

Desde PowerShell en `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

No ejecutes `docker compose down -v`; ese comando elimina los datos locales.

## Preparación para la prueba

Antes de crear el calendario confirma que existan:

1. Una competencia con reglamento publicado y equipos aprobados.
2. Al menos una cancha activa con disponibilidad semanal.
3. Al menos un árbitro activo con disponibilidad semanal.
4. Parámetros de duración y margen guardados en la liga.

## Prueba funcional

1. Ingresa como administrador y abre **Calendario**.
2. Selecciona **Generar todos contra todos**.
3. Elige una competencia, fecha inicial y separación entre jornadas.
4. Confirma que se creen jornadas en borrador.
5. Si hay número impar de equipos, comprueba que cada jornada tenga un descanso sin partido ficticio.
6. Abre un partido y asigna fecha, hora, cancha y árbitro central.
7. Intenta programar otro partido a la misma hora usando la misma cancha: debe rechazarse.
8. Repite usando el mismo árbitro: debe rechazarse.
9. Repite con uno de los mismos equipos: debe rechazarse.
10. Intenta asignar asistente en fase regular: debe rechazarse.
11. Crea una jornada eliminatoria y confirma que los asistentes sean opcionales.
12. Intenta publicar una jornada con un partido incompleto: debe rechazarse.
13. Completa todos los partidos y publica la jornada.
14. Inicia sesión como árbitro o representante: solo debe mostrarse el calendario publicado y no los controles administrativos.
15. Aplaza o cancela un partido indicando el motivo y revisa la **Bitácora**.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module10
```

Resultado esperado: PHPUnit debe terminar con `OK`; las validaciones estructurales, el tipado y la compilación deben finalizar sin errores.

## Migración y conservación de datos

La migración agrega `matchdays`, `matches`, `match_referee_assignments` y `match_schedule_changes`. No altera los datos existentes de equipos, jugadores, campos o árbitros. En producción realiza un respaldo antes de revertirla, porque su método `down` elimina el calendario creado.

## Problemas comunes

- **No aparecen equipos:** deben tener participación activa en la competencia seleccionada.
- **La cancha no está disponible:** agrega un horario recurrente que cubra el partido y el margen, o elimina un bloqueo que corresponda.
- **El árbitro no está disponible:** configura su disponibilidad para el día y horario completos.
- **No se puede publicar:** revisa que todos los partidos tengan fecha, cancha y árbitro central.
- **No aparecen asistentes:** la jornada debe estar marcada como fase eliminatoria.
- **El menú Calendario no aparece:** vuelve a ejecutar `setup.ps1` para sincronizar los permisos nuevos.
