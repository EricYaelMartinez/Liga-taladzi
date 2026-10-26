<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Team = { id: number; name: string; short_name: string; crest_path: string | null };
type Participation = { id: number; registered_name: string; team: Team };
type Competition = { id: number; name: string; format: string; status: string; division: { id: number; name: string }; category: { id: number; name: string }; tournament: { status: string; season: { id: number; name: string; status: string } }; team_participations: Participation[] };
type Field = { id: number; name: string; max_matches_per_day: number | null; venue: { id: number; name: string } };
type Referee = { id: number; category_level: string; user: { id: number; name: string } };
type Assignment = { id: number; role: string; referee_id: number; referee: Referee };
type TimeSlot = { id: number; playing_field_id: number | null; weekday: number; starts_at: string; field: { id: number; name: string } | null };
type Match = {
    id: number; status: string; scheduled_at: string | null; scheduled_end_at: string | null; duration_minutes: number;
    public_notes: string | null; playing_field_id: number | null; home_team_participation_id: number; away_team_participation_id: number;
    home_participation: Participation; away_participation: Participation; field: Field | null; referee_assignments: Assignment[];
};
type Matchday = { id: number; competition_id: number; number: number; name: string; phase: string; starts_on: string | null; ends_on: string | null; status: string; leg_number: number; matches: Match[]; competition: Competition };

const props = defineProps<{
    competitions: Competition[]; matchdays: Matchday[]; fields: Field[]; referees: Referee[]; timeSlots: TimeSlot[];
    defaultMaxMatchesPerFieldDay: number; canManage: boolean;
}>();

const competitionFilter = ref<number | 'all'>('all');
const showTerminalMatchdays = ref(false);
const generateOpen = ref(false);
const configurationOpen = ref(false);
const matchdayOpen = ref(false);
const matchMatchday = ref<Matchday | null>(null);
const scheduleMatch = ref<Match | null>(null);
const refereeMatch = ref<Match | null>(null);
const refereePhase = ref('regular');
const reopenMatch = ref<Match | null>(null);
const reopenPhase = ref('regular');
const expandedMatchdays = ref(new Set(props.matchdays.filter((item) => !['completed', 'cancelled'].includes(item.status)).slice(0, 2).map((item) => item.id)));

const generateForm = useForm({ competition_id: 0, first_match_date: '', days_between_matchdays: 7, scope: 'all', auto_schedule: true, reason: '' });
const matchdayForm = useForm({ competition_id: 0, number: 1, name: 'Jornada 1', phase: 'regular', leg_number: 1, starts_on: '', ends_on: '', reason: '' });
const matchForm = useForm({ home_team_participation_id: 0, away_team_participation_id: 0, public_notes: '', reason: '' });
const scheduleForm = useForm({ scheduled_at: '', playing_field_id: 0, public_notes: '', reason: '' });
const refereeForm = useForm({ central_referee_id: 0, assistant_1_referee_id: null as number | null, assistant_2_referee_id: null as number | null, fourth_referee_id: null as number | null, reason: '' });
const reopenForm = useForm({ scheduled_at: '', playing_field_id: 0, central_referee_id: 0, assistant_1_referee_id: null as number | null, assistant_2_referee_id: null as number | null, fourth_referee_id: null as number | null, public_notes: '', reason: '', status: '' });
const slotForm = useForm({ playing_field_id: null as number | null, weekday: 1, starts_at: '08:00', reason: '' });
const capacityForm = useForm({
    default_max_matches_per_field_day: props.defaultMaxMatchesPerFieldDay,
    fields: props.fields.map((field) => ({ id: field.id, max_matches_per_day: field.max_matches_per_day })),
    reason: '',
});

const terminalStatuses = ['finished', 'archived', 'cancelled'];
const manageableCompetitions = computed(() => props.competitions.filter((competition) => !terminalStatuses.includes(competition.status) && !terminalStatuses.includes(competition.tournament.status) && !terminalStatuses.includes(competition.tournament.season.status)));
const visibleMatchdays = computed(() => props.matchdays.filter((item) => {
    if (competitionFilter.value !== 'all' && item.competition_id !== competitionFilter.value) return false;
    if (showTerminalMatchdays.value) return true;
    const competition = competitionById(item.competition_id);
    return !['completed', 'cancelled'].includes(item.status)
        && !!competition
        && !terminalStatuses.includes(competition.status)
        && !terminalStatuses.includes(competition.tournament.status)
        && !terminalStatuses.includes(competition.tournament.season.status);
}));
const totalMatches = computed(() => visibleMatchdays.value.reduce((total, item) => total + item.matches.length, 0));
const scheduledMatches = computed(() => visibleMatchdays.value.flatMap((item) => item.matches).filter((item) => item.scheduled_at && !['cancelled', 'postponed'].includes(item.status)).length);
const pendingReferees = computed(() => visibleMatchdays.value.flatMap((item) => item.matches).filter((item) => item.scheduled_at && !item.referee_assignments.some((assignment) => assignment.role === 'central') && item.status !== 'cancelled').length);
const cancelledMatches = computed(() => visibleMatchdays.value.flatMap((item) => item.matches).filter((item) => item.status === 'cancelled').length);
const formats: Record<string, string> = { round_robin: 'Todos contra todos', double_round_robin: 'Ida y vuelta', knockout: 'Eliminación directa', groups_knockout: 'Grupos y eliminatoria', manual: 'Manual' };
const phases: Record<string, string> = { regular: 'Fase regular', group: 'Fase de grupos', knockout: 'Eliminatoria', custom: 'Personalizada' };
const statuses: Record<string, string> = { draft: 'Borrador', scheduled: 'Programado', in_progress: 'En curso', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Aplazado', cancelled: 'Cancelado', published: 'Publicada', completed: 'Completada' };
const weekdays: Record<number, string> = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };
const assignmentLabels: Record<string, string> = { central: 'Central', assistant_1: 'Asistente 1', assistant_2: 'Asistente 2', fourth: 'Cuarto árbitro' };

function competitionById(id: number): Competition | undefined { return props.competitions.find((item) => item.id === id); }
function competitionIsManageable(id: number): boolean { return manageableCompetitions.value.some((item) => item.id === id); }
function assignment(match: Match, role: string): number | null { return match.referee_assignments.find((item) => item.role === role)?.referee_id ?? null; }
function crest(team: Team): string | null { return team.crest_path ? `/storage/${team.crest_path}` : null; }
function initials(team: Team): string { return (team.short_name || team.name).split(/\s+/).map((word) => word[0]).join('').slice(0, 3).toUpperCase(); }
function toggleMatchday(id: number): void {
    const next = new Set(expandedMatchdays.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expandedMatchdays.value = next;
}
function matchStatusClass(status: string): string {
    return ({
        draft: 'status-neutral', scheduled: 'status-scheduled', in_progress: 'status-live', finished: 'status-finished',
        suspended: 'status-warning', postponed: 'status-warning', cancelled: 'status-cancelled',
    } as Record<string, string>)[status] ?? 'status-neutral';
}
function matchdayStatusClass(status: string): string {
    return ({ draft: 'bg-amber-50 text-amber-700 ring-amber-200', published: 'bg-emerald-50 text-emerald-700 ring-emerald-200', completed: 'bg-slate-100 text-slate-600 ring-slate-200', cancelled: 'bg-rose-50 text-rose-700 ring-rose-200' } as Record<string, string>)[status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
}
function matchdayProgress(matchday: Matchday): number {
    if (!matchday.matches.length) return 0;
    const ready = matchday.matches.filter((match) => match.scheduled_at && match.field && match.referee_assignments.some((item) => item.role === 'central')).length;
    return Math.round((ready / matchday.matches.length) * 100);
}
function matchDateParts(value: string | null): { day: string; month: string; time: string } {
    if (!value) return { day: '--', month: 'PEND.', time: 'Sin hora' };
    const date = new Date(value);
    return {
        day: new Intl.DateTimeFormat('es-MX', { day: '2-digit' }).format(date),
        month: new Intl.DateTimeFormat('es-MX', { month: 'short' }).format(date).replace('.', '').toUpperCase(),
        time: new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(date),
    };
}
function localDateTime(value: string): string { return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
function localDate(value: string): string {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(new Date(year, month - 1, day));
}
function inputDateTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value); const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
function openGenerate() {
    generateOpen.value = true; generateForm.reset(); generateForm.clearErrors(); generateForm.days_between_matchdays = 7; generateForm.scope = 'all'; generateForm.auto_schedule = true;
    generateForm.competition_id = manageableCompetitions.value.find((item) => ['round_robin', 'double_round_robin'].includes(item.format))?.id ?? 0;
}
function submitGenerate() {
    if (!generateForm.competition_id) return;
    generateForm.post(`/liga/competencias/${generateForm.competition_id}/generar-calendario`, { preserveScroll: true, onSuccess: () => { generateOpen.value = false; generateForm.reset(); } });
}
function openMatchday() {
    matchdayOpen.value = true; matchdayForm.reset(); matchdayForm.clearErrors(); matchdayForm.phase = 'regular'; matchdayForm.leg_number = 1;
    const filtered = competitionFilter.value === 'all' ? undefined : manageableCompetitions.value.find((item) => item.id === competitionFilter.value);
    matchdayForm.competition_id = filtered?.id ?? manageableCompetitions.value[0]?.id ?? 0;
    const current = props.matchdays.filter((item) => item.competition_id === matchdayForm.competition_id);
    matchdayForm.number = current.length ? Math.max(...current.map((item) => item.number)) + 1 : 1;
    matchdayForm.name = `Jornada ${matchdayForm.number}`;
}
function submitMatchday() { matchdayForm.post('/liga/jornadas', { preserveScroll: true, onSuccess: () => { matchdayOpen.value = false; matchdayForm.reset(); } }); }
function openMatch(matchday: Matchday) {
    matchMatchday.value = matchday; matchForm.reset(); matchForm.clearErrors();
    const teams = competitionById(matchday.competition_id)?.team_participations ?? [];
    matchForm.home_team_participation_id = teams[0]?.id ?? 0; matchForm.away_team_participation_id = teams[1]?.id ?? 0;
}
function submitMatch() { if (matchMatchday.value) matchForm.post(`/liga/jornadas/${matchMatchday.value.id}/partidos`, { preserveScroll: true, onSuccess: () => { matchMatchday.value = null; matchForm.reset(); } }); }
function openSchedule(match: Match) {
    scheduleMatch.value = match; scheduleForm.reset(); scheduleForm.clearErrors();
    Object.assign(scheduleForm, { scheduled_at: inputDateTime(match.scheduled_at), playing_field_id: match.playing_field_id ?? props.fields[0]?.id ?? 0, public_notes: match.public_notes ?? '', reason: '' });
}
function submitSchedule() { if (scheduleMatch.value) scheduleForm.put(`/liga/partidos/${scheduleMatch.value.id}/programacion`, { preserveScroll: true, onSuccess: () => { scheduleMatch.value = null; scheduleForm.reset(); } }); }
function openReferees(matchday: Matchday, match: Match) {
    refereeMatch.value = match; refereePhase.value = matchday.phase; refereeForm.reset(); refereeForm.clearErrors();
    Object.assign(refereeForm, { central_referee_id: assignment(match, 'central') ?? props.referees[0]?.id ?? 0, assistant_1_referee_id: assignment(match, 'assistant_1'), assistant_2_referee_id: assignment(match, 'assistant_2'), fourth_referee_id: assignment(match, 'fourth'), reason: '' });
}
function submitReferees() { if (refereeMatch.value) refereeForm.put(`/liga/partidos/${refereeMatch.value.id}/arbitros`, { preserveScroll: true, onSuccess: () => { refereeMatch.value = null; refereeForm.reset(); } }); }
function openReopen(matchday: Matchday, match: Match) {
    reopenMatch.value = match; reopenPhase.value = matchday.phase; reopenForm.reset(); reopenForm.clearErrors();
    Object.assign(reopenForm, {
        scheduled_at: '',
        playing_field_id: match.playing_field_id ?? props.fields[0]?.id ?? 0,
        central_referee_id: assignment(match, 'central') ?? props.referees[0]?.id ?? 0,
        assistant_1_referee_id: assignment(match, 'assistant_1'),
        assistant_2_referee_id: assignment(match, 'assistant_2'),
        fourth_referee_id: assignment(match, 'fourth'),
        public_notes: match.public_notes ?? '',
        reason: '',
        status: '',
    });
}
function submitReopen() {
    if (reopenMatch.value) reopenForm.put(`/liga/partidos/${reopenMatch.value.id}/reprogramar-cancelado`, { preserveScroll: true, onSuccess: () => { reopenMatch.value = null; reopenForm.reset(); } });
}
function submitSlot() { slotForm.post('/liga/horarios-estandar', { preserveScroll: true, onSuccess: () => slotForm.reset('starts_at', 'reason') }); }
function removeSlot(slot: TimeSlot) { const reason = prompt('Motivo para eliminar este horario estándar:'); if (reason?.trim()) router.delete(`/liga/horarios-estandar/${slot.id}`, { data: { reason }, preserveScroll: true }); }
function submitCapacity() { capacityForm.put('/liga/capacidad-canchas', { preserveScroll: true, onSuccess: () => { capacityForm.reason = ''; configurationOpen.value = false; } }); }
function publish(matchday: Matchday) { const reason = prompt('Motivo para publicar esta jornada:'); if (reason?.trim()) router.post(`/liga/jornadas/${matchday.id}/publicar`, { reason }, { preserveScroll: true }); }
function transition(match: Match, status: string) { const reason = prompt(`Motivo para cambiar el partido a "${statuses[status]}":`); if (reason?.trim()) router.put(`/liga/partidos/${match.id}/estado`, { status, reason }, { preserveScroll: true }); }
</script>

<template>
    <Head title="Jornadas y programación" />
    <AppLayout>
        <section class="schedule-page">
            <div class="schedule-hero overflow-hidden rounded-[1.75rem] text-white shadow-2xl shadow-slate-950/20">
                <div class="relative z-10 px-6 py-7 sm:px-8 lg:px-10 lg:py-9">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Operación deportiva · Módulo 10.1</p>
                            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Jornadas y programación</h1>
                            <p class="mt-2 text-sm text-white/75 sm:text-base">Organiza el calendario, confirma sedes y horarios, y coordina al cuerpo arbitral desde un solo tablero.</p>
                        </div>
                        <div v-if="canManage" class="flex flex-wrap gap-2">
                            <button class="hero-button hero-button-secondary" @click="configurationOpen = true"><span aria-hidden="true">⚙</span> Horarios y capacidad</button>
                            <button class="hero-button hero-button-secondary" @click="openMatchday"><span aria-hidden="true">＋</span> Jornada manual</button>
                            <button class="hero-button hero-button-primary" @click="openGenerate"><span aria-hidden="true">✦</span> Generar jornadas</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative z-10 mx-3 -mt-4 grid gap-3 sm:mx-6 sm:grid-cols-2 xl:grid-cols-4">
                <article class="schedule-stat"><span class="schedule-stat-icon bg-league-50 text-league-700">▦</span><div><strong>{{ visibleMatchdays.length }}</strong><p>Jornadas visibles</p></div></article>
                <article class="schedule-stat"><span class="schedule-stat-icon bg-sky-50 text-sky-700">⚽</span><div><strong>{{ totalMatches }}</strong><p>Partidos registrados</p></div></article>
                <article class="schedule-stat"><span class="schedule-stat-icon bg-emerald-50 text-emerald-700">✓</span><div><strong>{{ scheduledMatches }}</strong><p>Con horario definido</p></div></article>
                <article class="schedule-stat"><span class="schedule-stat-icon bg-amber-50 text-amber-700">◇</span><div><strong>{{ pendingReferees }}</strong><p>Árbitros pendientes</p></div></article>
            </div>

            <div class="mt-7 rounded-2xl border border-white/80 bg-white/95 p-4 shadow-md shadow-slate-900/5 backdrop-blur sm:p-5">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                    <div>
                        <label class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500"><span class="text-league-700">⌕</span> Competencia</label>
                        <select v-model="competitionFilter" class="form-input">
                            <option value="all">Todas las competencias</option>
                            <option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.name }} · {{ competition.division.name }} · {{ statuses[competition.status] ?? competition.status }}</option>
                        </select>
                    </div>
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-league-600 hover:bg-league-50">
                        <input v-model="showTerminalMatchdays" type="checkbox" class="h-4 w-4 accent-league-700">
                        <span>Mostrar jornadas finalizadas y canceladas</span>
                        <span v-if="cancelledMatches" class="ml-auto rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700">{{ cancelledMatches }}</span>
                    </label>
                </div>
            </div>

            <div v-if="!visibleMatchdays.length" class="mt-7 rounded-2xl border border-dashed border-slate-300 bg-white/80 px-6 py-16 text-center shadow-sm">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-league-50 text-2xl text-league-700">▦</div>
                <h2 class="mt-4 text-lg font-bold text-slate-900">No hay jornadas para mostrar</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ canManage ? 'Genera el calendario o crea una jornada manual para comenzar.' : 'Todavía no existen jornadas publicadas para esta selección.' }}</p>
                <button v-if="canManage" class="btn-primary mt-5" @click="openGenerate">Generar jornadas</button>
            </div>

            <div v-else class="mt-7 space-y-5">
                <article v-for="matchday in visibleMatchdays" :key="matchday.id" class="matchday-card">
                    <header class="matchday-header">
                        <button class="flex min-w-0 flex-1 items-start gap-4 text-left" :aria-expanded="expandedMatchdays.has(matchday.id)" @click="toggleMatchday(matchday.id)">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-league-900 text-white shadow-md shadow-league-900/20"><span class="text-[10px] font-bold uppercase tracking-wide">JOR</span><strong class="-mt-1 text-lg">{{ matchday.number }}</strong></span>
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2"><strong class="text-xl text-slate-900">{{ matchday.name }}</strong><span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset" :class="matchdayStatusClass(matchday.status)">{{ statuses[matchday.status] }}</span><span class="rounded-full bg-league-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-league-700">{{ phases[matchday.phase] }}</span></span>
                                <span class="mt-1.5 block truncate text-sm text-slate-500">{{ matchday.competition.name }} · {{ competitionById(matchday.competition_id)?.division.name ?? 'División' }} · {{ matchday.starts_on ? localDate(matchday.starts_on) : 'Fecha general pendiente' }}</span>
                            </span>
                        </button>
                        <div class="flex items-center gap-3 pl-16 sm:pl-0">
                            <div class="hidden min-w-32 sm:block"><div class="flex justify-between text-[10px] font-bold uppercase tracking-wide text-slate-400"><span>Preparación</span><span>{{ matchdayProgress(matchday) }}%</span></div><div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-league-600 transition-all" :style="{ width: `${matchdayProgress(matchday)}%` }"></div></div></div>
                            <span class="text-xs font-semibold text-slate-500">{{ matchday.matches.length }} partidos</span>
                            <button class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-league-600 hover:text-league-700" :aria-label="expandedMatchdays.has(matchday.id) ? 'Contraer jornada' : 'Expandir jornada'" @click="toggleMatchday(matchday.id)"><span class="transition-transform" :class="expandedMatchdays.has(matchday.id) ? 'rotate-180' : ''">⌄</span></button>
                        </div>
                    </header>

                    <div v-if="expandedMatchdays.has(matchday.id)" class="border-t border-slate-100">
                        <div v-if="canManage && competitionIsManageable(matchday.competition_id) && matchday.status === 'draft'" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-amber-50/60 px-5 py-3 sm:px-6">
                            <p class="text-xs font-medium text-amber-900">Completa cancha, horario y árbitro central antes de publicar.</p>
                            <div class="flex gap-2"><button class="schedule-action" @click="openMatch(matchday)">＋ Agregar partido</button><button class="btn-primary min-h-9 px-3 py-2 text-xs" @click="publish(matchday)">Publicar jornada</button></div>
                        </div>
                        <div v-if="!matchday.matches.length" class="px-6 py-12 text-center"><p class="font-semibold text-slate-700">Jornada sin partidos</p><p class="mt-1 text-sm text-slate-500">Agrega al menos un encuentro para poder publicarla.</p></div>
                        <div v-else class="space-y-3 bg-slate-50/70 p-3 sm:p-5">
                            <section v-for="match in matchday.matches" :key="match.id" class="match-card">
                                <div class="match-time" :class="match.status === 'cancelled' ? 'opacity-60' : ''">
                                    <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ matchDateParts(match.scheduled_at).month }}</span>
                                    <strong class="text-2xl leading-none text-league-900">{{ matchDateParts(match.scheduled_at).day }}</strong>
                                    <span class="mt-1 text-xs font-bold text-league-700">{{ matchDateParts(match.scheduled_at).time }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2"><span class="match-status" :class="matchStatusClass(match.status)"><span v-if="match.status === 'in_progress'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>{{ statuses[match.status] }}</span><span v-if="match.scheduled_at && !match.referee_assignments.some((item) => item.role === 'central')" class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200">Árbitro pendiente</span></div>
                                        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Partido #{{ match.id }}</span>
                                    </div>
                                    <div class="mt-4 grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                                        <div class="team-block sm:justify-end sm:text-right"><span class="team-crest"><img v-if="crest(match.home_participation.team)" :src="crest(match.home_participation.team)!" :alt="`Escudo de ${match.home_participation.team.name}`"><span v-else>{{ initials(match.home_participation.team) }}</span></span><strong>{{ match.home_participation.registered_name }}</strong></div>
                                        <span class="mx-auto rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-slate-400">VS</span>
                                        <div class="team-block sm:flex-row-reverse sm:justify-end"><span class="team-crest"><img v-if="crest(match.away_participation.team)" :src="crest(match.away_participation.team)!" :alt="`Escudo de ${match.away_participation.team.name}`"><span v-else>{{ initials(match.away_participation.team) }}</span></span><strong>{{ match.away_participation.registered_name }}</strong></div>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600"><span class="meta-chip"><span aria-hidden="true">⌖</span>{{ match.field ? `${match.field.venue.name} · ${match.field.name}` : 'Cancha pendiente' }}</span><span class="meta-chip"><span aria-hidden="true">◷</span>{{ match.duration_minutes }} min</span><span v-if="assignment(match, 'central')" class="meta-chip"><span aria-hidden="true">◇</span>{{ match.referee_assignments.find((item) => item.role === 'central')?.referee.user.name }}</span></div>
                                    <div v-if="match.referee_assignments.length > 1" class="mt-2 flex flex-wrap gap-1.5"><span v-for="item in match.referee_assignments.filter((item) => item.role !== 'central')" :key="item.id" class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] text-slate-500"><strong>{{ assignmentLabels[item.role] }}:</strong> {{ item.referee.user.name }}</span></div>
                                    <p v-if="match.public_notes" class="mt-3 rounded-lg border-l-2 border-gold-500 bg-gold-400/10 px-3 py-2 text-xs text-slate-600">{{ match.public_notes }}</p>
                                </div>
                                <div v-if="canManage && competitionIsManageable(matchday.competition_id)" class="match-actions">
                                    <button v-if="['draft', 'scheduled', 'postponed', 'suspended'].includes(match.status)" class="schedule-action" @click="openSchedule(match)">{{ match.scheduled_at ? 'Cambiar horario/campo' : 'Programar' }}</button>
                                    <button v-if="match.scheduled_at && ['draft', 'scheduled', 'postponed', 'suspended'].includes(match.status)" class="schedule-action" @click="openReferees(matchday, match)">{{ assignment(match, 'central') ? 'Árbitros' : 'Asignar árbitro' }}</button>
                                    <button v-if="match.status === 'cancelled'" class="schedule-action border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-400" @click="openReopen(matchday, match)">Reabrir</button>
                                    <button v-if="match.status === 'scheduled'" class="schedule-action" @click="transition(match, 'in_progress')">Iniciar</button>
                                    <button v-if="match.status === 'scheduled'" class="schedule-action" @click="transition(match, 'postponed')">Aplazar</button>
                                    <button v-if="match.status === 'in_progress'" class="schedule-action" @click="transition(match, 'suspended')">Suspender</button>
                                    <button v-if="['scheduled', 'in_progress', 'suspended', 'postponed'].includes(match.status)" class="schedule-action border-rose-200 text-rose-700 hover:border-rose-400 hover:bg-rose-50" @click="transition(match, 'cancelled')">Cancelar</button>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="generateOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="generateOpen = false"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitGenerate"><h2 class="text-xl font-semibold">Generar jornadas</h2><p class="mt-1 text-sm text-slate-500">Genera la siguiente jornada pendiente o todas las restantes sin repetir enfrentamientos.</p><div class="mt-5 space-y-4"><div><label class="form-label">Competencia</label><select v-model="generateForm.competition_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="item in manageableCompetitions.filter((competition) => ['round_robin', 'double_round_robin'].includes(competition.format))" :key="item.id" :value="item.id">{{ item.name }} · {{ formats[item.format] }} · {{ item.team_participations.length }} equipos</option></select><FormError :message="generateForm.errors.competition_id" /></div><div><label class="form-label">Alcance</label><div class="mt-2 grid gap-2 sm:grid-cols-2"><label class="rounded-xl border p-3"><input v-model="generateForm.scope" type="radio" value="next"> <span class="ml-2 font-medium">Siguiente jornada</span></label><label class="rounded-xl border p-3"><input v-model="generateForm.scope" type="radio" value="all"> <span class="ml-2 font-medium">Todas las restantes</span></label></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Fecha de inicio</label><input v-model="generateForm.first_match_date" type="date" class="form-input" required></div><div><label class="form-label">Días entre jornadas</label><input v-model="generateForm.days_between_matchdays" type="number" min="1" max="30" class="form-input" required></div></div><label class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4"><input v-model="generateForm.auto_schedule" type="checkbox" class="mt-1"><span><strong>Asignar horarios y campos automáticamente</strong><small class="mt-1 block text-blue-800">Utiliza horarios estándar, disponibilidad y capacidad diaria.</small></span></label><FormError :message="generateForm.errors.auto_schedule" /><div><label class="form-label">Motivo</label><textarea v-model="generateForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="generateOpen = false">Cancelar</button><button class="btn-primary" :disabled="generateForm.processing">Generar</button></div></form></div>

        <div v-if="configurationOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="configurationOpen = false"><div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6"><div class="flex items-start justify-between"><div><h2 class="text-xl font-semibold">Horarios estándar y capacidad</h2><p class="mt-1 text-sm text-slate-500">Los horarios específicos de una cancha sustituyen a los generales para ese día.</p></div><button class="text-2xl text-slate-400" @click="configurationOpen = false">×</button></div><div class="mt-6 grid gap-6 lg:grid-cols-2"><section><h3 class="font-semibold">Agregar horario</h3><form class="mt-3 space-y-3" @submit.prevent="submitSlot"><div><label class="form-label">Aplicar a</label><select v-model="slotForm.playing_field_id" class="form-input"><option :value="null">Todas las canchas (general)</option><option v-for="field in fields" :key="field.id" :value="field.id">{{ field.venue.name }} · {{ field.name }}</option></select></div><div class="grid grid-cols-2 gap-3"><div><label class="form-label">Día</label><select v-model="slotForm.weekday" class="form-input"><option v-for="(label, day) in weekdays" :key="day" :value="Number(day)">{{ label }}</option></select></div><div><label class="form-label">Hora</label><input v-model="slotForm.starts_at" type="time" class="form-input" required><FormError :message="slotForm.errors.starts_at" /></div></div><div><label class="form-label">Motivo</label><input v-model="slotForm.reason" class="form-input" required maxlength="500"></div><button class="btn-primary" :disabled="slotForm.processing">Agregar horario</button></form><div class="mt-5 space-y-2"><div v-for="slot in timeSlots" :key="slot.id" class="flex items-center justify-between rounded-lg border p-3 text-sm"><span><strong>{{ weekdays[slot.weekday] }} {{ slot.starts_at.slice(0, 5) }}</strong><small class="block text-slate-500">{{ slot.field?.name ?? 'Horario general' }}</small></span><button class="text-red-700" @click="removeSlot(slot)">Eliminar</button></div><p v-if="!timeSlots.length" class="text-sm text-slate-500">Aún no hay horarios configurados.</p></div></section><section><h3 class="font-semibold">Máximo de partidos por día</h3><form class="mt-3 space-y-3" @submit.prevent="submitCapacity"><div><label class="form-label">Máximo general por cancha</label><input v-model="capacityForm.default_max_matches_per_field_day" type="number" min="1" max="50" class="form-input" required></div><div v-for="(field, index) in fields" :key="field.id"><label class="form-label">{{ field.venue.name }} · {{ field.name }}</label><input v-model="capacityForm.fields[index].max_matches_per_day" type="number" min="1" max="50" class="form-input" placeholder="Usar máximo general"></div><div><label class="form-label">Motivo</label><textarea v-model="capacityForm.reason" class="form-input" required maxlength="500"></textarea></div><button class="btn-primary" :disabled="capacityForm.processing">Guardar capacidad</button></form></section></div></div></div>

        <div v-if="matchdayOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="matchdayOpen = false"><form class="w-full max-w-2xl rounded-2xl bg-white p-6" @submit.prevent="submitMatchday"><h2 class="text-xl font-semibold">Nueva jornada manual</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label class="form-label">Competencia</label><select v-model="matchdayForm.competition_id" class="form-input" required><option v-for="item in manageableCompetitions" :key="item.id" :value="item.id">{{ item.name }} · {{ item.division.name }}</option></select></div><div><label class="form-label">Número</label><input v-model="matchdayForm.number" type="number" min="1" max="999" class="form-input" required><FormError :message="matchdayForm.errors.number" /></div><div><label class="form-label">Nombre</label><input v-model="matchdayForm.name" class="form-input" required maxlength="120"></div><div><label class="form-label">Fase</label><select v-model="matchdayForm.phase" class="form-input"><option v-for="(label, value) in phases" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Vuelta</label><select v-model="matchdayForm.leg_number" class="form-input"><option :value="1">Primera vuelta</option><option :value="2">Segunda vuelta</option></select></div><div><label class="form-label">Fecha inicial</label><input v-model="matchdayForm.starts_on" type="date" class="form-input"></div><div><label class="form-label">Fecha final</label><input v-model="matchdayForm.ends_on" type="date" class="form-input"><FormError :message="matchdayForm.errors.ends_on" /></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="matchdayForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="matchdayOpen = false">Cancelar</button><button class="btn-primary" :disabled="matchdayForm.processing">Guardar</button></div></form></div>

        <div v-if="matchMatchday" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="matchMatchday = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitMatch"><h2 class="text-xl font-semibold">Agregar partido</h2><p class="mt-1 text-sm text-slate-500">{{ matchMatchday.name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Local</label><select v-model="matchForm.home_team_participation_id" class="form-input" required><option v-for="item in competitionById(matchMatchday.competition_id)?.team_participations" :key="item.id" :value="item.id">{{ item.registered_name }}</option></select><FormError :message="matchForm.errors.home_team_participation_id" /></div><div><label class="form-label">Visitante</label><select v-model="matchForm.away_team_participation_id" class="form-input" required><option v-for="item in competitionById(matchMatchday.competition_id)?.team_participations" :key="item.id" :value="item.id">{{ item.registered_name }}</option></select><FormError :message="matchForm.errors.away_team_participation_id" /></div><div><label class="form-label">Nota pública</label><textarea v-model="matchForm.public_notes" class="form-input" maxlength="1000"></textarea></div><div><label class="form-label">Motivo</label><textarea v-model="matchForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="matchMatchday = null">Cancelar</button><button class="btn-primary" :disabled="matchForm.processing">Agregar</button></div></form></div>

        <div v-if="scheduleMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="scheduleMatch = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitSchedule"><h2 class="text-xl font-semibold">{{ scheduleMatch.scheduled_at ? 'Cambiar horario o campo' : 'Asignar horario y campo' }}</h2><p class="mt-1 text-sm text-slate-500">{{ scheduleMatch.home_participation.registered_name }} vs {{ scheduleMatch.away_participation.registered_name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Fecha y hora</label><input v-model="scheduleForm.scheduled_at" type="datetime-local" class="form-input" required><FormError :message="scheduleForm.errors.scheduled_at" /></div><div><label class="form-label">Cancha</label><select v-model="scheduleForm.playing_field_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="field in fields" :key="field.id" :value="field.id">{{ field.venue.name }} · {{ field.name }}</option></select><FormError :message="scheduleForm.errors.playing_field_id" /></div><div><label class="form-label">Nota pública</label><textarea v-model="scheduleForm.public_notes" class="form-input" maxlength="1000"></textarea></div><div><label class="form-label">Motivo del cambio</label><textarea v-model="scheduleForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="scheduleMatch = null">Cancelar</button><button class="btn-primary" :disabled="scheduleForm.processing">Verificar y guardar</button></div></form></div>

        <div v-if="refereeMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="refereeMatch = null"><form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitReferees"><h2 class="text-xl font-semibold">Asignar cuerpo arbitral</h2><p class="mt-1 text-sm text-slate-500">{{ refereeMatch.home_participation.registered_name }} vs {{ refereeMatch.away_participation.registered_name }} · {{ refereeMatch.scheduled_at ? localDateTime(refereeMatch.scheduled_at) : '' }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label class="form-label">Árbitro central</label><select v-model="refereeForm.central_referee_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }} · {{ referee.category_level }}</option></select><FormError :message="refereeForm.errors.central_referee_id" /></div><template v-if="refereePhase === 'knockout'"><div><label class="form-label">Asistente 1</label><select v-model="refereeForm.assistant_1_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div><div><label class="form-label">Asistente 2</label><select v-model="refereeForm.assistant_2_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div><div><label class="form-label">Cuarto árbitro</label><select v-model="refereeForm.fourth_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div></template><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="refereeForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="refereeMatch = null">Cancelar</button><button class="btn-primary" :disabled="refereeForm.processing">Asignar árbitros</button></div></form></div>

        <div v-if="reopenMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="reopenMatch = null"><form class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitReopen"><h2 class="text-xl font-semibold">Reabrir y reprogramar partido</h2><p class="mt-1 text-sm text-slate-500">{{ reopenMatch.home_participation.registered_name }} vs {{ reopenMatch.away_participation.registered_name }}</p><div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Se conservarán el identificador y todo el historial del partido. Solo se admiten cancelaciones realizadas antes de iniciar el encuentro.</div><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nueva fecha y hora</label><input v-model="reopenForm.scheduled_at" type="datetime-local" class="form-input" required><FormError :message="reopenForm.errors.scheduled_at" /><FormError :message="reopenForm.errors.status" /></div><div><label class="form-label">Cancha</label><select v-model="reopenForm.playing_field_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="field in fields" :key="field.id" :value="field.id">{{ field.venue.name }} · {{ field.name }}</option></select><FormError :message="reopenForm.errors.playing_field_id" /></div><div class="sm:col-span-2"><label class="form-label">Árbitro central</label><select v-model="reopenForm.central_referee_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }} · {{ referee.category_level }}</option></select><FormError :message="reopenForm.errors.central_referee_id" /></div><template v-if="reopenPhase === 'knockout'"><div><label class="form-label">Asistente 1</label><select v-model="reopenForm.assistant_1_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div><div><label class="form-label">Asistente 2</label><select v-model="reopenForm.assistant_2_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div><div><label class="form-label">Cuarto árbitro</label><select v-model="reopenForm.fourth_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select></div></template><div class="sm:col-span-2"><label class="form-label">Nota pública</label><textarea v-model="reopenForm.public_notes" class="form-input" maxlength="1000"></textarea></div><div class="sm:col-span-2"><label class="form-label">Motivo de la reapertura</label><textarea v-model="reopenForm.reason" class="form-input" required maxlength="500"></textarea><FormError :message="reopenForm.errors.reason" /></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="reopenMatch = null">Cancelar</button><button class="btn-primary" :disabled="reopenForm.processing">Verificar y reprogramar</button></div></form></div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.schedule-hero {
    position: relative;
    background-image: linear-gradient(105deg, color-mix(in srgb, var(--color-league-900) 96%, #07152b), color-mix(in srgb, var(--color-league-600) 72%, transparent)), url('/images/public/league-stadium.jpg');
    background-position: center 58%;
    background-size: cover;
}
.schedule-hero::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 85% 20%, rgba(197, 160, 89, .22), transparent 28%), linear-gradient(90deg, rgba(4, 15, 30, .22), transparent 70%); }
.hero-button { @apply inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition focus:outline-none focus:ring-3 focus:ring-white/25; }
.hero-button-primary { @apply bg-gold-500 text-league-900 shadow-lg shadow-slate-950/20 hover:bg-gold-400; }
.hero-button-secondary { @apply border border-white/25 bg-white/10 text-white backdrop-blur hover:bg-white/20; }
.schedule-stat { @apply flex min-h-24 items-center gap-4 rounded-2xl border border-white/80 bg-white/95 p-4 shadow-lg shadow-slate-900/8 backdrop-blur; }
.schedule-stat-icon { @apply grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg font-black; }
.schedule-stat strong { @apply block text-2xl font-bold text-slate-900; }
.schedule-stat p { @apply mt-0.5 text-xs font-semibold text-slate-500; }
.matchday-card { @apply overflow-hidden rounded-2xl border border-white/80 bg-white/95 shadow-md shadow-slate-900/5 backdrop-blur transition hover:shadow-lg; }
.matchday-header { @apply flex flex-col justify-between gap-4 px-5 py-5 sm:flex-row sm:items-center sm:px-6; }
.match-card { @apply grid gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md lg:grid-cols-[5.5rem_minmax(0,1fr)_auto] lg:items-center; }
.match-time { @apply flex min-h-24 flex-row items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-3 lg:flex-col lg:gap-0; }
.team-block { @apply flex min-w-0 items-center justify-center gap-3 text-center text-sm text-slate-900 sm:text-base; }
.team-block strong { @apply min-w-0 break-words; }
.team-crest { @apply grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-white text-[10px] font-black text-league-700 shadow-sm; }
.team-crest img { @apply h-full w-full object-contain p-1; }
.meta-chip { @apply inline-flex items-center gap-1.5 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5; }
.match-actions { @apply flex flex-wrap gap-2 border-t border-slate-100 pt-3 lg:max-w-48 lg:flex-col lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0; }
.schedule-action { @apply inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-league-600 hover:bg-league-50 hover:text-league-700; }
.match-status { @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset; }
.status-neutral { @apply bg-slate-100 text-slate-600 ring-slate-200; }
.status-scheduled { @apply bg-sky-50 text-sky-700 ring-sky-200; }
.status-live { @apply bg-emerald-50 text-emerald-700 ring-emerald-200; }
.status-finished { @apply bg-slate-100 text-slate-500 ring-slate-200; }
.status-warning { @apply bg-amber-50 text-amber-700 ring-amber-200; }
.status-cancelled { @apply bg-rose-50 text-rose-700 ring-rose-200; }
.schedule-page ~ div.fixed { backdrop-filter: blur(5px); }
.schedule-page ~ div.fixed > form,
.schedule-page ~ div.fixed > div { @apply border border-white/70 shadow-2xl shadow-slate-950/25; }

@media (max-width: 639px) {
    .match-time { @apply min-h-0; }
    .team-block { @apply flex-col gap-2; }
}
</style>
