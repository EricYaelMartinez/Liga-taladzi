# Módulo 17 — Portal público

## Objetivo

Permitir que cualquier persona consulte información deportiva oficial sin iniciar sesión, conservando separados los datos públicos y los expedientes privados de la liga.

## Alcance implementado

- Directorio público de ligas activas en la página principal.
- Identidad visual con logotipo y colores configurados por cada liga.
- Portal individual mediante una dirección estable basada en el identificador público de la liga.
- Filtros por temporada, división y competencia.
- Calendario agrupado visualmente por jornada.
- Resultados provenientes únicamente de cédulas aprobadas.
- Tabla de posiciones, resumen, goleadores y tarjetas oficiales.
- Suspensiones y descalificaciones vigentes con información deportiva limitada.
- Diseño responsivo para computadora y teléfono móvil.
- Acceso de regreso al inicio de sesión sin mezclar el portal con los módulos privados.

## Reglas de publicación y privacidad

1. Solo aparecen ligas activas.
2. Solo aparecen temporadas y competencias activas o finalizadas.
3. Las jornadas en borrador no se publican.
4. Los marcadores y estadísticas solo se calculan con cédulas aprobadas.
5. Un marcador pendiente de aprobación no se muestra aunque el partido ya haya terminado.
6. Las sanciones públicas se limitan a suspensiones y descalificaciones vigentes.
7. De una sanción solo se publica el sujeto, equipo, tipo y partidos pendientes; no se publican motivo, fundamento, multa, resolución ni pruebas.
8. No se publican teléfonos, correos, contactos de emergencia, fotografías privadas, documentos ni datos de acceso.
9. Las rutas administrativas continúan protegidas por autenticación, contexto de liga y permisos del servidor.
10. El campo de notas mostrado en el calendario es exclusivamente `public_notes`, definido para publicación.

## Actualización segura

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

Este módulo no crea ni modifica tablas. No borra ni transforma información de ligas, equipos, jugadores, partidos, cédulas, sanciones o pagos. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Sin iniciar sesión, abre `http://localhost:8080/`.
2. Confirma que aparezcan únicamente las ligas activas.
3. Selecciona una liga y cambia los filtros de temporada, división y competencia.
4. En **Calendario**, verifica que los partidos estén agrupados por jornada y que no aparezcan jornadas en borrador.
5. Comprueba fecha, hora, campo y sede de un encuentro publicado.
6. En **Resultados**, verifica que solo aparezcan cédulas aprobadas.
7. Revisa **Posiciones**, **Goleadores** y **Disciplina**.
8. Confirma que una suspensión muestre únicamente la información deportiva limitada.
9. Repite la consulta desde un teléfono o reduciendo el ancho del navegador.
10. Pulsa **Acceso privado**, inicia sesión y comprueba que los módulos internos continúen funcionando.
11. Cierra sesión y prueba directamente una dirección `/liga/...`; debe pedir autenticación.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module17
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 17.
