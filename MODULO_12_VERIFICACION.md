# Módulo 12 — Cédulas arbitrales

## Objetivo

Registrar de forma segura los hechos de cada encuentro, obtener la conformidad individual de los representantes de ambos equipos y convertir el resultado en oficial únicamente después de la revisión del administrador de la liga.

## Alcance implementado

- Una cédula única por partido.
- Captura por el árbitro central asignado y edición administrativa controlada.
- Marcador, goles, autogoles, tarjetas, sustituciones, lesiones, incidentes e incomparecencia.
- Eventos limitados a la alineación enviada; si no existe alineación digital, se usa la plantilla activa aprobada.
- Titulares, capitán y suplentes de la alineación enviada visibles dentro de la cédula y en su PDF.
- Marcador calculado y validado contra goles y autogoles, excepto en incomparecencia.
- Borrador editable y bloqueo después del envío.
- Folio único generado al enviar.
- Conformidad o inconformidad independiente de los dos representantes, registrando usuario, fecha, hora y observaciones.
- Devolución automática cuando un representante manifiesta inconformidad.
- Revisión administrativa con aprobación o devolución para corrección.
- Resultado oficial y partido finalizado únicamente al aprobar.
- PDF descargable y bitácora de todas las operaciones.
- Interfaz adaptable a computadora y teléfono.

## Flujo

1. El árbitro central guarda el borrador.
2. El árbitro envía la cédula y esta queda bloqueada.
3. Los representantes local y visitante registran su decisión.
4. Una inconformidad devuelve la cédula a corrección.
5. Dos conformidades trasladan la cédula a revisión administrativa.
6. El administrador aprueba o devuelve.
7. La aprobación bloquea definitivamente la cédula y marca el partido como finalizado.

## Instalación

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración es aditiva. No elimina partidos, alineaciones ni resultados existentes. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Publica una jornada con horario, cancha y árbitro central.
2. Verifica que ambos equipos tengan representante activo.
3. Ingresa como árbitro central y abre **Cédulas**.
4. Comprueba que la cédula muestre automáticamente los titulares registrados, el capitán y los suplentes de ambos equipos.
5. Captura un gol y verifica que el selector identifique al jugador como titular o suplente y que el marcador calculado cambie.
6. Guarda el borrador y vuelve a editarlo.
7. Envía la cédula a conformidad.
8. Confirma que el árbitro ya no pueda editarla.
9. Ingresa como representante local y pulsa **Estoy conforme**.
10. Ingresa como representante visitante y registra su conformidad.
11. Ingresa como administrador, revisa y aprueba.
12. Comprueba que el estado sea **Aprobada**, descarga el PDF y revisa que incluya las alineaciones.
13. Repite con una inconformidad y comprueba que vuelva a **Requiere corrección**.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module12
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 12.
