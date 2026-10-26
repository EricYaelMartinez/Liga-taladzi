import { readFileSync } from 'node:fs';

const icon = readFileSync('resources/js/components/AppIcon.vue', 'utf8');
const layout = readFileSync('resources/js/layouts/AppLayout.vue', 'utf8');
const dashboard = readFileSync('resources/js/pages/Dashboard.vue', 'utf8');
const competitions = readFileSync('resources/js/pages/League/Competitions/Index.vue', 'utf8');

for (const marker of [
    "variant?: 'line' | 'illustrated'",
    'iconPalette',
    '#1B2A4A',
    '#2E7D32',
    '#C5A059',
    'proliga-icon--illustrated',
]) {
    if (!icon.includes(marker)) throw new Error(`Falta el requisito del sistema de iconos: ${marker}`);
}

for (const [source, marker] of [
    [layout, 'variant="illustrated"'],
    [layout, '<AppIcon name="settings"'],
    [layout, '<AppIcon name="users"'],
    [layout, '<AppIcon name="football"'],
    [layout, '<AppIcon name="clipboard"'],
    [dashboard, '<AppIcon name="calendar" variant="illustrated"'],
    [dashboard, '<AppIcon name="bell" variant="illustrated"'],
    [dashboard, '<AppIcon :name="action.icon" variant="illustrated"'],
    [competitions, 'variant="illustrated"'],
]) {
    if (!source.includes(marker)) throw new Error(`Falta la integración visual: ${marker}`);
}

console.log('Validación del sistema ilustrado de iconos ProLiga: OK');
