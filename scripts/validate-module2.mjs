import { existsSync, readFileSync } from 'node:fs';

const requiredFiles = [
    'app/Domain/League/Models/League.php',
    'app/Domain/League/Models/LeagueMembership.php',
    'app/Domain/Audit/Models/AuditLog.php',
    'app/Support/LeagueContext.php',
    'app/Support/AuditLogger.php',
    'app/Http/Middleware/EnsureLeagueContext.php',
    'app/Http/Controllers/Admin/LeagueController.php',
    'app/Http/Controllers/AccessContextController.php',
    'resources/js/pages/Access/Select.vue',
    'resources/js/pages/Admin/Leagues/Index.vue',
    'resources/js/pages/League/Members/Index.vue',
    'resources/js/pages/League/Audit/Index.vue',
    'tests/Feature/League/MultiLeagueAccessTest.php',
];

const missing = requiredFiles.filter((file) => !existsSync(file));
if (missing.length) throw new Error(`Faltan archivos del Módulo 2:\n${missing.join('\n')}`);

const routes = readFileSync('routes/web.php', 'utf8');
for (const required of ['league.context', 'permission:leagues.view', 'permission:league.members.view', 'permission:audit.view']) {
    if (!routes.includes(required)) throw new Error(`Falta protección requerida: ${required}`);
}

const context = readFileSync('app/Support/LeagueContext.php', 'utf8');
for (const required of ['active_league_id', 'active_role_id', 'hasActiveMembership']) {
    if (!context.includes(required)) throw new Error(`El contexto no valida: ${required}`);
}

console.log('Validación estructural del Módulo 2 completada.');
