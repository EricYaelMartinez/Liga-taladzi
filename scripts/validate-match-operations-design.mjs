import { readFileSync } from 'node:fs';

const styles = readFileSync('resources/css/app.css', 'utf8');
const layout = readFileSync('resources/js/layouts/AppLayout.vue', 'utf8');
const lineups = readFileSync('resources/js/pages/League/Lineups/Index.vue', 'utf8');
const reports = readFileSync('resources/js/pages/League/Reports/Index.vue', 'utf8');

for (const marker of ['match-operations-hero', 'match-kpi', 'match-card', 'match-scoreboard', 'match-status', 'lineup-player-row', 'match-collapse-bar', 'match-collapse-button', 'match-modal-panel']) {
    if (!styles.includes(marker)) throw new Error(`Falta el componente visual de partidos: ${marker}`);
}

for (const marker of ['8 mínimo · 11 máximo', 'submittedLineups', 'draftLineups', 'expandedMatches', 'toggleMatch', 'Ver y capturar alineaciones', 'Capturar alineación', 'Enviar y cerrar']) {
    if (!lineups.includes(marker)) throw new Error(`Falta el elemento de Alineaciones: ${marker}`);
}

for (const marker of ['approvedReports', 'reportsInProcess', 'Capturar cédula', 'Enviar a conformidad', 'Descargar PDF aprobado']) {
    if (!reports.includes(marker)) throw new Error(`Falta el elemento de Cédulas: ${marker}`);
}

if (!layout.includes('rgba(238, 242, 246, .78)')) throw new Error('No se aplicó la transparencia solicitada al fondo administrativo.');

console.log('Validación del rediseño de Partidos, Alineaciones y Cédulas: OK');
