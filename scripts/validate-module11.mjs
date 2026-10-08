import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_08_000017_create_match_lineups_tables.php',
    'app/Domain/Scheduling/Models/MatchLineup.php',
    'app/Domain/Scheduling/Models/MatchLineupPlayer.php',
    'app/Domain/Scheduling/Services/MatchLineupService.php',
    'app/Http/Controllers/League/LineupController.php',
    'app/Http/Requests/SaveMatchLineupRequest.php',
    'resources/js/pages/League/Lineups/Index.vue',
    'tests/Feature/League/LineupManagementTest.php',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const rule of ['match_lineups', 'match_lineup_players', "'draft', 'submitted'", "'starter', 'substitute'", "unique(['match_id', 'team_participation_id'])"]) {
    if (!migration.includes(rule)) throw new Error(`Falta la estructura de alineaciones: ${rule}`);
}

const service = readFileSync('app/Domain/Scheduling/Services/MatchLineupService.php', 'utf8');
for (const rule of ['assertParticipation', 'assertMatchAcceptsLineups', 'LineupStatus::Submitted', "where('status', 'active')", 'Solo puede seleccionarse un capitán', 'MIN_STARTERS = 8', 'MAX_STARTERS = 11']) {
    if (!service.includes(rule)) throw new Error(`Falta la regla de alineación: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/alineaciones'", "'/partidos/{match}/alineaciones/{participation}'", "'/partidos/{match}/alineaciones/{participation}/enviar'"]) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta: ${route}`);
}

const view = readFileSync('resources/js/pages/League/Lineups/Index.vue', 'utf8');
for (const element of ['Guardar borrador', 'Enviar y cerrar', 'Titulares', 'Suplentes', 'Capitán', 'entre 8 y 11 titulares']) {
    if (!view.includes(element)) throw new Error(`Falta el control visual: ${element}`);
}

console.log('Validación estructural del Módulo 11 completada.');
