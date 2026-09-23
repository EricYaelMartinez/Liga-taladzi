import { existsSync, readFileSync } from 'node:fs';

const requiredFiles = [
    'app/Domain/Identity/Models/User.php',
    'app/Http/Controllers/Auth/AuthenticatedSessionController.php',
    'app/Http/Controllers/Admin/UserController.php',
    'database/migrations/2026_09_23_000001_create_users_table.php',
    'database/migrations/2026_09_23_000002_create_roles_and_permissions_tables.php',
    'database/migrations/2026_09_23_000003_create_authentication_events_table.php',
    'database/seeders/IdentitySeeder.php',
    'resources/js/pages/Auth/Login.vue',
    'resources/js/pages/Admin/Users/Index.vue',
    'tests/Feature/Auth/AuthenticationTest.php',
    'tests/Feature/Admin/UserManagementTest.php',
];

const missing = requiredFiles.filter((file) => !existsSync(file));
if (missing.length > 0) {
    throw new Error(`Faltan archivos del Módulo 1:\n${missing.join('\n')}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const expected of ['iniciar-sesion', 'cerrar-sesion', 'administracion', 'permission:users.view']) {
    if (!routes.includes(expected)) throw new Error(`No se encontró la ruta o protección requerida: ${expected}`);
}

const middleware = readFileSync('bootstrap/app.php', 'utf8');
for (const expected of ['active', 'force.password', 'permission']) {
    if (!middleware.includes(expected)) throw new Error(`No se registró el middleware: ${expected}`);
}

console.log('Validación estructural del Módulo 1 completada.');
