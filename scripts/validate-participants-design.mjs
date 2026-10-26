import { readFileSync } from 'node:fs';

const teams = readFileSync('resources/js/pages/League/Teams/Index.vue', 'utf8');
const players = readFileSync('resources/js/pages/League/Players/Index.vue', 'utf8');
const styles = readFileSync('resources/css/app.css', 'utf8');

for (const marker of ['participants-hero', 'participants-tabs', 'participants-stat', 'participants-toolbar', 'entity-card', 'entity-status', 'entity-avatar', 'registration-shell', 'form-section', 'registration-aside', 'file-field']) {
    if (!styles.includes(marker)) throw new Error(`Falta el componente visual compartido: ${marker}`);
}

for (const marker of ['teamSearch', 'teamStatusFilter', 'visibleTeams', 'Equipos y representantes', 'Registrar equipo', 'Identidad del equipo', 'Propietario o representante', 'Nueva participación', 'Cambiar representante']) {
    if (!teams.includes(marker)) throw new Error(`Falta el elemento de Equipos: ${marker}`);
}

for (const marker of ['playerSearch', 'playerStatusFilter', 'playerTeamFilter', 'visiblePlayers', 'Jugadores y plantillas', 'Registrar jugador', 'Asignación deportiva', 'Contacto de emergencia', 'Credenciales', 'Fotografía privada']) {
    if (!players.includes(marker)) throw new Error(`Falta el elemento de Jugadores: ${marker}`);
}

console.log('Validación del rediseño de Equipos y Jugadores: OK');
