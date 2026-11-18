<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import AppIcon from '../../../components/AppIcon.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Participation = {
    id: number; status: string; team: { id: number; name: string; crest_url?: string | null };
    competition: { id: number; name: string; maximum_roster_size?: number; division: { id: number; name: string }; category: { id: number; name: string }; tournament: { season: { id: number; name: string; starts_on: string; ends_on: string } } };
};
type Registration = {
    id: number; jersey_number: number; status: string; review_reason: string | null; released_at: string | null;
    team: { id: number; name: string; short_name: string; crest_url?: string | null };
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
const expandedRosters = ref<Record<number, boolean>>({});
const registrationStep = ref(0);
const filePreview = ref<string | null>(null);
const registrationSteps = ['Datos personales', 'Asignación deportiva', 'Contacto y acceso', 'Documentos y revisión'];
const completedSections = computed(() => [
    Boolean(createForm.full_name.trim() && createForm.birth_date && createForm.gender),
    Boolean(createForm.team_participation_id && createForm.position && createForm.jersey_number !== null),
    Boolean(createForm.emergency_contact_name.trim() && createForm.emergency_contact_phone.trim() && createForm.emergency_contact_relationship.trim()),
    Boolean(createForm.photo && createForm.reason.trim() && (!isMinor.value || (createForm.guardian_name && createForm.guardian_phone && createForm.guardian_consent))),
]);
const registrationProgress = computed(() => Math.round(completedSections.value.filter(Boolean).length / 4 * 100));
const approvedCount = computed(() => props.players.flatMap(p => p.registrations).filter(r => r.status === 'active').length);
const rejectedCount = computed(() => props.players.flatMap(p => p.registrations).filter(r => r.status === 'rejected').length);
function updatePlayerPhoto(event: Event) {
    const selected = file(event);
    createForm.photo = selected;
    if (filePreview.value) URL.revokeObjectURL(filePreview.value);
    filePreview.value = selected ? URL.createObjectURL(selected) : null;
}
function toggleRoster(id: number) { expandedRosters.value[id] = !(expandedRosters.value[id] ?? true); }
function jumpToStep(index: number) { registrationStep.value = index; document.getElementById(`player-form-step-${index}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
const activeTab = ref(props.isPlayer ? 'players' : 'summary');
const tabIcons: Record<string, string> = { summary: 'chart', register: 'user', players: 'users', available: 'refresh', pending: 'clock', credentials: 'id' };
const recentPending = computed(() => pending.value.slice(0, 5));
const existingPlayer = ref<AvailablePlayer | null>(null);
const linkingPlayer = ref<Player | null>(null);
const editingPlayer = ref<Player | null>(null);
const documentPlayer = ref<Player | null>(null);
const playerSearch = ref('');
const playerStatusFilter = ref('all');
const playerTeamFilter = ref('all');
const playerSort = ref('recent');
const sortedVisiblePlayers = computed(() => [...visiblePlayers.value].sort((a, b) => playerSort.value === 'name' ? a.full_name.localeCompare(b.full_name, 'es-MX') : b.id - a.id));

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
    createForm.post('/liga/jugadores', { forceFormData: true, preserveScroll: true, onSuccess: () => { createForm.reset(); if (filePreview.value) URL.revokeObjectURL(filePreview.value); filePreview.value = null; registrationStep.value = 0; activeTab.value = 'players'; } });
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
            <div class="participants-hero players-hero proliga-unified-hero">
                <div class="participants-hero-content">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Participantes · Módulos 6 y 7</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Jugadores y plantillas</h1><p class="mt-2 text-sm text-white/75 sm:text-base">Gestiona altas, documentos privados, historial deportivo y credenciales oficiales desde una vista centralizada.</p></div>
                        <button v-if="!isPlayer" class="players-main-action" @click="activeTab = 'register'"><AppIcon name="user" :size="19" /> Registrar jugador</button>
                    </div>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto"><div class="participants-tabs">
                <button v-for="tab in tabs" :key="tab[0]" type="button" class="participants-tab" :class="activeTab === tab[0] ? 'participants-tab-active' : ''" @click="activeTab = tab[0]"><AppIcon :name="tabIcons[tab[0]] ?? 'file'"  :size="21" /> <span>{{ tab[1] }}</span></button>
            </div></div>

            <div v-if="activeTab === 'summary' && !isPlayer" class="mt-7 space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <button class="player-stat" type="button" @click="activeTab = 'players'"><span class="player-stat-icon tone-blue"><AppIcon name="users" variant="illustrated" :size="48" /></span><span class="player-stat-text"><strong>{{ players.length }}</strong><span>Jugadores registrados</span></span><AppIcon name="switch" :size="17" class="player-stat-arrow" /></button>
                    <button class="player-stat" type="button" @click="activeTab = 'players'; playerStatusFilter = 'active'"><span class="player-stat-icon tone-green"><AppIcon name="check" variant="illustrated" :size="48" /></span><span class="player-stat-text"><strong>{{ activeCount }}</strong><span>Jugadores activos</span></span><AppIcon name="switch" :size="17" class="player-stat-arrow" /></button>
                    <button class="player-stat" type="button" @click="activeTab = 'pending'"><span class="player-stat-icon tone-amber"><AppIcon name="clock" variant="illustrated" :size="48" /></span><span class="player-stat-text"><strong>{{ pending.length }}</strong><span>Altas pendientes</span></span><AppIcon name="switch" :size="17" class="player-stat-arrow" /></button>
                    <button class="player-stat" type="button" @click="activeTab = 'available'"><span class="player-stat-icon tone-blue"><AppIcon name="refresh" variant="illustrated" :size="48" /></span><span class="player-stat-text"><strong>{{ availablePlayers.length }}</strong><span>Disponibles para alta</span></span><AppIcon name="switch" :size="17" class="player-stat-arrow" /></button>
                </div>
                <div class="grid gap-5 xl:grid-cols-[1.45fr_.85fr]">
                    <section class="player-dashboard-panel">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div class="flex items-center gap-3"><span class="player-heading-icon"><AppIcon name="file" :size="24" /></span><div><h2 class="player-panel-title">Últimas solicitudes</h2><p class="player-panel-subtitle">Revisa las solicitudes de jugadores más recientes.</p></div></div><button type="button" class="btn-secondary" @click="activeTab = 'pending'">Ver todas</button></div>
                        <div v-if="!recentPending.length" class="player-empty player-empty-large"><span class="player-empty-art"><AppIcon name="file" :size="64" /><span class="player-art-check"><AppIcon name="check" :size="25" /></span></span><strong>No hay solicitudes pendientes</strong><span>Las nuevas altas aparecerán aquí para su revisión.</span></div>
                        <div v-else class="mt-5 space-y-2"><div v-for="item in recentPending" :key="item.registration.id" class="player-request-line"><span class="request-line-avatar">{{ initials(item.player.full_name) }}</span><div class="min-w-0 flex-1"><strong class="block truncate text-sm text-slate-900">{{ item.player.full_name }}</strong><span class="block truncate text-xs text-slate-500">{{ item.registration.team.name }} · #{{ item.registration.jersey_number }}</span></div><span class="entity-status entity-status-pending">Pendiente</span><button type="button" class="player-inline-link" @click="activeTab = 'pending'">Revisar</button></div></div>
                    </section>
                    <section class="player-dashboard-panel"><div class="flex items-center gap-3"><span class="player-heading-icon"><AppIcon name="bolt" :size="24" /></span><div><h2 class="player-panel-title">Acciones rápidas</h2><p class="player-panel-subtitle">Accede a las funciones principales del módulo.</p></div></div><div class="mt-5 space-y-2">
                        <button type="button" class="player-quick-link" @click="activeTab = 'register'"><AppIcon name="user" variant="illustrated" :size="34" /> Registrar nuevo jugador <span>›</span></button>
                        <button type="button" class="player-quick-link" @click="activeTab = 'players'"><AppIcon name="users" variant="illustrated" :size="34" /> Gestionar plantillas <span>›</span></button>
                        <button type="button" class="player-quick-link" @click="activeTab = 'available'"><AppIcon name="refresh" variant="illustrated" :size="34" /> Reincorporar jugador <span>›</span></button>
                        <button type="button" class="player-quick-link" @click="activeTab = 'pending'"><AppIcon name="clock" variant="illustrated" :size="34" /> Solicitudes pendientes <span>›</span></button>
                        <button v-if="canManage" type="button" class="player-quick-link" @click="activeTab = 'credentials'"><AppIcon name="id" variant="illustrated" :size="34" /> Administrar credenciales <span>›</span></button>
                    </div></section>
                </div>
                <div v-if="!teamParticipations.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Para registrar jugadores debe existir una participación de equipo aprobada y activa.</div>
            </div>

            <div v-if="activeTab === 'credentials' && canManage" class="mt-5 space-y-4">
                <section class="player-dashboard-panel">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-4"><span class="player-heading-icon"><AppIcon name="id" :size="27" /></span><div><h2 class="player-panel-title">Credenciales por equipo</h2><p class="player-panel-subtitle max-w-3xl">Genera credenciales oficiales en PDF para cada equipo. Cada archivo incluye jugadores activos y aprobados en formato de 9 × 6 cm, listo para hojas A4.</p></div></div>
                        <a href="/liga/configuracion" class="btn-secondary flex items-center gap-2"><AppIcon name="settings" :size="17" /> Configurar logotipos y colores</a>
                    </div>
                    <div v-if="credentialLogosConfigured < 4" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-900"><AppIcon name="info" :size="16" class="mr-2 inline-block" /> Hay <strong>{{ credentialLogosConfigured }} de 4 logotipos</strong> configurados. Las credenciales seguirán funcionando y los logotipos opcionales ausentes no mostrarán imágenes rotas.</div>
                </section>
                <div v-if="!credentialTeams.length" class="player-dashboard-panel player-empty py-12">No existen equipos con participación activa.</div>
                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <article v-for="participation in credentialTeams" :key="participation.id" class="player-dashboard-panel credential-team-card">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="team-crest-frame"><img v-if="participation.team.crest_url" :src="participation.team.crest_url" :alt="`Escudo de ${participation.team.name}`" loading="lazy" /><AppIcon v-else name="shield" :size="36" /></div>
                            <div class="min-w-0 flex-1"><span class="player-season-chip">{{ participation.season.name }}</span><h3 class="mt-2 text-lg font-extrabold text-slate-950">{{ participation.team.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ participation.competition.name }} · {{ participation.competition.category.name }} · {{ participation.competition.division.name }}</p><p class="mt-3 text-sm font-semibold text-emerald-700">● {{ participation.active_players_count }} jugadores activos y aprobados</p><p class="mt-1 text-xs text-slate-500">{{ participation.issued_credentials_count }} de {{ participation.active_players_count }} credenciales emitidas</p></div>
                        </div>
                        <div class="credential-layout-preview" aria-label="Referencia visual de credencial; no sustituye el PDF oficial"><div class="flex items-center gap-2"><div class="h-9 w-9 rounded-lg bg-white/90 p-1"><img v-if="participation.team.crest_url" class="h-full w-full object-contain" :src="participation.team.crest_url" :alt="participation.team.name" /><AppIcon v-else name="shield" :size="22" /></div><div><span class="block text-[9px] font-extrabold uppercase tracking-wider">Credencial oficial</span><strong class="block max-w-56 truncate text-xs">{{ participation.team.name }}</strong></div></div><span class="rounded-md bg-white/20 px-2 py-1 text-[10px] font-semibold">{{ participation.season.name }}</span></div>
                        <div class="credential-team-actions"><button v-if="participation.issued_credentials_count < participation.active_players_count" class="btn-secondary" @click="issueCredentials(participation)">Emitir faltantes</button><a v-if="participation.issued_credentials_count" :href="`/liga/plantillas/${participation.id}/credenciales`" target="_blank" rel="noopener" class="btn-secondary flex items-center gap-2"><AppIcon name="eye" :size="16" /> Vista previa</a><a v-if="participation.issued_credentials_count" :href="`/liga/plantillas/${participation.id}/credenciales.pdf`" class="btn-primary flex items-center gap-2"><AppIcon name="download" :size="16" /> Descargar PDF</a><span v-if="!participation.issued_credentials_count && participation.active_players_count === 0" class="text-sm text-slate-500">Sin credenciales disponibles</span></div>
                    </article>
                </div>
            </div>

            <form v-if="activeTab === 'register' && !isPlayer" class="registration-shell players-registration-shell" @submit.prevent="submitPlayer">
                <div class="players-progress-steps"><button v-for="(step, index) in registrationSteps" :key="step" type="button" class="players-progress-step" :class="{ 'is-current': registrationStep === index, 'is-complete': completedSections[index] }" @click="jumpToStep(index)"><span>{{ index + 1 }}</span>{{ step }}</button></div>

                <div class="registration-grid">
                    <div class="registration-main">
                        <section id="player-form-step-0" class="form-section player-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="user" :size="20" /></span><div><h3>Información personal</h3><p>Datos de identificación que integrarán el expediente del jugador.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2"><label class="form-label">Nombre completo<span class="form-required">*</span></label><input v-model="createForm.full_name" class="form-input" required maxlength="180" placeholder="Nombre y apellidos"><FormError :message="createForm.errors.full_name" /></div>
                                <div><label class="form-label">Fecha de nacimiento<span class="form-required">*</span></label><input v-model="createForm.birth_date" class="form-input" type="date" required><p v-if="createForm.birth_date" class="form-field-hint">Edad calculada: {{ age(createForm.birth_date) }} años.</p><FormError :message="createForm.errors.birth_date" /></div>
                                <div><label class="form-label">Género<span class="form-required">*</span></label><select v-model="createForm.gender" class="form-input" required><option value="unspecified">No especificado</option><option value="male">Masculino</option><option value="female">Femenino</option></select><FormError :message="createForm.errors.gender" /></div>
                            </div>
                        </section>

                        <section id="player-form-step-1" class="form-section player-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="football" :size="20" /></span><div><h3>Asignación deportiva</h3><p>Plantilla, posición y número que utilizará en la competencia.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_9rem]">
                                <div class="sm:col-span-2 lg:col-span-1"><label class="form-label">Equipo y competencia<span class="form-required">*</span></label><select v-model="createForm.team_participation_id" class="form-input" required><option :value="null" disabled>Selecciona una plantilla</option><option v-for="participation in teamParticipations" :key="participation.id" :value="participation.id">{{ participation.team.name }} · {{ participation.competition.tournament.season.name }} · {{ participation.competition.name }}</option></select><FormError :message="createForm.errors.team_participation_id" /></div>
                                <div><label class="form-label">Posición<span class="form-required">*</span></label><select v-model="createForm.position" class="form-input" required><option value="goalkeeper">Portero</option><option value="defender">Defensa</option><option value="midfielder">Mediocampista</option><option value="forward">Delantero</option></select></div>
                                <div><label class="form-label">Dorsal<span class="form-required">*</span></label><input v-model="createForm.jersey_number" class="form-input" type="number" min="0" max="999" required placeholder="10"><FormError :message="createForm.errors.jersey_number" /></div>
                            </div>
                        </section>

                        <section id="player-form-step-2" class="form-section player-form-section">
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

                        <section id="player-form-step-3" class="form-section player-form-section">
                            <div class="form-section-heading"><span class="form-section-icon">▣</span><div><h3>Fotografía y acceso</h3><p>Completa la imagen de la credencial y, opcionalmente, vincula una cuenta.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Fotografía del jugador<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="updatePlayerPhoto($event)"><p class="form-field-hint">JPG, PNG o WEBP. Máximo 5 MB, preferentemente cuadrada.</p><img v-if="filePreview" class="mt-3 h-24 w-24 rounded-xl border border-slate-200 object-cover" :src="filePreview" alt="Vista previa de fotografía" /></div><FormError :message="createForm.errors.photo" /></div>
                                <div v-if="canManage"><label class="form-label">Cuenta de usuario</label><select v-model="createForm.league_membership_id" class="form-input"><option :value="null">Sin acceso al sistema</option><option v-for="membership in playerMemberships" :key="membership.id" :value="membership.id">{{ membership.user.name }} · {{ membership.user.email }}</option></select><p class="form-field-hint">La vinculación es opcional y puede realizarse después.</p><FormError :message="createForm.errors.league_membership_id" /></div>
                            </div>
                        </section>

                        <section class="form-section player-form-section">
                            <div class="form-section-heading"><span class="form-section-icon">✎</span><div><h3>Motivo del alta</h3><p>Agrega una referencia administrativa para el historial.</p></div></div>
                            <label class="form-label">Motivo<span class="form-required">*</span></label><textarea v-model="createForm.reason" class="form-input min-h-24" required maxlength="500" placeholder="Ej. Inscripción inicial para la temporada vigente"></textarea>
                        </section>
                    </div>

                    <aside class="registration-aside players-checklist">
                        <div class="player-dashboard-panel"><div class="flex items-center gap-3"><span class="player-heading-icon"><AppIcon name="file" :size="24" /></span><div><h3 class="font-extrabold text-slate-900">Expediente completo</h3><p class="text-xs text-slate-500">Verifica que la información esté completa y correcta.</p></div></div>
                            <ol class="mt-6 space-y-4"><li v-for="(step, index) in registrationSteps" :key="step" class="flex items-center gap-3 text-sm font-semibold text-slate-800"><span class="player-step-check" :class="completedSections[index] ? 'is-done' : ''"><AppIcon v-if="completedSections[index]" name="check" :size="13" /><span v-else>{{ index+1 }}</span></span>{{ step }}</li></ol>
                            <div class="mt-6 border-t border-slate-200 pt-5"><div class="flex justify-between text-sm font-bold"><span>Progreso del registro</span><span>{{ registrationProgress }}%</span></div><div class="player-progress-track"><span :style="{ width: `${registrationProgress}%` }"></span></div></div>
                            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">El alta quedará registrada con su trazabilidad administrativa.</div>
                            <div v-if="!teamParticipations.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900">No existen plantillas activas. Aprueba primero la participación de un equipo.</div>
                            <button class="btn-primary mt-5 w-full" :disabled="createForm.processing || !teamParticipations.length"><span v-if="createForm.processing">Enviando…</span><span v-else>Enviar alta</span></button>
                        </div>
                    </aside>
                </div>
            </form>

            <div v-if="activeTab === 'players'" class="players-directory mt-7 space-y-4">
                <div v-if="!isPlayer" class="players-search-bar">
                    <div class="players-search-primary">
                        <label class="form-label" for="player-search">Buscar jugador</label>
                        <div class="search-input-shell"><AppIcon name="search" :size="19" /><input id="player-search" v-model="playerSearch" class="form-input" type="search" placeholder="Buscar jugador, equipo o correo..."></div>
                        <p class="mt-2 text-xs text-slate-500">Filtra por nombre, equipo o correo para encontrar un jugador.</p>
                    </div>
                    <div><label class="form-label" for="player-state">Estado</label><select id="player-state" v-model="playerStatusFilter" class="form-input"><option value="all">Todos</option><option v-for="(label, value) in labels" :key="value" :value="value">{{ label }}</option></select></div>
                    <div><label class="form-label" for="player-team">Equipo actual</label><select id="player-team" v-model="playerTeamFilter" class="form-input"><option value="all">Todos los equipos</option><option v-for="team in teamOptions" :key="team.id" :value="String(team.id)">{{ team.name }}</option></select></div>
                </div>
                <div v-if="!isPlayer" class="flex flex-wrap items-center justify-between gap-3 px-1"><p class="text-sm font-semibold text-slate-500">{{ sortedVisiblePlayers.length }} {{ sortedVisiblePlayers.length === 1 ? 'jugador' : 'jugadores' }}</p><label class="flex items-center gap-2 text-xs font-bold text-slate-600"><span>Ordenar por</span><select v-model="playerSort" class="form-input !w-auto !min-w-40"><option value="recent">Más recientes</option><option value="name">Nombre (A–Z)</option></select></label></div>
                <div v-if="!sortedVisiblePlayers.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/90 p-12 text-center text-slate-600"><AppIcon name="users" variant="illustrated" :size="54" class="mx-auto" /><p class="mt-4 font-bold text-slate-800">{{ players.length ? 'Ningún jugador coincide con los filtros.' : 'No hay jugadores disponibles para este acceso.' }}</p></div>
                <article v-for="player in sortedVisiblePlayers" :key="player.id" class="player-directory-card">
                    <div class="player-directory-header">
                        <div class="flex min-w-0 flex-1 items-start gap-4"><div class="player-directory-avatar">{{ initials(player.full_name) }}</div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-extrabold text-slate-900">{{ player.full_name }}</h2><span class="entity-status" :class="statusClass(player.status)">{{ labels[player.status] }}</span></div><div class="mt-2 flex flex-wrap gap-2"><span class="player-chip"><AppIcon name="football" :size="13" /> {{ positions[player.position] ?? player.position }}</span><span v-if="player.birth_date" class="player-chip"><AppIcon name="calendar" :size="13" /> {{ age(player.birth_date) }} años</span><span v-if="currentRegistration(player)" class="player-chip"><AppIcon name="team" :size="13" /> {{ currentRegistration(player)?.team.name }} · #{{ currentRegistration(player)?.jersey_number }}</span></div><p v-if="player.user" class="player-access-note text-emerald-700"><AppIcon name="check" :size="13" /> Cuenta vinculada: {{ player.user.email }}</p><p v-else-if="canManage" class="player-access-note text-rose-600"><AppIcon name="shield" :size="13" /> Jugador sin acceso al sistema</p></div></div>
                        <div class="flex flex-wrap items-center gap-2"><button v-if="canManage || isPlayer" class="btn-secondary directory-action" @click="startProfile(player)"><AppIcon name="edit" :size="16" /> Actualizar contacto</button><template v-if="canManage"><a :href="`/liga/jugadores/${player.id}/archivo/photo`" class="btn-secondary directory-action"><AppIcon name="image" :size="16" /> Fotografía privada</a><button v-if="player.birth_date && age(player.birth_date) < 18" class="btn-secondary directory-action" @click="documentPlayer = player">Subir carta</button><button v-if="!player.user_id && playerMemberships.length" class="btn-secondary directory-action" @click="linkingPlayer = player">Vincular cuenta</button></template></div>
                    </div>
                    <div v-if="canManage && player.documents.length" class="mx-5 mb-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4"><h3 class="text-sm font-bold text-slate-900">Documentos privados</h3><div class="mt-2 space-y-2"><div v-for="document in player.documents" :key="document.id" class="flex flex-wrap items-center justify-between gap-2 text-xs"><span>Carta responsiva · conservar hasta {{ document.retain_until ?? 'sin fecha' }}</span><div class="flex gap-3"><a :href="`/liga/documentos-jugador/${document.id}`" class="font-bold text-league-700 underline">Descargar</a><button v-if="retentionExpired(document)" class="font-bold text-red-700 underline" @click="deleteDocument(document)">Eliminar</button></div></div></div></div>
                    <div v-if="isPlayer" class="mx-5 mb-4 rounded-xl border-2 border-league-700 bg-white p-5 print:m-0"><div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase text-league-600">Credencial digital</p><h3 class="mt-2 text-xl font-bold">{{ player.full_name }}</h3><p class="mt-1 text-sm text-slate-600">{{ currentRegistration(player)?.team.name ?? 'Sin equipo activo' }} · Dorsal #{{ currentRegistration(player)?.jersey_number ?? '—' }}</p><p v-if="currentRegistration(player)?.active_credential" class="mt-2 text-sm font-bold text-league-800">Folio: {{ currentRegistration(player)?.active_credential?.folio }}</p><p class="mt-2 text-sm">Estado: {{ labels[player.status] }}</p></div><button type="button" class="btn-secondary print:hidden" @click="printCredential">Imprimir</button></div><p class="mt-4 border-t border-slate-200 pt-3 text-xs text-slate-500">Credencial sin QR. La fotografía y documentos permanecen privados para administración.</p></div>
                    <div class="player-roster-section"><button type="button" class="mb-2 flex items-center gap-2 text-xs font-bold text-slate-900" :aria-expanded="expandedRosters[player.id] ?? true" @click="toggleRoster(player.id)"><AppIcon name="chevron-down" :size="15" /> Historial de plantillas</button><div v-if="expandedRosters[player.id] ?? true" class="space-y-2"><div v-for="registration in player.registrations" :key="registration.id" class="player-roster-row"><div class="roster-team-mark"><img v-if="registration.team.crest_url" :src="registration.team.crest_url" :alt="`Escudo ${registration.team.name}`" loading="lazy" /><AppIcon v-else name="shield" :size="26" /></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-slate-900">#{{ registration.jersey_number }} · {{ registration.team.name }}</strong><span class="entity-status" :class="statusClass(registration.status)">{{ labels[registration.status] }}</span></div><p class="mt-1 text-xs text-slate-500">{{ registration.team_participation.competition.tournament.season.name }} · {{ registration.team_participation.competition.name }}</p><p v-if="registration.active_credential" class="mt-1 text-xs font-medium text-league-700">Credencial {{ registration.active_credential.folio }}</p></div><div class="flex flex-wrap items-center gap-2"><template v-if="canManage"><button v-if="registration.status === 'pending'" class="btn-primary directory-action" @click="transition(registration, 'approve', 'Aprobar')">Aprobar</button><button v-if="registration.status === 'pending'" class="btn-danger directory-action" @click="transition(registration, 'reject', 'Rechazar')">Rechazar</button><button v-if="registration.status === 'active'" class="btn-danger directory-action" @click="transition(registration, 'suspend', 'Suspender')">Suspender</button><button v-if="registration.status === 'suspended'" class="btn-primary directory-action" @click="transition(registration, 'reactivate', 'Reactivar')">Reactivar</button><button v-if="registration.active_credential" class="btn-secondary directory-action" @click="revokeCredential(registration)"><AppIcon name="lock" :size="14" /> Revocar credencial</button></template><button v-if="['active', 'suspended'].includes(registration.status)" class="btn-secondary directory-action" @click="release(registration)"><AppIcon name="user" :size="14" /> Registrar baja</button></div></div></div></div>
                </article>
            </div>

            <div v-if="activeTab === 'available' && !isPlayer" class="mt-5 space-y-4"><section class="player-dashboard-panel"><div class="flex items-center gap-4"><span class="player-heading-icon"><AppIcon name="refresh" :size="30" /></span><div><h2 class="player-panel-title">Jugadores disponibles para reincorporación</h2><p class="player-panel-subtitle">Los jugadores liberados aparecerán aquí cuando puedan ser agregados nuevamente a la liga.</p></div><span class="ml-auto rounded-xl bg-league-50 px-4 py-2 text-sm font-bold text-league-700">{{ availablePlayers.length }} jugadores</span></div>
                <div v-if="!availablePlayers.length" class="player-empty player-empty-large border-t border-slate-100"><span class="player-empty-art"><AppIcon name="refresh" :size="70" /></span><strong>No hay jugadores liberados disponibles</strong><span>Aún no hay jugadores liberados que puedan reincorporarse a la liga.</span><button class="btn-secondary mt-4" type="button" @click="activeTab = 'players'; playerStatusFilter = 'released'">Ver bajas recientes</button></div>
                <div v-else class="mt-6 grid gap-3 md:grid-cols-2"><div v-for="player in availablePlayers" :key="player.id" class="player-request-line"><span class="request-line-avatar">{{ initials(player.full_name) }}</span><div class="min-w-0 flex-1"><strong class="block text-sm">{{ player.full_name }}</strong><span class="text-xs text-slate-500">{{ positions[player.position] }} · {{ age(player.birth_date) }} años</span></div><button class="btn-primary" @click="existingPlayer = player">Solicitar alta</button></div></div>
            </section></div>

            <div v-if="activeTab === 'pending' && !isPlayer" class="mt-5 space-y-4"><div class="grid gap-4 sm:grid-cols-3"><div class="player-stat"><span class="player-stat-icon tone-green"><AppIcon name="check" :size="32" /></span><span class="player-stat-text"><strong>{{ approvedCount }}</strong><span>Aprobadas</span></span></div><div class="player-stat"><span class="player-stat-icon tone-red"><AppIcon name="close" :size="32" /></span><span class="player-stat-text"><strong>{{ rejectedCount }}</strong><span>Rechazadas</span></span></div><div class="player-stat"><span class="player-stat-icon tone-amber"><AppIcon name="clock" :size="32" /></span><span class="player-stat-text"><strong>{{ pending.length }}</strong><span>Pendientes</span></span></div></div>
                <section class="player-dashboard-panel"><div class="flex flex-wrap items-center gap-4"><span class="pending-heading-icon"><AppIcon name="clock" :size="32" /></span><div><h2 class="player-panel-title">Altas pendientes</h2><p class="player-panel-subtitle">Solicitudes de alta de jugadores que requieren revisión y aprobación.</p></div><span class="ml-auto rounded-xl bg-league-50 px-4 py-2 text-sm font-bold text-league-700">{{ pending.length }} solicitudes</span></div>
                    <div v-if="!pending.length" class="player-empty player-empty-large mt-5 border-t border-slate-100"><span class="player-empty-art"><AppIcon name="file" :size="67" /></span><strong>No hay altas pendientes</strong><span>Las nuevas solicitudes aparecerán aquí.</span></div>
                    <div v-else class="mt-5 space-y-3"><div v-for="item in pending" :key="item.registration.id" class="player-request-line"><span class="request-line-avatar">{{ initials(item.player.full_name) }}</span><div class="team-crest-mini"><img v-if="item.registration.team.crest_url" :src="item.registration.team.crest_url" :alt="item.registration.team.name" /><AppIcon v-else name="shield" :size="22" /></div><div class="min-w-0 flex-1"><strong class="block text-sm text-slate-900">{{ item.player.full_name }}</strong><span class="block text-xs text-slate-500">#{{ item.registration.jersey_number }} · {{ item.registration.team.name }}</span><span class="mt-1 block text-xs text-slate-500">{{ positions[item.player.position] ?? item.player.position }}</span></div><div v-if="canManage" class="flex flex-wrap gap-2"><button class="btn-primary" @click="transition(item.registration, 'approve', 'Aprobar')">Aprobar</button><button class="btn-danger" @click="transition(item.registration, 'reject', 'Rechazar')">Rechazar</button></div><span v-else class="text-xs text-amber-700">Esperando revisión administrativa</span></div></div>
                </section>
            </div>
        </section>

        <div v-if="existingPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="existingPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitExisting"><h2 class="text-xl font-semibold">Reincorporar jugador</h2><p class="mt-1 text-sm text-slate-500">{{ existingPlayer.full_name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Equipo y competencia</label><select v-model="registrationForm.team_participation_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="participation in teamParticipations" :key="participation.id" :value="participation.id">{{ participation.team.name }} · {{ participation.competition.tournament.season.name }}</option></select></div><div><label class="form-label">Dorsal</label><input v-model="registrationForm.jersey_number" type="number" min="0" max="999" class="form-input" required><FormError :message="registrationForm.errors.jersey_number" /></div><div><label class="form-label">Motivo</label><textarea v-model="registrationForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="existingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="registrationForm.processing">Enviar</button></div></form></div>

        <div v-if="linkingPlayer && canManage" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="linkingPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitLink"><h2 class="text-xl font-semibold">Vincular cuenta</h2><p class="mt-1 text-sm text-slate-500">{{ linkingPlayer.full_name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Usuario con rol Jugador</label><select v-model="linkForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="membership in playerMemberships" :key="membership.id" :value="membership.id">{{ membership.user.name }} · {{ membership.user.email }}</option></select></div><div><label class="form-label">Motivo</label><textarea v-model="linkForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="linkingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="linkForm.processing">Vincular</button></div></form></div>

        <div v-if="editingPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="editingPlayer = null"><form class="w-full max-w-2xl rounded-2xl bg-white p-6" @submit.prevent="submitProfile"><h2 class="text-xl font-semibold">Actualizar datos de contacto</h2><p class="mt-1 text-sm text-slate-500">{{ editingPlayer.full_name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Teléfono</label><input v-model="profileForm.phone" class="form-input"></div><div><label class="form-label">Correo</label><input v-model="profileForm.email" class="form-input" type="email"></div><div><label class="form-label">Contacto de emergencia</label><input v-model="profileForm.emergency_contact_name" class="form-input" required></div><div><label class="form-label">Teléfono de emergencia</label><input v-model="profileForm.emergency_contact_phone" class="form-input" required></div><div><label class="form-label">Parentesco</label><input v-model="profileForm.emergency_contact_relationship" class="form-input" required></div><div><label class="form-label">Motivo</label><input v-model="profileForm.reason" class="form-input" required maxlength="500"></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="editingPlayer = null">Cancelar</button><button class="btn-primary" :disabled="profileForm.processing">Guardar</button></div></form></div>

        <div v-if="documentPlayer" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="documentPlayer = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitDocument"><h2 class="text-xl font-semibold">Subir carta responsiva</h2><p class="mt-1 text-sm text-slate-500">{{ documentPlayer.full_name }} · archivo privado de hasta 5 MB</p><div class="mt-5 space-y-4"><div><label class="form-label">PDF, JPG o PNG</label><input class="form-input" type="file" required accept="application/pdf,image/jpeg,image/png" @change="documentForm.document = file($event)"><FormError :message="documentForm.errors.document" /></div><div><label class="form-label">Motivo</label><textarea v-model="documentForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="documentPlayer = null">Cancelar</button><button class="btn-primary" :disabled="documentForm.processing">Guardar documento</button></div></form></div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.players-hero {
    background-image: linear-gradient(100deg, rgba(4,17,38,.94) 0%, color-mix(in srgb, var(--color-league-900) 82%, transparent) 45%, color-mix(in srgb, var(--color-gold-500) 46%, #061429) 100%), url('/images/public/league-stadium.jpg') !important;
    background-position: center 48%; background-size: cover;
}
.players-main-action { @apply inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/60 bg-white px-5 py-3 text-sm font-bold text-league-700 shadow-lg transition hover:-translate-y-0.5 hover:bg-league-50; }
.participants-tab { display:inline-flex; align-items:center; justify-content:center; gap:.55rem; white-space:nowrap; }
.participants-tab :deep(svg) { flex-shrink:0; }
.player-stat { @apply flex min-h-28 items-center gap-3 rounded-2xl border border-white/80 bg-white/95 px-4 py-5 text-left shadow-md shadow-slate-950/5 transition hover:-translate-y-0.5 hover:border-league-100 hover:shadow-lg; }
.player-stat-icon { @apply grid h-16 w-16 shrink-0 place-items-center rounded-2xl; }
.tone-blue { background:#edf4ff; } .tone-green { background:#e7f8ed; } .tone-amber { background:#fff3de; }
.player-stat-text { @apply flex min-w-0 flex-1 flex-col; } .player-stat-text strong { @apply text-3xl font-extrabold tracking-tight text-slate-950; } .player-stat-text span { @apply text-sm font-medium text-slate-600; }
.player-stat-arrow { @apply shrink-0 text-slate-400; }
.player-dashboard-panel { @apply rounded-2xl border border-white/80 bg-white/95 p-5 shadow-md shadow-slate-900/5 sm:p-6; }
.player-panel-title { @apply text-xl font-bold text-slate-900; } .player-panel-subtitle { @apply mt-1 text-sm text-slate-500; }
.player-empty { @apply flex flex-col items-center justify-center gap-2 text-center; } .player-empty strong { @apply text-sm font-semibold text-slate-800; } .player-empty span { @apply text-sm text-slate-500; }
.player-request-line { @apply flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3; }
.request-line-avatar { @apply grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-league-50 text-sm font-extrabold text-league-700; }
.player-inline-link { @apply rounded-lg bg-league-50 px-3 py-2 text-xs font-bold text-league-700 hover:bg-league-100; }
.player-quick-link { @apply flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/65 px-3 py-2 text-left text-sm font-semibold text-slate-800 transition hover:border-league-100 hover:bg-league-50; }
.player-quick-link > span { margin-left:auto; font-size:1.5rem; color:var(--color-league-600); }
.players-filter-panel { @apply rounded-2xl border border-white/80 bg-white/95 p-4 shadow-sm sm:p-5; }
.player-profile-card { @apply overflow-hidden rounded-2xl border border-white/80 bg-white/95 shadow-sm transition hover:shadow-lg; }
.player-profile-card .entity-avatar { @apply rounded-xl bg-league-50 text-league-700; }
.players-registration-shell { @apply overflow-hidden rounded-2xl border border-white/80 shadow-lg; }
.player-form-section { @apply rounded-2xl border border-slate-200/80 bg-white shadow-sm; }
.player-form-section .form-section-icon { @apply flex items-center justify-center; }
.players-pending-panel { @apply rounded-2xl border border-white/80 bg-white/95 shadow-lg; }
.pending-heading-icon { @apply grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-amber-50; }
.player-pending-item { @apply rounded-xl px-3 transition hover:bg-slate-50; }
.players-available-list > .card { @apply rounded-2xl border border-white/80 bg-white/95 shadow-sm; }
@media (prefers-reduced-motion: reduce) { .player-stat, .player-quick-link, .player-profile-card, .players-main-action { transition:none; } }
.players-search-bar { display:grid;grid-template-columns:minmax(0,1fr) 12rem 13rem;gap:1rem;align-items:start;padding:1.15rem;border:1px solid #e1e8f3;border-radius:1.15rem;background:rgba(255,255,255,.97);box-shadow:0 8px 24px rgba(15,35,65,.04); }
.search-input-shell {position:relative;} .search-input-shell > svg {position:absolute;z-index:1;top:50%;left:1rem;transform:translateY(-50%);color:#2466ce;} .search-input-shell .form-input {padding-left:2.8rem;}
.player-directory-card {overflow:hidden;border:1px solid #e2e9f4;border-radius:1.15rem;background:#fff;box-shadow:0 8px 22px rgba(15,35,65,.045);} .player-directory-header {display:flex;align-items:flex-start;justify-content:space-between;gap:1.25rem;padding:1.15rem 1.3rem;} .player-directory-avatar {display:grid;place-items:center;flex:none;width:3.4rem;height:3.4rem;border-radius:.85rem;background:#e7efff;color:#1864d3;font-size:1.12rem;font-weight:800;} .player-chip {display:inline-flex;align-items:center;gap:.35rem;border-radius:.55rem;background:#f2f5fb;padding:.3rem .65rem;color:#38527d;font-size:.75rem;font-weight:600;} .player-access-note {display:flex;align-items:center;gap:.35rem;margin-top:.55rem;font-size:.76rem;font-weight:600;} .directory-action {display:inline-flex;align-items:center;justify-content:center;gap:.4rem;white-space:nowrap;font-size:.76rem;padding:.55rem .75rem;} .player-roster-section {border-top:1px solid #e6ebf3;padding:.7rem 1.25rem 1rem;} .player-roster-row {display:flex;align-items:center;gap:1rem;padding:.8rem;border:1px solid #e5ebf5;border-radius:.85rem;background:#fbfcff;} .roster-team-mark {display:grid;place-items:center;flex:none;width:2.4rem;height:2.4rem;border-radius:.6rem;background:#e8f0fb;color:#1d518d;}
@media(max-width:1050px){.players-search-bar{grid-template-columns:minmax(0,1fr) 1fr 1fr}.players-search-primary{grid-column:1/-1}.player-directory-header{flex-direction:column}.player-roster-row{flex-wrap:wrap}} @media(max-width:640px){.players-search-bar{grid-template-columns:1fr}.players-search-primary{grid-column:auto}.player-roster-row{align-items:flex-start}.player-roster-row>div:last-child{width:100%}} 
/* Acabado premium compartido: utiliza exclusivamente los tokens dinámicos de AppLayout. */
.players-hero { min-height: 170px; border: 1px solid color-mix(in srgb,var(--color-gold-500) 22%,transparent); box-shadow:0 16px 32px rgba(15,35,60,.14); }
.players-main-action { background:linear-gradient(135deg,color-mix(in srgb,var(--color-gold-500) 64%,white),var(--color-gold-500));color:#101f38;border-color:color-mix(in srgb,var(--color-gold-500) 50%,white); }
.players-main-action:hover { background:color-mix(in srgb,var(--color-gold-500) 72%,white); }
.participants-tabs { display:flex; min-width:max-content; gap:.35rem; padding:.42rem; background:rgba(255,255,255,.96); border:1px solid #fff; border-radius:1.05rem; box-shadow:0 8px 24px rgba(15,35,60,.07); }
.participants-tab { min-height:45px; padding:.65rem 1.3rem; border-radius:.7rem; color:#1b3050; font-weight:700; font-size:.82rem; position:relative; }
.participants-tab-active { background:color-mix(in srgb,var(--color-gold-500) 12%,white);color:var(--color-league-900); }
.participants-tab-active:after {content:"";position:absolute;bottom:-.42rem;height:3px;left:0;right:0;border-radius:2px;background:var(--color-gold-500);}
.player-heading-icon { display:grid;flex:none;place-items:center;width:3rem;height:3rem;border-radius:.9rem;background:var(--color-league-50);color:var(--color-league-700); }
.player-empty-large {min-height:295px;padding:3rem 1rem;gap:.6rem;}.player-empty-large strong {font-size:1.15rem;color:#132442;}.player-empty-art {position:relative;display:grid;place-items:center;width:7.5rem;height:7.5rem;margin-bottom:.6rem;border-radius:50%;color:var(--color-league-700);background:var(--color-league-50);}.player-art-check {position:absolute;bottom:.2rem;right:-.35rem;display:grid;place-items:center;width:2.75rem;height:2.75rem;border-radius:50%;background:#1aab56;color:#fff;border:4px solid #fff;}
.player-stat {min-height:106px;}.player-stat-icon {width:3.75rem;height:3.75rem;}.player-stat-text strong {font-size:2rem;}.tone-red {background:#fff0f0;color:#cf3041;}
.player-season-chip {display:inline-flex;border-radius:999px;background:var(--color-league-50);padding:.25rem .65rem;color:var(--color-league-700);font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;}
.credential-layout-preview {display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem .9rem;border-radius:.85rem;color:white;background:linear-gradient(110deg,var(--color-league-900),var(--color-league-600) 68%,var(--color-gold-500));}
.credential-team-card {display:flex;flex-direction:column;justify-content:space-between;gap:1.5rem;}.team-crest-frame {width:5rem;height:5rem;flex:none;display:grid;place-items:center;border:1px solid #e1e8f3;border-radius:1rem;background:#fff;color:var(--color-league-700);}.team-crest-frame img,.team-crest-mini img,.roster-team-mark img {width:100%;height:100%;object-fit:contain;}.credential-team-actions {display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.5rem;}
.team-crest-mini {display:grid;place-items:center;width:3rem;height:3rem;flex:none;overflow:hidden;border-radius:.75rem;background:#fff;border:1px solid #e2e9f4;color:var(--color-league-700);}
.roster-team-mark {overflow:hidden;}.players-checklist {background:transparent;box-shadow:none;padding:0;}.players-checklist .player-dashboard-panel {position:sticky;top:1rem;}.player-step-check {display:grid;place-items:center;flex:none;width:1.55rem;height:1.55rem;border:1px solid #ccd7e6;border-radius:50%;color:#617188;font-size:.68rem;}.player-step-check.is-done {border-color:#28ad63;background:#dff9ea;color:#087338;}.player-progress-track {height:.65rem;margin-top:.75rem;overflow:hidden;border-radius:999px;background:#e2e8f1;}.player-progress-track span {display:block;height:100%;border-radius:999px;background:var(--color-gold-500);transition:width .2s ease;}
.players-progress-steps {display:flex;flex-wrap:wrap;gap:.5rem;padding:1rem 1.25rem;border-bottom:1px solid #e7edf5;background:#fff;}.players-progress-step {display:flex;align-items:center;gap:.5rem;border-radius:999px;padding:.45rem .7rem;font-size:.75rem;font-weight:700;color:#526782;}.players-progress-step>span {display:grid;place-items:center;width:1.6rem;height:1.6rem;border-radius:50%;background:#eef2f8;color:#445875;}.players-progress-step.is-current {background:color-mix(in srgb,var(--color-gold-500) 15%,white);color:#1b2d4b;}.players-progress-step.is-current>span {background:var(--color-gold-500);color:#14213b;}.players-progress-step.is-complete:not(.is-current)>span {background:#daf5e6;color:#08763d;}
.players-registration-shell {margin-top:1.25rem;background:white;}.players-registration-shell .registration-grid {background:#fff;}.players-registration-shell .form-section-heading {gap:.75rem;}
.player-roster-row {border-color:#e5ebf5;}.player-roster-row:has(.entity-status-active) {background:color-mix(in srgb,var(--color-league-600) 7%,white);}
@media(max-width:767px){.players-hero {min-height:175px;}.player-empty-large {min-height:230px;padding:2.5rem 1rem;}.players-progress-steps {padding:.75rem;}.credential-team-actions {justify-content:flex-start;}}
@media(prefers-reduced-motion: reduce){.player-progress-track span {transition:none;}}
</style>

