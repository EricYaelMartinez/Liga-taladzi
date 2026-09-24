# Sistema de la Liga Taladzi

Proyecto base del sistema web multiliga para administrar ligas locales de fútbol.

## Tecnologías

- Laravel 13 y PHP 8.5
- Vue 3 y TypeScript
- Tailwind CSS 4
- PostgreSQL 18
- Redis 8
- Nginx
- Docker Compose

## Requisitos en Windows

1. Windows 10 u 11 de 64 bits.
2. Virtualización habilitada en el BIOS.
3. WSL 2.
4. Docker Desktop configurado para usar WSL 2.
5. Git, recomendado para control de versiones.

No es necesario instalar PHP, Composer, PostgreSQL, Redis o Node directamente en Windows.

## Instalación rápida

Abre PowerShell dentro de la carpeta del proyecto y ejecuta:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La primera ejecución descargará las imágenes y dependencias, por lo que puede tardar varios minutos.

Después abre:

```text
http://localhost:8080
```

Los indicadores Laravel, PostgreSQL y Redis deben aparecer como disponibles.

## Primer acceso

Después de instalar el Módulo 1, crea el primer administrador con:

```powershell
docker compose exec app php artisan liga:create-admin
```

El comando solicita el nombre, correo y contraseña de forma interactiva. Después
abre `http://localhost:8080/iniciar-sesion`. No se incluye ninguna contraseña
predeterminada en el proyecto.

## Comandos habituales

Iniciar el sistema:

```powershell
docker compose up -d
```

Detenerlo:

```powershell
docker compose down
```

Consultar servicios:

```powershell
docker compose ps
```

Ver registros:

```powershell
docker compose logs -f
```

Ejecutar pruebas:

```powershell
docker compose exec app php vendor/bin/phpunit
```

Comprobar tipos de Vue y TypeScript:

```powershell
docker compose exec frontend npm run type-check
```

Compilar la interfaz:

```powershell
docker compose exec frontend npm run build
```

La guía de aceptación del módulo se encuentra en `MODULO_1_VERIFICACION.md`.

## Módulo 2: multiliga

El sistema permite crear ligas independientes, asignar varios roles a un mismo
usuario, cambiar el contexto activo y personalizar nombre, logotipo y colores.
Todas las operaciones contextuales vuelven a validar en el servidor la liga y
el rol seleccionados. Consulta `MODULO_2_VERIFICACION.md` para la prueba completa.

## Datos locales

PostgreSQL y Redis utilizan volúmenes de Docker. `docker compose down` no elimina la información.

No ejecutes `docker compose down -v` si deseas conservar los datos locales.

## Solución de problemas

### La construcción de `app` termina con `exit code: 2`

La versión corregida usa el cliente Redis Predis y no compila la extensión
PECL de Redis. Tampoco intenta recompilar OPcache en PHP 8.5, pues no es
necesario en el entorno local. Para reconstruir la imagen limpia ejecuta:

```powershell
docker compose down
docker compose build app --no-cache --progress=plain
docker compose up -d
```

Si la construcción falla otra vez, copia las últimas 30 líneas anteriores a
`failed to solve`; ahí aparece la causa específica.

### PostgreSQL 18 termina con `db-1 exited (1)`

PostgreSQL 18 utiliza `/var/lib/postgresql` como punto de montaje. Este proyecto
ya utiliza la ubicación nueva. Si se creó un volumen con una versión anterior
del archivo `compose.yaml` y todavía no existen datos importantes, reinicia los
volúmenes locales una sola vez:

```powershell
docker compose down -v
.\scripts\setup.ps1
```

No uses `down -v` cuando el sistema ya contenga información real sin realizar
antes un respaldo.

### Aparece `Your requirements could not be resolved`

El proyecto base mantiene solo dependencias compatibles con Laravel 13. Se
retiraron Tinker, Pail y Collision porque no son necesarios para el sistema y
sus versiones anteriores pueden impedir la instalación con Laravel 13.

Después de actualizar los archivos ejecuta:

```powershell
docker compose down
docker compose up -d --build
```

### Falta `vendor/autoload.php`

Composer todavía estaba instalando dependencias cuando se intentó ejecutar
Artisan. El instalador actual espera hasta diez minutos a que el contenedor PHP
esté saludable antes de continuar. No ejecutes migraciones manualmente mientras
el servicio `app` indique `health: starting`.

### `bootstrap/cache directory must be present and writable`

El contenedor crea `bootstrap/cache` y las carpetas de `storage` antes de
ejecutar Composer. La carpeta también forma parte del proyecto mediante un
archivo `.gitignore`, por lo que estará disponible desde la primera ejecución.
Composer se vuelve a ejecutar de forma segura para reparar instalaciones que
hayan quedado incompletas.

### `Command "test" is not defined`

Las pruebas se ejecutan directamente con PHPUnit mediante
`php vendor/bin/phpunit`; no dependen del comando auxiliar `artisan test`.

### `Test directory tests/Unit not found`

La carpeta `tests/Unit` forma parte del proyecto aunque todavía no contenga
pruebas unitarias. PHPUnit puede ejecutar así todas las suites configuradas.

### `Unsupported cipher or incorrect key length`

`phpunit.xml` contiene una clave exclusiva para pruebas que decodifica a 32
bytes, como requiere el cifrado AES-256-CBC de Laravel. No se utiliza en el
entorno local ni debe copiarse a producción.

### `tempnam(): file created in the system's temporary directory`

PHP no podía escribir las vistas compiladas en `storage/framework/views`.
El arranque aplica permisos de desarrollo compatibles con los directorios
compartidos por Docker Desktop en Windows.

### El puerto 8080 está ocupado

Modifica en `compose.yaml`:

```yaml
ports:
  - "8081:80"
```

Después abre `http://localhost:8081`.

### Un servicio no inicia

Ejecuta:

```powershell
docker compose ps
docker compose logs app
docker compose logs db
docker compose logs frontend
```

### Reiniciar sin perder la base de datos

```powershell
docker compose down
docker compose up -d
```

## Seguridad

- `.env` no se incluye en Git.
- Las credenciales incluidas son únicamente para desarrollo local.
- Producción utilizará contraseñas diferentes y secretos administrados en el servidor.
- Los archivos privados se almacenarán fuera de `public`.
