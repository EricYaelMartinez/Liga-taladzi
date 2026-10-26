import fs from 'node:fs';

const read = (path) => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const dashboard = read('resources/js/pages/Dashboard.vue');
const layout = read('resources/js/layouts/AppLayout.vue');
const controller = read('app/Http/Controllers/DashboardController.php');

const expectations = [
    [dashboard, 'Accesos rápidos'],
    [dashboard, 'Próximos encuentros'],
    [dashboard, 'Actividad reciente'],
    [dashboard, 'Contexto aislado y activo'],
    [dashboard, "can('schedule.manage')"],
    [layout, 'Cambiar liga o rol'],
    [layout, 'userInitials'],
    [layout, 'desktopMenuOpen'],
    [layout, 'closeMenusOnOutsideClick'],
    [layout, "router.on('start'"],
    [layout, '/images/public/league-stadium.jpg'],
    [controller, "whereHas('competition.tournament.season'"],
    [controller, "when(! $hasPermission('audit.view')"],
];

for (const [source, fragment] of expectations) {
    if (!source.includes(fragment)) {
        throw new Error(`Falta el requisito estructural del dashboard: ${fragment}`);
    }
}

console.log('Validación estructural del panel administrado ProLiga completada.');
