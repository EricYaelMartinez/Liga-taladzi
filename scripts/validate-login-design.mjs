import { existsSync, readFileSync, statSync } from 'node:fs';

const required = [
    'public/images/auth/proliga-stadium.jpg',
    'resources/js/layouts/GuestLayout.vue',
    'resources/js/pages/Auth/Login.vue',
    'MODULO_19_2_ACCESO_VISUAL.md',
];

for (const file of required) {
    if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
}

if (statSync('public/images/auth/proliga-stadium.jpg').size === 0) {
    throw new Error('La imagen del acceso está vacía.');
}

const layout = readFileSync('resources/js/layouts/GuestLayout.vue', 'utf8');
for (const value of [
    '/images/auth/proliga-stadium.jpg',
    'brand.logoUrl',
    'La potencia detrás de',
    'lg:hidden',
    'Portal público',
]) {
    if (!layout.includes(value)) throw new Error(`Falta un elemento del diseño de acceso: ${value}`);
}

const login = readFileSync('resources/js/pages/Auth/Login.vue', 'utf8');
for (const value of [
    "form.post('/iniciar-sesion'",
    "form.reset('password')",
    'showPassword',
    'Recordar mi sesión',
    '¿Olvidaste tu contraseña?',
    'Google y Apple estarán disponibles en una versión posterior.',
    'disabled title="Inicio de sesión con Google: próximamente"',
    'disabled title="Inicio de sesión con Apple: próximamente"',
]) {
    if (!login.includes(value)) throw new Error(`Falta una función o protección del acceso: ${value}`);
}

console.log('Validación estructural del acceso visual ProLiga completada.');
