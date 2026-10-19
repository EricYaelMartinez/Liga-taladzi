import { existsSync, readFileSync } from 'node:fs';

const required = [
    'public/images/auth/proliga-stadium.jpg',
    'public/images/public/league-stadium.jpg',
    'app/Http/Controllers/PublicPortalController.php',
    'resources/js/pages/Public/LeagueDirectory.vue',
    'resources/js/pages/Public/LeaguePortal.vue',
    'tests/Feature/PublicPortalTest.php',
    'MODULO_19_3_PORTAL_PUBLICO_VISUAL.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

const controller = readFileSync('app/Http/Controllers/PublicPortalController.php', 'utf8');
for (const value of [
    "where('status', 'active')",
    "Storage::disk('public')->url($league->logo_path)",
    "'featured_competition'",
    "'genders'",
    "whereIn('status', ['active', 'finished'])",
]) {
    if (!controller.includes(value)) throw new Error(`Falta una regla pública del servidor: ${value}`);
}

const page = readFileSync('resources/js/pages/Public/LeagueDirectory.vue', 'utf8');
for (const value of [
    '/images/auth/proliga-stadium.jpg',
    'filteredLeagues',
    'league.logo_url',
    'featured_competition',
    'Buscar por liga, división o categoría',
    'Mostrando {{ filteredLeagues.length }} de {{ leagues.length }} ligas activas',
]) {
    if (!page.includes(value)) throw new Error(`Falta un elemento del directorio público: ${value}`);
}

const portal = readFileSync('resources/js/pages/Public/LeaguePortal.vue', 'utf8');
for (const value of [
    '/images/public/league-stadium.jpg',
    'Portal público oficial',
    'Detalles del partido',
    'match.home_crest_url',
    'match.away_crest_url',
    'selectedMatch',
]) {
    if (!portal.includes(value)) throw new Error(`Falta un elemento del portal público de liga: ${value}`);
}

for (const value of ["'home_crest_url'", "'away_crest_url'"]) {
    if (!controller.includes(value)) throw new Error(`Falta un escudo público del servidor: ${value}`);
}

console.log('Validación estructural del directorio y portal público visual completada.');
