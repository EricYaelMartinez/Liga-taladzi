import { existsSync, readFileSync, statSync } from 'node:fs';

const required = [
    'config/branding.php',
    'public/images/brand/proliga-logo.png',
    'public/favicon.ico',
    'public/site.webmanifest',
    'resources/views/app.blade.php',
    'resources/js/layouts/AppLayout.vue',
    'resources/js/layouts/GuestLayout.vue',
    'resources/js/pages/Public/LeagueDirectory.vue',
    'resources/js/pages/Public/LeaguePortal.vue',
    'MODULO_19_1_IDENTIDAD_VISUAL.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

for (const asset of ['public/images/brand/proliga-logo.png', 'public/favicon.ico']) {
    if (statSync(asset).size === 0) throw new Error(`El recurso visual está vacío: ${asset}`);
}

const branding = readFileSync('config/branding.php', 'utf8');
for (const value of ['ProLiga', 'Administra tu liga en un solo espacio', '#1B2A4A', '#2E7D32', '#C5A059', '/images/brand/proliga-logo.png', '/favicon.ico']) {
    if (!branding.includes(value)) throw new Error(`Falta el valor de marca: ${value}`);
}

const root = readFileSync('resources/views/app.blade.php', 'utf8');
for (const value of ["config('branding.favicon_path')", '/site.webmanifest', "config('branding.colors.navy')", "config('branding.tagline')"]) {
    if (!root.includes(value)) throw new Error(`Falta metadato de identidad: ${value}`);
}

const inertia = readFileSync('app/Http/Middleware/HandleInertiaRequests.php', 'utf8');
for (const value of ["'brand' => [", "config('branding.name')", "config('branding.logo_path')", "config('branding.colors')"]) {
    if (!inertia.includes(value)) throw new Error(`Falta la marca compartida con la interfaz: ${value}`);
}

const layout = readFileSync('resources/js/layouts/AppLayout.vue', 'utf8');
for (const value of ['brand.logoUrl', 'brand.name', 'context.value?.league.primaryColor', 'context.value?.league.secondaryColor']) {
    if (!layout.includes(value)) throw new Error(`Falta identidad global o personalización por liga: ${value}`);
}

const manifest = JSON.parse(readFileSync('public/site.webmanifest', 'utf8'));
if (manifest.name !== 'ProLiga' || manifest.theme_color !== '#1B2A4A') throw new Error('El manifiesto web no contiene la identidad aprobada.');

console.log('Validación estructural de identidad visual ProLiga completada.');
