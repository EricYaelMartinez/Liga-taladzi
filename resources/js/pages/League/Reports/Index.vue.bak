<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Player = { id: number; name: string; jersey_number: number | null; position: string | null; lineup_role: 'starter' | 'substitute' | null; is_captain: boolean; display_order: number | null };
type Team = { id: number; name: string; team: { id: number; name: string; crest_url: string | null }; has_submitted_lineup: boolean; players: Player[] };
type EventRow = { id?: number; team_participation_id: number | null; team_name?: string | null; player_registration_id: number | null; player_name?: string | null; related_player_registration_id: number | null; related_player_name?: string | null; type: string; minute: number | null; period: string; notes: string };
type Confirmation = { team: string; representative: string | null; decision: string; notes: string | null; decided_at: string | null };
type Report = { id: number; folio: string | null; status: string; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; summary: string | null; incidents: string | null; submitted_at: string | null; review_notes: string | null; approved_at: string | null; events: EventRow[]; confirmations: Confirmation[] };
type Match = { id: number; status: string; scheduled_at: string | null; competition: { name: string }; matchday: { name: string }; field: { name: string; venue: { name: string } } | null; home: Team; away: Team; officials: { role: string; name: string }[]; report: Report | null; permissions: { capture_available: boolean; edit: boolean; submit: boolean; view: boolean; confirm: boolean; review: boolean; download: boolean } };
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{ matches: { data: Match[]; links: PageLink[]; total: number } }>();

const editing = ref<Match | null>(null);
const viewing = ref<Match | null>(null);
const searchTerm = ref('');
const documentFilter = ref('all');
const matchdayFilter = ref('all');
const sortOrder = ref('recent');
const form = useForm<{ home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; summary: string; incidents: string; events: EventRow[]; reason: string }>({ home_score: 0, away_score: 0, home_no_show: false, away_no_show: false, summary: '', incidents: '', events: [], reason: '' });
const statuses: Record<string, string> = { draft: 'Borrador', awaiting_confirmations: 'Esperando conformidad', correction_required: 'Requiere corrección', under_review: 'En revisión', approved: 'Aprobada' };
const decisions: Record<string, string> = { pending: 'Pendiente', confirmed: 'Conforme', rejected: 'No conforme' };
const eventTypes: Record<string, string> = { goal: 'Gol', own_goal: 'Autogol', yellow_card: 'Tarjeta amarilla', second_yellow: 'Segunda amarilla', red_card: 'Tarjeta roja', substitution: 'Sustitución', injury: 'Lesión', incident: 'Incidente' };
const periods: Record<string, string> = { first_half: 'Primer tiempo', halftime: 'Medio tiempo', second_half: 'Segundo tiempo', extra_time: 'Tiempo extra', penalties: 'Penales', post_match: 'Después del partido', match: 'Partido' };
const officialRoles: Record<string, string> = { central: 'Árbitro central', assistant_1: 'Asistente 1', assistant_2: 'Asistente 2', fourth: 'Cuarto árbitro' };
const approvedReports = computed(() => props.matches.data.filter((match) => match.report?.status === 'approved').length);
const reportsInProcess = computed(() => props.matches.data.filter((match) => match.report && match.report.status !== 'approved').length);
const pendingReports = computed(() => props.matches.data.filter((match) => !match.report).length);

const matchdayOptions = computed(() => Array.from(new Set(props.matches.data.map((match) => match.matchday.name))));
const filteredMatches = computed(() => props.matches.data.filter((match) => {
    const query = searchTerm.value.trim().toLocaleLowerCase('es');
    const searchable = [match.home.name, match.away.name, match.competition.name, match.matchday.name, match.report?.folio ?? ''].join(' ').toLocaleLowerCase('es');
    if (query && !searchable.includes(query)) return false;
    if (matchdayFilter.value !== 'all' && match.matchday.name !== matchdayFilter.value) return false;
    if (documentFilter.value === 'none' && match.report) return false;
    if (documentFilter.value === 'approved' && match.report?.status !== 'approved') return false;
    if (documentFilter.value === 'process' && (!match.report || match.report.status === 'approved')) return false;
    return true;
}).sort((a, b) => sortOrder.value === 'recent'
    ? (b.scheduled_at ?? '').localeCompare(a.scheduled_at ?? '')
    : (a.scheduled_at ?? '').localeCompare(b.scheduled_at ?? '')));
function teamLogo(team: Team): string | null { return team.team.crest_url; }
function reportEventIcon(type: string): string { return type === 'goal' ? 'football' : ['yellow_card', 'second_yellow', 'red_card'].includes(type) ? 'badge' : type === 'own_goal' ? 'refresh' : 'file'; }
function reportEventClass(type: string): string { return ({ goal: 'event-goal', own_goal: 'event-own-goal', yellow_card: 'event-yellow', second_yellow: 'event-red', red_card: 'event-red', substitution: 'event-change' } as Record<string, string>)[type] ?? 'event-other'; }
function officialCentral(match: Match): string { return match.officials.find((official) => official.role === 'central')?.name ?? 'Sin asignar'; }
const derivedScore = computed(() => {
    if (!editing.value) return { home: 0, away: 0 };
    let home = 0; let away = 0;
    form.events.forEach((event) => {
        if (event.type === 'goal') event.team_participation_id === editing.value?.home.id ? home++ : away++;
        if (event.type === 'own_goal') event.team_participation_id === editing.value?.home.id ? away++ : home++;
    });
    return { home, away };
});
function localDate(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin horario'; }
function openEditor(match: Match): void {
    editing.value = match; form.clearErrors();
    const report = match.report;
    form.home_score = report?.home_score ?? 0; form.away_score = report?.away_score ?? 0;
    form.home_no_show = report?.home_no_show ?? false; form.away_no_show = report?.away_no_show ?? false;
    form.summary = report?.summary ?? ''; form.incidents = report?.incidents ?? '';
    form.events = (report?.events ?? []).map((event) => ({ ...event, notes: event.notes ?? '' }));
    form.reason = report ? 'Actualización del borrador de cédula' : 'Captura inicial de cédula';
}
function closeEditor(): void { editing.value = null; form.reset(); }
function openViewer(match: Match): void { viewing.value = match; }
function closeViewer(): void { viewing.value = null; }
function addEvent(): void { if (!editing.value) return; form.events.push({ team_participation_id: editing.value.home.id, player_registration_id: null, related_player_registration_id: null, type: 'goal', minute: null, period: 'match', notes: '' }); }
function removeEvent(index: number): void { form.events.splice(index, 1); }
function players(teamId: number | null): Player[] { if (!editing.value) return []; return teamId === editing.value.home.id ? editing.value.home.players : teamId === editing.value.away.id ? editing.value.away.players : []; }
function lineupPlayers(team: Team, role: 'starter' | 'substitute'): Player[] { return team.players.filter((player) => player.lineup_role === role); }
function playerLabel(player: Player): string { return `#${player.jersey_number ?? 'S/N'} ${player.name}${player.is_captain ? ' (C)' : ''}${player.lineup_role ? ` · ${player.lineup_role === 'starter' ? 'Titular' : 'Suplente'}` : ''}`; }
function save(): void {
    if (!editing.value) return;
    if (!form.home_no_show && !form.away_no_show) { form.home_score = derivedScore.value.home; form.away_score = derivedScore.value.away; }
    form.put(`/liga/partidos/${editing.value.id}/cedula`, { preserveScroll: true, onSuccess: closeEditor });
}
function submit(match: Match): void { if (confirm('La cédula quedará bloqueada mientras ambos representantes registran su conformidad. ¿Deseas enviarla?')) router.post(`/liga/partidos/${match.id}/cedula/enviar`, { reason: 'Envío de cédula para conformidad' }, { preserveScroll: true }); }
function confirmReport(match: Match, decision: 'confirmed' | 'rejected'): void {
    if (!match.report) return;
    const notes = decision === 'rejected' ? prompt('Describe claramente el motivo de la inconformidad:') : 'Conformidad registrada por el representante';
    if (notes === null || (decision === 'rejected' && !notes.trim())) return;
    router.post(`/liga/cedulas/${match.report.id}/conformidad`, { decision, notes }, { preserveScroll: true, onSuccess: closeViewer });
}
function review(match: Match, action: 'approve' | 'return'): void {
    if (!match.report) return;
    const notes = prompt(action === 'approve' ? 'Escribe la observación de aprobación:' : 'Indica las correcciones solicitadas:');
    if (!notes?.trim()) return;
    router.post(`/liga/cedulas/${match.report.id}/revision`, { action, notes }, { preserveScroll: true });
}
function reportStatusClass(status: string | undefined): string {
    if (status === 'approved') return 'match-status-ready';
    if (status === 'draft') return 'match-status-draft';
    if (['awaiting_confirmations', 'under_review'].includes(status ?? '')) return 'match-status-review';
    if (status === 'correction_required') return 'match-status-alert';
    return 'match-status-neutral';
}
</script>

<template>
    <Head title="Cédulas arbitrales" />
    <AppLayout>
        <section class="match-operations-page">
            <header class="match-operations-hero cedula-hero">
                <div class="match-operations-hero-content">
                    <div><p class="text-xs font-black uppercase tracking-[0.2em] text-pink-300">Módulo 12 · Documento oficial</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">Cédulas arbitrales</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-white/85">Registro de resultados, eventos y conformidades oficiales.</p></div>
                    <div class="flex items-center gap-3 rounded-xl border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur-sm"><AppIcon name="clipboard" :size="24" /> Control y trazabilidad</div>
                </div>
            </header>

            <div class="match-kpi-grid cedula-kpis">
                <article class="match-kpi"><AppIcon name="football" variant="illustrated" :size="48"/><div><strong>{{ matches.total }}</strong><p>Partidos visibles</p></div></article>
                <article class="match-kpi"><AppIcon name="check" variant="illustrated" :size="48"/><div><strong>{{ approvedReports }}</strong><p>Cédulas aprobadas</p></div></article>
                <article class="match-kpi"><AppIcon name="clock" variant="illustrated" :size="48"/><div><strong>{{ reportsInProcess }}</strong><p>En validación</p></div></article>
                <article class="match-kpi"><AppIcon name="file" variant="illustrated" :size="48"/><div><strong>{{ pendingReports }}</strong><p>Sin cédula</p></div></article>
            </div>

            <div class="match-flow-note cedula-note"><AppIcon name="badge" :size="22" class="shrink-0 text-blue-600"/><p><strong>Flujo oficial:</strong> Borrador → Conformidad de representantes → Revisión administrativa → Aprobación. El PDF se habilita únicamente al concluir el proceso.</p></div>

            <div class="cedula-filters">
                <label class="cedula-search"><AppIcon name="file" :size="18" class="shrink-0 text-league-600"/><input v-model="searchTerm" type="search" placeholder="Buscar por equipo, torneo o folio…" aria-label="Buscar cédulas" /></label>
                <label class="cedula-filter"><span>Estado documental</span><select v-model="documentFilter"><option value="all">Todos</option><option value="approved">Aprobadas</option><option value="process">En proceso</option><option value="none">Sin cédula</option></select></label>
                <label class="cedula-filter"><span>Jornada</span><select v-model="matchdayFilter"><option value="all">Todas</option><option v-for="day in matchdayOptions" :key="day" :value="day">{{ day }}</option></select></label>
                <label class="cedula-filter"><span>Ordenar por</span><select v-model="sortOrder"><option value="recent">Más recientes</option><option value="oldest">Más antiguos</option></select></label>
            </div>

            <div v-if="!filteredMatches.length" class="match-empty-state"><AppIcon name="file" variant="illustrated" :size="50"/><h2 class="mt-4 text-lg font-bold text-slate-900">No hay cédulas para mostrar</h2><p class="mt-2 text-sm">Prueba otros filtros o consulta otra página.</p></div>
            <div v-else class="cedula-list">
                <article v-for="match in filteredMatches" :key="match.id" class="cedula-match">
                    <div class="cedula-match-head">
                        <div class="cedula-round-icon"><AppIcon name="calendar" :size="22"/></div>
                        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="text-lg text-slate-900">{{ match.matchday.name }}</strong><span class="match-status" :class="reportStatusClass(match.report?.status)">{{ match.report ? statuses[match.report.status] : 'Sin cédula' }}</span><span v-if="!match.permissions.capture_available && !match.report" class="match-status match-status-draft"><AppIcon name="lock" :size="12"/> Captura bloqueada</span></div><p class="mt-1 text-xs text-slate-600">{{ match.competition.name }} · {{ localDate(match.scheduled_at) }}</p><p v-if="match.field" class="mt-1 text-xs text-slate-500">{{ match.field.venue.name }} · {{ match.field.name }}</p></div>
                        <span v-if="match.report?.folio" class="cedula-folio">Folio {{ match.report.folio }}</span>
                    </div>
                    <div class="cedula-match-body">
                        <div class="cedula-scoreboard">
                            <div class="cedula-team"><div class="cedula-crest"><img v-if="teamLogo(match.home)" :src="teamLogo(match.home)" :alt="`Escudo de ${match.home.name}`" loading="lazy"><AppIcon v-else name="shield" :size="25"/></div><div><strong>{{ match.home.name }}</strong><small>Local</small></div></div>
                            <div class="cedula-score">{{ match.report?.home_score ?? 0 }} – {{ match.report?.away_score ?? 0 }}</div>
                            <div class="cedula-team cedula-team--away"><div class="cedula-crest"><img v-if="teamLogo(match.away)" :src="teamLogo(match.away)" :alt="`Escudo de ${match.away.name}`" loading="lazy"><AppIcon v-else name="shield" :size="25"/></div><div><strong>{{ match.away.name }}</strong><small>Visitante</small></div></div>
                        </div>
                        <div class="cedula-actions"><button v-if="match.permissions.view" class="cedula-btn cedula-btn-outline" @click="openViewer(match)"><AppIcon name="file" :size="16"/> {{ match.permissions.confirm ? 'Revisar cédula' : 'Ver cédula' }}</button><button v-if="match.permissions.edit" class="cedula-btn cedula-btn-outline" @click="openEditor(match)"><AppIcon name="edit" :size="16"/> {{ match.report ? 'Editar borrador' : 'Capturar cédula' }}</button><button v-if="match.permissions.submit && match.report" class="cedula-btn cedula-btn-primary" @click="submit(match)"><AppIcon name="check" :size="16"/> Enviar a conformidad</button><template v-if="match.permissions.review"><button class="cedula-btn cedula-btn-primary" @click="review(match, 'approve')">Aprobar cédula</button><button class="cedula-btn cedula-btn-outline" @click="review(match, 'return')">Solicitar corrección</button></template><a v-if="match.permissions.download && match.report" class="cedula-btn cedula-btn-primary" :href="`/liga/cedulas/${match.report.id}.pdf`"><AppIcon name="download" :size="16"/> Descargar PDF</a></div>
                    </div>
                    <div v-if="match.report?.confirmations.length || (!match.permissions.capture_available && !match.report)" class="cedula-match-foot">
                        <template v-if="match.report?.confirmations.length"><span v-for="item in match.report.confirmations" :key="item.team" class="flex items-center gap-2"><AppIcon name="check" :size="14" :class="item.decision === 'confirmed' ? 'text-emerald-600' : 'text-slate-400'"/> {{ item.team }}: <strong>{{ decisions[item.decision] }}</strong></span></template>
                        <span v-else class="text-amber-800"><AppIcon name="lock" :size="14" class="inline"/> La captura estará disponible cuando inicie el encuentro.</span>
                    </div>
                </article>
            </div>
            <nav v-if="matches.links.length > 3" class="mt-5 flex flex-wrap justify-center gap-2"><Link v-for="link in matches.links" :key="link.label" :href="link.url ?? ''" class="rounded-lg border px-3 py-2 text-sm backdrop-blur-sm" :class="link.active ? 'border-league-600 bg-league-600 text-white' : 'border-white/80 bg-white/75 text-slate-700'" v-html="link.label" /></nav>
        </section>

        <div v-if="viewing?.report" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-5" @click.self="closeViewer">
            <article class="cedula-viewer match-modal-panel max-h-[95vh] w-full max-w-6xl overflow-y-auto rounded-[1.6rem]">
                <header class="cedula-viewer-head">
                    <div class="flex items-center gap-3"><AppIcon name="file" variant="illustrated" :size="46"/><div><p class="text-xs font-extrabold uppercase tracking-widest text-blue-700">Vista de solo lectura</p><h2 class="text-xl font-bold text-slate-900">Cédula: {{ viewing.home.name }} vs {{ viewing.away.name }}</h2><p class="mt-1 text-xs text-slate-600">{{ viewing.competition.name }} · {{ viewing.matchday.name }} · Folio {{ viewing.report.folio ?? 'pendiente' }}</p></div></div>
                    <button type="button" class="cedula-close" aria-label="Cerrar" @click="closeViewer">×</button>
                </header>
                <div class="cedula-viewer-content">
                    <section class="cedula-view-score"><div class="cedula-team"><div class="cedula-crest"><img v-if="teamLogo(viewing.home)" :src="teamLogo(viewing.home)" :alt="`Escudo de ${viewing.home.name}`"><AppIcon v-else name="shield" :size="25"/></div><div><strong>{{ viewing.home.name }}</strong><small>Local</small></div></div><div class="cedula-score">{{ viewing.report.home_score }} – {{ viewing.report.away_score }}</div><div class="cedula-team cedula-team--away"><div class="cedula-crest"><img v-if="teamLogo(viewing.away)" :src="teamLogo(viewing.away)" :alt="`Escudo de ${viewing.away.name}`"><AppIcon v-else name="shield" :size="25"/></div><div><strong>{{ viewing.away.name }}</strong><small>Visitante</small></div></div></section>
                    <section class="cedula-info-strip"><div><AppIcon name="calendar" :size="22"/><span><strong>Fecha y cancha</strong><small>{{ localDate(viewing.scheduled_at) }} · {{ viewing.field ? `${viewing.field.venue.name} / ${viewing.field.name}` : 'Sin cancha' }}</small></span></div><div><AppIcon name="badge" :size="22"/><span><strong>Estado</strong><small>{{ statuses[viewing.report.status] }}</small></span></div><div><AppIcon name="user" :size="22"/><span><strong>Árbitro central</strong><small>{{ officialCentral(viewing) }}</small></span></div></section>
                    <section v-if="viewing.report.home_no_show || viewing.report.away_no_show" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><strong>Incomparecencia:</strong> {{ viewing.report.home_no_show ? viewing.home.name : viewing.away.name }}</section>
                    <section><h3 class="cedula-section-title">Alineaciones registradas</h3><div class="mt-2 grid gap-3 sm:grid-cols-2"><article v-for="team in [viewing.home, viewing.away]" :key="team.id" class="cedula-light-card"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><div class="cedula-crest cedula-crest--small"><img v-if="teamLogo(team)" :src="teamLogo(team)" :alt="`Escudo de ${team.name}`"><AppIcon v-else name="shield" :size="18"/></div><strong>{{ team.name }}</strong></div><span class="text-xs font-semibold text-emerald-700">{{ team.has_submitted_lineup ? 'Alineación enviada' : 'Plantilla activa' }}</span></div><template v-if="team.has_submitted_lineup"><p class="mt-2 text-xs text-slate-600">{{ lineupPlayers(team, 'starter').length }} titulares · {{ lineupPlayers(team, 'substitute').length }} suplentes</p></template><p v-else class="mt-2 text-xs text-slate-600">No se envió una alineación digital para este equipo.</p></article></div></section>
                    <section><h3 class="cedula-section-title">Eventos del encuentro</h3><div class="mt-2 overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full text-left text-sm"><thead class="bg-slate-100 text-xs text-slate-700"><tr><th class="px-3 py-2">Minuto</th><th class="px-3 py-2">Periodo</th><th class="px-3 py-2">Evento</th><th class="px-3 py-2">Equipo</th><th class="px-3 py-2">Jugador</th><th class="px-3 py-2">Detalle</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="event in viewing.report.events" :key="event.id"><td class="px-3 py-2 font-bold">{{ event.minute ?? '—' }}'</td><td class="px-3 py-2">{{ periods[event.period] ?? event.period }}</td><td class="px-3 py-2"><span class="cedula-event" :class="reportEventClass(event.type)"><AppIcon :name="reportEventIcon(event.type)" :size="15"/>{{ eventTypes[event.type] ?? event.type }}</span></td><td class="px-3 py-2">{{ event.team_name ?? 'General' }}</td><td class="px-3 py-2">{{ event.player_name ?? '—' }}<span v-if="event.related_player_name"> → {{ event.related_player_name }}</span></td><td class="px-3 py-2">{{ event.notes || '—' }}</td></tr><tr v-if="!viewing.report.events.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Sin eventos registrados.</td></tr></tbody></table></div></section>
                    <section class="grid gap-3 sm:grid-cols-2"><div class="cedula-light-card"><h3 class="cedula-section-title"><AppIcon name="file" :size="18"/> Resumen del encuentro</h3><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ viewing.report.summary || 'Sin resumen.' }}</p></div><div class="cedula-light-card"><h3 class="cedula-section-title"><AppIcon name="badge" :size="18"/> Incidentes y observaciones</h3><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ viewing.report.incidents || 'Sin incidentes.' }}</p></div></section>
                    <section><h3 class="cedula-section-title">Conformidad de representantes</h3><div class="mt-2 grid gap-3 sm:grid-cols-2"><div v-for="item in viewing.report.confirmations" :key="item.team" class="cedula-light-card"><div class="flex items-center justify-between gap-3"><strong>{{ item.team }}</strong><span class="cedula-decision" :class="item.decision === 'confirmed' ? 'cedula-decision--ok' : 'cedula-decision--pending'">{{ decisions[item.decision] }}</span></div><p class="mt-2 text-xs text-slate-600">{{ item.representative ?? 'Representante no disponible' }}<span v-if="item.decided_at"> · {{ localDate(item.decided_at) }}</span></p><p v-if="item.notes" class="mt-2 text-sm text-slate-700">{{ item.notes }}</p></div></div></section>
                    <section v-if="viewing.report.review_notes" class="cedula-review"><AppIcon name="check" :size="20"/><strong>Revisión administrativa</strong><span>{{ viewing.report.review_notes }}</span></section>
                </div>
                <footer class="cedula-view-footer"><button type="button" class="btn-secondary" @click="closeViewer">Cerrar</button><template v-if="viewing.permissions.confirm"><button type="button" class="btn-secondary" @click="confirmReport(viewing, 'rejected')">No estoy conforme</button><button type="button" class="btn-primary" @click="confirmReport(viewing, 'confirmed')">Estoy conforme</button></template><a v-if="viewing.permissions.download" class="btn-primary" :href="`/liga/cedulas/${viewing.report.id}.pdf`"><AppIcon name="download" :size="16" class="inline"/> Descargar PDF aprobado</a></footer>
            </article>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeEditor"><form class="match-modal-panel max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-[1.5rem] p-6" @submit.prevent="save"><div class="flex justify-between gap-4"><div><h2 class="text-xl font-semibold">Cédula: {{ editing.home.name }} vs {{ editing.away.name }}</h2><p class="mt-1 text-sm text-slate-500">Registra todos los hechos antes de enviarla.</p></div><button type="button" class="text-2xl text-slate-400" @click="closeEditor">×</button></div>
            <FormError class="mt-4" :message="(form.errors as Record<string, string>).report || (form.errors as Record<string, string>).events" />
            <section class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div><h3 class="font-semibold">Alineaciones registradas</h3><p class="mt-1 text-xs text-slate-500">Los jugadores de la alineación enviada se cargan automáticamente en la cédula.</p></div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article v-for="team in [editing.home, editing.away]" :key="team.id" class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3"><strong>{{ team.name }}</strong><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="team.has_submitted_lineup ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">{{ team.has_submitted_lineup ? 'Alineación enviada' : 'Plantilla activa' }}</span></div>
                        <template v-if="team.has_submitted_lineup">
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Titulares ({{ lineupPlayers(team, 'starter').length }})</p>
                            <div class="mt-2 grid gap-1.5 sm:grid-cols-2"><span v-for="player in lineupPlayers(team, 'starter')" :key="player.id" class="rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ playerLabel(player) }}</span></div>
                            <template v-if="lineupPlayers(team, 'substitute').length"><p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Suplentes ({{ lineupPlayers(team, 'substitute').length }})</p><div class="mt-2 grid gap-1.5 sm:grid-cols-2"><span v-for="player in lineupPlayers(team, 'substitute')" :key="player.id" class="rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ playerLabel(player) }}</span></div></template>
                        </template>
                        <p v-else class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Este equipo no ha enviado una alineación digital. Para no bloquear la cédula, se muestran sus jugadores activos y aprobados.</p>
                    </article>
                </div>
            </section>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="rounded-xl border p-4"><input v-model="form.home_no_show" type="checkbox" :disabled="form.away_no_show"> Incomparecencia de {{ editing.home.name }}</label><label class="rounded-xl border p-4"><input v-model="form.away_no_show" type="checkbox" :disabled="form.home_no_show"> Incomparecencia de {{ editing.away.name }}</label></div>
            <div class="mt-5 grid grid-cols-2 gap-4"><div><label class="form-label">Marcador local</label><input v-model.number="form.home_score" type="number" min="0" max="999" class="form-input" :readonly="!form.home_no_show && !form.away_no_show"><p v-if="!form.home_no_show && !form.away_no_show" class="mt-1 text-xs text-slate-500">Calculado por eventos: {{ derivedScore.home }}</p></div><div><label class="form-label">Marcador visitante</label><input v-model.number="form.away_score" type="number" min="0" max="999" class="form-input" :readonly="!form.home_no_show && !form.away_no_show"><p v-if="!form.home_no_show && !form.away_no_show" class="mt-1 text-xs text-slate-500">Calculado por eventos: {{ derivedScore.away }}</p></div></div>
            <div class="mt-6 flex items-center justify-between"><h3 class="font-semibold">Eventos del partido</h3><button type="button" class="btn-secondary" @click="addEvent">Agregar evento</button></div>
            <div class="mt-3 space-y-3"><div v-for="(event, index) in form.events" :key="index" class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-6"><select v-model="event.type" class="form-input"><option v-for="(label, value) in eventTypes" :key="value" :value="value">{{ label }}</option></select><select v-model.number="event.team_participation_id" class="form-input"><option :value="editing.home.id">{{ editing.home.name }}</option><option :value="editing.away.id">{{ editing.away.name }}</option></select><select v-model.number="event.player_registration_id" class="form-input"><option :value="null">Sin jugador</option><option v-for="player in players(event.team_participation_id)" :key="player.id" :value="player.id">{{ playerLabel(player) }}</option></select><select v-if="event.type === 'substitution'" v-model.number="event.related_player_registration_id" class="form-input"><option :value="null">Jugador que ingresa</option><option v-for="player in players(event.team_participation_id)" :key="player.id" :value="player.id">{{ playerLabel(player) }}</option></select><select v-else v-model="event.period" class="form-input"><option v-for="(label, value) in periods" :key="value" :value="value">{{ label }}</option></select><input v-model.number="event.minute" type="number" min="0" max="300" class="form-input" placeholder="Minuto"><div class="flex gap-2"><input v-model="event.notes" class="form-input" maxlength="2000" placeholder="Notas"><button type="button" class="text-red-600" @click="removeEvent(index)">Eliminar</button></div></div><p v-if="!form.events.length" class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">No hay eventos registrados.</p></div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Resumen del encuentro</label><textarea v-model="form.summary" class="form-input min-h-28" maxlength="5000"></textarea></div><div><label class="form-label">Incidentes y observaciones</label><textarea v-model="form.incidents" class="form-input min-h-28" maxlength="10000"></textarea></div></div><div class="mt-5"><label class="form-label">Motivo del guardado</label><input v-model="form.reason" class="form-input" required maxlength="500"></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="closeEditor">Cancelar</button><button class="btn-primary" :disabled="form.processing">Guardar borrador</button></div></form></div>
    </AppLayout>
</template>

<style scoped>
.cedula-hero {background-image:linear-gradient(104deg,rgba(5,29,74,.89),rgba(13,77,153,.72)),url('/images/public/league-stadium.jpg');background-size:cover;background-position:center 53%;border-radius:24px}
.cedula-kpis .match-kpi{background:rgba(255,255,255,.96);border:1px solid #e0e9f4;border-radius:16px;box-shadow:0 7px 18px rgba(28,55,85,.06)}
.cedula-kpis .match-kpi strong{color:#102446;font-weight:850}
.cedula-note{background:rgba(239,246,255,.93);border-color:#bad5ff;color:#144eab}
.cedula-filters{display:grid;grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,.9fr));align-items:end;gap:14px;padding:16px;margin:20px 0;background:rgba(255,255,255,.88);border:1px solid #dce6f3;border-radius:18px;box-shadow:0 8px 22px rgba(15,43,83,.05)}
.cedula-search{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #cbdaf0;border-radius:11px;padding:0 12px;height:44px;min-width:0}
.cedula-search input{width:100%;min-width:0;outline:none;background:transparent;font-size:13px;color:#102446}
.cedula-filter{display:flex;flex-direction:column;gap:6px;min-width:0;font-size:12px;font-weight:650;color:#153664}
.cedula-filter select{width:100%;height:44px;min-width:0;padding:0 10px;background:white;border:1px solid #cbdaf0;border-radius:11px;color:#173765}
.cedula-list{display:grid;gap:14px}
.cedula-match{overflow:hidden;background:rgba(255,255,255,.96);border:1px solid #d9e5f3;border-radius:18px;box-shadow:0 8px 22px rgba(15,44,84,.07)}
.cedula-match-head{display:flex;align-items:start;gap:12px;padding:17px 20px 12px}
.cedula-round-icon{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:10px;background:#0d60d0;color:white}
.cedula-folio{font-size:11px;color:#435f86;background:#f3f7fd;border:1px solid #e0e9f4;border-radius:8px;padding:7px 9px}
.cedula-match-body{display:flex;align-items:center;gap:20px;padding:0 20px 15px}
.cedula-scoreboard,.cedula-view-score{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:12px;width:100%;min-width:0}
.cedula-team{display:flex;align-items:center;gap:12px;min-width:0}
.cedula-team--away{justify-content:flex-end;flex-direction:row-reverse;text-align:right}
.cedula-team strong{display:block;font-size:14px;color:#0e2346;line-height:1.25}
.cedula-team small{display:block;color:#617896;font-size:11px;margin-top:4px}
.cedula-crest{display:grid;place-items:center;flex:none;width:47px;height:47px;border:1px solid #dbe7f4;border-radius:11px;background:#f7faff;color:#1b58a2;overflow:hidden}
.cedula-crest img{display:block;width:100%;height:100%;object-fit:contain;padding:3px}
.cedula-crest--small{width:33px;height:33px}
.cedula-score{padding:8px 12px;border-radius:10px;background:linear-gradient(180deg,#102b58,#07192e);color:white;white-space:nowrap;font-size:21px;font-weight:850;letter-spacing:1px}
.cedula-actions{display:flex;flex-wrap:wrap;justify-content:end;gap:8px;min-width:180px;max-width:285px;border-left:1px solid #e1e8f1;padding-left:17px}
.cedula-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:9px;padding:9px 11px;font-size:12px;font-weight:750;white-space:nowrap;transition:background .18s}
.cedula-btn-outline{background:white;border:1px solid #b7cee9;color:#0a4a9d}
.cedula-btn-outline:hover{background:#eff6ff}
.cedula-btn-primary{color:white;background:#0764e7;border:1px solid #0764e7}
.cedula-btn-primary:hover{background:#044eb6}
.cedula-match-foot{display:flex;gap:10px 20px;flex-wrap:wrap;border-top:1px solid #e6eef7;margin:0 20px;padding:10px 0;font-size:11px;color:#446183}
.cedula-viewer{background:#fff}
.cedula-viewer-head{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:15px;padding:16px 22px;background:rgba(255,255,255,.98);border-bottom:1px solid #e3ebf5}
.cedula-close{font-size:25px;color:#496587;border-radius:8px;padding:0 9px}
.cedula-viewer-content{display:grid;gap:15px;padding:18px 22px}
.cedula-view-score{background:#f3f7fd;border-radius:14px;padding:13px 20px}
.cedula-view-score .cedula-score{font-size:26px;padding:9px 18px}
.cedula-info-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid #dbe6f5;border-radius:13px;overflow:hidden}
.cedula-info-strip>div{display:flex;gap:12px;align-items:center;padding:12px 14px;min-width:0;color:#164f9a}
.cedula-info-strip>div+div{border-left:1px solid #dbe6f5}
.cedula-info-strip strong{display:block;font-size:12px;color:#102446}
.cedula-info-strip small{display:block;font-size:12px;color:#405c7e;line-height:1.5}
.cedula-section-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:800;color:#0f2546}
.cedula-light-card{border:1px solid #dbe7f5;background:#f9fbff;border-radius:13px;padding:13px 15px;font-size:13px;color:#152e53}
.cedula-event{display:inline-flex;gap:5px;align-items:center;white-space:nowrap;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:700}
.event-goal{background:#e1efff;color:#0753bd}.event-own-goal{background:#ffeadc;color:#b84805}.event-yellow{background:#fff0b8;color:#8c5400}.event-red{background:#ffe2e4;color:#b91c1c}.event-change{background:#e7f9ee;color:#138144}.event-other{background:#f0f4f8;color:#475569}
.cedula-decision{font-size:11px;padding:4px 8px;border-radius:99px;background:#edf2f8;color:#4b607e}.cedula-decision--ok{background:#d9fae6;color:#12713b}
.cedula-review{display:flex;align-items:center;gap:10px;padding:13px;border:1px solid #a3dbb5;border-radius:12px;background:#e7f8eb;color:#1b6835;font-size:13px}
.cedula-view-footer{position:sticky;bottom:0;display:flex;justify-content:end;flex-wrap:wrap;gap:10px;background:white;border-top:1px solid #dfe9f5;padding:15px 22px}
@media(max-width:900px){.cedula-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.cedula-match-body{flex-direction:column;align-items:stretch}.cedula-actions{max-width:none;min-width:0;border-left:0;border-top:1px solid #e5edf6;padding:12px 0 0}.cedula-info-strip{grid-template-columns:1fr}.cedula-info-strip>div+div{border-left:0;border-top:1px solid #dbe6f5}}
@media(max-width:550px){.cedula-filters{grid-template-columns:1fr}.cedula-match-head{flex-wrap:wrap}.cedula-folio{margin-left:50px}.cedula-scoreboard,.cedula-view-score{gap:7px}.cedula-team{gap:6px}.cedula-team strong{font-size:12px}.cedula-crest{width:36px;height:36px}.cedula-score{font-size:16px;padding:7px}.cedula-viewer-content{padding:15px}.cedula-view-score{padding:9px}}
</style>
