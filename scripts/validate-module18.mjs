import { existsSync, readFileSync } from 'node:fs';

const required = [
    'app/Domain/Reporting/Services/LeagueReportService.php',
    'app/Domain/Reporting/Services/SimpleXlsxWriter.php',
    'app/Http/Controllers/League/ExportController.php',
    'app/Http/Requests/ExportLeagueReportRequest.php',
    'resources/js/pages/League/Exports/Index.vue',
    'resources/views/exports/report.blade.php',
    'tests/Feature/League/ExportManagementTest.php',
    'MODULO_18_VERIFICACION.md',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const service = readFileSync(required[0], 'utf8');
for (const type of ['teams', 'rosters', 'schedule', 'results', 'standings', 'scorers', 'cards', 'sanctions', 'referee_matches', 'match_reports', 'player_movements', 'season_statistics']) {
    if (!service.includes(`'${type}'`)) throw new Error(`Falta el reporte: ${type}`);
}
for (const security of ["where('league_id', $league->id)", "where('status', 'approved')", 'competition(League $league']) {
    if (!service.includes(security)) throw new Error(`Falta la regla de alcance: ${security}`);
}

const controller = readFileSync(required[2], 'utf8');
for (const rule of ["'report.exported'", "'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'", "Pdf::loadView('exports.report'"]) {
    if (!controller.includes(rule)) throw new Error(`Falta la salida o auditoría: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/reportes'", "'/reportes/exportar'", "permission:exports.view"]) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta protegida: ${route}`);
}

const seed = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
if (!seed.includes("'exports.view'")) throw new Error('Falta el permiso administrativo de exportaciones.');

const tests = readFileSync(required[6], 'utf8');
for (const check of ['assertForbidden', 'assertNotFound', 'report.exported', 'LeagueReportService::TYPES']) {
    if (!tests.includes(check)) throw new Error(`Falta una prueba crítica: ${check}`);
}

console.log('Validación estructural del Módulo 18 completada.');
