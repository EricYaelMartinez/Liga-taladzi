import { existsSync, readFileSync } from 'node:fs';

const required = [
    'app/Domain/Statistics/Services/CompetitionStatisticsService.php',
    'app/Http/Controllers/League/StatisticsController.php',
    'resources/js/pages/League/Statistics/Index.vue',
    'tests/Feature/League/StatisticsTest.php',
    'MODULO_13_VERIFICACION.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const service = readFileSync(required[0], 'utf8');
for (const rule of [
    'MatchReportStatus::Approved',
    'applyResult',
    'head_to_head',
    'fair_play',
    'fairPlayValue',
    "event->type === 'goal'",
]) {
    if (!service.includes(rule)) throw new Error(`Falta la regla estadística: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
if (!routes.includes("'/resultados'")) throw new Error('Falta la ruta de resultados y estadísticas.');
if (!routes.includes("permission:statistics.view")) throw new Error('La ruta de estadísticas no comprueba el permiso requerido.');

const seeder = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
if (!seeder.includes("'statistics.view'")) throw new Error('Falta el permiso para consultar estadísticas.');

const view = readFileSync(required[2], 'utf8');
for (const element of [
    'Tabla de posiciones',
    'Resultados oficiales',
    'Tabla de goleadores',
    'Amonestados y expulsados',
    'cédulas aprobadas',
]) {
    if (!view.includes(element)) throw new Error(`Falta el elemento visual: ${element}`);
}

console.log('Validación estructural del Módulo 13 completada.');
