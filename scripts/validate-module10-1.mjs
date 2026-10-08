import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_07_000016_add_advanced_schedule_configuration.php',
    'app/Domain/Scheduling/Models/ScheduleTimeSlot.php',
    'app/Domain/Scheduling/Services/AutomaticScheduleAllocator.php',
    'app/Domain/Scheduling/Services/RoundRobinScheduleGenerator.php',
    'app/Domain/Scheduling/Services/MatchSchedulingService.php',
    'app/Http/Requests/AssignMatchRefereesRequest.php',
    'app/Http/Requests/UpdateMatchScheduleRequest.php',
    'resources/js/pages/League/Schedule/Index.vue',
    'tests/Feature/League/ScheduleManagementTest.php',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const rule of ['schedule_time_slots', 'default_max_matches_per_field_day', 'max_matches_per_day', 'pairing_key', 'generation_round', 'referees_assigned']) {
    if (!migration.includes(rule)) throw new Error(`Falta la estructura avanzada: ${rule}`);
}

const generator = readFileSync('app/Domain/Scheduling/Services/RoundRobinScheduleGenerator.php', 'utf8');
for (const rule of ["$scope === 'next'", 'pairingKey', 'leg_number', 'AutomaticScheduleAllocator']) {
    if (!generator.includes(rule)) throw new Error(`Falta la regla del generador: ${rule}`);
}

const scheduler = readFileSync('app/Domain/Scheduling/Services/MatchSchedulingService.php', 'utf8');
for (const rule of ['updateSchedule', 'assignReferees', 'assertDailyCapacity', 'assertNoRefereeConflict']) {
    if (!scheduler.includes(rule)) throw new Error(`Falta la regla de programación: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ['horarios-estandar', 'capacidad-canchas', '/programacion', '/arbitros']) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta: ${route}`);
}

const view = readFileSync('resources/js/pages/League/Schedule/Index.vue', 'utf8');
for (const element of ['Siguiente jornada', 'Todas las restantes', 'Horarios y capacidad', 'Asignar árbitros', 'Cambiar horario/campo']) {
    if (!view.includes(element)) throw new Error(`Falta el control visual: ${element}`);
}

console.log('Validación estructural del Módulo 10.1 completada.');
