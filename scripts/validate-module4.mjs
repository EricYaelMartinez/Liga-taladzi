import { existsSync, readFileSync } from 'node:fs';

const requiredFiles = [
    'app/Domain/Competition/Models/Season.php',
    'app/Domain/Competition/Models/Tournament.php',
    'app/Domain/Competition/Models/Division.php',
    'app/Domain/Competition/Models/Category.php',
    'app/Domain/Competition/Models/Regulation.php',
    'app/Domain/Competition/Models/Competition.php',
    'app/Http/Controllers/League/CompetitionSetupController.php',
    'database/migrations/2026_09_24_000008_create_competition_structure_tables.php',
    'resources/js/pages/League/Competitions/Index.vue',
    'tests/Feature/League/CompetitionSetupTest.php',
];

const missing = requiredFiles.filter((file) => !existsSync(file));
if (missing.length) throw new Error(`Faltan archivos del Módulo 4:\n${missing.join('\n')}`);

const migration = readFileSync(requiredFiles[7], 'utf8');
for (const table of ['seasons', 'tournaments', 'divisions', 'categories', 'regulations', 'regulation_tiebreakers', 'competitions']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}.`);
}

const controller = readFileSync(requiredFiles[6], 'utf8');
for (const behavior of ['league_settings_snapshot', 'RegulationStatus::InUse', 'replaceTiebreakers', 'assertRegulation']) {
    if (!controller.includes(behavior)) throw new Error(`Falta la protección ${behavior}.`);
}
if (controller.includes("lockForUpdate()->max('version')")) {
    throw new Error('PostgreSQL no permite bloquear una consulta agregada MAX con FOR UPDATE.');
}
if (!controller.includes("League::whereKey($league->id)->lockForUpdate()")) {
    throw new Error('Falta el bloqueo de la liga al calcular una nueva versión del reglamento.');
}

const routes = readFileSync('routes/web.php', 'utf8');
if (!routes.includes("'/competencias'")) throw new Error('No se registraron las rutas de competencias.');

console.log('Validación estructural del Módulo 4 completada.');
