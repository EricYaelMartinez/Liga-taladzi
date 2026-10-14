# Módulo 14 — Sanciones, suspensiones y apelaciones

## Objetivo

Administrar el proceso disciplinario completo de la liga: generar sanciones a partir de cédulas aprobadas, registrar resoluciones manuales, impedir la participación de jugadores suspendidos, controlar el cumplimiento por partidos oficiales y permitir apelaciones dentro del plazo configurado.

## Alcance implementado

- Configuración disciplinaria independiente por liga.
- Valores iniciales visibles y editables: acumulación de 5 amarillas, 1 partido por acumulación, 1 por segunda amarilla y 1 por roja directa.
- Activación o desactivación de la generación automática sin eliminar sanciones existentes.
- Sanciones automáticas únicamente desde eventos de cédulas aprobadas.
- Sanciones manuales para jugadores, equipos e integrantes del cuerpo técnico.
- Tipos: suspensión, descuento de puntos, multa, amonestación administrativa y descalificación.
- Motivo, fundamento reglamentario, temporada, competencia y partido relacionado.
- Cumplimiento automático de suspensiones con partidos oficiales posteriores del equipo.
- Bloqueo del jugador suspendido al guardar o enviar una alineación.
- Arrastre de suspensiones pendientes aunque el jugador cambie de equipo, división, torneo o temporada.
- Acumulación de amarillas limitada a una sola temporada.
- Conservación exclusiva de suspensiones no cumplidas; las cumplidas quedan como historial.
- Descuentos de puntos aplicados automáticamente a la tabla oficial.
- Apelación por el representante activo del equipo dentro del plazo general configurado; valor inicial: 2 horas.
- Evidencia privada opcional en PDF o imagen, máximo 5 MB.
- Resolución administrativa para confirmar, reducir o revocar una sanción.
- Historial de partidos cumplidos, apelación, resolución, usuario y fechas.
- Registro de cambios relevantes en la bitácora.
- Aislamiento de información entre ligas.

## Reglas importantes

1. Una tarjeta en una cédula no aprobada no genera sanciones.
2. Una roja directa prevalece sobre una segunda amarilla registrada para el mismo jugador en la misma cédula, evitando una doble sanción automática por la expulsión.
3. Una sanción automática utiliza una clave única; sincronizar varias veces no crea duplicados.
4. El partido que originó la suspensión no cuenta como partido cumplido.
5. Una suspensión termina cuando se aprueba la cantidad requerida de partidos oficiales posteriores del equipo al que pertenezca el jugador.
6. El jugador no aparece como elegible y el servidor rechaza cualquier alineación que lo incluya mientras exista una suspensión vigente.
7. Las amarillas anteriores no se trasladan a una nueva temporada; una suspensión ya generada y pendiente sí se traslada.
8. El plazo de apelación se calcula desde la emisión de la sanción usando el parámetro general de la liga.
9. Solo el administrador de la liga puede configurar, sancionar y resolver. El representante puede consultar y apelar sanciones de su equipo. El jugador puede consultar sus propias sanciones.

## Actualización segura

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración crea tablas nuevas y no modifica ni elimina cédulas, jugadores, equipos o resultados existentes. No ejecutes `docker compose down -v`.

Después de actualizar, ingresa como administrador a **Disciplina** y pulsa **Sincronizar cédulas** una sola vez para procesar las cédulas que ya estaban aprobadas antes de instalar el módulo. El proceso es idempotente y puede repetirse sin duplicar sanciones.

## Prueba funcional

1. Entra como administrador y abre **Disciplina**.
2. Abre **Configuración**, revisa los valores iniciales y guarda un cambio con su motivo.
3. Pulsa **Sincronizar cédulas** y verifica el mensaje del sistema.
4. Aprueba una cédula con tarjeta roja y confirma que se genere una suspensión vigente.
5. Intenta incluir al jugador en una alineación y comprueba que el servidor lo rechace.
6. Aprueba el siguiente partido oficial de su equipo y verifica que el contador de cumplimiento avance.
7. Registra manualmente una sanción para un equipo y otra para un integrante del cuerpo técnico.
8. Registra un descuento de puntos y verifica la columna **PD** en **Resultados**.
9. Ingresa como representante del equipo sancionado y presenta una apelación antes de vencer el plazo.
10. Adjunta una evidencia y comprueba que otro equipo no pueda descargarla.
11. Ingresa como administrador, revisa la apelación y elige confirmar, reducir o revocar.
12. Ingresa como jugador y confirma que solo pueda consultar sus propias sanciones.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module14
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 14.
