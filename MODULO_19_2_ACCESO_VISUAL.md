# Módulo 19.2 — Acceso visual de ProLiga

## Resultado

El acceso de ProLiga adopta la composición de la referencia aprobada:

- panel deportivo con imagen y capa de contraste;
- logotipo y colores institucionales;
- resumen visual de las funciones principales;
- formulario claro para correo y contraseña;
- control para mostrar u ocultar la contraseña;
- opción para recordar la sesión;
- enlace de recuperación de contraseña;
- enlace de regreso al portal público;
- distribución adaptable para computadoras, tabletas y teléfonos.

En pantallas pequeñas se oculta la imagen lateral para priorizar el formulario,
reducir el consumo visual y conservar controles táctiles amplios.

## Google y Apple

Los botones se muestran deshabilitados y acompañados por la leyenda
“Próximamente”. No ejecutan solicitudes ni simulan una autenticación. Para
habilitarlos en una versión futura se requerirá configurar OAuth, credenciales
por ambiente, rutas de retorno y vinculación segura de cuentas.

No se añadieron accesos demo, selección ficticia de roles ni registro público,
porque esas funciones no existen actualmente. El rol continúa resolviéndose
mediante los permisos reales del usuario después de iniciar sesión.

## Seguridad y datos

El cambio es visual. Mantiene la autenticación, limitación de intentos, registro
de eventos, usuarios activos y sesiones existentes. No modifica tablas ni
requiere migraciones; no existe riesgo de pérdida de información.

## Instalación

En PowerShell, desde el proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

## Pruebas automáticas

```powershell
docker compose exec frontend npm run validate:login-design
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec app php vendor/bin/phpunit --filter=AuthenticationTest
```

## Prueba manual

1. Abre `http://localhost:8080/iniciar-sesion` y presiona `Ctrl+F5`.
2. Comprueba que la imagen deportiva solo se muestra en pantallas amplias.
3. Verifica el logotipo, los colores y la legibilidad de los textos.
4. Prueba mostrar y ocultar la contraseña.
5. Comprueba “Recordar mi sesión” y “¿Olvidaste tu contraseña?”.
6. Confirma que Google y Apple permanecen deshabilitados.
7. Inicia sesión con una cuenta válida y prueba también unas credenciales
   incorrectas para verificar los mensajes de validación.
8. Revisa el formulario con el modo adaptable del navegador.
