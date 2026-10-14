# Módulo 13 — Resultados y estadísticas oficiales

## Objetivo

Publicar los resultados oficiales de cada competencia y calcular automáticamente la tabla de posiciones, los goleadores y el resumen de tarjetas utilizando únicamente cédulas arbitrales aprobadas.

## Alcance implementado

- Selector de competencia con acceso al historial de temporadas anteriores.
- Resultados oficiales agrupados visualmente por jornada.
- Tabla de posiciones con partidos jugados, victorias, empates, derrotas, goles a favor, goles en contra, diferencia, puntos de juego limpio y puntos de competencia.
- Puntaje configurable según el reglamento asignado a la competencia.
- Desempates aplicados en el orden establecido en el reglamento: puntos, diferencia de goles, goles a favor, resultados entre equipos empatados y juego limpio.
- Tabla de goleadores; los autogoles no se acreditan a un jugador.
- Tabla informativa de amarillas, segundas amarillas, expulsiones y puntos de juego limpio.
- Resumen de partidos oficiales, goles, promedio por partido y equipos registrados.
- Consulta para administradores, árbitros, representantes y jugadores mediante el permiso `statistics.view`.
- Aislamiento por liga: no es posible consultar una competencia perteneciente a otra liga.
- Cálculo dinámico sin tablas estadísticas duplicadas; si una cédula aprobada cambia por un proceso administrativo futuro, el siguiente cálculo reflejará la fuente oficial.

## Reglas importantes

1. Una cédula en borrador, en conformidad, en corrección o en revisión no afecta resultados ni estadísticas.
2. Solamente el estado **Aprobada** convierte el marcador y sus eventos en información oficial.
3. El criterio de juego limpio favorece al equipo con menos puntos disciplinarios.
4. Los criterios **decisión administrativa** y **sorteo** no se resuelven automáticamente. Mientras no exista una resolución formal, los equipos que sigan empatados aparecen en orden alfabético provisional.
5. La tabla disciplinaria de este módulo es informativa; el cumplimiento y arrastre de suspensiones corresponderá al módulo disciplinario.

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

Este módulo no agrega ni elimina tablas. El proceso actualiza los permisos iniciales y conserva toda la información existente. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Ingresa como administrador de la liga.
2. Comprueba que el menú muestre **Resultados**.
3. Abre **Resultados** y selecciona una competencia.
4. Verifica que una cédula todavía no aprobada no aparezca en resultados ni modifique la tabla.
5. Completa el flujo de conformidad y aprobación de una cédula.
6. Regresa a **Resultados** y confirma que el marcador aparezca dentro de su jornada.
7. Comprueba los puntos, goles y diferencia de ambos equipos.
8. Verifica que los goles individuales aparezcan en **Goleadores**.
9. Verifica las tarjetas en **Amonestados y expulsados**.
10. Cambia a una competencia de una temporada anterior y confirma que su historial se conserve.
11. Ingresa como árbitro, representante o jugador y confirma que pueda consultar la información sin editarla.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module13
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 13.
