import { readFileSync } from 'node:fs';

const page = readFileSync('resources/js/pages/League/Competitions/Index.vue', 'utf8');
const styles = readFileSync('resources/css/app.css', 'utf8');

for (const marker of [
    'competition-hero',
    'competition-tabs',
    'competition-stat',
    'competition-workflow',
    'season-card',
    'regulation-card',
    'competition-card',
    'competition-toolbar',
]) {
    if (!styles.includes(marker)) throw new Error(`Falta el componente visual: ${marker}`);
}

for (const marker of [
    'Competencias y reglamentos',
    'Ruta de configuración',
    'Divisiones y categorías',
    'Temporadas registradas',
    'Reglamentos de la liga',
    'visibleCompetitions',
    'competitionSearch',
    'competitionStatus',
    'parseSeasonDate',
    'seasonYear',
    'Crear competencia',
]) {
    if (!page.includes(marker)) throw new Error(`Falta el elemento del rediseño: ${marker}`);
}

console.log('Validación del rediseño de Competencias y Reglamento: OK');
