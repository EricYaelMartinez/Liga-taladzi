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
const teamOrder = ref('newest');
const teamView = ref<'list' | 'grid'>('list');
const openTeamMenu = ref<number | null>(null);
const registrationStep = ref(0);
const registrationAlert = ref('');
const requestSearch = ref('');
const requestType = ref('all');
const requestStatus = ref('pending');
const stepNames = ['Identidad y colores', 'Competencia y contacto', 'Escudo y documentos', 'Representante', 'Confirmación'];
const stepDescriptions = ['Datos generales e identidad visual', 'Competencia, contacto y medios', 'Escudo del equipo y documentos', 'Datos del representante y permisos', 'Revisa la información y registra'];
const requestTypes = [
    { icon: 'users', title: 'Cambios de representante', description: 'Actualizaciones del responsable o datos de contacto.' },
    { icon: 'trophy', title: 'Nuevas participaciones', description: 'Inscripciones de equipos en competencias de la liga.' },
    { icon: 'file', title: 'Actualizaciones de documentos', description: 'Carga o renovación de archivos requeridos.' },
    { icon: 'edit', title: 'Revisiones de datos', description: 'Correcciones o complementos de información.' },
];

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
}).sort((a, b) => teamOrder.value === 'az' ? a.name.localeCompare(b.name, 'es') : teamOrder.value === 'za' ? b.name.localeCompare(a.name, 'es') : b.id - a.id));
const filteredParticipations = computed(() => pendingParticipations.value.filter(({team, participation}) =>
    (requestType.value === 'all' || requestType.value === 'participation') &&
    (requestStatus.value === 'all' || participation.status === requestStatus.value) &&
    (!requestSearch.value || `${team.name} ${participation.competition.name}`.toLowerCase().includes(requestSearch.value.toLowerCase()))
));
const filteredChanges = computed(() => pendingChanges.value.filter(({team, change}) =>
    (requestType.value === 'all' || requestType.value === 'change') &&
    (requestStatus.value === 'all' || change.status === requestStatus.value) &&
    (!requestSearch.value || `${team.name} ${change.request_reason} ${change.requester.name}`.toLowerCase().includes(requestSearch.value.toLowerCase()))
));
function goRegistrationStep(next: number) {
    registrationAlert.value = '';
    if (next > registrationStep.value) {
        const invalid = registrationStep.value === 0 ? !createForm.name.trim() || !createForm.short_name.trim()
            : registrationStep.value === 1 ? !createForm.competition_id
            : registrationStep.value === 3 ? !createForm.league_membership_id || !createForm.representative_photo || !createForm.representative_ine
            : false;
        if (invalid) { registrationAlert.value = 'Completa los campos obligatorios de este paso para continuar.'; return; }
    }
    registrationStep.value = Math.max(0, Math.min(4, next));
}

const labels: Record<string, string> = {
    pending: 'Pendiente', active: 'Activo', suspended: 'Suspendido', inactive: 'Inactivo', deregistered: 'Baja', rejected: 'Rechazado',
};

function file(event: Event): File | null { return (event.target as HTMLInputElement).files?.[0] ?? null; }
function initials(team: Team): string { return (team.short_name || team.name).split(/\s+/).map((word) => word[0]).join('').slice(0, 3).toUpperCase(); }
function statusClass(status: string): string {
    return ({ active: 'entity-status-active', pending: 'entity-status-pending', suspended: 'entity-status-suspended', rejected: 'entity-status-rejected', inactive: 'entity-status-neutral', deregistered: 'entity-status-neutral' } as Record<string, string>)[status] ?? 'entity-status-neutral';
}
function submitTeam() {
    const check = !createForm.name.trim() ? 0 : !createForm.short_name.trim() ? 0 : !createForm.competition_id ? 1 :
        (!createForm.league_membership_id || !createForm.representative_photo || !createForm.representative_ine) ? 3 : !createForm.reason.trim() ? 4 : -1;
    if (check >= 0) { registrationStep.value = check; registrationAlert.value = 'Revisa los campos obligatorios antes de registrar el equipo.'; return; }
    registrationAlert.value = '';
    createForm.post('/liga/equipos', { forceFormData: true, preserveScroll: true, onSuccess: () => { createForm.reset(); registrationStep.value = 0; activeTab.value = 'equipos'; } });
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
                        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.24em] text-amber-200">Participantes · Módulo 5</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Equipos y representantes</h1><p class="mt-2 text-sm text-white/75 sm:text-base">Centraliza la identidad de cada club, sus responsables y la participación en las competencias de la liga.</p></div>
                        <button v-if="canManage" class="participants-hero-cta" @click="activeTab = 'registrar'"><AppIcon name="users" :size="20" /> Registrar equipo</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto"><div class="participants-tabs">
                <button v-for="tab in tabs" :key="tab[0]" type="button" class="participants-tab" :class="activeTab === tab[0] ? 'participants-tab-active' : ''" @click="activeTab = tab[0]"><AppIcon :name="({ resumen: 'chart', registrar: 'badge', equipos: 'team', solicitudes: 'clipboard' } as Record<string,string>)[tab[0]] ?? 'team'" :size="19" />{{ tab[1] }}</button>
            </div></div>

            <div v-if="activeTab === 'resumen' && canManage" class="mt-7 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="participants-stat"><span class="participants-stat-icon"><AppIcon name="users" :size="26" /></span><div><strong>{{ teams.length }}</strong><p>Equipos registrados</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon bg-emerald-50 text-emerald-700"><AppIcon name="check" :size="26" /></span><div><strong>{{ teams.filter((team) => team.status === 'active').length }}</strong><p>Equipos activos</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon bg-amber-50 text-amber-700"><AppIcon name="clock" :size="26" /></span><div><strong>{{ pendingParticipations.length }}</strong><p>Participaciones pendientes</p></div></div>
                    <div class="participants-stat"><span class="participants-stat-icon bg-sky-50 text-sky-700"><AppIcon name="edit" :size="26" /></span><div><strong>{{ pendingChanges.length }}</strong><p>Cambios por revisar</p></div></div>
                </div>
                <div class="summary-bottom-grid">
                    <div v-if="!representatives.length" class="summary-empty premium-panel">
                        <div class="summary-illustration"><AppIcon name="shield" :size="80" /><span class="summary-illustration-small"><AppIcon name="users" :size="38" /></span></div>
                        <div><h2>Aún no hay representantes disponibles</h2><p>Crea o asigna primero un usuario con el rol Representante de equipo desde Miembros para que los clubes puedan gestionar su información, participaciones y contactos.</p>
                            <div class="summary-buttons"><a href="/liga/miembros" class="btn-primary"><AppIcon name="users" :size="17" /> Ir a miembros</a><button type="button" class="btn-secondary" @click="activeTab = 'registrar'"><AppIcon name="users" :size="17" /> Registrar equipo</button></div>
                        </div>
                    </div>
                    <div v-else class="premium-panel summary-empty"><div class="summary-illustration"><AppIcon name="check" :size="66" /></div><div><h2>{{ representatives.length }} representantes disponibles</h2><p>Ya puedes registrar nuevos clubes y asignarles una persona responsable.</p><button type="button" class="btn-primary mt-5" @click="activeTab='registrar'">Registrar equipo</button></div></div>
                    <aside class="premium-panel next-steps"><div class="flex items-center gap-3"><span class="step-rocket"><AppIcon name="trophy" :size="24" /></span><div><h3>Próximos pasos</h3><p>Completa la configuración del módulo y ponlo en marcha.</p></div></div>
                        <button type="button" class="next-step" @click="activeTab='registrar'"><span>1</span><div><strong>Registrar tu primer equipo</strong><small>Crea la identidad del club y su información básica.</small></div><b>›</b></button>
                        <a class="next-step" href="/liga/miembros"><span>2</span><div><strong>Asignar representante</strong><small>Vincula un usuario autorizado a la liga.</small></div><b>›</b></a>
                        <button type="button" class="next-step" @click="activeTab='equipos'"><span>3</span><div><strong>Gestionar participaciones</strong><small>Define en qué competencias participará el club.</small></div><b>›</b></button>
                    </aside>
                </div>
                <div v-if="!competitions.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No hay competencias disponibles. Crea primero la temporada, torneo, división, categoría y competencia.</div>
            </div>

            <form v-if="activeTab === 'registrar' && canManage" class="registration-shell registration-premium" novalidate @submit.prevent="submitTeam">
                <div class="registration-stepper"><button v-for="(name, index) in stepNames" :key="name" type="button" :class="['wizard-step', registrationStep === index ? 'wizard-step-active' : '']" @click="registrationStep = index; registrationAlert = ''"><span>{{ index + 1 }}</span><div><strong>{{ name }}</strong><small>{{ stepDescriptions[index] }}</small></div></button></div>
                <div class="registration-grid wizard-layout">
                    <div class="registration-main wizard-main">
                        <section v-show="registrationStep === 0" class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="shield" :size="22" /></span><div><h3>1. Identidad y colores</h3><p>Información básica del equipo en la liga.</p></div></div>
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

                        <section v-show="registrationStep === 1" class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="trophy" :size="22" /></span><div><h3>2. Competencia y contacto</h3><p>Competencia inicial y medios privados de comunicación.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2"><label class="form-label">Competencia<span class="form-required">*</span></label><select v-model="createForm.competition_id" class="form-input" required><option :value="null" disabled>Selecciona temporada, competencia, división y categoría</option><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }} · {{ competition.division.name }} · {{ competition.category.name }}</option></select><FormError :message="createForm.errors.competition_id" /></div>
                                <div><label class="form-label">Teléfono privado</label><input v-model="createForm.phone" class="form-input" type="tel" maxlength="30" placeholder="Ej. 951 000 0000"><p class="form-field-hint">Visible solo para administración.</p></div>
                                <div><label class="form-label">Correo privado</label><input v-model="createForm.email" class="form-input" type="email" maxlength="150" placeholder="equipo@correo.com"><FormError :message="createForm.errors.email" /></div>
                            </div>
                        </section>

                        <section v-show="registrationStep === 2" class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="image" :size="22" /></span><div><h3>3. Escudo y documentos</h3><p>Archivos que identificarán al equipo en el portal y sus documentos.</p></div></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="form-label">Escudo del equipo</label><div class="file-field"><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.crest = file($event)"><p class="form-field-hint">JPG, PNG o WEBP. Preferentemente cuadrado.</p></div><FormError :message="createForm.errors.crest" /></div>
                                <div><label class="form-label">Fotografía del equipo</label><div class="file-field"><input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.team_photo = file($event)"><p class="form-field-hint">Imagen grupal opcional del plantel.</p></div><FormError :message="createForm.errors.team_photo" /></div>
                            </div>
                        </section>

                        <section v-show="registrationStep === 3" class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="users" :size="22" /></span><div><h3>4. Representante y permisos</h3><p>Persona responsable de la gestión del equipo ante la liga.</p></div></div>
                            <div class="grid gap-5 lg:grid-cols-2">
                                <div class="lg:col-span-2"><label class="form-label">Usuario representante<span class="form-required">*</span></label><select v-model="createForm.league_membership_id" class="form-input" required><option :value="null" disabled>Selecciona un miembro con rol Representante</option><option v-for="representative in representatives" :key="representative.id" :value="representative.id">{{ representative.user.name }} · {{ representative.user.email }}</option></select><FormError :message="createForm.errors.league_membership_id" /></div>
                                <div><label class="form-label">Fotografía del representante<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_photo = file($event)"><p class="form-field-hint">Imagen clara y reciente.</p></div><FormError :message="createForm.errors.representative_photo" /></div>
                                <div><label class="form-label">Identificación INE<span class="form-required">*</span></label><div class="file-field"><input class="form-input" type="file" required accept="image/jpeg,image/png,image/webp" @change="createForm.representative_ine = file($event)"><p class="form-field-hint">Archivo privado, visible solo para administración.</p></div><FormError :message="createForm.errors.representative_ine" /></div>
                            </div>
                        </section>

                        <section v-show="registrationStep === 4" class="form-section premium-form-section">
                            <div class="form-section-heading"><span class="form-section-icon"><AppIcon name="edit" :size="26" /></span><div><h3>5. Confirmación del registro</h3><p>Describe brevemente el motivo administrativo del alta.</p></div></div>
                            <label class="form-label">Motivo<span class="form-required">*</span></label><textarea v-model="createForm.reason" class="form-input min-h-24" required maxlength="500" placeholder="Ej. Alta para participar en la temporada vigente"></textarea><FormError :message="createForm.errors.reason" />
                        </section>
                    </div>

                    <aside class="registration-aside wizard-aside">
                        <div class="registration-note premium-guide"><div class="guide-header"><span class="guide-icon"><AppIcon name="file" :size="22" /></span><div><h3>Guía del paso</h3><p class="text-xs text-slate-500">{{ stepDescriptions[registrationStep] }}</p></div></div>
                            <p class="guide-copy">{{ creationSteps[registrationStep] }}</p><div class="guide-warning"><AppIcon name="shield" :size="18" /><p>El registro requiere una competencia y un representante autorizado. Los documentos privados no son públicos.</p></div></div>
                        <div v-if="!representatives.length || !competitions.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">Debes contar con al menos un representante y una competencia disponibles para completar el registro.</div>
                        <div class="wizard-actions"><p v-if="registrationAlert" role="alert" class="wizard-alert">{{ registrationAlert }}</p><div class="flex flex-wrap items-center justify-end gap-2"><button v-if="registrationStep > 0" type="button" class="btn-secondary" @click="goRegistrationStep(registrationStep - 1)">← Anterior</button><button v-if="registrationStep < 4" type="button" class="btn-primary" @click="goRegistrationStep(registrationStep + 1)">Continuar →</button><button v-else type="submit" class="btn-primary" :disabled="createForm.processing || !representatives.length || !competitions.length">{{ createForm.processing ? 'Registrando…' : 'Registrar equipo' }}</button></div></div>
                    </aside>
                </div>
            </form>

            <div v-if="activeTab === 'equipos'" :class="['mt-7 space-y-5', teamView === 'grid' ? 'directory-grid' : '']">
                <div class="premium-panel directory-shell"><div class="directory-title"><h2>Directorio de equipos</h2><p>Gestiona los equipos, sus representantes y sus participaciones en la liga.</p></div><div class="directory-filters"><input v-model="teamSearch" aria-label="Buscar equipo o representante" class="form-input" type="search" placeholder="⌕  Buscar equipo o representante..."><select v-model="teamStatusFilter" aria-label="Filtrar por estado" class="form-input"><option value="all">Estado: Todos</option><option v-for="(label, value) in labels" :key="value" :value="value">{{ label }}</option></select><select v-model="teamOrder" aria-label="Ordenar equipos" class="form-input"><option value="newest">Más recientes</option><option value="az">Nombre A-Z</option><option value="za">Nombre Z-A</option></select><div class="view-switch"><button type="button" title="Vista de lista" :class="teamView === 'list' ? 'selected' : ''" @click="teamView = 'list'"><AppIcon name="clipboard" :size="17" /></button><button type="button" title="Vista en cuadrícula" :class="teamView === 'grid' ? 'selected' : ''" @click="teamView='grid'"><AppIcon name="team" :size="17" /></button></div></div></div>
                <div v-if="!visibleTeams.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/80 p-12 text-center text-slate-600"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-league-50 text-league-700"><AppIcon name="team" :size="30" /></div><p class="mt-4 font-bold text-slate-800">{{ teams.length ? 'Ningún equipo coincide con los filtros.' : (canManage ? 'No hay equipos registrados.' : 'Todavía no tienes un equipo asignado.') }}</p></div>
                <article v-for="team in visibleTeams" :key="team.id" class="entity-card premium-entity-card">
                    <div class="grid gap-5 p-5 lg:grid-cols-[92px_minmax(0,1fr)_auto]">
                        <div class="entity-avatar premium-crest h-24 w-24"><img v-if="team.crest_url" :src="team.crest_url" :alt="`Escudo de ${team.name}`"><span v-else>{{ initials(team) }}</span></div>
                        <div><div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-bold text-slate-900">{{ team.name }}</h2><span class="entity-status" :class="statusClass(team.status)">{{ labels[team.status] }}</span></div><p class="mt-2 text-sm text-slate-600">Representante: <strong>{{ team.active_representative?.user.name ?? 'Sin representante' }}</strong></p><div class="mt-3 flex flex-wrap gap-2"><span class="entity-meta"><AppIcon name="mail" :size="14" /> {{ team.email || 'Sin correo' }}</span><span class="entity-meta"><AppIcon name="phone" :size="14" /> {{ team.phone || 'Sin teléfono' }}</span><span class="entity-meta"><AppIcon name="users" :size="14" /> {{ team.participations.length }} participaciones</span></div><div class="mt-3 flex gap-1.5"><span class="h-4 w-8 rounded-full border border-slate-200" :style="{ backgroundColor: team.primary_color }"></span><span class="h-4 w-8 rounded-full border border-slate-200" :style="{ backgroundColor: team.secondary_color }"></span></div></div>
                        <div class="premium-team-actions flex flex-wrap content-start gap-2"><button v-if="canManage" class="btn-primary" :disabled="!eligibleCompetitions(team).length" @click="participationTeam = team"><AppIcon name="users" :size="16" /> Nueva participación</button><button class="btn-secondary" @click="startEdit(team)"><AppIcon name="edit" :size="15" /> {{ canManage ? 'Editar' : 'Solicitar cambio' }}</button><div v-if="canManage" class="team-more"><button type="button" class="btn-secondary" :aria-expanded="openTeamMenu === team.id" :aria-label="`Más opciones para ${team.name}`" @click="openTeamMenu = openTeamMenu === team.id ? null : team.id">···</button><div v-if="openTeamMenu === team.id" class="team-more-menu"><button type="button" @click="representativeTeam = team; openTeamMenu = null"><AppIcon name="users" :size="16" /> Cambiar representante</button><a :href="`/liga/equipos/${team.id}/representante/photo`"><AppIcon name="image" :size="16" /> Foto del representante</a><a :href="`/liga/equipos/${team.id}/representante/ine`"><AppIcon name="file" :size="16" /> INE</a><p class="menu-helper">Los cambios de estado se gestionan por participación.</p></div></div></div>
                    </div>
                    <div class="premium-participations border-t border-slate-200 bg-slate-50 p-5"><h3 class="text-sm font-semibold">Participaciones ({{ team.participations.length }})</h3><div class="mt-3 space-y-3"><div v-for="participation in team.participations" :key="participation.id" class="premium-participation-row flex flex-col justify-between gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-center"><div><p class="text-sm font-medium">{{ participation.competition.tournament.season.name }} · {{ participation.competition.name }}</p><p class="mt-1 text-xs text-slate-500">{{ participation.competition.division.name }} · {{ participation.competition.category.name }} · {{ labels[participation.status] }}</p></div><div v-if="canManage" class="flex flex-wrap gap-2"><button v-if="['pending', 'rejected'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'approve', 'Aprobar')">Aprobar</button><button v-if="participation.status === 'pending'" class="btn-danger" @click="transition(participation, 'reject', 'Rechazar')">Rechazar</button><button v-if="participation.status === 'active'" class="btn-danger" @click="transition(participation, 'suspend', 'Suspender')">Suspender</button><button v-if="['suspended', 'inactive'].includes(participation.status)" class="btn-primary" @click="transition(participation, 'reactivate', 'Reactivar')">Reactivar</button><button v-if="['active', 'suspended'].includes(participation.status)" class="btn-secondary" @click="transition(participation, 'deactivate', 'Desactivar')">Desactivar</button></div></div></div></div>
                    <details v-if="team.names.length > 1" class="border-t border-slate-200 p-5"><summary class="cursor-pointer text-sm font-semibold">Historial de nombres ({{ team.names.length }})</summary><ul class="mt-3 space-y-2 text-sm text-slate-600"><li v-for="name in team.names" :key="name.id">{{ name.name }} ({{ name.short_name }}) · desde {{ new Date(name.valid_from).toLocaleDateString('es-MX') }}</li></ul></details>
                </article>
            </div>

            <div v-if="activeTab === 'solicitudes'" class="mt-7 space-y-5"><div class="premium-panel request-heading"><div class="flex items-center gap-3"><span class="request-heading-icon"><AppIcon name="clipboard" :size="25" /></span><div><h2>Centro de solicitudes</h2><p>Revisa y gestiona cambios, actualizaciones y aprobaciones de equipos y representantes.</p></div></div><div class="request-filter-row"><input v-model="requestSearch" class="form-input" aria-label="Buscar solicitudes" placeholder="Buscar solicitud por equipo, representante o detalle..."><select v-model="requestType" class="form-input" aria-label="Tipo de solicitud"><option value="all">Todos los tipos</option><option value="participation">Participaciones</option><option value="change">Cambios de datos</option></select><select v-model="requestStatus" class="form-input" aria-label="Estado de solicitud"><option value="pending">Pendientes</option><option value="all">Todos los estados cargados</option></select></div></div><div class="request-content-grid"><div class="request-list">
                <div v-if="canManage && filteredParticipations.length" class="premium-request-card card p-6"><h2 class="text-xl font-semibold"> <AppIcon name="clock" :size="22" /> Participaciones pendientes</h2><div class="mt-4 divide-y divide-slate-200"><div v-for="item in filteredParticipations" :key="item.participation.id" class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"><div><div class="flex items-center gap-3"><span class="request-crest"><img v-if="item.team.crest_url" :src="item.team.crest_url" :alt="`Escudo de ${item.team.name}`"><AppIcon v-else name="team" :size="23" /></span><div><p class="font-semibold text-slate-900">{{ item.team.name }}</p><p class="text-sm text-slate-500">{{ item.participation.competition.name }}</p></div></div></div><div class="flex gap-2"><button class="btn-primary" @click="transition(item.participation, 'approve', 'Aprobar')">Aprobar</button><button class="btn-danger" @click="transition(item.participation, 'reject', 'Rechazar')">Rechazar</button></div></div></div></div>
                <div v-if="filteredChanges.length" class="premium-request-card card p-6"><h2 class="text-xl font-semibold"><AppIcon name="refresh" :size="22" /> {{ canManage ? 'Cambios pendientes' : 'Solicitudes enviadas' }}</h2><div v-if="!filteredChanges.length" class="mt-4 text-sm text-slate-500">No hay cambios que coincidan con los filtros.</div><div v-else class="mt-4 divide-y divide-slate-200"><div v-for="item in filteredChanges" :key="item.change.id" class="py-4"><div class="flex flex-col justify-between gap-3 sm:flex-row"><div><p class="font-medium">{{ item.team.name }}</p><p class="mt-1 text-sm text-slate-500">{{ item.change.request_reason }}</p><p class="mt-2 text-xs text-slate-500">Solicitó: {{ item.change.requester.name }}</p></div><div v-if="canManage" class="flex gap-2"><button class="btn-primary" @click="reviewChange(item.change, 'approve')">Aprobar</button><button class="btn-danger" @click="reviewChange(item.change, 'reject')">Rechazar</button></div></div></div></div></div>
                <div v-if="!filteredParticipations.length && !filteredChanges.length" class="premium-panel request-empty"><span class="request-empty-illustration"><AppIcon name="clipboard" :size="70" /></span><h3>No hay solicitudes pendientes</h3><p>¡Todo al día! Actualmente no hay cambios o aprobaciones que coincidan con los filtros.</p><div class="flex flex-wrap justify-center gap-3"><button type="button" class="btn-secondary" @click="activeTab='equipos'">Ver equipos</button><button v-if="canManage" type="button" class="btn-primary" @click="activeTab='registrar'">Registrar equipo</button></div></div>
                </div><aside class="request-info"><div class="premium-panel request-guide"><h3><AppIcon name="file" :size="20" /> Qué aparecerá aquí</h3><p>En este centro podrás revisar y gestionar solicitudes como:</p><div v-for="(type, index) in requestTypes" :key="index" class="request-guide-item"><span><AppIcon :name="type.icon" :size="18" /></span><div><strong>{{ type.title }}</strong><small>{{ type.description }}</small></div></div></div><div class="premium-panel request-guide"><h3><AppIcon name="clock" :size="19" /> Actividad y seguimiento</h3><p>Las solicitudes se conservan en los registros de la liga y se atienden desde sus acciones disponibles.</p></div></aside></div>
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
    background: linear-gradient(112deg, #133b75 0%, #155ca9 68%, #0e447e 100%);
    border-radius: 1.5rem; min-height: 192px;
}
.participants-premium .participants-hero::before {
    content: ''; position: absolute; z-index: 0; inset: 0;
    background: linear-gradient(133deg, transparent 62%, rgba(255,255,255,.05) 62% 74%, transparent 74%),
      linear-gradient(133deg, transparent 76%, rgba(255,255,255,.035) 76% 86%, transparent 86%);
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


/* Rediseño de Módulo 5: diseño coherente, marca dinámica, y comportamiento responsive */
.participants-premium { --pt-accent: var(--color-league-600, #b86a0a); --pt-strong: var(--color-league-700, #97510b); --pt-dark: var(--color-league-900, #56320c); }
.participants-premium .participants-hero { background-image: linear-gradient(104deg, color-mix(in srgb, var(--pt-dark) 88%, transparent), color-mix(in srgb, var(--pt-accent) 74%, transparent)), url('/images/public/league-stadium.jpg'); background-size: cover; background-position: center 54%; min-height: 174px; }
.participants-premium .participants-hero::before { background: linear-gradient(90deg,rgba(0,0,0,.15),transparent 75%); }
.participants-premium .participants-hero .text-amber-200 { color: #e6f58f; }
.participants-premium .participants-tab-active { background: var(--pt-accent); box-shadow: 0 6px 14px color-mix(in srgb, var(--pt-accent) 28%, transparent); }
.participants-premium .btn-primary, .participants-premium .participants-empty-link { background: var(--pt-accent); color: #fff; }
.participants-premium .btn-primary:hover { background: var(--pt-strong); }
.participants-premium .participants-stat { position: relative; overflow: hidden; min-height: 112px; }
.participants-premium .participants-stat::after { content:''; position:absolute; right:13px; bottom:13px; width:70px; height:27px; opacity:.22; border-bottom:3px solid var(--pt-accent); border-radius:0 0 65% 40%; transform: skewY(-9deg); }
.participants-premium .participants-stat:first-child .participants-stat-icon { background:color-mix(in srgb, var(--pt-accent) 12%, white); color: var(--pt-strong); }
.premium-panel { background:#fff; border:1px solid #e2eaf4; border-radius:20px; box-shadow:0 12px 28px rgba(23,48,82,.07); }
.summary-bottom-grid { display:grid; grid-template-columns:1.4fr .9fr; gap:18px; }
.summary-empty { display:grid; grid-template-columns: 36% 1fr; gap:20px; align-items:center; padding:32px; min-height:330px; }
.summary-illustration { height:180px; display:grid; place-items:center; position:relative; border-radius:50%; color:var(--pt-accent); background:radial-gradient(circle, color-mix(in srgb, var(--pt-accent) 14%,white), transparent 68%); }
.summary-illustration-small { position:absolute; display:grid; place-items:center; width:70px;height:70px;background:#fff;border-radius:50%; color:var(--pt-strong); }
.summary-empty h2 { font-size:clamp(1.25rem,2vw,1.65rem); font-weight:800; color:#172943; }
.summary-empty p { color:#687c98; line-height:1.7; margin-top:10px; }
.summary-buttons { display:flex; flex-wrap:wrap; gap:10px; margin-top:24px; }
.summary-buttons > * { display:inline-flex; gap:9px; align-items:center; justify-content:center; border-radius:10px; padding:11px 17px; font-weight:700; }
.next-steps { padding:26px; }.next-steps h3 { font-size:1.1rem;font-weight:800;color:#152b4b; }.next-steps p {font-size:.83rem;color:#64748b;margin-top:4px;}
.step-rocket { display:grid;place-items:center;width:46px;height:46px;border-radius:12px;background:#fff2d8;color:#b36800; }
.next-step { display:flex;align-items:center;gap:13px;padding:18px 2px;width:100%;text-align:left;border-top:1px solid #edf1f6; }.next-step:first-of-type{margin-top:16px}.next-step>span {display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:color-mix(in srgb,var(--pt-accent) 15%,white);color:var(--pt-strong);font-weight:800}.next-step>div{flex:1}.next-step strong{font-size:.88rem;color:#1b304b}.next-step small{display:block;font-size:.78rem;color:#71809b;margin-top:3px}.next-step b{font-size:1.4rem;color:var(--pt-accent)}
.participants-premium .registration-premium { background:transparent;overflow:visible;box-shadow:none;border:none; }
.participants-premium .registration-stepper { display:flex;gap:10px; flex-wrap:wrap; margin:20px 0 16px; }
.wizard-step { display:flex;align-items:center;gap:9px;padding:11px 13px; background:#fff; border:1px solid #dfe7f1; border-radius:14px; flex:1; min-width:150px;text-align:left; }
.wizard-step>span { display:grid;place-items:center;width:30px;height:30px;flex:0 0 30px;background:#eff4fb;color:#416080;border-radius:50%;font-weight:800; }
.wizard-step strong { display:block;font-size:.78rem; color:#23405e; }.wizard-step small {display:block;color:#8291a8;font-size:.68rem;line-height:1.4;margin-top:3px;}
.wizard-step-active { background:color-mix(in srgb,var(--pt-accent) 9%,white);border-color:color-mix(in srgb,var(--pt-accent) 33%,white); }
.wizard-step-active>span {background:var(--pt-accent);color:#fff}
.participants-premium .wizard-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,300px);align-items:start;gap:16px}.participants-premium .registration-main{min-width:0}
.participants-premium .premium-form-section {min-height:350px;padding:28px;}.participants-premium .form-section-icon{background:color-mix(in srgb,var(--pt-accent) 15%,white);color:var(--pt-strong)}
.participants-premium .premium-guide{min-height:245px;padding:25px}.participants-premium .guide-icon{background:color-mix(in srgb,var(--pt-accent) 13%,white);color:var(--pt-strong)}.guide-copy{font-size:.85rem;color:#5a6d86;line-height:1.7;margin-top:18px}.wizard-actions{margin-top:15px}.wizard-alert{background:#fff4e5;color:#9b4e00;border:1px solid #fed7aa;border-radius:10px;padding:10px;margin-bottom:10px;font-size:.82rem}
.directory-shell{padding:22px}.directory-title h2,.request-heading h2{font-size:1.3rem;font-weight:800;color:#162d4d}.directory-title p,.request-heading p{font-size:.85rem;color:#6b7d96;margin-top:4px}.directory-filters{display:grid;grid-template-columns:minmax(200px,1fr) 190px 190px auto;gap:12px;margin-top:18px}.view-switch{display:flex;gap:4px}.view-switch button{display:grid;place-items:center;width:43px;border:1px solid #dce6f0;border-radius:9px;color:#315478}.view-switch button.selected{background:var(--pt-accent);color:#fff;border-color:var(--pt-accent)}
.participants-premium .premium-team-actions{max-width:330px;}.team-more{position:relative}.team-more-menu{position:absolute;z-index:20;right:0;top:calc(100% + 8px);min-width:240px;padding:7px;background:#fff;border:1px solid #dce5f0;border-radius:12px;box-shadow:0 14px 30px #112f5225;}.team-more-menu a,.team-more-menu button{display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;text-align:left;border-radius:8px;font-size:.82rem;color:#253e5d}.team-more-menu a:hover,.team-more-menu button:hover{background:#f2f6fc}.team-more-menu .menu-helper{padding:8px;border-top:1px solid #e5edf5;color:#74839b;font-size:.72rem;line-height:1.5}.participants-premium .premium-entity-card .premium-participation-row{padding:11px 14px}.participants-premium .premium-entity-card .premium-participations{padding:15px 19px}
.directory-grid .premium-entity-card { display:block; }.directory-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.directory-grid>.directory-shell{grid-column:1/-1}.directory-grid .premium-entity-card>.grid{grid-template-columns:75px minmax(0,1fr);}.directory-grid .premium-entity-card .premium-team-actions{grid-column:1/-1;justify-content:flex-start}
.request-heading{padding:23px}.request-heading-icon{display:grid;place-items:center;width:46px;height:46px;background:color-mix(in srgb,var(--pt-accent) 12%,white);color:var(--pt-strong);border-radius:12px}.request-filter-row{display:grid;grid-template-columns:1fr 190px 190px;gap:12px;margin-top:20px}.request-content-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.8fr);gap:16px}.request-list{min-width:0}.request-empty{text-align:center;padding:34px;margin-top:16px}.request-empty-illustration{display:grid;place-items:center;height:140px;color:var(--pt-accent);background:radial-gradient(circle,color-mix(in srgb,var(--pt-accent) 14%,white),transparent 65%)}.request-empty h3{font-size:1.4rem;font-weight:800;color:#173152}.request-empty p{max-width:440px;margin:12px auto 22px;color:#64748b;line-height:1.6}.request-guide{padding:21px;margin-bottom:16px}.request-guide h3{display:flex;align-items:center;gap:8px;font-weight:800;color:#162b4e}.request-guide>p{margin:8px 0 13px;font-size:.82rem;line-height:1.5;color:#61718a}.request-guide-item{display:flex;gap:11px;padding:13px 0;border-top:1px solid #e6edf5}.request-guide-item>span{display:grid;place-items:center;width:36px;height:36px;flex-shrink:0;border-radius:10px;background:color-mix(in srgb,var(--pt-accent) 11%,white);color:var(--pt-strong)}.request-guide-item strong{display:block;font-size:.82rem;color:#203653}.request-guide-item small{font-size:.72rem;color:#77859c;display:block;margin-top:3px;line-height:1.45}
@media (max-width:1100px){.summary-bottom-grid,.request-content-grid{grid-template-columns:1fr}.directory-filters{grid-template-columns:1fr 1fr}.participants-premium .wizard-layout{grid-template-columns:1fr}.directory-grid{grid-template-columns:1fr}.request-filter-row{grid-template-columns:1fr 1fr}.request-filter-row input{grid-column:1/-1}}
@media (max-width:700px){.summary-empty{grid-template-columns:1fr;text-align:center;padding:20px}.summary-illustration{height:120px}.summary-buttons{justify-content:center}.directory-filters,.request-filter-row{grid-template-columns:1fr}.request-filter-row input{grid-column:auto}.wizard-step{min-width:100%;}.participants-premium .premium-form-section{padding:16px}.participants-premium .premium-entity-card>.grid{grid-template-columns:1fr}.team-more-menu{right:auto;left:0}.participants-premium .participants-hero{min-height:160px}}

</style>
