# Módulo 8 — Campos y disponibilidad

## Objetivo

Administrar instalaciones deportivas con una o varias canchas programables, sus horarios recurrentes y los periodos en los que no pueden utilizarse.

## Reglas implementadas

- Cada instalación pertenece exclusivamente a una liga.
- Una instalación puede contener varias canchas independientes.
- El nombre de la instalación no puede repetirse dentro de la liga, sin distinguir mayúsculas.
- El nombre de la cancha no puede repetirse dentro de la misma instalación.
- Cada cancha registra superficie, iluminación, capacidad opcional, notas y estado.
- Una cancha puede recomendarse para una o varias divisiones; la recomendación no impide usarla con otra división.
- La disponibilidad se define mediante día de la semana, hora inicial, hora final y vigencia opcional.
- Dos disponibilidades del mismo día no pueden superponerse cuando sus periodos de vigencia coinciden.
- Los bloqueos por mantenimiento, inactividad o eventos externos no pueden superponerse.
- Una instalación o cancha inactiva no está disponible para programación.
- La verificación considera el margen opcional entre partidos configurado en los parámetros de la liga.
- La disponibilidad se valida en el servidor mediante un servicio reutilizable por el futuro módulo de calendario.
- Todos los cambios generan registros en la bitácora.
- Solo el administrador de liga puede modificar instalaciones, canchas, horarios y bloqueos.

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

No ejecutes `docker compose down -v`, porque eliminaría los datos locales.

## Prueba funcional

1. Ingresa como administrador de liga.
2. Confirma que aparezca **Campos** en la navegación.
3. Registra una instalación con nombre, dirección y estado activo.
4. Agrega dos canchas a la misma instalación.
5. Define superficie, iluminación y divisiones recomendadas.
6. Agrega una disponibilidad semanal, por ejemplo sábado de 08:00 a 18:00.
7. Intenta agregar otro horario superpuesto: el sistema debe rechazarlo.
8. Agrega el mismo horario con un periodo de vigencia distinto y no superpuesto: debe aceptarse.
9. Registra un mantenimiento con fecha y hora inicial/final.
10. Intenta registrar un bloqueo superpuesto: debe rechazarse.
11. Cambia el estado de una cancha a mantenimiento o inactiva.
12. Revisa **Bitácora** y confirma los cambios realizados.
13. Ingresa con un jugador o representante: la administración de campos no debe estar disponible.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module8
```

Resultado esperado de PHPUnit:

```text
OK (69 tests, ...)
```

## Migración y conservación de datos

La migración es aditiva: crea tablas nuevas y no modifica temporadas, equipos, jugadores ni credenciales existentes. En producción realiza un respaldo antes de revertirla, porque el `down` elimina las instalaciones y disponibilidades registradas en este módulo.
