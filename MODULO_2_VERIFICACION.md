# Verificación del Módulo 2

Este módulo incorpora múltiples ligas independientes, roles por liga, selector de acceso, personalización, aislamiento de datos y bitácora.

## 1. Instalar la actualización

Desde PowerShell, dentro del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El proceso conserva los usuarios existentes y aplica únicamente las migraciones nuevas.

## 2. Crear la primera liga

1. Ingresa como administrador del sistema.
2. Abre **Ligas**.
3. Selecciona **Crear liga**.
4. Captura `Liga Municipal de Futbol Taladzi`, colores y administrador inicial.
5. Si el administrador no aparece, crea primero su cuenta en **Usuarios**.

## 3. Probar el aislamiento

1. Desde **Cambiar acceso**, selecciona la liga y el rol Administrador de liga.
2. En **Miembros**, asigna a otro usuario uno o más roles.
3. Inicia sesión con ese usuario y comprueba que solo aparezcan las ligas y roles asignados.
4. Intenta abrir directamente `/administracion/ligas`; un administrador de liga sin privilegios globales debe recibir HTTP 403.
5. Comprueba en **Bitácora** que las asignaciones, cambios de estado y personalización quedaron registradas.

## 4. Pruebas técnicas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module2
```

## Resultado esperado

- Deben aprobarse 25 pruebas.
- No se puede seleccionar una liga o rol sin asignación.
- Manipular la sesión o una URL no permite consultar otra liga.
- La liga conserva al menos un administrador activo.
- El logotipo admite JPG, PNG o WebP de hasta 2 MB.
- Las eliminaciones de liga son lógicas y conservan el historial.
