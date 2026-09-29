import { readFileSync, existsSync } from 'node:fs';

const required = [
    'database/migrations/2026_09_26_000010_create_players_and_rosters_tables.php',
    'database/migrations/2026_09_28_000011_add_credential_logos_to_leagues_table.php',
    'app/Domain/Player/Models/Player.php',
    'app/Domain/Player/Models/PlayerRegistration.php',
    'app/Domain/Player/Models/PlayerMovement.php',
    'app/Http/Controllers/League/PlayerController.php',
    'app/Http/Controllers/League/PlayerCredentialController.php',
    'app/Http/Requests/StorePlayerRequest.php',
    'resources/js/pages/League/Players/Index.vue',
    'resources/views/credentials/team.blade.php',
    'tests/Feature/League/PlayerManagementTest.php',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const migration = readFileSync(required[0], 'utf8');
for (const table of ['players', 'player_documents', 'player_registrations', 'player_movements']) {
    if (!migration.includes(`Schema::create('${table}'`)) throw new Error(`Falta la tabla ${table}`);
}
for (const index of ['player_registrations_one_active_per_season', 'player_registrations_jersey_unique']) {
    if (!migration.includes(index)) throw new Error(`Falta la restricción ${index}`);
}

const controller = readFileSync('app/Http/Controllers/League/PlayerController.php', 'utf8');
for (const rule of ['assertNotDuplicate', 'assertRosterEligibility', 'assertRosterSpace', 'assertJerseyAvailable', 'assertPlayerAvailableForSeason']) {
    if (!controller.includes(rule)) throw new Error(`Falta la regla ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
if (!routes.includes("Route::get('/jugadores'")) throw new Error('Falta la ruta de jugadores.');
if (!routes.includes("Route::put('/plantillas/{registration}/baja'")) throw new Error('Falta la ruta de bajas.');
if (!routes.includes("Route::get('/plantillas/{participation}/credenciales.pdf'")) throw new Error('Falta la descarga de credenciales.');

const credentialController = readFileSync('app/Http/Controllers/League/PlayerCredentialController.php', 'utf8');
for (const rule of ["where('status', 'active')", "whereHas('player'", "chunk(8)", "setPaper('a4', 'portrait')"]) {
    if (!credentialController.includes(rule)) throw new Error(`Falta la regla de credenciales: ${rule}`);
}

const credentialView = readFileSync('resources/views/credentials/team.blade.php', 'utf8');
for (const dimension of ['width: 90mm', 'height: 60mm']) {
    if (!credentialView.includes(dimension)) throw new Error(`Falta la dimensión ${dimension}`);
}

const composer = JSON.parse(readFileSync('composer.json', 'utf8'));
if (!composer.require?.['barryvdh/laravel-dompdf']) throw new Error('Falta el generador PDF de credenciales.');

const dockerfile = readFileSync('docker/php/Dockerfile', 'utf8');
for (const dependency of ['docker-php-ext-configure gd', 'libjpeg-turbo-dev', 'libwebp-dev']) {
    if (!dockerfile.includes(dependency)) throw new Error(`Falta soporte de imágenes PDF: ${dependency}`);
}

const permissions = readFileSync('database/seeders/IdentitySeeder.php', 'utf8');
for (const permission of ['players.view', 'players.propose', 'players.manage', 'players.self-update']) {
    if (!permissions.includes(permission)) throw new Error(`Falta el permiso ${permission}`);
}

console.log('Validación estructural del Módulo 6 completada.');
