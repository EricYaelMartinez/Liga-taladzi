import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_15_000021_create_notice_tables.php',
    'app/Domain/Notification/Models/LeagueNotice.php',
    'app/Domain/Notification/Models/LeagueNoticeRecipient.php',
    'app/Domain/Notification/Models/NotificationPreference.php',
    'app/Domain/Notification/Services/NoticePublicationService.php',
    'app/Http/Controllers/League/NoticeController.php',
    'app/Http/Requests/StoreLeagueNoticeRequest.php',
    'resources/js/pages/League/Notices/Index.vue',
    'tests/Feature/League/NoticeManagementTest.php',
    'MODULO_16_VERIFICACION.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const migration = readFileSync(required[0], 'utf8');
for (const table of ['league_notices', 'league_notice_recipients', 'notification_preferences']) {
    if (!migration.includes(table)) throw new Error(`Falta la estructura de notificaciones: ${table}`);
}

const service = readFileSync('app/Domain/Notification/Services/NoticePublicationService.php', 'utf8');
for (const rule of ['resolveAudience', 'applyPreferences', 'NoticePriority::Urgent', 'TeamRepresentative', 'Player']) {
    if (!service.includes(rule)) throw new Error(`Falta la regla de entrega: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/avisos'", "'/avisos/{recipient}/leer'", "'/avisos/leer-todos'", "'/avisos/preferencias'", "'/avisos/{notice}/publicar'", "'/avisos/{notice}/retirar'"]) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta de avisos: ${route}`);
}

const view = readFileSync('resources/js/pages/League/Notices/Index.vue', 'utf8');
for (const element of ['Mi bandeja', 'Administrar avisos', 'Preferencias personales', 'Toda la liga', 'Roles seleccionados', 'Equipos seleccionados', 'Urgente (obligatorio)']) {
    if (!view.includes(element)) throw new Error(`Falta el control visual: ${element}`);
}

console.log('Validación estructural del Módulo 16 completada.');
