# Módulo 15 — Pagos, fianzas y recibos

## Objetivo

Administrar los adeudos de los equipos, registrar pagos presenciales en efectivo, emitir recibos PDF y aplicar el ciclo de prórroga, suspensión, reactivación y exclusión configurado por cada liga.

## Alcance implementado

- Conceptos reutilizables para cuotas, fianzas, multas y otros cobros.
- Adeudos ligados al equipo, participación, temporada y concepto.
- Asignación masiva de un mismo adeudo a varios equipos, con filtros por temporada, competencia y división.
- Confirmación previa de cantidad de equipos e importe total proyectado.
- Protección contra cobros duplicados para un mismo concepto, participación y vencimiento.
- Cálculo automático de la prórroga usando los parámetros de la liga.
- Pagos parciales y un solo pago aplicado a varios adeudos.
- Folios de recibo consecutivos e independientes por liga.
- Recibo PDF descargable por administrador y representante del equipo.
- Pagos únicamente en efectivo; no se integran pasarelas bancarias en este módulo.
- Anulación de recibos con restauración de saldos, motivo, usuario y fecha.
- Cancelación controlada de adeudos sin eliminarlos.
- Generación idempotente de fianzas configuradas y multas disciplinarias.
- Suspensión automática después de finalizar la prórroga.
- Reactivación automática si se liquida dentro del periodo configurado.
- Exclusión definitiva cuando también vence el periodo de reactivación.
- Evaluación automática cada hora mediante el servicio programador incluido en Docker.
- Conservación permanente del historial de pagos y recibos.
- Separación de datos y folios entre ligas.

## Reglas importantes

1. Durante la prórroga, el equipo conserva su participación activa.
2. Al vencer la prórroga, una participación activa pasa a suspendida por causa financiera.
3. El pago total dentro del periodo de reactivación restablece la participación cuando la suspensión fue financiera.
4. Una suspensión administrativa nunca se levanta automáticamente por registrar un pago.
5. Al vencer el periodo de reactivación, la participación queda excluida y no vuelve al torneo aunque el adeudo se pague después.
6. Una fianza se genera una sola vez por participación aunque se pulse varias veces **Sincronizar**.
7. Un pago puede cubrir total o parcialmente uno o varios adeudos del mismo equipo.
8. El importe aplicado nunca puede superar el saldo del adeudo.
9. Los pagos y recibos no se eliminan. Una corrección se realiza mediante anulación con motivo obligatorio.
10. Un adeudo con pagos vigentes no puede cancelarse; primero debe anularse el recibo relacionado.
11. Un cobro masivo crea un adeudo independiente para cada participación seleccionada.
12. Si algún equipo ya tiene el mismo cobro y vencimiento, se rechaza el lote completo y no se crean registros parciales.
13. Solo las participaciones y equipos activos pueden incluirse en una asignación masiva. La temporada y competencia pueden estar en planeación, registro o activas, pero no finalizadas, archivadas o canceladas.

## Actualización segura

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración crea tablas financieras nuevas y agrega campos de control a las participaciones. No elimina equipos, partidos, sanciones, pagos ni otra información existente. No ejecutes `docker compose down -v`.

La ampliación de cobros masivos no agrega ni modifica tablas, por lo que puede aplicarse sin transformar los adeudos y pagos existentes.

## Prueba funcional

1. En **Parámetros**, configura la prórroga, el periodo de reactivación y una fianza.
2. Abre **Finanzas** como administrador.
3. Crea un concepto y registra un adeudo individual con vencimiento futuro.
4. En **Asignar adeudo**, selecciona **Varios equipos**, filtra una temporada, competencia o división y marca varios equipos.
5. Comprueba la cantidad seleccionada, el total proyectado y confirma la operación.
6. Verifica que cada equipo tenga su propio adeudo y que pueda pagarse o cancelarse individualmente.
7. Intenta repetir el mismo concepto y vencimiento para uno de esos equipos; el sistema debe rechazar todo el lote sin crear cargos para los demás.
8. Registra dos adeudos para un mismo equipo y aplica un pago parcial a ambos.
9. Verifica los saldos y descarga el recibo PDF.
10. Anula el recibo y confirma que los saldos se restauren y el folio permanezca visible como anulado.
11. Pulsa **Sincronizar fianzas y vencimientos** dos veces y comprueba que no se duplique la fianza.
12. Usa un adeudo vencido para comprobar los estados de prórroga y suspensión.
13. Liquídalo dentro del periodo de reactivación y confirma la reactivación.
14. Prueba un adeudo cuyo periodo de reactivación haya vencido y confirma la exclusión definitiva.
15. Ingresa como representante y verifica que solo vea el estado de cuenta y los recibos de su equipo.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module15
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 15.
La suite completa debe terminar sin errores ni fallos; el número puede aumentar al incorporarse nuevas pruebas.
