import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_12_000019_add_cancelled_matchday_status_and_sync_terminal_competitions.php',
    'app/Domain/Scheduling/Services/MatchLifecycleService.php',
    'app/Console/Commands/SyncMatchLifecycle.php',
    'resources/js/pages/League/Schedule/Index.vue',
    'resources/js/pages/League/Reports/Index.vue',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const lifecycle = readFileSync(required[1], 'utf8');
for (const rule of ['scheduled_at', 'MatchStatus::InProgress', 'MatchdayStatus::Completed', 'MatchdayStatus::Cancelled', 'status_changed']) {
    if (!lifecycle.includes(rule)) throw new Error(`Falta regla del ciclo de vida: ${rule}`);
}

const consoleRoutes = readFileSync('routes/console.php', 'utf8');
if (!consoleRoutes.includes("Schedule::command('liga:sync-match-lifecycle')->everyMinute()")) throw new Error('Falta la sincronización automática cada minuto.');

const compose = readFileSync('compose.yaml', 'utf8');
if (!compose.includes('scheduler:') || !compose.includes('php artisan schedule:work')) throw new Error('Falta el servicio programador en Docker.');

const scheduleView = readFileSync(required[3], 'utf8');
for (const element of ['showTerminalMatchdays', 'Mostrar jornadas finalizadas y canceladas', 'manageableCompetitions']) {
    if (!scheduleView.includes(element)) throw new Error(`Falta control de jornadas: ${element}`);
}

const reportService = readFileSync('app/Domain/Scheduling/Services/MatchReportService.php', 'utf8');
if (!reportService.includes('assertCaptureAvailable')) throw new Error('Falta el bloqueo de cédulas antes del inicio.');

const competitionController = readFileSync('app/Http/Controllers/League/CompetitionSetupController.php', 'utf8');
if (!competitionController.includes("'propagated' => $propagated")) throw new Error('Falta la propagación auditable de estados de temporada.');

console.log('Validación de estabilización del ciclo competitivo completada.');
