import { access, readFile } from 'node:fs/promises';

const requiredFiles = [
    '.env.example',
    'artisan',
    'bootstrap/app.php',
    'bootstrap/cache/.gitignore',
    'compose.yaml',
    'composer.json',
    'docker/nginx/default.conf',
    'docker/php/Dockerfile',
    'package.json',
    'public/index.php',
    'resources/js/app.ts',
    'resources/js/pages/Welcome.vue',
    'routes/web.php',
    'tests/Feature/EnvironmentTest.php',
    'tests/Unit/.gitkeep',
    'vite.config.ts',
];

const errors = [];

for (const file of requiredFiles) {
    try {
        await access(file);
    } catch {
        errors.push(`Falta el archivo requerido: ${file}`);
    }
}

const composer = JSON.parse(await readFile('composer.json', 'utf8'));
const npm = JSON.parse(await readFile('package.json', 'utf8'));
const compose = await readFile('compose.yaml', 'utf8');
const environment = await readFile('.env.example', 'utf8');
const dockerfile = await readFile('docker/php/Dockerfile', 'utf8');
const entrypoint = await readFile('docker/php/entrypoint.sh', 'utf8');
const phpunit = await readFile('phpunit.xml', 'utf8');

for (const dependency of ['laravel/framework', 'inertiajs/inertia-laravel', 'predis/predis']) {
    if (!composer.require?.[dependency]) {
        errors.push(`Falta la dependencia Composer: ${dependency}`);
    }
}

for (const incompatible of ['laravel/tinker', 'laravel/pail', 'nunomaduro/collision']) {
    if (composer.require?.[incompatible] || composer['require-dev']?.[incompatible]) {
        errors.push(`Dependencia auxiliar no permitida en la base Laravel 13: ${incompatible}`);
    }
}

for (const dependency of ['vue', '@inertiajs/vue3']) {
    if (!npm.dependencies?.[dependency]) {
        errors.push(`Falta la dependencia NPM: ${dependency}`);
    }
}

for (const service of ['app:', 'nginx:', 'frontend:', 'db:', 'redis:']) {
    if (!compose.includes(service)) {
        errors.push(`Falta el servicio Docker: ${service.slice(0, -1)}`);
    }
}

if (!compose.includes('postgres_data:/var/lib/postgresql')) {
    errors.push('El volumen de PostgreSQL 18 no usa /var/lib/postgresql.');
}

if (!compose.includes('/tmp/liga-app-ready') || !compose.includes('condition: service_healthy')) {
    errors.push('Falta la espera de salud del contenedor PHP.');
}

if (/^\s*opcache\s*\\?$/m.test(dockerfile)) {
    errors.push('OPcache no debe recompilarse en la imagen local de PHP 8.5.');
}

if (entrypoint.indexOf('mkdir -p') > entrypoint.indexOf('composer install')) {
    errors.push('Las carpetas escribibles deben crearse antes de ejecutar Composer.');
}

if (!entrypoint.includes('composer install --no-interaction --prefer-dist --no-progress')) {
    errors.push('Composer debe poder reparar instalaciones incompletas al iniciar.');
}

if (!entrypoint.includes('chmod -R a+rwX storage')) {
    errors.push('Storage debe ser escribible por PHP en Docker Desktop.');
}

if (!npm.scripts?.['validate:module0'] || !composer.scripts?.test?.includes('@php vendor/bin/phpunit')) {
    errors.push('Falta la ejecución directa de PHPUnit.');
}

const testingKey = phpunit.match(/name="APP_KEY" value="base64:([^"]+)"/)?.[1];

if (!testingKey || Buffer.from(testingKey, 'base64').length !== 32) {
    errors.push('La clave APP_KEY de PHPUnit debe decodificar exactamente 32 bytes.');
}

for (const variable of ['APP_KEY=', 'DB_CONNECTION=pgsql', 'REDIS_CLIENT=predis', 'REDIS_HOST=redis']) {
    if (!environment.includes(variable)) {
        errors.push(`Falta la configuración: ${variable}`);
    }
}

if (errors.length > 0) {
    console.error(errors.join('\n'));
    process.exit(1);
}

console.log(`Módulo 0 válido: ${requiredFiles.length} archivos y configuración base verificados.`);
