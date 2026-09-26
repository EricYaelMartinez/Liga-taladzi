import { existsSync, readFileSync } from 'node:fs';

const requiredFiles = [
    'app/Domain/League/Models/LeagueSetting.php',
    'app/Http/Controllers/League/OperationalSettingsController.php',
    'app/Http/Requests/UpdateOperationalSettingsRequest.php',
    'database/migrations/2026_09_24_000007_create_league_settings_table.php',
    'resources/js/pages/League/Settings/Operational.vue',
    'tests/Feature/League/OperationalSettingsTest.php',
];

const missing = requiredFiles.filter((file) => !existsSync(file));
if (missing.length) throw new Error(`Faltan archivos del Módulo 3:\n${missing.join('\n')}`);

const migration = readFileSync(requiredFiles[3], 'utf8');
for (const field of ['period_duration_minutes', 'schedule_buffer_minutes', 'appeal_deadline_hours', 'payment_grace_days', 'reactivation_window_days', 'bond_amount']) {
    if (!migration.includes(field)) throw new Error(`Falta el parámetro: ${field}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
if (!routes.includes("'/parametros'")) throw new Error('No se registraron las rutas de parámetros por liga.');

console.log('Validación estructural del Módulo 3 completada.');
