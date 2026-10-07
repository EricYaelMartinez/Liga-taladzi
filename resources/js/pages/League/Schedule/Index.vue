<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Team = { id: number; name: string; short_name: string };
type Participation = { id: number; registered_name: string; team: Team };
type Competition = { id: number; name: string; format: string; division: { id: number; name: string }; category: { id: number; name: string }; tournament: { season: { id: number; name: string } }; team_participations: Participation[] };
type Field = { id: number; name: string; venue: { id: number; name: string } };
type Referee = { id: number; category_level: string; user: { id: number; name: string } };
type Assignment = { id: number; role: string; referee_id: number; referee: Referee };
type Match = {
    id: number; status: string; scheduled_at: string | null; scheduled_end_at: string | null; duration_minutes: number;
    public_notes: string | null; playing_field_id: number | null; home_team_participation_id: number; away_team_participation_id: number;
    home_participation: Participation; away_participation: Participation; field: Field | null; referee_assignments: Assignment[];
};
type Matchday = { id: number; competition_id: number; number: number; name: string; phase: string; starts_on: string | null; ends_on: string | null; status: string; matches: Match[]; competition: Competition };

const props = defineProps<{ competitions: Competition[]; matchdays: Matchday[]; fields: Field[]; referees: Referee[]; canManage: boolean }>();
const competitionFilter = ref<number | 'all'>('all');
const generateOpen = ref(false);
const matchdayOpen = ref(false);
const matchMatchday = ref<Matchday | null>(null);
const programMatch = ref<Match | null>(null);
const programPhase = ref('regular');

const generateForm = useForm({ competition_id: 0, competition: '', first_match_date: '', days_between_matchdays: 7, reason: '' });
const matchdayForm = useForm({ competition_id: 0, number: 1, name: 'Jornada 1', phase: 'regular', starts_on: '', ends_on: '', reason: '' });
const matchForm = useForm({ home_team_participation_id: 0, away_team_participation_id: 0, public_notes: '', reason: '' });
const programForm = useForm({ scheduled_at: '', playing_field_id: 0, central_referee_id: 0, assistant_1_referee_id: null as number | null, assistant_2_referee_id: null as number | null, fourth_referee_id: null as number | null, public_notes: '', reason: '' });

const visibleMatchdays = computed(() => competitionFilter.value === 'all' ? props.matchdays : props.matchdays.filter((item) => item.competition_id === competitionFilter.value));
const totalMatches = computed(() => visibleMatchdays.value.reduce((total, item) => total + item.matches.length, 0));
const scheduledMatches = computed(() => visibleMatchdays.value.flatMap((item) => item.matches).filter((item) => item.scheduled_at && !['cancelled', 'postponed'].includes(item.status)).length);
const formats: Record<string, string> = { round_robin: 'Todos contra todos', double_round_robin: 'Ida y vuelta', knockout: 'Eliminación directa', groups_knockout: 'Grupos y eliminatoria', manual: 'Manual' };
const phases: Record<string, string> = { regular: 'Fase regular', group: 'Fase de grupos', knockout: 'Eliminatoria', custom: 'Personalizada' };
const statuses: Record<string, string> = { draft: 'Borrador', scheduled: 'Programado', in_progress: 'En curso', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Aplazado', cancelled: 'Cancelado', published: 'Publicada', completed: 'Completada' };
const assignmentLabels: Record<string, string> = { central: 'Central', assistant_1: 'Asistente 1', assistant_2: 'Asistente 2', fourth: 'Cuarto árbitro' };

function competitionById(id: number): Competition | undefined { return props.competitions.find((item) => item.id === id); }
function assignment(match: Match, role: string): number | null { return match.referee_assignments.find((item) => item.role === role)?.referee_id ?? null; }
function localDateTime(value: string): string { return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
function inputDateTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value); const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
function openGenerate() {
    generateOpen.value = true; generateForm.reset(); generateForm.clearErrors(); generateForm.days_between_matchdays = 7;
    generateForm.competition_id = props.competitions.find((item) => ['round_robin', 'double_round_robin'].includes(item.format))?.id ?? 0;
}
function submitGenerate() {
    if (!generateForm.competition_id) return;
    generateForm.post(`/liga/competencias/${generateForm.competition_id}/generar-calendario`, { preserveScroll: true, onSuccess: () => { generateOpen.value = false; generateForm.reset(); } });
}
function openMatchday() {
    matchdayOpen.value = true; matchdayForm.reset(); matchdayForm.clearErrors(); matchdayForm.phase = 'regular';
    matchdayForm.competition_id = competitionFilter.value === 'all' ? (props.competitions[0]?.id ?? 0) : competitionFilter.value;
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
function openProgram(matchday: Matchday, match: Match) {
    programMatch.value = match; programPhase.value = matchday.phase; programForm.reset(); programForm.clearErrors();
    Object.assign(programForm, {
        scheduled_at: inputDateTime(match.scheduled_at), playing_field_id: match.playing_field_id ?? props.fields[0]?.id ?? 0,
        central_referee_id: assignment(match, 'central') ?? props.referees[0]?.id ?? 0,
        assistant_1_referee_id: assignment(match, 'assistant_1'), assistant_2_referee_id: assignment(match, 'assistant_2'),
        fourth_referee_id: assignment(match, 'fourth'), public_notes: match.public_notes ?? '', reason: '',
    });
}
function submitProgram() { if (programMatch.value) programForm.post(`/liga/partidos/${programMatch.value.id}/programar`, { preserveScroll: true, onSuccess: () => { programMatch.value = null; programForm.reset(); } }); }
function publish(matchday: Matchday) { const reason = prompt('Motivo para publicar esta jornada:'); if (reason?.trim()) router.post(`/liga/jornadas/${matchday.id}/publicar`, { reason }, { preserveScroll: true }); }
function transition(match: Match, status: string) { const reason = prompt(`Motivo para cambiar el partido a "${statuses[status]}":`); if (reason?.trim()) router.put(`/liga/partidos/${match.id}/estado`, { status, reason }, { preserveScroll: true }); }
</script>

<template>
    <Head title="Jornadas y programación" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 10</p><h1 class="mt-1 text-3xl font-semibold">Jornadas y programación</h1><p class="mt-2 text-slate-600">Genera encuentros, asigna cancha y cuerpo arbitral, y publica el calendario.</p></div>
                <div v-if="canManage" class="flex flex-wrap gap-2"><button class="btn-secondary" @click="openMatchday">Nueva jornada manual</button><button class="btn-primary" @click="openGenerate">Generar todos contra todos</button></div>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-3"><div class="stat-card"><span class="stat-label">Jornadas visibles</span><span class="stat-value">{{ visibleMatchdays.length }}</span></div><div class="stat-card"><span class="stat-label">Partidos</span><span class="stat-value">{{ totalMatches }}</span></div><div class="stat-card"><span class="stat-label">Con horario</span><span class="stat-value">{{ scheduledMatches }}</span></div></div>
            <div class="mt-5 flex flex-col gap-2 sm:max-w-xl"><label class="form-label">Filtrar por competencia</label><select v-model="competitionFilter" class="form-input"><option value="all">Todas las competencias</option><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.name }} · {{ competition.division.name }}</option></select></div>
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">El calendario automático crea emparejamientos y descansos; los horarios, campos y árbitros se asignan después. Las alineaciones y cédulas se incorporarán en los módulos siguientes.</div>

            <div v-if="!visibleMatchdays.length" class="card mt-7 p-12 text-center text-slate-600">No hay jornadas {{ canManage ? 'creadas para este filtro.' : 'publicadas para consultar.' }}</div>
            <div v-else class="mt-7 space-y-6">
                <article v-for="matchday in visibleMatchdays" :key="matchday.id" class="card overflow-hidden">
                    <header class="flex flex-col justify-between gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-start">
                        <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-2xl font-semibold">{{ matchday.name }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ statuses[matchday.status] }}</span><span class="rounded-full bg-league-100 px-3 py-1 text-xs font-semibold text-league-900">{{ phases[matchday.phase] }}</span></div><p class="mt-2 text-sm text-slate-600">{{ matchday.competition.name }} · {{ matchday.starts_on || 'Sin fecha general' }}<span v-if="matchday.ends_on && matchday.ends_on !== matchday.starts_on"> a {{ matchday.ends_on }}</span></p></div>
                        <div v-if="canManage && matchday.status === 'draft'" class="flex gap-2"><button class="btn-secondary" @click="openMatch(matchday)">Agregar partido</button><button class="btn-primary" @click="publish(matchday)">Publicar jornada</button></div>
                    </header>
                    <div v-if="!matchday.matches.length" class="p-8 text-center text-sm text-slate-500">Jornada sin partidos.</div>
                    <div v-else class="divide-y divide-slate-200">
                        <section v-for="match in matchday.matches" :key="match.id" class="p-5 sm:p-6">
                            <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="match.status === 'cancelled' ? 'bg-red-100 text-red-800' : match.status === 'postponed' || match.status === 'suspended' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">{{ statuses[match.status] }}</span><span v-if="match.scheduled_at" class="text-sm font-medium text-slate-600">{{ localDateTime(match.scheduled_at) }}</span><span v-else class="text-sm text-amber-700">Pendiente de programar</span></div>
                                    <div class="mt-3 grid items-center gap-2 text-center sm:grid-cols-[1fr_auto_1fr]"><strong class="text-lg sm:text-right">{{ match.home_participation.registered_name }}</strong><span class="rounded-lg bg-slate-100 px-3 py-1 text-sm font-bold">VS</span><strong class="text-lg sm:text-left">{{ match.away_participation.registered_name }}</strong></div>
                                    <div class="mt-3 text-sm text-slate-600"><p v-if="match.field">{{ match.field.venue.name }} · {{ match.field.name }} · {{ match.duration_minutes }} min</p><p v-else>Cancha pendiente · {{ match.duration_minutes }} min</p><div v-if="match.referee_assignments.length" class="mt-2 flex flex-wrap gap-2"><span v-for="item in match.referee_assignments" :key="item.id" class="rounded-full bg-slate-100 px-3 py-1 text-xs"><strong>{{ assignmentLabels[item.role] }}:</strong> {{ item.referee.user.name }}</span></div><p v-if="match.public_notes" class="mt-2 text-xs">{{ match.public_notes }}</p></div>
                                </div>
                                <div v-if="canManage" class="flex flex-wrap gap-2 xl:max-w-xs xl:justify-end"><button v-if="['draft', 'scheduled', 'postponed', 'suspended'].includes(match.status)" class="btn-secondary" @click="openProgram(matchday, match)">{{ match.scheduled_at ? 'Reprogramar' : 'Programar' }}</button><button v-if="match.status === 'scheduled'" class="btn-secondary" @click="transition(match, 'in_progress')">Iniciar</button><button v-if="match.status === 'scheduled'" class="btn-secondary" @click="transition(match, 'postponed')">Aplazar</button><button v-if="match.status === 'in_progress'" class="btn-secondary" @click="transition(match, 'suspended')">Suspender</button><button v-if="['scheduled', 'in_progress', 'suspended', 'postponed'].includes(match.status)" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700" @click="transition(match, 'cancelled')">Cancelar</button></div>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="generateOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="generateOpen = false"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitGenerate"><h2 class="text-xl font-semibold">Generar calendario</h2><p class="mt-1 text-sm text-slate-500">Crea jornadas y emparejamientos en borrador. Admite número impar de equipos.</p><div class="mt-5 space-y-4"><div><label class="form-label">Competencia</label><select v-model="generateForm.competition_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="item in competitions.filter((competition) => ['round_robin', 'double_round_robin'].includes(competition.format))" :key="item.id" :value="item.id">{{ item.name }} · {{ formats[item.format] }} · {{ item.team_participations.length }} equipos</option></select><FormError :message="generateForm.errors.competition_id || generateForm.errors.competition" /></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Fecha de la primera jornada</label><input v-model="generateForm.first_match_date" type="date" class="form-input" required></div><div><label class="form-label">Días entre jornadas</label><input v-model="generateForm.days_between_matchdays" type="number" min="1" max="30" class="form-input" required></div></div><div><label class="form-label">Motivo</label><textarea v-model="generateForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="generateOpen = false">Cancelar</button><button class="btn-primary" :disabled="generateForm.processing">Generar</button></div></form></div>

        <div v-if="matchdayOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="matchdayOpen = false"><form class="w-full max-w-2xl rounded-2xl bg-white p-6" @submit.prevent="submitMatchday"><h2 class="text-xl font-semibold">Nueva jornada manual</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label class="form-label">Competencia</label><select v-model="matchdayForm.competition_id" class="form-input" required><option v-for="item in competitions" :key="item.id" :value="item.id">{{ item.name }} · {{ item.division.name }}</option></select></div><div><label class="form-label">Número</label><input v-model="matchdayForm.number" type="number" min="1" max="999" class="form-input" required><FormError :message="matchdayForm.errors.number" /></div><div><label class="form-label">Nombre</label><input v-model="matchdayForm.name" class="form-input" required maxlength="120"></div><div><label class="form-label">Fase</label><select v-model="matchdayForm.phase" class="form-input"><option v-for="(label, value) in phases" :key="value" :value="value">{{ label }}</option></select></div><div></div><div><label class="form-label">Fecha inicial (opcional)</label><input v-model="matchdayForm.starts_on" type="date" class="form-input"></div><div><label class="form-label">Fecha final (opcional)</label><input v-model="matchdayForm.ends_on" type="date" class="form-input"><FormError :message="matchdayForm.errors.ends_on" /></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="matchdayForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="matchdayOpen = false">Cancelar</button><button class="btn-primary" :disabled="matchdayForm.processing">Guardar</button></div></form></div>

        <div v-if="matchMatchday" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="matchMatchday = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitMatch"><h2 class="text-xl font-semibold">Agregar partido</h2><p class="mt-1 text-sm text-slate-500">{{ matchMatchday.name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Local</label><select v-model="matchForm.home_team_participation_id" class="form-input" required><option v-for="item in competitionById(matchMatchday.competition_id)?.team_participations" :key="item.id" :value="item.id">{{ item.registered_name }}</option></select><FormError :message="matchForm.errors.home_team_participation_id" /></div><div><label class="form-label">Visitante</label><select v-model="matchForm.away_team_participation_id" class="form-input" required><option v-for="item in competitionById(matchMatchday.competition_id)?.team_participations" :key="item.id" :value="item.id">{{ item.registered_name }}</option></select><FormError :message="matchForm.errors.away_team_participation_id" /></div><div><label class="form-label">Nota pública (opcional)</label><textarea v-model="matchForm.public_notes" class="form-input" maxlength="1000"></textarea></div><div><label class="form-label">Motivo</label><textarea v-model="matchForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="matchMatchday = null">Cancelar</button><button class="btn-primary" :disabled="matchForm.processing">Agregar</button></div></form></div>

        <div v-if="programMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="programMatch = null"><form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitProgram"><h2 class="text-xl font-semibold">{{ programMatch.scheduled_at ? 'Reprogramar partido' : 'Programar partido' }}</h2><p class="mt-1 text-sm text-slate-500">{{ programMatch.home_participation.registered_name }} vs {{ programMatch.away_participation.registered_name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Fecha y hora</label><input v-model="programForm.scheduled_at" type="datetime-local" class="form-input" required><FormError :message="programForm.errors.scheduled_at" /></div><div><label class="form-label">Cancha</label><select v-model="programForm.playing_field_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="field in fields" :key="field.id" :value="field.id">{{ field.venue.name }} · {{ field.name }}</option></select><FormError :message="programForm.errors.playing_field_id" /></div><div><label class="form-label">Árbitro central</label><select v-model="programForm.central_referee_id" class="form-input" required><option :value="0" disabled>Selecciona</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }} · {{ referee.category_level }}</option></select><FormError :message="programForm.errors.central_referee_id" /></div><template v-if="programPhase === 'knockout'"><div><label class="form-label">Asistente 1 (opcional)</label><select v-model="programForm.assistant_1_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select><FormError :message="programForm.errors.assistant_1_referee_id" /></div><div><label class="form-label">Asistente 2 (opcional)</label><select v-model="programForm.assistant_2_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select><FormError :message="programForm.errors.assistant_2_referee_id" /></div><div><label class="form-label">Cuarto árbitro (opcional)</label><select v-model="programForm.fourth_referee_id" class="form-input"><option :value="null">Sin asignar</option><option v-for="referee in referees" :key="referee.id" :value="referee.id">{{ referee.user.name }}</option></select><FormError :message="programForm.errors.fourth_referee_id" /></div></template><div class="sm:col-span-2"><label class="form-label">Nota pública (opcional)</label><textarea v-model="programForm.public_notes" class="form-input" maxlength="1000"></textarea></div><div class="sm:col-span-2"><label class="form-label">Motivo de programación o cambio</label><textarea v-model="programForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="programMatch = null">Cancelar</button><button class="btn-primary" :disabled="programForm.processing">Verificar y guardar</button></div></form></div>
    </AppLayout>
</template>
