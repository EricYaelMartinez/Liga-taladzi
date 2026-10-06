# Módulo 9 — Árbitros y disponibilidad

## Objetivo

Administrar el expediente del cuerpo arbitral y registrar los horarios en los que cada árbitro puede dirigir encuentros. La información queda preparada para que el Módulo 10 asigne árbitro central, asistentes y cuarto árbitro sin conflictos.

## Alcance implementado

- Expediente arbitral ligado a un usuario con acceso activo y rol **Árbitro** en la misma liga.
- Fotografía, correo y teléfono de contacto, categoría o nivel, fecha de ingreso, estado y notas privadas.
- Disponibilidad recurrente por día, horas y periodo opcional de vigencia.
- Evaluaciones u observaciones internas con fecha y autor.
- Vista propia para que el árbitro actualice su fotografía, contacto y disponibilidad.
- Servicio de validación reutilizable por el futuro calendario.
- Permisos, separación multiliga y bitácora de cambios.

Las asignaciones y el historial real de partidos no se simulan en este módulo. Se incorporarán con jornadas y partidos en el Módulo 10; las cédulas se conectarán en su módulo correspondiente.

## Reglas implementadas

- Solo un administrador puede crear el expediente y cambiar categoría, estado o notas privadas.
- El usuario elegido debe tener una membresía activa con rol Árbitro en la liga actual.
- Un usuario solo puede tener un expediente arbitral por liga.
- Un árbitro solo puede consultar su propio expediente y modificar sus propios datos de contacto y horarios.
- Las notas del expediente y observaciones administrativas no se envían al navegador del árbitro.
- Dos disponibilidades del mismo día no pueden superponerse si sus periodos de vigencia coinciden.
- La hora final debe ser posterior a la inicial.
- Un árbitro inactivo nunca se considera disponible.
- Todas las operaciones vuelven a comprobar la liga y el usuario en el servidor.
- Altas, cambios, horarios y observaciones generan entradas en la bitácora.

## Archivos principales

- `database/migrations/2026_10_06_000014_create_referees_and_availability_tables.php`
- `app/Domain/Referee/Models/Referee.php`
- `app/Domain/Referee/Models/RefereeAvailability.php`
- `app/Domain/Referee/Models/RefereeObservation.php`
- `app/Domain/Referee/Services/RefereeAvailabilityService.php`
- `app/Http/Controllers/League/RefereeController.php`
- `resources/js/pages/League/Referees/Index.vue`
- `tests/Feature/League/RefereeManagementTest.php`

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El instalador aplica la migración y vuelve a ejecutar `IdentitySeeder` para incorporar los permisos nuevos. No uses `docker compose down -v`, porque eliminaría los datos locales.

## Datos para una prueba manual

Usa datos ficticios; por ejemplo:

- Nombre del usuario: `Árbitro de Prueba`
- Rol de liga: `Árbitro`
- Categoría o nivel: `Categoría estatal`
- Disponibilidad: sábado, `08:00` a `18:00`
- Vigencia: sin fechas o un periodo futuro

## Prueba funcional

1. Ingresa como administrador de liga.
2. En **Miembros**, crea un usuario o asigna a uno existente el rol **Árbitro**.
3. Abre **Árbitros** y pulsa **Registrar árbitro**.
4. Selecciona al miembro, captura categoría, contacto y estado; la fotografía es opcional.
5. Agrega una disponibilidad, por ejemplo sábado de 08:00 a 18:00.
6. Intenta agregar otro horario de 17:00 a 19:00 con la misma vigencia: debe rechazarse por superposición.
7. Registra una observación interna.
8. Revisa **Bitácora** y confirma el alta, horario y observación.
9. Inicia sesión como el árbitro y selecciona su acceso de liga.
10. Confirma que aparezca **Árbitros**, que solo vea su expediente y pueda actualizar contacto, fotografía y disponibilidad.
11. Confirma que no aparezcan notas privadas ni observaciones administrativas.
12. Intenta abrir o modificar el expediente de otro árbitro mediante una URL: el servidor debe impedirlo.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module9
```

Resultado esperado: todas las pruebas deben terminar con `OK` y los tres comandos de frontend deben finalizar sin errores.

## Migración y conservación de datos

La migración es aditiva: crea `referees`, `referee_availabilities` y `referee_observations`; no modifica temporadas, equipos, jugadores, documentos ni campos existentes. En producción realiza un respaldo antes de revertirla, porque el método `down` elimina los expedientes y horarios creados por este módulo.

## Problemas comunes

- **No aparece nadie para seleccionar:** el usuario todavía no tiene una membresía activa con rol Árbitro en esa liga. Asígnala en **Miembros**.
- **El árbitro no ve el menú:** ejecuta nuevamente `IdentitySeeder` mediante `setup.ps1` para sincronizar `referees.view` con el rol.
- **La fotografía no aparece:** confirma que `php artisan storage:link --force` terminó correctamente; el instalador ya lo ejecuta.
- **Horario superpuesto:** corrige las horas o utiliza un periodo de vigencia que no se cruce con el existente.
