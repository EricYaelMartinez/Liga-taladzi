# Módulo 18 — Reportes y exportaciones

## Objetivo

Centralizar la generación de información oficial de la liga en documentos PDF listos para impresión y libros `.xlsx` compatibles con Microsoft Excel.

## Reportes disponibles

1. Equipos por división.
2. Plantillas de jugadores.
3. Calendario de partidos.
4. Resultados oficiales.
5. Tabla de posiciones.
6. Tabla de goleadores.
7. Tarjetas y expulsiones.
8. Sanciones.
9. Partidos por árbitro.
10. Cédulas oficiales.
11. Altas, bajas y transferencias.
12. Estadísticas de temporada.

## Filtros

- Temporada.
- Torneo.
- División.
- Competencia.
- Jornada.
- Equipo.
- Jugador.
- Árbitro.
- Rango de fechas.

Los filtros vacíos incluyen todos los registros autorizados dentro de la liga activa. Las posiciones, goleadores y tarjetas requieren seleccionar una competencia porque sus reglas y criterios pertenecen al reglamento de esa competencia.

## Reglas de seguridad

1. El centro de reportes requiere el permiso `exports.view`, asignado al administrador del sistema y al administrador de liga.
2. Árbitros, representantes y jugadores no pueden abrir ni descargar reportes administrativos.
3. Todas las consultas se limitan en el servidor a la liga activa.
4. Una competencia de otra liga devuelve acceso no encontrado y nunca genera información.
5. Resultados, posiciones, goleadores y tarjetas solo utilizan cédulas aprobadas.
6. Los reportes no se publican en el portal público.
7. Cada descarga genera un folio `REP-...` y una entrada `report.exported` en la bitácora con tipo, formato, filtros y cantidad de registros.
8. Las plantillas no exportan fecha de nacimiento, contactos de emergencia, documentos, fotografías ni datos de acceso.

## Formatos

- **PDF:** incorpora logotipo, colores de la liga, título, folio, fecha, filtros y total de registros.
- **Excel:** genera un libro `.xlsx` nativo, con encabezado resaltado, primera fila inmovilizada y autofiltro.

## Actualización segura

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

No se crean ni modifican tablas. El instalador actualiza los permisos mediante `IdentitySeeder` sin borrar usuarios, roles ni información deportiva. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Ingresa como administrador de liga.
2. Abre **Control > Reportes y exportaciones**.
3. Selecciona **Equipos por división** y descarga PDF y Excel.
4. Abre el `.xlsx` en Microsoft Excel y confirma encabezados, autofiltro y registros.
5. Selecciona temporada, torneo, división y competencia; verifica que las opciones dependientes se actualicen.
6. Genera posiciones, goleadores y tarjetas seleccionando obligatoriamente una competencia.
7. Verifica que resultados y cédulas solo incluyan documentos aprobados.
8. Filtra partidos por árbitro y por rango de fechas.
9. Genera sanciones y movimientos de jugadores.
10. Abre **Control > Bitácora** y confirma la acción `report.exported`.
11. Ingresa como árbitro, representante o jugador y comprueba que **Reportes y exportaciones** no aparezca y la URL directa responda con acceso denegado.
12. Cambia de liga y confirma que no aparezcan registros de la anterior.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run validate:module18
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 18.
