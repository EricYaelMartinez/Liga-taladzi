# Módulo 19.3 — Directorio público visual de ProLiga

## Resultado

El directorio público adopta la composición deportiva aprobada y reutiliza la
imagen optimizada del inicio de sesión. Incluye:

- encabezado institucional de ProLiga;
- acceso visible al inicio de sesión;
- portada con imagen, degradados y alto contraste;
- búsqueda por liga, competencia, división, categoría o temporada;
- filtros varonil, femenil y libre/mixta;
- cantidad de resultados visibles;
- tarjetas responsivas con identidad independiente por liga;
- resumen de la competencia pública vigente;
- acceso al calendario, resultados y estadísticas de cada liga.

## Logotipos de liga

Cada tarjeta muestra el archivo configurado en **Configuración > Identidad
visual** de la liga. El servidor genera su URL pública mediante el disco de
almacenamiento. Si todavía no existe un logotipo, se presenta temporalmente la
inicial de la liga con sus colores para que el directorio nunca quede roto.

Después de agregar o reemplazar logotipos debe existir el enlace de Laravel:

```powershell
docker compose exec app php artisan storage:link --force
```

El instalador `setup.ps1` ya ejecuta esa instrucción.

## Datos públicos y seguridad

Solo aparecen ligas activas. El resumen utiliza competencias y temporadas
activas o finalizadas que ya son consultables en el portal público. No se
serializan contactos, documentos, expedientes, motivos disciplinarios ni datos
financieros.

No se modifica la base de datos y no se requiere migración.

## Instalación

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

## Pruebas automáticas

```powershell
docker compose exec frontend npm run validate:public-design
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec app php vendor/bin/phpunit --filter=PublicPortalTest
```

## Prueba manual

1. Abre `http://localhost:8080` y presiona `Ctrl+F5`.
2. Comprueba el fondo deportivo, marca ProLiga y botón de acceso.
3. Verifica que cada liga con logotipo configurado lo muestre en su tarjeta.
4. Busca una liga por nombre, división y categoría.
5. Prueba los filtros Varonil, Femenil y Libre.
6. Abre una liga y confirma su calendario y estadísticas públicas.
7. Revisa el diseño en escritorio y en modo adaptable para teléfono.
