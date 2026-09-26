<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
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
const labels: Record<string, string> = {
    pending: 'Pendiente', active: 'Activo', suspended: 'Suspendido', inactive: 'Inactivo', deregistered: 'Baja', rejected: 'Rechazado',
};

function file(event: Event): File | null { return (event.target as HTMLInputElement).files?.[0] ?? null; }
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
        <section>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 5</p>
            <h1 class="mt-1 text-3xl font-semibold">Equipos y representantes</h1>
            <p class="mt-2 text-slate-600">Administra la identidad permanente del equipo, su propietario y sus participaciones.</p>

            <div class="mt-7 overflow-x-auto border-b border-slate-200"><div class="flex min-w-max gap-1">
                <button v-for="tab in tabs" :key="tab[0]" type="button" class="rounded-t-xl px-4 py-3 text-sm font-semibold" :class="activeTab === tab[0] ? 'bg-league-700 text-white' : 'text-slate-600 hover:bg-slate-200'" @click="activeTab = tab[0]">{{ tab[1] }}</button>
            </div></div>

            <div v-if="activeTab === 'resumen' && canManage" class="mt-7 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="stat-card"><span class="stat-label">Equipos</span><span class="stat-value">{{ teams.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Activos</span><span class="stat-value">{{ teams.filter((team) => team.status === 'active').length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Participaciones pendientes</span><span class="stat-value">{{ pendingParticipations.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Cambios pendientes</span><span class="stat-value">{{ pendingChanges.length }}</span></div>
                </div>
                <div v-if="!representatives.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No hay representantes disponibles. Crea o asigna primero un usuario con el rol <strong>Representante de equipo</strong> desde Miembros.</div>
                <div v-if="!competitions.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No hay competencias disponibles. Crea primero la temporada, torneo, división, categoría y competencia.</div>
            </div>

            <form v-if="activeTab === 'registrar' && canManage" class="card mt-7 space-y-6 p-6 sm:p-8" @submit.prevent="submitTeam">
                <div><h2 class="text-xl font-semibold">Registrar equipo</h2><p class="mt-1 text-sm text-slate-600">El equipo quedará pendiente hasta que apruebes su participación.</p></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="createForm.name" class="form-input" required maxlength="150"><FormError :message="createForm.errors.name" /></div><div><label class="form-label">Nombre corto</label><input v-model="createForm.short_name" class="form-input" required maxlength="30"><FormError :message="createForm.errors.short_name" /></div></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Color principal</label><input v-model="createForm.primary_color" class="form-input h-12" type="color"></div><div><label class="form-label">Color secundario</label><input v-model="createForm.secondary_color" class="form-input h-12" type="color"></div></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Teléfono privado</label><input v-model="createForm.phone" class="form-input" maxlength="30"></div><div><label class="form-label">Correo privado</label><input v-model="createForm.email" class="form-input" type="email" maxlength="150"><FormError :message="createForm.errors.email" /></div></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Fecha de fundación (opcional)</label><input v-model="createForm.founded_on" class="form-input" type="date"></div><div><label class="form-label">Competencia</label><select v-model="createForm.competition_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }} · {{ competition.division.name }} · {{ competition.category.name }}</option></select><FormError :message="createForm.errors.competition_id" /></div></div>
                <div><label class="form-label">Descripción (opcional)</label><textarea v-model="createForm.description" class="form-input min-h-24" maxlength="2000"></textarea></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Escudo (opcional)</label><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.crest = file($event)"><FormError :message="createForm.errors.crest" /></div><div><label class="form-label">Fotografía del equipo (opcional)</label><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.team_photo = file($event)"><FormError :message="createForm.errors.team_photo" /></div></div>
                <div class="rounded-2xl bg-slate-50 p-5"><h3 class="font-semibold">Propietario / representante</h3><div class="mt-4 grid gap-5 sm:grid-cols-3"><div><label class="form-label">Usuario representante</label><select v-model="createForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="representative in representatives" :key="representative.id" :value="representative.id">{{ representative.user.name }} · {{ representative.user.email }}</option></select><FormError :message="createForm.errors.league_membership_id" /></div><div><label class="form-label">Fotografía</label><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_photo = file($event)"><FormError :message="createForm.errors.representative_photo" /></div><div><label class="form-label">Imagen del INE</label><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_ine = file($event)"><FormError :message="createForm.errors.representative_ine" /></div></div></div>
                <div><label class="form-label">Motivo del registro</label><textarea v-model="createForm.reason" class="form-input min-h-20" required maxlength="500"></textarea><FormError :message="createForm.errors.reason" /></div>
                <button class="btn-primary" :disabled="createForm.processing || !representatives.length || !competitions.length">Registrar equipo</button>
            </form>

            <div v-if="activeTab === 'equipos'" class="mt-7 space-y-5">
                <div v-if="!teams.length" class="card p-12 text-center text-slate-600">{{ canManage ? 'No hay equipos registrados.' : 'Todavía no tienes un equipo asignado.' }}</div>
                <article v-for="team in teams" :key="team.id" class="card overflow-hidden">
                    <div class="grid gap-6 p-6 lg:grid-cols-[110px_1fr_auto]">
                        <div class="flex h-24 w-24 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50"><img v-if="team.crest_url" :src="team.crest_url" :alt="`Escudo de ${team.name}`" class="h-20 w-20 object-contain"><span v-else class="text-3xl font-bold text-slate-300">{{ team.short_name.slice(0, 3) }}</span></div>
                        <div><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-semibold">{{ team.name }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ labels[team.status] }}</span></div><p class="mt-2 text-sm text-slate-600">Representante: <strong>{{ team.active_representative?.user.name ?? 'Sin representante' }}</strong></p><p class="mt-1 text-sm text-slate-500">{{ team.email || 'Sin correo' }} · {{ team.phone || 'Sin teléfono' }}</p><div class="mt-3 flex gap-2"><span class="h-5 w-10 rounded border" :style="{ backgroundColor: team.primary_color }"></span><span class="h-5 w-10 rounded border" :style="{ backgroundColor: team.secondary_color }"></span></div></div>
                        <div class="flex flex-wrap content-start gap-2"><button class="btn-secondary" @click="startEdit(team)">{{ canManage ? 'Editar' : 'Solicitar cambio' }}</button><template v-if="canManage"><button class="btn-primary" :disabled="!eligibleCompetitions(team).length" @click="participationTeam = team">Nueva participación</button><a :href="`/liga/equipos/${team.id}/representante/photo`" class="btn-secondary">Foto del representante</a><a :href="`/liga/equipos/${team.id}/representante/ine`" class="btn-secondary">INE</a><button class="btn-secondary" @click="representativeTeam = team">Cambiar representante</button></template></div>
                    </div>
                    <div class="border-t border-slate-200 bg-slate-50 p-5"><h3 class="text-sm font-semibold">Participaciones</h3><div class="mt-3 space-y-3"><div v-for="participation in team.participations" :key="participation.id" class="flex flex-col justify-between gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-center"><div><p class="text-sm font-medium">{{ participation.competition.tournament.season.name }} · {{ participation.competition.name }}</p><p class="mt-1 text-xs text-slate-500">{{ participation.competition.division.name }} · {{ participation.competition.category.name }} · {{ labels[participation.status] }}</p></div><div v-if="canManage" class="flex flex-wrap gap-2"><button v-if="['pending', 'rejected'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'approve', 'Aprobar')">Aprobar</button><button v-if="participation.status === 'pending'" class="btn-danger" @click="transition(participation, 'reject', 'Rechazar')">Rechazar</button><button v-if="participation.status === 'active'" class="btn-danger" @click="transition(participation, 'suspend', 'Suspender')">Suspender</button><button v-if="['suspended', 'inactive'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'reactivate', 'Reactivar')">Reactivar</button><button v-if="['active', 'suspended'].includes(participation.status)" class="btn-secondary" @click="transition(participation, 'deactivate', 'Desactivar')">Desactivar</button></div></div></div></div>
                    <details v-if="team.names.length > 1" class="border-t border-slate-200 p-5"><summary class="cursor-pointer text-sm font-semibold">Historial de nombres ({{ team.names.length }})</summary><ul class="mt-3 space-y-2 text-sm text-slate-600"><li v-for="name in team.names" :key="name.id">{{ name.name }} ({{ name.short_name }}) · desde {{ new Date(name.valid_from).toLocaleDateString('es-MX') }}</li></ul></details>
                </article>
            </div>

            <div v-if="activeTab === 'solicitudes'" class="mt-7 space-y-5">
                <div v-if="canManage && pendingParticipations.length" class="card p-6"><h2 class="text-xl font-semibold">Participaciones pendientes</h2><div class="mt-4 divide-y divide-slate-200"><div v-for="item in pendingParticipations" :key="item.participation.id" class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"><div><p class="font-medium">{{ item.team.name }}</p><p class="text-sm text-slate-500">{{ item.participation.competition.name }}</p></div><div class="flex gap-2"><button class="btn-primary" @click="transition(item.participation, 'approve', 'Aprobar')">Aprobar</button><button class="btn-danger" @click="transition(item.participation, 'reject', 'Rechazar')">Rechazar</button></div></div></div></div>
                <div class="card p-6"><h2 class="text-xl font-semibold">{{ canManage ? 'Cambios pendientes' : 'Solicitudes enviadas' }}</h2><div v-if="!pendingChanges.length" class="mt-4 text-sm text-slate-500">No hay solicitudes pendientes.</div><div v-else class="mt-4 divide-y divide-slate-200"><div v-for="item in pendingChanges" :key="item.change.id" class="py-4"><div class="flex flex-col justify-between gap-3 sm:flex-row"><div><p class="font-medium">{{ item.team.name }}</p><p class="mt-1 text-sm text-slate-500">{{ item.change.request_reason }}</p><p class="mt-2 text-xs text-slate-500">Solicitó: {{ item.change.requester.name }}</p></div><div v-if="canManage" class="flex gap-2"><button class="btn-primary" @click="reviewChange(item.change, 'approve')">Aprobar</button><button class="btn-danger" @click="reviewChange(item.change, 'reject')">Rechazar</button></div></div></div></div></div>
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
