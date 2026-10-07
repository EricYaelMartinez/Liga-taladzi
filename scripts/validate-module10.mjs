import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_06_000015_create_match_scheduling_tables.php',
    'app/Domain/Scheduling/Models/Matchday.php',
    'app/Domain/Scheduling/Models/GameMatch.php',
    'app/Domain/Scheduling/Models/MatchRefereeAssignment.php',
    'app/Domain/Scheduling/Models/MatchScheduleChange.php',
    'app/Domain/Scheduling/Services/RoundRobinScheduleGenerator.php',
    'app/Domain/Scheduling/Services/MatchSchedulingService.php',
    'app/Http/Controllers/League/ScheduleController.php',
    'resources/js/pages/League/Schedule/Index.vue',
    'tests/Feature/League/ScheduleManagementTest.php',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const table of ['matchdays', 'matches', 'match_referee_assignments', 'match_schedule_changes']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}`);
}
for (const rule of ['matchdays_phase_check', 'matches_teams_check', 'matches_schedule_check', 'match_referee_role_check']) {
    if (!migration.includes(rule)) throw new Error(`Falta la restricción ${rule}`);
}

const generator = readFileSync('app/Domain/Scheduling/Services/RoundRobinScheduleGenerator.php', 'utf8');
for (const rule of ['DoubleRoundRobin', 'count($teams) % 2', 'array_pop', 'durationMinutes']) {
    if (!generator.includes(rule)) throw new Error(`Falta la regla de generación: ${rule}`);
}

const scheduler = readFileSync('app/Domain/Scheduling/Services/MatchSchedulingService.php', 'utf8');
for (const rule of ['assertNoFieldConflict', 'assertNoTeamConflict', 'assertNoRefereeConflict', 'schedule_buffer_minutes', "phase->value !== 'knockout'", 'No se permite reemplazar árbitros']) {
    if (!scheduler.includes(rule)) throw new Error(`Falta la regla de programación: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const rule of ["Route::get('/calendario'", 'permission:schedule.manage', 'generar-calendario', "Route::post('/partidos/{match}/programar'"]) {
    if (!routes.includes(rule)) throw new Error(`Falta la ruta o permiso: ${rule}`);
}

const permissions = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
for (const permission of ['schedule.view', 'schedule.manage']) {
    if (!permissions.includes(permission)) throw new Error(`Falta el permiso ${permission}`);
}

console.log('Validación estructural del Módulo 10 completada.');
