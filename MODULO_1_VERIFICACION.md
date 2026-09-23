# Verificación del Módulo 1

Este módulo incorpora autenticación, recuperación y cambio de contraseña, usuarios, roles globales, permisos y registro de accesos.

## 1. Actualizar el entorno

Desde PowerShell, dentro de la carpeta del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El instalador conserva los datos, aplica las migraciones pendientes, registra los roles y permisos iniciales y ejecuta las pruebas.

## 2. Crear el primer administrador

Solo la primera vez:

```powershell
docker compose exec app php artisan liga:create-admin
```

El comando solicitará nombre, correo y una contraseña segura sin guardarla en archivos.

En el entorno local, los mensajes de recuperación de contraseña se escriben en
`storage/logs/laravel.log`. En producción se configurará el servicio SMTP de la
liga mediante variables de entorno.

## 3. Probar en el navegador

1. Abre `http://localhost:8080`.
2. Selecciona **Iniciar sesión**.
3. Ingresa con el administrador creado.
4. Abre **Usuarios** desde el menú.
5. Crea una cuenta con contraseña temporal.
6. Comprueba que la cuenta nueva debe cambiar su contraseña al ingresar.

## 4. Comprobaciones técnicas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
```

Los tres comandos deben finalizar sin errores.

## Resultado esperado

- Las cuentas suspendidas o inactivas no pueden iniciar sesión.
- Las rutas administrativas responden con 403 cuando faltan permisos.
- El administrador no puede suspender, degradar ni eliminar su propia cuenta.
- La eliminación de usuarios es lógica y conserva el historial.
- Cada intento de inicio de sesión queda registrado con fecha, resultado e IP.
