<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Participation = {
    id: number; status: string; team: { id: number; name: string };
    competition: { id: number; name: string; maximum_roster_size?: number; division: { id: number; name: string }; category: { id: number; name: string }; tournament: { season: { id: number; name: string; starts_on: string; ends_on: string } } };
};
type Registration = {
    id: number; jersey_number: number; status: string; review_reason: string | null; released_at: string | null;
    team: { id: number; name: string; short_name: string };
    team_participation: Participation;
    active_credential: { id: number; folio: string; status: string; issued_at: string } | null;
};
type Movement = { id: number; type: string; reason: string; occurred_at: string; from_team: { id: number; name: string } | null; to_team: { id: number; name: string } | null };
type Player = {
    id: number; user_id?: number | null; full_name: string; birth_date?: string; gender: string; position: string;
    phone?: string | null; email?: string | null; emergency_contact_name?: string; emergency_contact_phone?: string;
    emergency_contact_relationship?: string; guardian_name?: string | null; guardian_phone?: string | null;
    status: string; user: { id: number; name: string; email: string } | null;
    documents: { id: number; type: string; original_name: string | null; mime_type: string | null; size_bytes: number | null; retain_until: string | null; created_at: string }[]; registrations: Registration[]; movements: Movement[];
};
type Membership = { id: number; user_id: number; user: { id: number; name: string; email: string } };
type AvailablePlayer = { id: number; full_name: string; birth_date: string; position: string };
type CredentialParticipation = Participation & {
    active_players_count: number;
    issued_credentials_count: number;
    season: { id: number; name: string; starts_on: string; ends_on: string };
};

const props = defineProps<{
    players: Player[]; canManage: boolean; isRepresentative: boolean; isPlayer: boolean;
    teamParticipations: Participation[]; playerMemberships: Membership[]; availablePlayers: AvailablePlayer[];
    credentialTeams: CredentialParticipation[]; credentialLogosConfigured: number;
}>();
const activeTab = ref(props.isPlayer ? 'players' : 'summary');
const existingPlayer = ref<AvailablePlayer | null>(null);
const linkingPlayer = ref<Player | null>(null);
const editingPlayer = ref<Player | null>(null);
const documentPlayer = ref<Player | null>(null);
const playerSearch = ref('');
const playerStatusFilter = ref('all');
const playerTeamFilter = ref('all');

const createForm = useForm({
    full_name: '', birth_date: '', gender: 'unspecified', position: 'midfielder', phone: '', email: '',
    emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relationship: '',
    guardian_name: '', guardian_phone: '', photo: null as File | null, guardian_consent: null as File | null,
    team_participation_id: null as number | null, jersey_number: null as number | null,
    league_membership_id: null as number | null, reason: '',
});
const registrationForm = useForm({ team_participation_id: null as number | null, jersey_number: null as number | null, reason: '' });
const linkForm = useForm({ league_membership_id: null as number | null, reason: '' });
const profileForm = useForm({ phone: '', email: '', emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relationship: '', reason: '' });
const documentForm = useForm({ document: null as File | null, reason: '' });

const tabs = computed(() => {
    if (props.isPlayer) return [['players', 'Mi perfil']];
    const options = [['summary', 'Resumen'], ['register', 'Registrar jugador'], ['players', props.isRepresentative ? 'Mi plantilla' : 'Jugadores'], ['available', 'Reincorporar'], ['pending', 'Pendientes']];
    if (props.canManage) options.push(['credentials', 'Credenciales']);
    return options;
});
const pending = computed(() => props.players.flatMap((player) => player.registrations.filter((registration) => registration.status === 'pending').map((registration) => ({ player, registration }))));
const activeCount = computed(() => props.players.filter((player) => player.status === 'active').length);
const teamOptions = computed(() => Array.from(new Map(props.players.flatMap((player) => player.registrations.map((registration) => [registration.team.id, registration.team] as const))).values()).sort((a, b) => a.name.localeCompare(b.name, 'es-MX')));
const visiblePlayers = computed(() => props.players.filter((player) => {
    const query = playerSearch.value.trim().toLocaleLowerCase('es-MX');
    const registration = currentRegistration(player);
    const matchesQuery = !query || [player.full_name, player.email ?? '', registration?.team.name ?? ''].some((value) => value.toLocaleLowerCase('es-MX').includes(query));
    const matchesStatus = playerStatusFilter.value === 'all' || player.status === playerStatusFilter.value;
    const matchesTeam = playerTeamFilter.value === 'all' || registration?.team.id === Number(playerTeamFilter.value);
    return matchesQuery && matchesStatus && matchesTeam;
}));
const isMinor = computed(() => createForm.birth_date ? age(createForm.birth_date) < 18 : false);
const labels: Record<string, string> = { pending: 'Pendiente', active: 'Activo', rejected: 'Rechazado', suspended: 'Suspendido', released: 'Baja', inactive: 'Inactivo' };
const positions: Record<string, string> = { goalkeeper: 'Portero', defender: 'Defensa', midfielder: 'Mediocampista', forward: 'Delantero' };

function file(event: Event): File | null { return (event.target as HTMLInputElement).files?.[0] ?? null; }
function initials(name: string): string { return name.split(/\s+/).map((word) => word[0]).join('').slice(0, 2).toUpperCase(); }
function statusClass(status: string): string {
    return ({ active: 'entity-status-active', pending: 'entity-status-pending', suspended: 'entity-status-suspended', rejected: 'entity-status-rejected', released: 'entity-status-neutral', inactive: 'entity-status-neutral' } as Record<string, string>)[status] ?? 'entity-status-neutral';
}
function age(date: string): number { const today = new Date(); const birth = new Date(`${date.slice(0, 10)}T00:00:00`); let value = today.getFullYear() - birth.getFullYear(); if (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate())) value--; return value; }
function currentRegistration(player: Player): Registration | undefined { return player.registrations.find((registration) => ['active', 'suspended'].includes(registration.status)); }
function printCredential(): void { window.print(); }
function submitPlayer() {
    createForm.post('/liga/jugadores', { forceFormData: true, preserveScroll: true, onSuccess: () => { createForm.reset(); activeTab.value = 'players'; } });
}
function transition(registration: Registration, action: string, verb: string) {
    const reason = prompt(`Motivo para ${verb.toLowerCase()} al jugador:`);
    if (reason?.trim()) router.put(`/liga/plantillas/${registration.id}/estado`, { action, reason }, { preserveScroll: true });
}
function release(registration: Registration) {
    const reason = prompt('Motivo de la baja:');
    if (reason?.trim() && confirm('La baja liberará al jugador para otro equipo. ¿Continuar?')) router.put(`/liga/plantillas/${registration.id}/baja`, { reason }, { preserveScroll: true });
}
function submitExisting() {
    if (!existingPlayer.value) return;
    registrationForm.post(`/liga/jugadores/${existingPlayer.value.id}/plantillas`, { preserveScroll: true, onSuccess: () => { existingPlayer.value = null; registrationForm.reset(); } });
}
function submitLink() {
    if (!linkingPlayer.value) return;
    linkForm.post(`/liga/jugadores/${linkingPlayer.value.id}/vincular`, { preserveScroll: true, onSuccess: () => { linkingPlayer.value = null; linkForm.reset(); } });
}
function startProfile(player: Player) {
    editingPlayer.value = player; profileForm.phone = player.phone ?? ''; profileForm.email = player.email ?? '';
    profileForm.emergency_contact_name = player.emergency_contact_name ?? ''; profileForm.emergency_contact_phone = player.emergency_contact_phone ?? '';
    profileForm.emergency_contact_relationship = player.emergency_contact_relationship ?? ''; profileForm.reason = '';
}
function submitProfile() {
    if (!editingPlayer.value) return;
    profileForm.post(`/liga/jugadores/${editingPlayer.value.id}/datos`, { preserveScroll: true, onSuccess: () => { editingPlayer.value = null; profileForm.reset(); } });
}
function issueCredentials(participation: CredentialParticipation) {
    const reason = prompt('Motivo de la emisión de credenciales:');
    if (reason?.trim()) router.post(`/liga/plantillas/${participation.id}/credenciales/emitir`, { reason }, { preserveScroll: true });
}
function revokeCredential(registration: Registration) {
    if (!registration.active_credential) return;
    const reason = prompt(`Motivo para revocar ${registration.active_credential.folio}:`);
    if (reason?.trim() && confirm('La credencial dejará de ser válida. ¿Continuar?')) router.put(`/liga/credenciales/${registration.active_credential.id}/revocar`, { reason }, { preserveScroll: true });
}
function submitDocument() {
    if (!documentPlayer.value) return;
    documentForm.post(`/liga/jugadores/${documentPlayer.value.id}/carta-responsiva`, { forceFormData: true, preserveScroll: true, onSuccess: () => { documentPlayer.value = null; documentForm.reset(); } });
}
function deleteDocument(document: Player['documents'][number]) {
    const reason = prompt('Motivo de eliminación del documento:');
    if (reason?.trim() && confirm('El archivo privado se eliminará definitivamente. ¿Continuar?')) router.delete(`/liga/documentos-jugador/${document.id}`, { data: { reason }, preserveScroll: true });
}
function retentionExpired(document: Player['documents'][number]): boolean { return !!document.retain_until && new Date(`${document.retain_until.slice(0, 10)}T23:59:59`) < new Date(); }
</script>

<template>
    <Head title="Jugadores y plantillas" />
    <AppLayout>
        <section class="participants-page">
            <div class="participants-hero">
                <div class="participants-hero-content">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Participantes · Módulos 6 y 7</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Jugadores y plantillas</h1><p class="mt-2 text-sm text-white/75 sm:text-base">Gestiona altas, documentos privados, historial deportivo y credenciales oficiales desde una vista centralizada.</p></div>
                        <button v-if="!isPlayer" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gold-500 px-4 py-2.5 text-sm font-bold text-league-900 shadow-lg transition hover:bg-gold-400" @click="activeTab = 'register'">＋ Registrar jugador</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto"><div class="participants-tabs">
                <button v-for="tab in tabs" :key="tab[0]" type="button" class="participants-tab" :class="activeTab === tab[0] ? 'participants-tab-active' : ''" @click="activeTab = tab[0]">{{ tab[1] }}</button>
            </div></div>

            <div v-if="activeTab === 'summary' && !isPlayer" class="mt-7 space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div class="participants-stat"><span class="participants-stat-icon">♟</span><div><strong>{{ players.length }}</strong><p>Jugadores registrados</p></div></div><div class="participants-stat"><span class="participants-stat-icon bg-emerald-50 text-emerald-700">✓</span><div><strong>{{ activeCount }}</strong><p>Jugadores activos</p></div></div><div class="participants-stat"><span class="participants-stat-icon bg-amber-50 text-amber-700">◷</span><div><strong>{{ pending.length }}</strong><p>Altas pendientes</p></div></div><div class="participants-stat"><span class="participants-stat-icon bg-sky-50 text-sky-700">↻</span><div><strong>{{ availablePlayers.length }}</strong><p>Disponibles para alta</p></div></div></div>
                <div v-if="!teamParticipations.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Para registrar jugadores debe existir una participación de equipo aprobada y activa.</div>
            </div>

            <div v-if="activeTab === 'credentials' && canManage" class="mt-7 space-y-5">
                <div class="card p-6 sm:p-8">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                        <div>
                            <h2 class="text-xl font-semibold">Credenciales por equipo</h2>
                            <p class="mt-2 max-w-3xl text-sm text-slate-600">Cada archivo contiene únicamente jugadores activos y aprobados, en credenciales de 9 × 6 cm acomodadas para impresión en hojas A4.</p>
                        </div>
                        <a href="/liga/configuracion" class="btn-secondary">Configurar logotipos y colores</a>
                    </div>
                    <div v-if="credentialLogosConfigured < 4" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Hay {{ credentialLogosConfigured }} de 4 logotipos configurados. Puedes generar las credenciales ahora; los espacios faltantes mostrarán un marcador hasta que cargues las imágenes.
                    </div>
                </div>

                <div v-if="!credentialTeams.length" class="card p-12 text-center text-slate-600">No existen equipos con participación activa.</div>
                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <article v-for="participation in credentialTeams" :key="participation.id" class="entity-card flex flex-col justify-between gap-5 p-6 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-league-600">{{ participation.season.name }}</p>
                            <h3 class="mt-1 text-xl font-semibold">{{ participation.team.name }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ participation.competition.name }} · {{ participation.competition.category.name }} · {{ participation.competition.division.name }}</p>
                            <p class="mt-3 text-sm font-medium" :class="participation.active_players_count ? 'text-emerald-700' : 'text-amber-700'">{{ participation.active_players_count }} jugador{{ participation.active_players_count === 1 ? '' : 'es' }} activo{{ participation.active_players_count === 1 ? '' : 's' }} y aprobado{{ participation.active_players_count === 1 ? '' : 's' }}</p>
                            <p v-if="participation.active_players_count" class="mt-1 text-xs text-slate-500">{{ participation.issued_credentials_count }} de {{ participation.active_players_count }} credenciales emitidas</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button v-if="participation.issued_credentials_count < participation.active_players_count" class="btn-secondary" @click="issueCredentials(participation)">Emitir faltantes</button>
                            <a v-if="participation.issued_credentials_count" :href="`/liga/plantillas/${participation.id}/credenciales`" target="_blank" rel="noopener" class="btn-secondary">Vista previa</a>
                            <a v-if="participation.issued_credentials_count" :href="`/liga/plantillas/${participation.id}/credenciales.pdf`" class="btn-primary">Descargar PDF</a>
                            <span v-else class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500">Sin credenciales</span>
                        </div>
                    </article>
                </div>
            </div>

            <form v-if="activeTab === 'register' && !isPlayer" class="registration-shell" @submit.prevent="submitPlayer">
                <header class="registration-heading">
                    <div><span class="registration-step">Nueva alta</span><h2 class="mt-3 text-2xl font-bold">Registrar jugador</h2><p class="mt-1 text-sm text-white/75">Crea el expediente y asígnalo a una plantilla activa.</p></div>
                    <p class="max-w-xs text-xs leading-5 text-white/70"><span class="font-bold text-gold-400">*</span> Los campos marcados son obligatorios.</p>
                </header>

                <div class="registration-grid">
                    <div class="registration-main">
                        <section class="form-section">
                            <div class="form-section-heading"><span class="form-section-icon">☺</span><div><h3>Información personal</h3><p>Datos de identificación que integrarán el expediente del jugador.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2"><label class="form-label">Nombre completo<span class="form-required">*</span></label><input v-model="createForm.full_name" class="form-input" required maxlength="180" placeholder="Nombre y apellidos"><FormError :message="createForm.errors.full_name" /></div>
                                <div><label class="form-label">Fecha de nacimiento<span class="form-required">*</span></label><input v-model="createForm.birth_date" class="form-input" type="date" required><p v-if="createForm.birth_date" class="form-field-hint">Edad calculada: {{ age(createForm.birth_date) }} años.</p><FormError :message="createForm.errors.birth_date" /></div>
                                <div><label class="form-label">Género<span class="form-required">*</span></label><select v-model="createForm.gender" class="form-input" required><option value="unspecified">No especificado</option><option value="male">Masculino</option><option value="female">Femenino</option></select><FormError :message="createForm.errors.gender" /></div>
                            </div>
                        </section>

                        <section class="form-section">
                            <div class="form-section-heading"><span class="form-section-icon">⚽</span><div><h3>Asignación deportiva</h3><p>Plantilla, posición y número que utilizará en la competencia.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_9rem]">
                                <div class="sm:col-span-2 lg:col-span-1"><label class="form-label">Equipo y competencia<span class="form-required">*</span></label><select v-model="createForm.team_participation_id" class="form-input" required><option :value="null" disabled>Selecciona una plantilla</option><option v-for="participation in teamParticipations" :key="participation.id" :value="participation.id">{{ participation.team.name }} · {{ participation.competition.tournament.season.name }} · {{ participation.competition.name }}</option></select><FormError :message="createForm.errors.team_participation_id" /></div>
                                <div><label class="form-label">Posición<span class="form-required">*</span></label><select v-model="createForm.position" class="form-input" required><option value="goalkeeper">Portero</option><option value="defender">Defensa</option><option value="midfielder">Mediocampista</option><option value="forward">Delantero</option></select></div>
                                <div><label class="form-label">Dorsal<span class="form-required">*</span></label><input v-model="createForm.jersey_number" class="form-input" type="number" min="0" max="999" required placeholder="10"><FormError :message="createForm.errors.jersey_number" /></div>
                            </div>
                        </section>

                        <section class="form-section">
                            <div class="form-section-heading"><span class="form-section-icon">☎</span><div><h3>Contacto privado</h3><p>Datos disponibles exclusivamente para la administración de la liga.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Teléfono</label><input v-model="createForm.phone" class="form-input" type="tel" maxlength="30" placeholder="Ej. 951 000 0000"></div>
                                <div><label class="form-label">Correo electrónico</label><input v-model="createForm.email" class="form-input" type="email" maxlength="150" placeholder="jugador@correo.com"><FormError :message="createForm.errors.email" /></div>
                            </div>
                            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5"><h4 class="font-bold text-slate-900">Contacto de emergencia</h4><p class="mt-1 text-xs text-slate-500">Persona a quien contactar ante cualquier eventualidad.</p><div class="mt-4 grid gap-5 sm:grid-cols-3"><div><label class="form-label">Nombre<span class="form-required">*</span></label><input v-model="createForm.emergency_contact_name" class="form-input" required placeholder="Nombre completo"></div><div><label class="form-label">Teléfono<span class="form-required">*</span></label><input v-model="createForm.emergency_contact_phone" class="form-input" type="tel" required placeholder="951 000 0000"></div><div><label class="form-label">Parentesco<span class="form-required">*</span></label><input v-model="createForm.emergency_contact_relationship" class="form-input" required placeholder="Madre, padre, tutor…"></div></div></div>
                        </section>

                        <section v-if="isMinor" class="form-section border-amber-200 bg-amber-50/50">
                            <div class="form-section-heading border-amber-200"><span class="form-section-icon bg-amber-100 text-amber-800">⚑</span><div><h3 class="text-amber-950">Autorización para menor de edad</h3><p class="text-amber-800">Estos datos son obligatorios porque el jugador tiene menos de 18 años.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Nombre del tutor<span class="form-required">*</span></label><input v-model="createForm.guardian_name" class="form-input" required><FormError :message="createForm.errors.guardian_name" /></div><div><label class="form-label">Teléfono del tutor<span class="form-required">*</span></label><input v-model="createForm.guardian_phone" class="form-input" type="tel" required><FormError :message="createForm.errors.guardian_phone" /></div><div class="sm:col-span-2"><label class="form-label">Carta responsiva firmada<span class="form-required">*</span></label><div class="file-field bg-white"><input class="form-input" type="file" required accept="application/pdf,image/jpeg,image/png" @change="createForm.guardian_consent = file($event)"><p class="form-field-hint">PDF, JPG o PNG.</p></div><FormError :message="createForm.errors.guardian_consent" /></div></div>
                        </section>

                        <section class="form-section">
                            <div class="form-section-heading"><span class="form-section-icon">▣</span><div><h3>Fotografía y acceso</h3><p>Completa la imagen de la credencial y, opcionalmente, vincula una cuenta.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Fotografía del jugador<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.photo = file($event)"><p class="form-field-hint">JPG, PNG o WEBP. Rostro visible y fondo uniforme.</p></div><FormError :message="createForm.errors.photo" /></div>
                                <div v-if="canManage"><label class="form-label">Cuenta de usuario</label><select v-model="createForm.league_membership_id" class="form-input"><option :value="null">Sin acceso al sistema</option><option v-for="membership in playerMemberships" :key="membership.id" :value="membership.id">{{ membership.user.name }} · {{ membership.user.email }}</option></select><p class="form-field-hint">La vinculación es opcional y puede realizarse después.</p><FormError :message="createForm.errors.league_membership_id" /></div>
                            </div>
                        </section>

                        <section class="form-section">
                            <div class="form-section-heading"><span class="form-section-icon">✎</span><div><h3>Motivo del alta</h3><p>Agrega una referencia administrativa para el historial.</p></div></div>
                            <label class="form-label">Motivo<span class="form-required">*</span></label><textarea v-model="createForm.reason" class="form-input min-h-24" required maxlength="500" placeholder="Ej. Inscripción inicial para la temporada vigente"></textarea>
                        </section>
                    </div>

                    <aside class="registration-aside">
                        <div class="registration-note"><h3>Expediente completo</h3><ul><li>Comprueba nombre y fecha de nacimiento.</li><li>Verifica equipo, posición y dorsal.</li><li>Utiliza una fotografía apta para credencial.</li><li>Si es menor, adjunta la autorización del tutor.</li></ul></div>
                        <div v-if="!teamParticipations.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">No existen plantillas activas disponibles. Registra y aprueba primero la participación de un equipo.</div>
                        <div class="registration-actions"><p class="mb-4 text-xs leading-5 text-slate-600">El alta quedará registrada con su trazabilidad administrativa.</p><button class="btn-primary" :disabled="createForm.processing || !teamParticipations.length"><span v-if="createForm.processing">Enviando…</span><span v-else>Enviar alta</span></button></div>
                    </aside>
                </div>
            </form>

            <div v-if="activeTab === 'players'" class="mt-7 space-y-5">
                <div v-if="!isPlayer" class="participants-toolbar"><div><label class="form-label">Buscar jugador</label><input v-model="playerSearch" class="form-input" type="search" placeholder="Nombre, correo o equipo"></div><div><label class="form-label">Estado</label><select v-model="playerStatusFilter" class="form-input"><option value="all">Todos</option><option v-for="(label, value) in labels" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Equipo actual</label><select v-model="playerTeamFilter" class="form-input"><option value="all">Todos los equipos</option><option v-for="team in teamOptions" :key="team.id" :value="String(team.id)">{{ team.name }}</option></select></div></div>
                <div v-if="!visiblePlayers.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/80 p-12 text-center text-slate-600"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-league-50 text-2xl text-league-700">♟</div><p class="mt-4 font-bold text-slate-800">{{ players.length ? 'Ningún jugador coincide con los filtros.' : 'No hay jugadores disponibles para este acceso.' }}</p></div>
                <article v-for="player in visiblePlayers" :key="player.id" class="entity-card">
                    <div class="flex flex-col justify-between gap-5 p-6 lg:flex-row"><div class="flex min-w-0 gap-4"><div class="entity-avatar">{{ initials(player.full_name) }}</div><div class="min-w-0"><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-bold text-slate-900">{{ player.full_name }}</h2><span class="entity-status" :class="statusClass(player.status)">{{ labels[player.status] }}</span></div><div class="mt-3 flex flex-wrap gap-2"><span class="entity-meta">⚽ {{ positions[player.position] }}</span><span v-if="player.birth_date" class="entity-meta">◷ {{ age(player.birth_date) }} años</span><span v-if="currentRegistration(player)" class="entity-meta">♜ {{ currentRegistration(player)?.team.name }} · #{{ currentRegistration(player)?.jersey_number }}</span></div><p v-if="player.user" class="mt-3 text-xs font-semibold text-emerald-700">Cuenta vinculada: {{ player.user.email }}</p><p v-else-if="canManage" class="mt-3 text-xs font-semibold text-amber-700">Jugador sin acceso al sistema</p></div></div><div class="flex flex-wrap content-start gap-2"><button v-if="canManage || isPlayer" class="btn-secondary" @click="startProfile(player)">Actualizar contacto</button><template v-if="canManage"><a :href="`/liga/jugadores/${player.id}/archivo/photo`" class="btn-secondary">Fotografía privada</a><button v-if="player.birth_date && age(player.birth_date) < 18" class="btn-secondary" @click="documentPlayer = player">Subir carta</button><button v-if="!player.user_id && playerMemberships.length" class="btn-secondary" @click="linkingPlayer = player">Vincular cuenta</button></template></div></div>
                    <div v-if="canManage && player.documents.length" class="mx-6 mb-5 rounded-xl border border-slate-200 p-4"><h3 class="text-sm font-semibold">Documentos privados</h3><div class="mt-3 space-y-2"><div v-for="document in player.documents" :key="document.id" class="flex flex-col justify-between gap-2 text-sm sm:flex-row sm:items-center"><div><span class="font-medium">Carta responsiva</span><span class="text-slate-500"> · conservar hasta {{ document.retain_until ?? 'sin fecha' }}</span></div><div class="flex gap-2"><a :href="`/liga/documentos-jugador/${document.id}`" class="text-league-700 underline">Descargar</a><button v-if="retentionExpired(document)" class="text-red-700 underline" @click="deleteDocument(document)">Eliminar</button></div></div></div></div>
                    <div v-if="isPlayer" class="mx-5 mb-5 rounded-2xl border-2 border-league-700 bg-white p-5 shadow-sm print:m-0 print:shadow-none"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.2em] text-league-600">Credencial digital</p><h3 class="mt-2 text-2xl font-bold text-league-900">{{ player.full_name }}</h3><p class="mt-1 text-sm text-slate-600">{{ currentRegistration(player)?.team.name ?? 'Sin equipo activo' }} · Dorsal #{{ currentRegistration(player)?.jersey_number ?? '—' }}</p><p v-if="currentRegistration(player)?.active_credential" class="mt-2 text-sm font-semibold text-league-800">Folio: {{ currentRegistration(player)?.active_credential?.folio }}</p><p class="mt-3 text-sm font-semibold">Estado: {{ labels[player.status] }}</p></div><button type="button" class="btn-secondary print:hidden" @click="printCredential">Imprimir</button></div><p class="mt-4 border-t border-slate-200 pt-3 text-xs text-slate-500">Credencial sin QR. La fotografía y documentos permanecen privados para administración.</p></div>
                    <div class="border-t border-slate-200 bg-slate-50 p-5"><h3 class="text-sm font-semibold">Historial de plantillas</h3><div class="mt-3 space-y-3"><div v-for="registration in player.registrations" :key="registration.id" class="flex flex-col justify-between gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-center"><div><p class="font-medium">#{{ registration.jersey_number }} · {{ registration.team.name }}</p><p class="mt-1 text-xs text-slate-500">{{ registration.team_participation.competition.tournament.season.name }} · {{ registration.team_participation.competition.name }} · {{ labels[registration.status] }}</p><p v-if="registration.active_credential" class="mt-1 text-xs font-semibold text-emerald-700">Credencial {{ registration.active_credential.folio }}</p></div><div class="flex flex-wrap gap-2"><template v-if="canManage"><button v-if="registration.status === 'pending'" class="btn-primary" @click="transition(registration, 'approve', 'Aprobar')">Aprobar</button><button v-if="registration.status === 'pending'" class="btn-danger" @click="transition(registration, 'reject', 'Rechazar')">Rechazar</button><button v-if="registration.status === 'active'" class="btn-danger" @click="transition(registration, 'suspend', 'Suspender')">Suspender</button><button v-if="registration.status === 'suspended'" class="btn-primary" @click="transition(registration, 'reactivate', 'Reactivar')">Reactivar</button><button v-if="registration.active_credential" class="btn-secondary" @click="revokeCredential(registration)">Revocar credencial</button></template><button v-if="['active', 'suspended'].includes(registration.status)" class="btn-secondary" @click="release(registration)">Registrar baja</button></div></div></div></div>
                </article>
            </div>

            <div v-if="activeTab === 'available' && !isPlayer" class="mt-7 grid gap-4 md:grid-cols-2"><div v-if="!availablePlayers.length" class="card p-10 text-center text-slate-600 md:col-span-2">No hay jugadores liberados disponibles.</div><div v-for="player in availablePlayers" :key="player.id" class="card flex items-center justify-between gap-4 p-5"><div><p class="font-semibold">{{ player.full_name }}</p><p class="mt-1 text-sm text-slate-500">{{ positions[player.position] }} · {{ age(player.birth_date) }} años</p></div><button class="btn-primary" @click="existingPlayer = player">Solicitar alta</button></div></div>

            <div v-if="activeTab === 'pending' && !isPlayer" class="mt-7 card p-6"><h2 class="text-xl font-semibold">Altas pendientes</h2><div v-if="!pending.length" class="mt-4 text-sm text-slate-500">No existen solicitudes pendientes.</div><div v-else class="mt-4 divide-y divide-slate-200"><div v-for="item in pending" :key="item.registration.id" class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"><div><p class="font-medium">{{ item.player.full_name }}</p><p class="text-sm text-slate-500">#{{ item.registration.jersey_number }} · {{ item.registration.team.name }}</p></div><div v-if="canManage" class="flex gap-2"><button class="btn-primary" @click="transition(item.registration, 'approve', 'Aprobar')">Aprobar</button><button class="btn-danger" @click="transition(item.registration, 'reject', 'Rechazar')">Rechazar</button></div><span v-else class="text-sm text-amber-700">Esperando revisión administrativa</span></div></div></div>
        </section>

        <div v-if="existingPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="existingPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitExisting"><h2 class="text-xl font-semibold">Reincorporar jugador</h2><p class="mt-1 text-sm text-slate-500">{{ existingPlayer.full_name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Equipo y competencia</label><select v-model="registrationForm.team_participation_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="participation in teamParticipations" :key="participation.id" :value="participation.id">{{ participation.team.name }} · {{ participation.competition.tournament.season.name }}</option></select></div><div><label class="form-label">Dorsal</label><input v-model="registrationForm.jersey_number" type="number" min="0" max="999" class="form-input" required><FormError :message="registrationForm.errors.jersey_number" /></div><div><label class="form-label">Motivo</label><textarea v-model="registrationForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="existingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="registrationForm.processing">Enviar</button></div></form></div>

        <div v-if="linkingPlayer && canManage" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="linkingPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitLink"><h2 class="text-xl font-semibold">Vincular cuenta</h2><p class="mt-1 text-sm text-slate-500">{{ linkingPlayer.full_name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Usuario con rol Jugador</label><select v-model="linkForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="membership in playerMemberships" :key="membership.id" :value="membership.id">{{ membership.user.name }} · {{ membership.user.email }}</option></select></div><div><label class="form-label">Motivo</label><textarea v-model="linkForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="linkingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="linkForm.processing">Vincular</button></div></form></div>

        <div v-if="editingPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="editingPlayer = null"><form class="w-full max-w-2xl rounded-2xl bg-white p-6" @submit.prevent="submitProfile"><h2 class="text-xl font-semibold">Actualizar datos de contacto</h2><p class="mt-1 text-sm text-slate-500">{{ editingPlayer.full_name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Teléfono</label><input v-model="profileForm.phone" class="form-input"></div><div><label class="form-label">Correo</label><input v-model="profileForm.email" class="form-input" type="email"></div><div><label class="form-label">Contacto de emergencia</label><input v-model="profileForm.emergency_contact_name" class="form-input" required></div><div><label class="form-label">Teléfono de emergencia</label><input v-model="profileForm.emergency_contact_phone" class="form-input" required></div><div><label class="form-label">Parentesco</label><input v-model="profileForm.emergency_contact_relationship" class="form-input" required></div><div><label class="form-label">Motivo</label><input v-model="profileForm.reason" class="form-input" required maxlength="500"></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="editingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="profileForm.processing">Guardar</button></div></form></div>

        <div v-if="documentPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="documentPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitDocument"><h2 class="text-xl font-semibold">Subir carta responsiva</h2><p class="mt-1 text-sm text-slate-500">{{ documentPlayer.full_name }} · archivo privado de hasta 5 MB</p><div class="mt-5 space-y-4"><div><label class="form-label">PDF, JPG o PNG</label><input class="form-input" type="file" required accept="application/pdf,image/jpeg,image/png" @change="documentForm.document = file($event)"><FormError :message="documentForm.errors.document" /></div><div><label class="form-label">Motivo</label><textarea v-model="documentForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="documentPlayer = null">Cancelar</button><button class="btn-primary" :disabled="documentForm.processing">Guardar documento</button></div></form></div>
    </AppLayout>
</template>
