import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_08_000018_create_match_reports_tables.php',
    'app/Domain/Scheduling/Models/MatchReport.php',
    'app/Domain/Scheduling/Models/MatchReportEvent.php',
    'app/Domain/Scheduling/Models/MatchReportConfirmation.php',
    'app/Domain/Scheduling/Services/MatchReportService.php',
    'app/Http/Controllers/League/MatchReportController.php',
    'resources/js/pages/League/Reports/Index.vue',
    'resources/views/reports/match.blade.php',
    'tests/Feature/League/MatchReportManagementTest.php',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);

const migration = readFileSync(required[0], 'utf8');
for (const rule of ['match_reports', 'match_report_events', 'match_report_confirmations', "'draft', 'awaiting_confirmations', 'correction_required', 'under_review', 'approved'", "unique(['match_report_id', 'team_participation_id'])"]) {
    if (!migration.includes(rule)) throw new Error(`Falta estructura de cédulas: ${rule}`);
}
const service = readFileSync('app/Domain/Scheduling/Services/MatchReportService.php', 'utf8');
for (const rule of ['assertScoreAndEvents', 'AwaitingConfirmations', 'CorrectionRequired', 'UnderReview', 'Approved', 'Ambos equipos deben tener un representante activo']) {
    if (!service.includes(rule)) throw new Error(`Falta regla de cédulas: ${rule}`);
}
const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/cedulas'", "'/partidos/{match}/cedula'", "'/cedulas/{report}/conformidad'", "'/cedulas/{report}/revision'", "'/cedulas/{report}.pdf'"]) {
    if (!routes.includes(route)) throw new Error(`Falta ruta de cédulas: ${route}`);
}
const view = readFileSync('resources/js/pages/League/Reports/Index.vue', 'utf8');
for (const element of ['Cédulas arbitrales', 'Alineaciones registradas', 'lineup_role', 'Agregar evento', 'Vista de solo lectura', 'Revisar antes de confirmar', 'Estoy conforme', 'No estoy conforme', 'Aprobar cédula', 'Descargar PDF aprobado', 'permissions.download']) {
    if (!view.includes(element)) throw new Error(`Falta control visual: ${element}`);
}
const controller = readFileSync('app/Http/Controllers/League/MatchReportController.php', 'utf8');
if (!controller.includes('$report->status === MatchReportStatus::Approved')) throw new Error('El PDF no está restringido a cédulas aprobadas.');
console.log('Validación estructural del Módulo 12 completada.');
