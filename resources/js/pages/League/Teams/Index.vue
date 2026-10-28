<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type User = { id: number; name: string; email: string; phone: string | null };
type RepresentativeOption = { id: number; user_id: number; user: User };
type Competition = {
    id: number; name: string; division: { id: number; name: string }; category: { id: number; name: string };
    tournament: { id: number; name: string; season: { id: number; name: string } };
};
type Participation = {
    id: number; status: string; registered_name: string; review_reason: string | null;
    competition: Competition;
};
type NameHistory = { id: number; name: string; short_name: string; valid_from: string; valid_until: string | null; reason: string | null };
type ChangeRequest = { id: number; status: string; changes: Record<string, string | null>; request_reason: string; requester: { id: number; name: string } };
type Team = {
    id: number; name: string; short_name: string; primary_color: string; secondary_color: string;
    phone: string | null; email: string | null; founded_on: string | null; description: string | null;
    status: string; crest_url: string | null; photo_url: string | null;
    active_representative: { id: number; user: User } | null;
    participations: Participation[]; names: NameHistory[]; change_requests: ChangeRequest[];
};

const props = defineProps<{ teams: Team[]; canManage: boolean; representatives: RepresentativeOption[]; competitions: Competition[] }>();
const activeTab = ref(props.canManage ? 'resumen' : 'equipos');
const editingTeam = ref<Team | null>(null);
const representativeTeam = ref<Team | null>(null);
const participationTeam = ref<Team | null>(null);
const teamSearch = ref('');
const teamStatusFilter = ref('all');
const creationSteps = [
    'Completa la identidad y clasificación del equipo.',
    'Define la competencia y los datos de contacto.',
    'Adjunta el escudo y la fotografía del plantel.',
    'Selecciona al representante responsable.',
    'Escribe la justificación y guarda el registro.',
];

const createForm = useForm({
    name: '', short_name: '', primary_color: '#125444', secondary_color: '#ffffff', phone: '', email: '',
    founded_on: '', description: '', competition_id: null as number | null, league_membership_id: null as number | null,
    crest: null as File | null, team_photo: null as File | null, representative_photo: null as File | null,
    representative_ine: null as File | null, reason: '',
});
const teamForm = useForm({
    name: '', short_name: '', primary_color: '#125444', secondary_color: '#ffffff', phone: '', email: '',
    founded_on: '', description: '', crest: null as File | null, team_photo: null as File | null,
    justification: '', reason: '', request_reason: '',
});
const representativeForm = useForm({ league_membership_id: null as number | null, representative_photo: null as File | null, representative_ine: null as File | null, reason: '' });
const participationForm = useForm({ competition_id: null as number | null, reason: '' });

const tabs = computed(() => props.canManage
    ? [['resumen', 'Resumen'], ['registrar', 'Registrar equipo'], ['equipos', 'Equipos'], ['solicitudes', 'Solicitudes']]
    : [['equipos', 'Mi equipo'], ['solicitudes', 'Mis solicitudes']]);
const pendingParticipations = computed(() => props.teams.flatMap((team) => team.participations.filter((item) => item.status === 'pending').map((participation) => ({ team, participation }))));
const pendingChanges = computed(() => props.teams.flatMap((team) => team.change_requests.map((change) => ({ team, change }))));
const visibleTeams = computed(() => props.teams.filter((team) => {
    const query = teamSearch.value.trim().toLocaleLowerCase('es-MX');
    const matchesQuery = !query || [team.name, team.short_name, team.active_representative?.user.name ?? ''].some((value) => value.toLocaleLowerCase('es-MX').includes(query));
    return matchesQuery && (teamStatusFilter.value === 'all' || team.status === teamStatusFilter.value);
}));
const labels: Record<string, string> = {
    pending: 'Pendiente', active: 'Activo', suspended: 'Suspendido', inactive: 'Inactivo', deregistered: 'Baja', rejected: 'Rechazado',
};

function file(event: Event): File | null { return (event.target as HTMLInputElement).files?.[0] ?? null; }
function initials(team: Team): string { return (team.short_name || team.name).split(/\s+/).map((word) => word[0]).join('').slice(0, 3).toUpperCase(); }
function statusClass(status: string): string {
    return ({ active: 'entity-status-active', pending: 'entity-status-pending', suspended: 'entity-status-suspended', rejected: 'entity-status-rejected', inactive: 'entity-status-neutral', deregistered: 'entity-status-neutral' } as Record<string, string>)[status] ?? 'entity-status-neutral';
}
function submitTeam() {
    createForm.post('/liga/equipos', { forceFormData: true, preserveScroll: true, onSuccess: () => { createForm.reset(); activeTab.value = 'equipos'; } });
}
function startEdit(team: Team) {
    editingTeam.value = team;
    teamForm.name = team.name; teamForm.short_name = team.short_name; teamForm.primary_color = team.primary_color;
    teamForm.secondary_color = team.secondary_color; teamForm.phone = team.phone ?? ''; teamForm.email = team.email ?? '';
    teamForm.founded_on = team.founded_on?.slice(0, 10) ?? ''; teamForm.description = team.description ?? '';
    teamForm.crest = null; teamForm.team_photo = null; teamForm.justification = '';
}
function submitTeamChange() {
    if (!editingTeam.value) return;
    const url = props.canManage ? `/liga/equipos/${editingTeam.value.id}/actualizar` : `/liga/equipos/${editingTeam.value.id}/solicitudes-cambio`;
    teamForm.reason = teamForm.justification;
    teamForm.request_reason = teamForm.justification;
    teamForm.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => { editingTeam.value = null; teamForm.reset(); } });
}
function transition(participation: Participation, action: string, verb: string) {
    const reason = prompt(`Motivo para ${verb.toLowerCase()} esta participación:`);
    if (reason?.trim()) router.put(`/liga/participaciones/${participation.id}/estado`, { action, reason }, { preserveScroll: true });
}
function reviewChange(change: ChangeRequest, decision: string) {
    const reason = prompt(`Motivo para ${decision === 'approve' ? 'aprobar' : 'rechazar'} la solicitud:`);
    if (reason?.trim()) router.put(`/liga/solicitudes-cambio/${change.id}`, { decision, reason }, { preserveScroll: true });
}
function submitRepresentative() {
    if (!representativeTeam.value) return;
    representativeForm.post(`/liga/equipos/${representativeTeam.value.id}/representante`, {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => { representativeTeam.value = null; representativeForm.reset(); },
    });
}
function eligibleCompetitions(team: Team): Competition[] {
    const seasonIds = new Set(team.participations.map((participation) => participation.competition.tournament.season.id));
    return props.competitions.filter((competition) => !seasonIds.has(competition.tournament.season.id));
}
function submitParticipation() {
    if (!participationTeam.value) return;
    participationForm.post(`/liga/equipos/${participationTeam.value.id}/participaciones`, {
        preserveScroll: true,
        onSuccess: () => { participationTeam.value = null; participationForm.reset(); },
    });
}
</script>

<template>
    <Head title="Equipos y representantes" />
    <AppLayout>
        <section class="participants-page participants-premium">
            <div class="participants-hero">
                <div class="participants-hero-content">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Participantes · Módulo 5</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Equipos y representantes</h1><p class="mt-2 text-sm text-white/75 sm:text-base">Centraliza la identidad de cada club, sus responsables y la participación en las competencias de la liga.</p></div>
                        <button v-if="canManage" class="participants-hero-cta" @click="activeTab = 'registrar'"><AppIcon name="users" :size="20" /> Registrar equipo</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto"><div class="participants-tabs">
                <button v-for="tab in tabs" :key="tab[0]" type="button" class="participants-tab" :class="activeTab === tab[0] ? 'participants-tab-active' : ''" @click="activeTab = tab[0]"><span class="participants-tab-icon"><AppIcon :name="({ resumen: 'chart', registrar: 'users', equipos: 'team', solicitudes: 'clipboard' } as Record<string,string>)[tab[0]] ?? 'team'" :size="19" /></span>{{ tab[1] }}</button>
            </div></div>

            <div v-if="activeTab === 'resumen' && canManage" class="mt-7 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="participants-stat"><span class="participants-stat-icon"><AppIcon name="team" variant="illustrated" :size="52" /></span><div><strong>{{ teams.length }}</strong><p>Equipos registrados</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon"><AppIcon name="check" variant="illustrated" :size="52" /></span><div><strong>{{ teams.filter((team) => team.status === 'active').length }}</strong><p>Equipos activos</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon"><AppIcon name="clock" variant="illustrated" :size="52" /></span><div><strong>{{ pendingParticipations.length }}</strong><p>Participaciones pendientes</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon"><AppIcon name="edit" variant="illustrated" :size="52" /></span><div><strong>{{ pendingChanges.length }}</strong><p>Cambios por revisar</p></div></div>
                </div>
                <div v-if="!representatives.length" class="participants-empty-state"><div class="empty-icon"><AppIcon name="users" :size="40" /></div><h2>No hay representantes disponibles</h2><p>Crea o asigna primero un usuario con el rol Representante de equipo desde Miembros para poder gestionar los equipos de la liga.</p><div class="mt-5"><a href="/liga/miembros" class="participants-empty-link"><AppIcon name="users" :size="18" /> Ir a miembros</a></div></div>
                <div v-if="!competitions.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No hay competencias disponibles. Crea primero la temporada, torneo, división, categoría y competencia.</div>
            </div>

            <form v-if="activeTab === 'registrar' && canManage" class="registration-shell registration-premium" @submit.prevent="submitTeam">
                <header class="registration-heading">
                    <div><span class="registration-step">Nuevo registro</span><h2 class="mt-3 text-2xl font-bold">Registrar equipo</h2><p class="mt-1 text-sm text-white/75">Integra la identidad, participación y responsable oficial del equipo.</p></div>
                    <p class="max-w-xs text-xs leading-5 text-white/70"><span class="font-bold text-gold-400">*</span> Los campos marcados son obligatorios.</p>
                </header>

                <div class="registration-grid">
                    <div class="registration-main">
                        <section class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="shield" :size="22" /></span><div><h3>Identidad y clasificación</h3><p>Información básica del equipo en la liga.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Nombre oficial<span class="form-required">*</span></label><input v-model="createForm.name" class="form-input" required maxlength="150" placeholder="Ej. Deportivo Municipal"><FormError :message="createForm.errors.name" /></div>
                                <div><label class="form-label">Nombre corto<span class="form-required">*</span></label><input v-model="createForm.short_name" class="form-input" required maxlength="30" placeholder="Ej. D. Municipal"><p class="form-field-hint">Se utilizará en tablas y marcadores.</p><FormError :message="createForm.errors.short_name" /></div>
                                <div><label class="form-label">Fecha de fundación</label><input v-model="createForm.founded_on" class="form-input" type="date"></div>
                                <div class="sm:row-span-2"><label class="form-label">Descripción</label><textarea v-model="createForm.description" class="form-input min-h-28" maxlength="2000" placeholder="Historia, sede o información relevante del equipo"></textarea><p class="form-field-hint">Opcional · máximo 2,000 caracteres.</p></div>
                                <div class="grid gap-4 sm:grid-cols-2 sm:col-span-1">
                                    <div><label class="form-label">Color principal</label><div class="color-field"><input v-model="createForm.primary_color" type="color"><span>{{ createForm.primary_color }}</span></div></div>
                                    <div><label class="form-label">Color secundario</label><div class="color-field"><input v-model="createForm.secondary_color" type="color"><span>{{ createForm.secondary_color }}</span></div></div>
                                </div>
                            </div>
                        </section>

                        <section class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="trophy" :size="22" /></span><div><h3>Participación y contacto</h3><p>Competencia inicial y medios privados de comunicación.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2"><label class="form-label">Competencia<span class="form-required">*</span></label><select v-model="createForm.competition_id" class="form-input" required><option :value="null" disabled>Selecciona temporada, competencia, división y categoría</option><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }} · {{ competition.division.name }} · {{ competition.category.name }}</option></select><FormError :message="createForm.errors.competition_id" /></div>
                                <div><label class="form-label">Teléfono privado</label><input v-model="createForm.phone" class="form-input" type="tel" maxlength="30" placeholder="Ej. 951 000 0000"><p class="form-field-hint">Visible solo para administración.</p></div>
                                <div><label class="form-label">Correo privado</label><input v-model="createForm.email" class="form-input" type="email" maxlength="150" placeholder="equipo@correo.com"><FormError :message="createForm.errors.email" /></div>
                            </div>
                        </section>

                        <section class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="image" :size="22" /></span><div><h3>Recursos gráficos</h3><p>Archivos que identificarán al equipo en el portal y sus documentos.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Escudo del equipo</label><div class="file-field"><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.crest = file($event)"><p class="form-field-hint">JPG, PNG o WEBP. Preferentemente cuadrado.</p></div><FormError :message="createForm.errors.crest" /></div>
                                <div><label class="form-label">Fotografía del equipo</label><div class="file-field"><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.team_photo = file($event)"><p class="form-field-hint">Imagen grupal opcional del plantel.</p></div><FormError :message="createForm.errors.team_photo" /></div>
                            </div>
                        </section>

                        <section class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="users" :size="22" /></span><div><h3>Representante y permisos</h3><p>Persona responsable de la gestión del equipo ante la liga.</p></div></div>
                            <div class="grid gap-5 lg:grid-cols-2">
                                <div class="lg:col-span-2"><label class="form-label">Usuario representante<span class="form-required">*</span></label><select v-model="createForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona un miembro con rol Representante</option><option v-for="representative in representatives" :key="representative.id" :value="representative.id">{{ representative.user.name }} · {{ representative.user.email }}</option></select><FormError :message="createForm.errors.league_membership_id" /></div>
                                <div><label class="form-label">Fotografía del representante<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_photo = file($event)"><p class="form-field-hint">Imagen clara y reciente.</p></div><FormError :message="createForm.errors.representative_photo" /></div>
                                <div><label class="form-label">Identificación INE<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_ine = file($event)"><p class="form-field-hint">Archivo privado, visible solo para administración.</p></div><FormError :message="createForm.errors.representative_ine" /></div>
                            </div>
                        </section>

                        <section class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="edit" :size="26" /></span><div><h3>Justificación del registro</h3><p>Describe brevemente el motivo administrativo del alta.</p></div></div>
                            <label class="form-label">Motivo<span class="form-required">*</span></label><textarea v-model="createForm.reason" class="form-input min-h-24" required maxlength="500" placeholder="Ej. Alta para participar en la temporada vigente"></textarea><FormError :message="createForm.errors.reason" />
                        </section>
                    </div>

                    <aside class="registration-aside">
                        <div class="registration-note premium-guide"><div class="guide-header"><span class="guide-icon"><AppIcon name="file" :size="22" /></span><div><h3>Guía rápida</h3><p class="text-xs text-slate-500">Cinco pasos para un registro correcto.</p></div></div><ol class="guide-steps"><li v-for="(step, index) in creationSteps" :key="index"><span>{{ index + 1 }}</span><p>{{ step }}</p></li></ol><div class="guide-warning"><AppIcon name="shield" :size="19" /><p>Los archivos de identidad son privados. La participación inicial quedará pendiente de aprobación.</p></div></div>
                        <div v-if="!representatives.length || !competitions.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">Debes contar con al menos un representante y una competencia disponibles para completar el registro.</div>
                        <div class="registration-actions"><p class="mb-4 text-xs leading-5 text-slate-600">Al registrar confirmas que la información fue revisada.</p><button class="btn-primary" :disabled="createForm.processing || !representatives.length || !competitions.length"><span v-if="createForm.processing">Registrando…</span><span v-else>Registrar equipo</span></button></div>
                    </aside>
                </div>
            </form>

            <div v-if="activeTab === 'equipos'" class="mt-7 space-y-5">
                <div class="participants-toolbar premium-toolbar lg:grid-cols-[minmax(0,1fr)_14rem]"><div><label class="form-label">Buscar equipo o representante</label><input v-model="teamSearch" class="form-input" type="search" placeholder="Nombre, abreviatura o representante"></div><div><label class="form-label">Estado</label><select v-model="teamStatusFilter" class="form-input"><option value="all">Todos los estados</option><option v-for="(label, value) in labels" :key="value" :value="value">{{ label }}</option></select></div></div>
                <div v-if="!visibleTeams.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/80 p-12 text-center text-slate-600"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-league-50 text-league-700"><AppIcon name="team" :size="30" /></div><p class="mt-4 font-bold text-slate-800">{{ teams.length ? 'Ningún equipo coincide con los filtros.' : (canManage ? 'No hay equipos registrados.' : 'Todavía no tienes un equipo asignado.') }}</p></div>
                <article v-for="team in visibleTeams" :key="team.id" class="entity-card premium-entity-card">
                    <div class="grid gap-6 p-6 lg:grid-cols-[110px_1fr_auto]">
                        <div class="entity-avatar premium-crest h-24 w-24"><img v-if="team.crest_url" :src="team.crest_url" :alt="`Escudo de ${team.name}`"><span v-else>{{ initials(team) }}</span></div>
                        <div><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-bold text-slate-900">{{ team.name }}</h2><span class="entity-status" :class="statusClass(team.status)">{{ labels[team.status] }}</span></div><p class="mt-2 text-sm text-slate-600">Representante: <strong>{{ team.active_representative?.user.name ?? 'Sin representante' }}</strong></p><div class="mt-3 flex flex-wrap gap-2"><span class="entity-meta"><AppIcon name="mail" :size="14" /> {{ team.email || 'Sin correo' }}</span><span class="entity-meta"><AppIcon name="phone" :size="14" /> {{ team.phone || 'Sin teléfono' }}</span><span class="entity-meta"><AppIcon name="users" :size="14" /> {{ team.participations.length }} participaciones</span></div><div class="mt-3 flex gap-1.5"><span class="h-4 w-8 rounded-full border border-slate-200" :style="{ backgroundColor: team.primary_color }"></span><span class="h-4 w-8 rounded-full border border-slate-200" :style="{ backgroundColor: team.secondary_color }"></span></div></div>
                        <div class="premium-team-actions flex flex-wrap content-start gap-2"><button class="btn-secondary" @click="startEdit(team)">{{ canManage ? 'Editar' : 'Solicitar cambio' }}</button><template v-if="canManage"><button class="btn-primary" :disabled="!eligibleCompetitions(team).length" @click="participationTeam = team">Nueva participación</button><a :href="`/liga/equipos/${team.id}/representante/photo`" class="btn-secondary">Foto del representante</a><a :href="`/liga/equipos/${team.id}/representante/ine`" class="btn-secondary">INE</a><button class="btn-secondary" @click="representativeTeam = team">Cambiar representante</button></template></div>
                    </div>
                    <div class="premium-participations border-t border-slate-200 bg-slate-50 p-5"><h3 class="text-sm font-semibold">Participaciones</h3><div class="mt-3 space-y-3"><div v-for="participation in team.participations" :key="participation.id" class="premium-participation-row flex flex-col justify-between gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-center"><div><p class="text-sm font-medium">{{ participation.competition.tournament.season.name }} · {{ participation.competition.name }}</p><p class="mt-1 text-xs text-slate-500">{{ participation.competition.division.name }} · {{ participation.competition.category.name }} · {{ labels[participation.status] }}</p></div><div v-if="canManage" class="flex flex-wrap gap-2"><button v-if="['pending', 'rejected'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'approve', 'Aprobar')">Aprobar</button><button v-if="participation.status === 'pending'" class="btn-danger" @click="transition(participation, 'reject', 'Rechazar')">Rechazar</button><button v-if="participation.status === 'active'" class="btn-danger" @click="transition(participation, 'suspend', 'Suspender')">Suspender</button><button v-if="['suspended', 'inactive'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'reactivate', 'Reactivar')">Reactivar</button><button v-if="['active', 'suspended'].includes(participation.status)" class="btn-secondary" @click="transition(participation, 'deactivate', 'Desactivar')">Desactivar</button></div></div></div></div>
                    <details v-if="team.names.length > 1" class="border-t border-slate-200 p-5"><summary class="cursor-pointer text-sm font-semibold">Historial de nombres ({{ team.names.length }})</summary><ul class="mt-3 space-y-2 text-sm text-slate-600"><li v-for="name in team.names" :key="name.id">{{ name.name }} ({{ name.short_name }}) · desde {{ new Date(name.valid_from).toLocaleDateString('es-MX') }}</li></ul></details>
                </article>
            </div>

            <div v-if="activeTab === 'solicitudes'" class="mt-7 space-y-5">
                <div v-if="canManage && pendingParticipations.length" class="premium-request-card card p-6"><h2 class="text-xl font-semibold"> <AppIcon name="clock" :size="22" /> Participaciones pendientes</h2><div class="mt-4 divide-y divide-slate-200"><div v-for="item in pendingParticipations" :key="item.participation.id" class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"><div><div class="flex items-center gap-3"><span class="request-crest"><img v-if="item.team.crest_url" :src="item.team.crest_url" :alt="`Escudo de ${item.team.name}`"><AppIcon v-else name="team" :size="23" /></span><div><p class="font-semibold text-slate-900">{{ item.team.name }}</p><p class="text-sm text-slate-500">{{ item.participation.competition.name }}</p></div></div></div><div class="flex gap-2"><button class="btn-primary" @click="transition(item.participation, 'approve', 'Aprobar')">Aprobar</button><button class="btn-danger" @click="transition(item.participation, 'reject', 'Rechazar')">Rechazar</button></div></div></div></div>
                <div class="premium-request-card card p-6"><h2 class="text-xl font-semibold"><AppIcon name="refresh" :size="22" /> {{ canManage ? 'Cambios pendientes' : 'Solicitudes enviadas' }}</h2><div v-if="!pendingChanges.length" class="mt-4 text-sm text-slate-500">No hay solicitudes pendientes.</div><div v-else class="mt-4 divide-y divide-slate-200"><div v-for="item in pendingChanges" :key="item.change.id" class="py-4"><div class="flex flex-col justify-between gap-3 sm:flex-row"><div><p class="font-medium">{{ item.team.name }}</p><p class="mt-1 text-sm text-slate-500">{{ item.change.request_reason }}</p><p class="mt-2 text-xs text-slate-500">Solicitó: {{ item.change.requester.name }}</p></div><div v-if="canManage" class="flex gap-2"><button class="btn-primary" @click="reviewChange(item.change, 'approve')">Aprobar</button><button class="btn-danger" @click="reviewChange(item.change, 'reject')">Rechazar</button></div></div></div></div></div>
            </div>
        </section>

        <div v-if="editingTeam" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="editingTeam = null">
            <form class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="submitTeamChange">
                <div class="flex justify-between"><div><h2 class="text-xl font-semibold">{{ canManage ? 'Editar equipo' : 'Solicitar modificación' }}</h2><p class="mt-1 text-sm text-slate-500">{{ editingTeam.name }}</p></div><button type="button" class="text-2xl text-slate-500" @click="editingTeam = null">×</button></div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="teamForm.name" class="form-input" required><FormError :message="teamForm.errors.name" /></div><div><label class="form-label">Nombre corto</label><input v-model="teamForm.short_name" class="form-input" required></div><div><label class="form-label">Color principal</label><input v-model="teamForm.primary_color" class="form-input h-12" type="color"></div><div><label class="form-label">Color secundario</label><input v-model="teamForm.secondary_color" class="form-input h-12" type="color"></div><div><label class="form-label">Teléfono</label><input v-model="teamForm.phone" class="form-input"></div><div><label class="form-label">Correo</label><input v-model="teamForm.email" class="form-input" type="email"></div><div><label class="form-label">Fundación</label><input v-model="teamForm.founded_on" class="form-input" type="date"></div><div><label class="form-label">Nuevo escudo (opcional)</label><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="teamForm.crest = file($event)"></div><div class="sm:col-span-2"><label class="form-label">Nueva fotografía (opcional)</label><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="teamForm.team_photo = file($event)"></div><div class="sm:col-span-2"><label class="form-label">Descripción</label><textarea v-model="teamForm.description" class="form-input min-h-20"></textarea></div><div class="sm:col-span-2"><label class="form-label">{{ canManage ? 'Motivo del cambio' : 'Justificación para el administrador' }}</label><textarea v-model="teamForm.justification" class="form-input min-h-20" required maxlength="500"></textarea><FormError :message="teamForm.errors.reason || teamForm.errors.request_reason" /></div></div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="editingTeam = null">Cancelar</button><button class="btn-primary" :disabled="teamForm.processing">{{ canManage ? 'Guardar cambios' : 'Enviar solicitud' }}</button></div>
            </form>
        </div>

        <div v-if="representativeTeam && canManage" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="representativeTeam = null">
            <form class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="submitRepresentative">
                <h2 class="text-xl font-semibold">Cambiar representante</h2><p class="mt-1 text-sm text-slate-500">{{ representativeTeam.name }}</p>
                <div class="mt-5 space-y-4"><div><label class="form-label">Nuevo representante disponible</label><select v-model="representativeForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="representative in representatives" :key="representative.id" :value="representative.id">{{ representative.user.name }} · {{ representative.user.email }}</option></select><FormError :message="representativeForm.errors.league_membership_id" /></div><div><label class="form-label">Fotografía</label><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="representativeForm.representative_photo = file($event)"></div><div><label class="form-label">Imagen del INE</label><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="representativeForm.representative_ine = file($event)"></div><div><label class="form-label">Motivo</label><textarea v-model="representativeForm.reason" class="form-input min-h-20" required maxlength="500"></textarea></div></div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="representativeTeam = null">Cancelar</button><button class="btn-primary" :disabled="representativeForm.processing || !representatives.length">Asignar</button></div>
            </form>
        </div>

        <div v-if="participationTeam && canManage" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="participationTeam = null">
            <form class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="submitParticipation">
                <h2 class="text-xl font-semibold">Nueva participación</h2><p class="mt-1 text-sm text-slate-500">{{ participationTeam.name }}</p>
                <div class="mt-5 space-y-4"><div><label class="form-label">Competencia de otra temporada</label><select v-model="participationForm.competition_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="competition in eligibleCompetitions(participationTeam)" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }} · {{ competition.division.name }}</option></select><FormError :message="participationForm.errors.competition_id" /></div><div><label class="form-label">Motivo del registro</label><textarea v-model="participationForm.reason" class="form-input min-h-20" required maxlength="500"></textarea><FormError :message="participationForm.errors.reason" /></div></div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="participationTeam = null">Cancelar</button><button class="btn-primary" :disabled="participationForm.processing || !eligibleCompetitions(participationTeam).length">Registrar participación</button></div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.participants-premium { --premium-ink: #14294b; color: var(--premium-ink); }
.participants-premium .participants-hero { border: 1px solid rgba(255,255,255,.24); box-shadow: 0 18px 42px rgba(11,35,73,.16); }
.participants-premium .participants-hero h1 { letter-spacing: -.035em; }
.participants-premium .participants-tabs { gap: .4rem; padding: .45rem; border-radius: 1.15rem; background: rgba(255,255,255,.96); box-shadow: 0 9px 28px rgba(24,51,88,.07); }
.participants-premium .participants-tab { display: inline-flex; align-items: center; justify-content: center; gap: .65rem; min-height: 3rem; padding: .6rem 1.2rem; border-radius: .8rem; font-weight: 700; white-space: nowrap; transition: background .18s ease, color .18s ease; }
.participants-premium .participants-tab:not(.participants-tab-active):hover { background: #eef4fb; color: #163b6b; }
.participants-premium .participants-stat { min-height: 7.75rem; border: 1px solid #e5edf5; background: rgba(255,255,255,.98); box-shadow: 0 9px 24px rgba(20,44,77,.06); border-radius: 1.15rem; }
.participants-premium .participants-stat-icon { display: grid; place-items: center; flex: 0 0 3.5rem; width: 3.5rem; height: 3.5rem; font-size: unset; background: #e8f1fd; color: #174e92; border-radius: 1rem; }
.participants-premium .participants-stat:nth-child(2) .participants-stat-icon { background: #e3f5eb; color: #13734a; }
.participants-premium .participants-stat:nth-child(3) .participants-stat-icon { background: #fff1dd; color: #a96311; }
.participants-premium .participants-stat:nth-child(4) .participants-stat-icon { background: #eaf2fd; color: #2762b6; }
.participants-premium .registration-premium { border-radius: 1.4rem; overflow: hidden; box-shadow: 0 16px 44px rgba(20,44,77,.09); }
.participants-premium .premium-form-section { margin-bottom: 1.15rem; border: 1px solid #e5edf5; border-radius: 1.2rem; background: white; padding: 1.4rem; box-shadow: 0 5px 15px rgba(20,44,77,.03); }
.participants-premium .form-section-icon { display: grid; place-items: center; flex: 0 0 2.8rem; width: 2.8rem; height: 2.8rem; border-radius: .85rem; background: #eaf2fc; color: #215ca4; font-size: unset; }
.participants-premium .form-section h3 { color: #14294b; font-weight: 800; }
.participants-premium .form-section .form-input { border-color: #cddbec; background: #fff; }
.participants-premium .premium-toolbar { background: rgba(255,255,255,.98); border: 1px solid #e4ebf5; box-shadow: 0 6px 19px rgba(20,44,77,.05); border-radius: 1.1rem; }
.participants-premium .premium-entity-card { border-radius: 1.25rem; border: 1px solid #e1eaf4; background: rgba(255,255,255,.98); box-shadow: 0 9px 28px rgba(20,44,77,.07); overflow: hidden; }
.participants-premium .premium-crest { border: 1px solid #d9e4ef; border-radius: 1rem; background: #f8fafc; box-shadow: 0 3px 12px rgba(20,44,77,.06); }
.participants-premium .premium-team-actions { max-width: 36rem; justify-content: flex-end; align-items: flex-start; }
.participants-premium .premium-team-actions > * { min-height: 2.55rem; font-size: .76rem; }
.participants-premium .premium-participations { background: #f6f9fd; }
.participants-premium .premium-participation-row { border: 1px solid #e4ebf5; }
.participants-premium .premium-request-card { border: 1px solid #e3ebf4; border-radius: 1.2rem; box-shadow: 0 10px 28px rgba(20,44,77,.06); }
.participants-premium .premium-request-card h2 { display: flex; align-items: center; gap: .65rem; color: #14294b; font-weight: 800; }
@media (max-width: 767px) { .participants-premium .premium-form-section { padding: 1rem; } .participants-premium .premium-team-actions { justify-content: flex-start; } }

/* Refinamiento de diseño fiel a las referencias proporcionadas. */
.participants-premium { max-width: 1500px; margin-inline: auto; }
.participants-premium .participants-hero {
    position: relative; isolation: isolate; overflow: hidden;
    background-image: linear-gradient(108deg, rgba(11, 43, 92, .96) 0%, rgba(20, 82, 155, .87) 57%, rgba(7, 54, 82, .82) 100%), url('/images/public/league-stadium.jpg');
    background-size: cover; background-position: center 55%;
    border-radius: 1.5rem; min-height: 192px;
}
.participants-premium .participants-hero::before {
    content: ''; position: absolute; z-index: 0; inset: 0;
    background: linear-gradient(90deg, rgba(5, 20, 45, .14), transparent 72%);
    pointer-events: none;
}
.participants-premium .participants-hero-content { position: relative; z-index: 1; padding: 2.2rem 2.6rem; }
.participants-premium .participants-hero h1 { font-size: clamp(1.85rem, 3vw, 2.55rem); line-height: 1.12; font-weight: 800; }
.participants-premium .participants-hero-cta {
    display: inline-flex; align-items: center; justify-content: center; gap: .7rem; padding: .85rem 1.3rem;
    min-height: 3rem; border-radius: .8rem; background: #fff; color: #1454a0;
    font-size: .91rem; font-weight: 800; box-shadow: 0 8px 20px rgba(0,18,54,.12);
    transition: background .18s, transform .18s;
}
.participants-premium .participants-hero-cta:hover { transform: translateY(-2px); background: #edf5ff; }
.participants-premium .participants-tabs { display: flex; align-items: center; width: 100%; min-width: max-content; gap: .3rem; padding: .5rem; border-radius: 1.15rem; }
.participants-premium .participants-tab { min-width: 0; flex: 1; color: #29476b; border-radius: .75rem; }
.participants-premium .participants-tab-active { background: #185bbb; color: white; box-shadow: 0 5px 12px rgba(24,91,187,.16); }
.participants-premium .participants-stat { display: flex; align-items: center; gap: 1rem; padding: 1.3rem; min-height: 7.6rem; }
.participants-premium .participants-stat strong { display: block; font-size: 2rem; line-height: 1.08; font-weight: 850; color: #102b52; }
.participants-premium .participants-stat p { margin-top: .35rem; font-size: .89rem; color: #426086; }
.participants-premium .participants-stat-icon { width: 3.65rem; height: 3.65rem; flex-basis: 3.65rem; }
.participants-premium .registration-premium { margin-top: 1.5rem; background: #f8fbff; border: 1px solid #e1eaf5; }
.participants-premium .registration-heading { padding: 1.5rem 1.8rem; background: linear-gradient(110deg, #143e77, #1e66b8); }
.participants-premium .registration-grid { gap: 1.1rem; }
.participants-premium .premium-form-section { padding: 1.45rem; border-radius: 1.15rem; margin-bottom: 1rem; }
.participants-premium .form-section-heading { margin-bottom: 1.25rem; }
.participants-premium .form-section-heading h3 { color: #142e52; font-weight: 800; font-size: 1.02rem; }
.participants-premium .form-section-icon { background: linear-gradient(135deg,#2b79e8,#1559ba); color: white; border-radius: .85rem; }
.participants-premium .premium-guide { background: white; border: 1px solid #e2eaf5; border-radius: 1.15rem; padding: 1.35rem; }
.participants-premium .guide-header { display: flex; align-items: center; gap: .8rem; }
.participants-premium .guide-header h3 { font-weight: 800; color: #152f53; font-size: 1rem; }
.participants-premium .guide-icon { display: grid; place-items: center; width: 2.55rem; height: 2.55rem; background: #e7f0ff; color: #1559ba; border-radius: .75rem; }
.participants-premium .guide-steps { margin-top: 1.35rem; display: grid; gap: 1.05rem; }
.participants-premium .guide-steps li { display: flex; align-items: flex-start; gap: .75rem; font-size: .83rem; line-height: 1.5; color: #395479; }
.participants-premium .guide-steps li span { flex: 0 0 1.7rem; display: grid; place-items: center; height: 1.7rem; width: 1.7rem; border-radius: 50%; background: #e9f1fe; color: #145cb8; font-weight: 800; }
.participants-premium .guide-warning { display: flex; gap: .65rem; padding: .85rem; margin-top: 1.5rem; background: #fff7e8; border: 1px solid #f6d68e; color: #875316; font-size: .8rem; border-radius: .8rem; }
.participants-premium .guide-warning svg { flex-shrink: 0; }
.participants-premium .premium-toolbar { padding: 1rem 1.25rem; }
.participants-premium .premium-entity-card { border-radius: 1.2rem; }
.participants-premium .premium-entity-card h2 { font-size: 1.14rem; }
.participants-premium .premium-team-actions { max-width: 45rem; }
.participants-premium .premium-team-actions > * { border-radius: .7rem; white-space: nowrap; }
.participants-premium .premium-team-actions .btn-primary { background: #1860c5; }
.participants-premium .entity-meta { display: inline-flex; align-items: center; gap: .35rem; }
.participants-premium .premium-participations { background: #fbfcfe; }
.participants-premium .premium-participation-row { border-radius: .85rem; }
.participants-premium .request-crest { display: grid; place-items: center; flex: 0 0 3.5rem; width: 3.5rem; height: 3.5rem; overflow: hidden; background: #f5f9ff; border: 1px solid #dce8f4; border-radius: .85rem; color: #205aa5; }
.participants-premium .request-crest img { width: 100%; height: 100%; object-fit: contain; padding: .2rem; }
.participants-premium .participants-empty-state { padding: 3rem 1.5rem; text-align: center; background: rgba(255,255,255,.98); border: 1px solid #e1eaf3; border-radius: 1.3rem; }
.participants-premium .participants-empty-state .empty-icon { display: grid; place-items: center; margin: 0 auto 1rem; width: 5.5rem; height: 5.5rem; background: #ebf3ff; color: #2257a0; border-radius: 50%; }
.participants-premium .participants-empty-state h2 { font-size: 1.3rem; font-weight: 800; color: #172e51; }
.participants-premium .participants-empty-state p { margin: .65rem auto 0; max-width: 33rem; color: #5b7090; line-height: 1.65; }
.participants-premium .participants-empty-link { display: inline-flex; gap: .6rem; align-items: center; padding: .8rem 1.2rem; border-radius: .75rem; background: #155fc2; color: white; font-size: .9rem; font-weight: 700; }
@media (max-width: 767px) {
  .participants-premium .participants-hero-content { padding: 1.5rem; }
  .participants-premium .participants-tabs { min-width: max-content; }
  .participants-premium .registration-heading { padding: 1.35rem; }
  .participants-premium .registration-grid { display: block; }
  .participants-premium .premium-form-section { padding: 1rem; }
}

/* Sistema de iconos coherente con las tarjetas de Competencias y reglamentos. */
.participants-premium .participants-stat-icon,
.participants-premium .participants-stat:nth-child(n) .participants-stat-icon {
    width: 3.55rem;
    height: 3.55rem;
    flex: 0 0 3.55rem;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}
.participants-premium .participants-tab-icon {
    display: grid;
    width: 2rem;
    height: 2rem;
    flex: 0 0 2rem;
    place-items: center;
    border-radius: .65rem;
    background: #eaf2fc;
    color: #174e92;
}
.participants-premium .participants-tab-active .participants-tab-icon {
    background: rgba(255,255,255,.18);
    color: #fff;
}
.participants-premium .participants-stat strong { color: #112746; }
.participants-premium .participants-stat p { color: #223b5e; font-weight: 700; }
</style>

<style scoped>
/* Mejoras puntuales de legibilidad e iconografía del módulo. */
.participants-premium .participants-stat-icon {
    border: 1px solid rgba(23, 75, 141, .10);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
}
.participants-premium .participants-tab :deep(svg) {
    flex-shrink: 0;
    stroke-width: 2.1;
}
.participants-premium .participants-tab-active :deep(svg) {
    filter: none;
}
</style>
