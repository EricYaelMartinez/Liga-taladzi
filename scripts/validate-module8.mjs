import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_05_000013_create_venues_and_field_availability_tables.php',
    'app/Domain/Scheduling/Models/Venue.php',
    'app/Domain/Scheduling/Models/PlayingField.php',
    'app/Domain/Scheduling/Models/FieldAvailability.php',
    'app/Domain/Scheduling/Models/FieldBlock.php',
    'app/Domain/Scheduling/Services/FieldAvailabilityService.php',
    'app/Http/Controllers/League/FieldController.php',
    'resources/js/pages/League/Fields/Index.vue',
    'tests/Feature/League/FieldManagementTest.php',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const migration = readFileSync(required[0], 'utf8');
for (const table of ['venues', 'playing_fields', 'field_division', 'field_availabilities', 'field_blocks']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}`);
}
for (const rule of ['field_availabilities_time_check', 'field_blocks_time_check', 'playing_fields_status_check']) {
    if (!migration.includes(rule)) throw new Error(`Falta la restricción ${rule}`);
}

const controller = readFileSync('app/Http/Controllers/League/FieldController.php', 'utf8');
for (const rule of ['assertUniqueVenue', 'assertUniqueField', 'divisionIds', "where('starts_at', '<'", "where('ends_at', '>'"]) {
    if (!controller.includes(rule)) throw new Error(`Falta la regla de administración: ${rule}`);
}

const service = readFileSync('app/Domain/Scheduling/Services/FieldAvailabilityService.php', 'utf8');
for (const rule of ['schedule_buffer_minutes', 'isoWeekday', 'isSameDay', 'blocks()']) {
    if (!service.includes(rule)) throw new Error(`Falta la regla de disponibilidad: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const rule of ["Route::get('/campos'", 'permission:fields.manage', "Route::post('/canchas/{field}/bloqueos'"]) {
    if (!routes.includes(rule)) throw new Error(`Falta la ruta o permiso: ${rule}`);
}

const permissions = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
for (const permission of ['fields.view', 'fields.manage']) {
    if (!permissions.includes(permission)) throw new Error(`Falta el permiso ${permission}`);
}

console.log('Validación estructural del Módulo 8 completada.');
