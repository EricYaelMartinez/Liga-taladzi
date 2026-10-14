import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_13_000019_create_discipline_tables.php',
    'app/Domain/Discipline/Models/DisciplinePolicy.php',
    'app/Domain/Discipline/Models/Sanction.php',
    'app/Domain/Discipline/Models/SanctionAppeal.php',
    'app/Domain/Discipline/Services/DisciplineService.php',
    'app/Http/Controllers/League/DisciplineController.php',
    'resources/js/pages/League/Discipline/Index.vue',
    'tests/Feature/League/DisciplineManagementTest.php',
    'MODULO_14_VERIFICACION.md',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const table of ['discipline_policies', 'sanctions', 'sanction_served_matches', 'sanction_appeals']) {
    if (!migration.includes(table)) throw new Error(`Falta la tabla disciplinaria: ${table}`);
}

const service = readFileSync('app/Domain/Discipline/Services/DisciplineService.php', 'utf8');
for (const rule of ['syncReport', 'fulfillSuspensions', 'yellow_card_threshold', 'yellow:', 'red_card_suspension_matches', 'refreshPlayerStatus']) {
    if (!service.includes(rule)) throw new Error(`Falta la regla disciplinaria: ${rule}`);
}

const lineups = readFileSync('app/Domain/Scheduling/Services/MatchLineupService.php', 'utf8');
if (!lineups.includes('suspensión vigente')) throw new Error('Las alineaciones no bloquean sanciones vigentes.');

const statistics = readFileSync('app/Domain/Statistics/Services/CompetitionStatisticsService.php', 'utf8');
if (!statistics.includes('points_deduction') || !statistics.includes('points_deducted')) throw new Error('La tabla oficial no aplica descuentos de puntos.');

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/disciplina'", "'/sanciones'", "'/sanciones/sincronizar'", "'/sanciones/{sanction}/apelaciones'", "'/apelaciones/{appeal}/resolver'"]) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta disciplinaria: ${route}`);
}

const view = readFileSync('resources/js/pages/League/Discipline/Index.vue', 'utf8');
for (const element of ['Sanciones y apelaciones', 'Sincronizar cédulas', 'Nueva sanción', 'Presentar apelación', 'Resolver apelación']) {
    if (!view.includes(element)) throw new Error(`Falta el control visual: ${element}`);
}

console.log('Validación estructural del Módulo 14 completada.');
