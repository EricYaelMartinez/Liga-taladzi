# Módulo 6 — Jugadores y plantillas

## Objetivo

Administrar el expediente permanente del jugador y su pertenencia histórica a las plantillas de cada temporada, sin exponer información privada.

## Reglas implementadas

- El jugador puede existir sin cuenta de acceso y vincularse posteriormente.
- No se solicita CURP ni se genera QR en esta etapa.
- El jugador vinculado puede mostrar o imprimir una credencial digital sin QR.
- El administrador genera credenciales físicas de 9 × 6 cm agrupadas por equipo.
- Cada PDF incluye únicamente jugadores con expediente activo y alta aprobada/activa.
- La liga configura cuatro logotipos y los colores aplicados a sus credenciales.
- La descarga genera un PDF independiente por equipo, con hasta ocho credenciales por hoja A4.
- La imagen PHP incluye GD con soporte PNG, JPEG y WebP para incrustar fotografías y logotipos.
- La fotografía es obligatoria, privada y visible únicamente para administración.
- La detección de duplicados compara nombre, fecha de nacimiento y fotografía.
- Los menores requieren nombre y teléfono del tutor, además de carta responsiva.
- La carta se conserva, como mínimo, hasta el final del torneo correspondiente.
- Un jugador solo puede tener un alta pendiente, activa o suspendida por temporada.
- El dorsal es único dentro de la plantilla activa del equipo.
- Se validan edad, género y tamaño máximo configurados en la competencia.
- Una baja libera al jugador inmediatamente para solicitar alta con otro equipo.
- Los registros anteriores no se eliminan y forman el historial deportivo.

## Instalación

Desde PowerShell, en `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

No utilices `docker compose down -v`, porque elimina los datos almacenados.

## Resultado automatizado esperado

```text
OK (59 tests, ...)
```

## Prueba funcional

1. Ingresa como administrador y confirma que aparezca **Jugadores**.
2. Verifica que exista un equipo con participación activa.
3. Registra un adulto sin cuenta y comprueba que quede pendiente.
4. Repite nombre, nacimiento y fotografía: debe rechazarse.
5. Registra un menor: tutor y carta responsiva deben ser obligatorios.
6. Aprueba al jugador y verifica que pase a activo.
7. Intenta asignar el mismo dorsal a otro jugador: debe rechazarse.
8. Como representante, comprueba que solo vea su plantilla y no los datos privados.
9. Registra la baja y comprueba que el jugador aparezca en **Reincorporar**.
10. Vincula una cuenta con rol Jugador y verifica que solo vea su perfil.
11. En **Identidad**, carga los cuatro logotipos para credenciales y guarda los cambios.
12. En **Jugadores > Credenciales**, confirma que los equipos activos aparezcan por separado.
13. Abre la vista previa y revisa que solo contenga jugadores activos y aprobados.
14. Descarga el PDF de un equipo y comprueba el tamaño de 9 × 6 cm al imprimir al 100 %, sin ajustar la escala.

Si una instalación anterior muestra `The PHP GD extension is required`, vuelve a ejecutar `scripts/setup.ps1` para reconstruir la imagen PHP con soporte gráfico.

## Privacidad

Los teléfonos, contactos de emergencia, datos del tutor y documentos no aparecen en las credenciales ni se muestran al representante o a visitantes. Las fotografías se incrustan en el PDF generado para el administrador y las descargas se validan nuevamente en el servidor.
