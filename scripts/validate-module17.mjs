import { existsSync, readFileSync } from 'node:fs';

const required = [
    'app/Http/Controllers/PublicPortalController.php',
    'resources/js/pages/Public/LeagueDirectory.vue',
    'resources/js/pages/Public/LeaguePortal.vue',
    'tests/Feature/PublicPortalTest.php',
    'MODULO_17_VERIFICACION.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["Route::get('/', [PublicPortalController::class, 'directory'])", "Route::get('/ligas/{league:slug}', [PublicPortalController::class, 'show'])"]) {
    if (!routes.includes(route)) throw new Error(`Falta la ruta pública: ${route}`);
}

const controller = readFileSync(required[0], 'utf8');
for (const rule of ["whereIn('status', ['active', 'finished'])", "where('status', '!=', 'draft')", "where('status', 'approved')", "whereIn('kind', ['suspension', 'disqualification'])"]) {
    if (!controller.includes(rule)) throw new Error(`Falta una regla de publicación: ${rule}`);
}
if (!controller.includes('calculate($selected, publishedOnly: true)')) throw new Error('El cálculo público debe excluir jornadas en borrador.');
for (const privateField of ["'reason' =>", "'legal_basis' =>", "'fine_amount' =>", "'phone' =>", "'email' =>"]) {
    if (controller.includes(privateField)) throw new Error(`El portal intenta publicar el campo privado: ${privateField}`);
}

const portal = readFileSync(required[2], 'utf8');
for (const section of ['Calendario', 'Resultados', 'Posiciones', 'Goleadores', 'Disciplina', 'Sanciones deportivas vigentes']) {
    if (!portal.includes(section)) throw new Error(`Falta la sección pública: ${section}`);
}

const tests = readFileSync(required[3], 'utf8');
for (const check of ['assertDontSee', 'assertNotFound', "missing('activeSanctions.0.reason')", "assertRedirect('/iniciar-sesion')"]) {
    if (!tests.includes(check)) throw new Error(`Falta una comprobación de seguridad: ${check}`);
}

console.log('Validación estructural del Módulo 17 completada.');
