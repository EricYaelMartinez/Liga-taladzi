# Verificación del Módulo 5

Este módulo administra los equipos permanentes, su propietario o representante, el historial de nombres y las participaciones por temporada.

## 1. Instalar la actualización

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración agrega las tablas del módulo sin borrar equipos, usuarios, competencias ni configuraciones existentes.

## 2. Preparar los datos

1. Inicia sesión y selecciona la liga como **Administrador de liga**.
2. En **Miembros**, crea o asigna un usuario con el rol **Representante de equipo**.
3. Confirma que exista una competencia en **Competencias**.
4. Abre **Equipos**. En pantallas menores a 1536 píxeles, la opción se encuentra en el menú `☰`.

## 3. Registrar y aprobar un equipo

1. Abre **Registrar equipo**.
2. Captura nombre, nombre corto, colores, contacto privado y competencia.
3. El escudo y la fotografía del equipo son opcionales.
4. Selecciona al representante y carga obligatoriamente su fotografía e imagen del INE.
5. Registra el motivo y guarda.
6. Comprueba que el equipo y su participación aparezcan como **Pendiente**.
7. Presiona **Aprobar**, captura un motivo y verifica que ambos cambien a **Activo**.

## 4. Probar restricciones

- Intenta registrar nuevamente el mismo nombre, aunque cambies mayúsculas y minúsculas: debe rechazarse.
- Intenta asignar el mismo representante a otro equipo: debe rechazarse.
- Un equipo no puede tener dos representantes activos.
- Un representante no puede administrar dos equipos.
- Un equipo solo puede tener una participación en la misma temporada.
- La fotografía e INE solo pueden descargarse como administrador autorizado.

## 5. Probar al representante

1. Cambia de acceso e ingresa como **Representante de equipo**.
2. Abre **Equipos** y confirma que únicamente aparezca su equipo.
3. Presiona **Solicitar cambio**, modifica el nombre o contacto, registra la justificación y envía.
4. El equipo no debe cambiar todavía.
5. Regresa como administrador y abre **Solicitudes**.
6. Aprueba la solicitud y confirma que el cambio se aplique.
7. Si cambiaste el nombre, despliega **Historial de nombres** para comprobar que se conservaron ambos.

## 6. Suspensión y reactivación

1. Como administrador, presiona **Suspender** en una participación activa y captura el motivo.
2. Comprueba que el equipo quede suspendido.
3. Presiona **Reactivar** y captura el motivo.
4. Confirma que vuelva a estar activo.

La cancelación automática de partidos futuros por suspensión se conectará cuando exista el módulo de jornadas y programación. El control de pagos se incorporará en su módulo financiero.

## 7. Pruebas técnicas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module5
```

## Resultado esperado

- Deben aprobarse 47 pruebas.
- No deben aparecer errores de migración ni de permisos.
- El administrador puede registrar, aprobar, rechazar, suspender y reactivar.
- El representante únicamente consulta y propone cambios sobre su equipo.
- Los cambios propuestos no se aplican antes de la aprobación.
- Los cambios de nombre conservan el historial.
- Los datos y documentos de una liga no pueden consultarse desde otra.
- Todas las operaciones importantes aparecen en **Bitácora**.

## Errores frecuentes

### No aparece Equipos

Ejecuta el instalador nuevamente para cargar los permisos, selecciona una liga y actualiza con `Ctrl + F5`.

### No hay representantes disponibles

Desde **Miembros**, crea o asigna un usuario con el rol **Representante de equipo**. Los representantes ya asignados no vuelven a aparecer porque solo pueden administrar un equipo.

### No permite registrar el equipo

Comprueba que exista una competencia no archivada, que el nombre no esté repetido y que hayas cargado la fotografía e INE del representante.
