import { readFileSync } from 'node:fs';

const schedule = readFileSync('resources/js/pages/League/Schedule/Index.vue', 'utf8');
const controller = readFileSync('app/Http/Controllers/League/ScheduleController.php', 'utf8');

for (const marker of [
    'schedule-hero',
    'schedule-stat',
    'matchday-card',
    'match-card',
    'expandedMatchdays',
    'matchdayProgress',
    'matchStatusClass',
    'team-crest',
    'Mostrar jornadas finalizadas y canceladas',
    'Reabrir',
    'Horarios y capacidad',
    'Generar jornadas',
]) {
    if (!schedule.includes(marker)) throw new Error(`Falta el elemento visual o funcional: ${marker}`);
}

if (!controller.includes('crest_path')) throw new Error('Los escudos de los equipos no se cargan en la programación.');

console.log('Validación del rediseño de Jornadas y programación: OK');
