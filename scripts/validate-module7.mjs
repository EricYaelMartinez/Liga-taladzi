import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_05_000012_create_player_credentials_and_document_controls.php',
    'app/Domain/Player/Enums/CredentialStatus.php',
    'app/Domain/Player/Models/PlayerCredential.php',
    'app/Domain/Player/Services/PlayerCredentialService.php',
    'app/Http/Controllers/League/PlayerCredentialController.php',
    'app/Http/Controllers/League/PlayerDocumentController.php',
    'resources/views/credentials/team.blade.php',
    'tests/Feature/League/PlayerManagementTest.php',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const migration = readFileSync(required[0], 'utf8');
for (const rule of ['player_credentials', 'player_credentials_one_active_registration', 'original_name', 'size_bytes', 'deleted_at']) {
    if (!migration.includes(rule)) throw new Error(`Falta la estructura: ${rule}`);
}

const service = readFileSync('app/Domain/Player/Services/PlayerCredentialService.php', 'utf8');
for (const rule of ['lockForUpdate', "status->value !== 'active'", 'revoked_at', "sprintf('L%04d-%s-%06d'"]) {
    if (!service.includes(rule)) throw new Error(`Falta la regla de credencial: ${rule}`);
}

const documents = readFileSync('app/Http/Controllers/League/PlayerDocumentController.php', 'utf8');
for (const rule of ["mimes:pdf,jpg,jpeg,png", "'max:5120'", "now()->startOfDay()->gt", 'player.document.downloaded']) {
    if (!documents.includes(rule)) throw new Error(`Falta la regla documental: ${rule}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const permission of ['documents.view', 'documents.manage', 'credentials.manage']) {
    if (!routes.includes(permission)) throw new Error(`Falta proteger rutas con ${permission}`);
}

const view = readFileSync('resources/views/credentials/team.blade.php', 'utf8');
if (!view.includes('activeCredential?->folio')) throw new Error('La credencial impresa no muestra el folio activo.');

console.log('Validación estructural del Módulo 7 completada.');
