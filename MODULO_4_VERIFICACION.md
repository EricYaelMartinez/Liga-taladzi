# Verificación del Módulo 4

Este módulo administra temporadas, torneos, divisiones, categorías, competencias y reglamentos versionados de cada liga.

## 1. Instalar la actualización

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración agrega las tablas deportivas sin modificar usuarios, ligas, accesos ni parámetros existentes.

## 2. Abrir el módulo

1. Inicia sesión.
2. Presiona **Cambiar acceso**.
3. Selecciona la liga y el rol **Administrador de liga**.
4. Abre **Competencias**.

Si el menú no aparece, ejecuta el instalador nuevamente para cargar los permisos y actualiza con `Ctrl + F5`.

## 3. Prueba funcional guiada

Sigue el orden de la pestaña **Resumen**:

1. En **Divisiones y categorías**, registra `Primera fuerza`, `Segunda fuerza` y `Tercera fuerza`. Registra la categoría `Libre`.
2. En **Temporadas y torneos**, crea `Temporada 2027` y dentro de ella `Torneo de Liga`.
3. En **Reglamentos**, crea `Reglamento general`. Verifica los valores iniciales aprobados de juego limpio: amarilla `1`, doble amarilla `2` y roja directa `3`.
4. Ordena los criterios con las flechas y guarda el borrador.
5. Edita el borrador para comprobar que aún admite cambios.
6. Publica el reglamento. La interfaz debe indicar que su contenido quedó protegido.
7. En **Competencias**, crea `Primera fuerza · Libre`, seleccionando el torneo, la división, la categoría, el formato y el reglamento publicado.
8. Regresa a **Reglamentos**: su estado debe ser **En uso** y no debe permitir edición.
9. Avanza una temporada por los estados permitidos: Planeación, Inscripciones, Activa, Finalizada y Archivada.
10. Abre **Bitácora** y verifica los eventos del módulo.

La competencia permite números pares o impares de equipos. La generación del calendario se incorporará en el módulo de jornadas y programación.

## 4. Pruebas técnicas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module4
```

## Resultado esperado

- Deben aprobarse 38 pruebas.
- La misma liga no admite nombres duplicados de temporada, división o categoría.
- Las fechas finales no pueden ser anteriores a las iniciales.
- Los límites máximos no pueden ser menores que los mínimos.
- Solo un reglamento publicado puede asignarse a una competencia.
- Un reglamento publicado o en uso no puede modificarse; debe crearse una nueva versión.
- La combinación torneo, división y categoría no puede repetirse.
- Los registros de una liga no pueden consultarse ni utilizarse desde otra liga.
- Un jugador no puede abrir ni modificar el módulo.
- Las altas y cambios de estado quedan en la bitácora.

## Errores frecuentes

### No aparece Competencias

Confirma que estás dentro de una liga como **Administrador de liga**. Después ejecuta:

```powershell
docker compose exec app php artisan db:seed --class=IdentitySeeder --force
docker compose exec app php artisan optimize:clear
docker compose restart app nginx frontend
```

### No permite crear un reglamento

Abre **Parámetros**, guarda la duración del partido y vuelve a intentarlo. El reglamento necesita copiar esos valores para conservar el historial.

### No permite seleccionar un reglamento

Primero abre **Reglamentos** y publica el borrador. Las competencias no pueden utilizar reglas incompletas.
