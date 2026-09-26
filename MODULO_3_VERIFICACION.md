# Verificación del Módulo 3

Este módulo completa la configuración general de cada liga. Los parámetros se aíslan por liga y cada modificación queda registrada en la bitácora.

## 1. Instalar la actualización

Desde PowerShell, dentro del proyecto:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup.ps1
```

La migración crea `league_settings` sin modificar usuarios, ligas ni accesos existentes.

## 2. Configurar la liga

1. Inicia sesión como administrador.
2. Selecciona una liga y el rol **Administrador de liga**.
3. Abre **Parámetros**.
4. Define obligatoriamente la cantidad de tiempos y su duración.
5. Configura, cuando corresponda, descanso y margen entre partidos.
6. Revisa el plazo de apelación; el valor inicial es 2 horas.
7. Activa opcionalmente la prórroga de pago y define sus días.
8. Revisa el periodo de reincorporación; el valor inicial es 21 días.
9. Activa la fianza únicamente si la liga la utilizará y captura el monto.
10. Registra el motivo y guarda.

## 3. Comprobar auditoría y aislamiento

- En **Bitácora** debe aparecer `league.operational_settings.updated`.
- Modifica un parámetro y comprueba que la revisión aumenta.
- Cambia a otra liga y verifica que conserve sus propios valores.
- Un jugador o árbitro no debe poder abrir `/liga/parametros` sin permiso.

## 4. Pruebas técnicas

```powershell
docker compose exec app php vendor/bin/phpunit
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
docker compose exec frontend npm run validate:module3
```

## Resultado esperado

- Deben aprobarse 30 pruebas.
- La duración por tiempo debe estar entre 5 y 120 minutos.
- El margen entre partidos es opcional.
- Si se activa la fianza, el monto es obligatorio.
- Si se desactiva la fianza, cualquier monto anterior se elimina.
- Cada liga conserva su configuración independiente.
- Los cambios quedan registrados con autor, fecha, motivo y revisión.

Estos valores serán la base para nuevas competencias. Cada torneo guardará posteriormente una copia versionada para proteger los historiales.
