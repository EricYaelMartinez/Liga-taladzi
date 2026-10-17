# Módulo 19.1 — Identidad visual definitiva de ProLiga

## Identidad aprobada

- Nombre: **ProLiga**.
- Lema: **Administra tu liga en un solo espacio**.
- Azul marino: `#1B2A4A`.
- Verde: `#2E7D32`.
- Dorado: `#C5A059`.
- Logotipo global: `public/images/brand/proliga-logo.png`.
- Favicon: `public/favicon.ico`.

## Alcance

La marca ProLiga se presenta en el acceso, el encabezado privado, el directorio
público, el portal público, los metadatos del navegador y los pies de los
reportes. El nombre de la liga activa continúa visible como contexto de trabajo.

Los logotipos y colores configurables de cada liga no fueron eliminados: siguen
aplicándose dentro del contexto privado y en el portal público de esa liga. Las
credenciales de jugadores mantienen igualmente la identidad configurada por
liga.

Los identificadores técnicos existentes (`liga-taladzi`, nombres de contenedores
y base de datos) se conservan para evitar romper instalaciones, volúmenes o
respaldos. No son textos visibles de la marca.

## Actualización segura

Esta mejora no agrega ni modifica tablas. No requiere migraciones y no elimina
información. En Windows PowerShell, desde el directorio del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

El instalador actualiza `APP_NAME` a `ProLiga`, conserva los datos persistentes,
reconstruye los recursos y ejecuta las comprobaciones del proyecto.

## Comprobaciones automáticas

```powershell
docker compose exec frontend npm run validate:branding
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec app php vendor/bin/phpunit
```

## Comprobación manual

1. Abre `http://localhost:8080` y fuerza la recarga con `Ctrl+F5`.
2. Comprueba el logotipo, nombre y lema en el directorio público.
3. Comprueba el favicon y el título de la pestaña.
4. Inicia sesión y verifica ProLiga en el encabezado y el nombre de la liga
   activa como contexto secundario.
5. Entra al portal público de una liga y confirma que conserva su logotipo y sus
   colores particulares.
6. Revisa la vista desde un teléfono o mediante el modo adaptable del navegador.

## Criterio de aprobación

La sección queda aprobada cuando la marca global se muestra correctamente en
escritorio y móvil, el portal de cada liga conserva su identidad y las cuatro
comprobaciones automáticas finalizan sin errores.
