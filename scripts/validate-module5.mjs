import { existsSync, readFileSync } from 'node:fs';

const requiredFiles = [
    'app/Domain/Team/Models/Team.php',
    'app/Domain/Team/Models/TeamRepresentative.php',
    'app/Domain/Team/Models/TeamParticipation.php',
    'app/Domain/Team/Models/TeamNameHistory.php',
    'app/Domain/Team/Models/TeamChangeRequest.php',
    'app/Http/Controllers/League/TeamController.php',
    'app/Http/Requests/StoreTeamParticipationRequest.php',
    'database/migrations/2026_09_26_000009_create_teams_and_representatives_tables.php',
    'resources/js/pages/League/Teams/Index.vue',
    'tests/Feature/League/TeamManagementTest.php',
];

const missing = requiredFiles.filter((file) => !existsSync(file));
if (missing.length) throw new Error(`Faltan archivos del Módulo 5:\n${missing.join('\n')}`);

const migration = readFileSync(requiredFiles[7], 'utf8');
for (const table of ['teams', 'team_name_histories', 'team_representatives', 'team_participations', 'team_change_requests']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}.`);
}
for (const constraint of ['one_active_per_team', 'one_active_team_per_user', 'team_participations_name_scope_unique']) {
    if (!migration.includes(constraint)) throw new Error(`Falta la restricción ${constraint}.`);
}

const controller = readFileSync(requiredFiles[5], 'utf8');
for (const behavior of ['assertOwnedTeam', 'assertRepresentativeAvailable', 'applyTeamChanges', 'requestParticipation', 'team.participation.status_changed']) {
    if (!controller.includes(behavior)) throw new Error(`Falta el comportamiento ${behavior}.`);
}

const routes = readFileSync('routes/web.php', 'utf8');
if (!routes.includes("'/equipos'")) throw new Error('No se registraron las rutas de equipos.');

console.log('Validación estructural del Módulo 5 completada.');
