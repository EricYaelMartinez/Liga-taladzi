import { readFileSync } from 'node:fs';

const required = [
    'app/Http/Requests/ReopenCancelledMatchRequest.php',
    'app/Domain/Scheduling/Services/MatchSchedulingService.php',
    'app/Http/Controllers/League/ScheduleController.php',
    'resources/js/pages/League/Schedule/Index.vue',
    'routes/web.php',
    'tests/Feature/League/ScheduleManagementTest.php',
];

const files = Object.fromEntries(required.map((path) => [path, readFileSync(path, 'utf8')]));
const combined = Object.values(files).join('\n');

for (const marker of [
    'reprogramar-cancelado',
    'Reabrir y reprogramar',
    'reopenCancelled',
    'schedule.match.reopened',
    'after:now',
    'cédula aprobada',
    'resolución administrativa especial',
    "['cancelled', 'completed']",
]) {
    if (!combined.includes(marker)) throw new Error(`Falta la protección o interfaz: ${marker}`);
}

for (const test of [
    'test_administrator_reopens_cancelled_match_and_restores_cancelled_matchday',
    'test_cancelled_match_that_had_started_cannot_be_reopened',
    'test_cancelled_match_with_approved_report_cannot_be_reopened',
]) {
    if (!files['tests/Feature/League/ScheduleManagementTest.php'].includes(test)) throw new Error(`Falta la prueba: ${test}`);
}

console.log('Validación de reapertura y reprogramación de partidos: OK');
