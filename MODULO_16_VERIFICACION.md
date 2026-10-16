# Módulo 16 — Avisos y notificaciones

## Objetivo

Permitir que la administración de cada liga publique comunicados internos para audiencias controladas y que cada usuario disponga de una bandeja con preferencias y constancia de lectura.

## Alcance implementado

- Creación y edición de avisos en borrador.
- Publicación independiente del guardado, para revisar el mensaje antes de entregarlo.
- Prioridades normal, importante y urgente.
- Categorías general, calendario, pagos y disciplina.
- Audiencia para toda la liga, uno o varios roles, o equipos seleccionados.
- En avisos por equipo se incluye a representantes activos y jugadores activos vinculados al sistema con registro activo en el equipo.
- Lista histórica de destinatarios congelada al publicar.
- Bandeja individual separada por liga.
- Marcado individual o masivo como leído.
- Preferencias personales por categoría.
- Entrega obligatoria de avisos urgentes aunque una categoría esté desactivada.
- Vencimiento opcional y retiro administrativo sin eliminar historial.
- Contador de avisos sin leer en el menú y el panel.
- Registro en bitácora de creación, edición, publicación y retiro.
- Permisos separados para consultar y administrar avisos.

## Reglas importantes

1. Guardar un borrador no entrega ninguna notificación.
2. Al publicar se resuelve la audiencia una sola vez y se conserva la lista exacta de destinatarios.
3. Toda la liga incluye usuarios activos con membresía activa en la liga.
4. La audiencia por rol solo incluye membresías activas de los roles seleccionados.
5. La audiencia por equipo incluye representantes activos y jugadores con cuenta, perfil activo y registro activo en el equipo.
6. Los avisos normales e importantes respetan las preferencias personales de la categoría.
7. Los avisos urgentes siempre se entregan.
8. Un aviso retirado deja de aparecer en las bandejas, pero no se elimina ni pierde sus lecturas.
9. Un aviso vencido deja de mostrarse y de contarse como pendiente.
10. Solo los administradores de la liga pueden crear, editar, publicar o retirar avisos.
11. Árbitros, representantes y jugadores solo consultan su propia bandeja y preferencias.

## Actualización segura

Desde PowerShell, dentro de `C:\Proyectos\liga-taladzi`:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración crea tres tablas nuevas. No modifica ni elimina usuarios, ligas, equipos, jugadores, partidos, pagos, sanciones ni datos de módulos anteriores. No ejecutes `docker compose down -v`.

## Prueba funcional

1. Ingresa como administrador de liga y abre **Control > Avisos y notificaciones**.
2. Crea un aviso normal para toda la liga y confirma que quede en estado **Borrador**.
3. Edita el borrador y publícalo.
4. Comprueba el número de destinatarios y lecturas en **Administrar avisos**.
5. Ingresa como árbitro, representante o jugador de la misma liga.
6. Verifica el contador en la campana, abre **Mi bandeja** y marca el aviso como leído.
7. En **Preferencias**, desactiva una categoría.
8. Como administrador, publica un aviso normal de esa categoría y verifica que ese usuario no lo reciba.
9. Publica otro aviso urgente de la misma categoría y confirma que sí se entregue.
10. Crea un aviso para un rol y comprueba que otros roles no lo reciban.
11. Crea un aviso para un equipo y comprueba que solo sus representantes y jugadores vinculados lo reciban.
12. Retira un aviso publicado y confirma que desaparezca de las bandejas sin borrarse de la administración ni la bitácora.
13. Cambia a otra liga y verifica que no aparezcan avisos de la liga anterior.

## Comprobaciones automáticas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module16
```

Todos los comandos deben finalizar sin errores antes de aprobar el Módulo 16.
