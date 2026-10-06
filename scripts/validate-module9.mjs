import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_06_000014_create_referees_and_availability_tables.php',
    'app/Domain/Referee/Models/Referee.php',
    'app/Domain/Referee/Models/RefereeAvailability.php',
    'app/Domain/Referee/Models/RefereeObservation.php',
    'app/Domain/Referee/Services/RefereeAvailabilityService.php',
    'app/Http/Controllers/League/RefereeController.php',
    'resources/js/pages/League/Referees/Index.vue',
    'tests/Feature/League/RefereeManagementTest.php',
];

for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const table of ['referees', 'referee_availabilities', 'referee_observations']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}`);
}
for (const rule of ['referees_status_check', 'referee_availabilities_weekday_check', 'referee_availabilities_time_check']) {
    if (!migration.includes(rule)) throw new Error(`Falta la restricción ${rule}`);
}

const controller = readFileSync('app/Http/Controllers/League/RefereeController.php', 'utf8');
for (const rule of ['assertEligibleUser', 'assertCanEditOwnOrManage', 'makeHidden', "where('starts_at', '<'", "where('ends_at', '>'"]) {
    if (!controller.includes(rule)) throw new Error(`Falta la regla arbitral: ${rule}`);
}

const service = readFileSync('app/Domain/Referee/Services/RefereeAvailabilityService.php', 'utf8');
for (const rule of ['isoWeekday', "status->value !== 'active'", 'isSameDay']) {
    if (!service.includes(rule)) throw new Error(`Falta la regla de disponibilidad: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const rule of ["Route::get('/arbitros'", 'permission:referees.view', "Route::post('/arbitros/{referee}/observaciones'"]) {
    if (!routes.includes(rule)) throw new Error(`Falta la ruta o permiso: ${rule}`);
}

const permissions = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
for (const permission of ['referees.view', 'referees.manage']) {
    if (!permissions.includes(permission)) throw new Error(`Falta el permiso ${permission}`);
}

console.log('Validación estructural del Módulo 9 completada.');
