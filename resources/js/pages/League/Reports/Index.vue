<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Player = { id: number; name: string; jersey_number: number | null; position: string | null; lineup_role: 'starter' | 'substitute' | null; is_captain: boolean; display_order: number | null };
type Team = { id: number; name: string; team: { id: number; name: string }; has_submitted_lineup: boolean; players: Player[] };
type EventRow = { id?: number; team_participation_id: number | null; team_name?: string | null; player_registration_id: number | null; player_name?: string | null; related_player_registration_id: number | null; related_player_name?: string | null; type: string; minute: number | null; period: string; notes: string };
type Confirmation = { team: string; representative: string | null; decision: string; notes: string | null; decided_at: string | null };
type Report = { id: number; folio: string | null; status: string; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; summary: string | null; incidents: string | null; submitted_at: string | null; review_notes: string | null; approved_at: string | null; events: EventRow[]; confirmations: Confirmation[] };
type Match = { id: number; status: string; scheduled_at: string | null; competition: { name: string }; matchday: { name: string }; field: { name: string; venue: { name: string } } | null; home: Team; away: Team; officials: { role: string; name: string }[]; report: Report | null; permissions: { capture_available: boolean; edit: boolean; submit: boolean; view: boolean; confirm: boolean; review: boolean; download: boolean } };
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{ matches: { data: Match[]; links: PageLink[]; total: number } }>();

const editing = ref<Match | null>(null);
const viewing = ref<Match | null>(null);
const form = useForm<{ home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; summary: string; incidents: string; events: EventRow[]; reason: string }>({ home_score: 0, away_score: 0, home_no_show: false, away_no_show: false, summary: '', incidents: '', events: [], reason: '' });
const statuses: Record<string, string> = { draft: 'Borrador', awaiting_confirmations: 'Esperando conformidad', correction_required: 'Requiere corrección', under_review: 'En revisión', approved: 'Aprobada' };
const decisions: Record<string, string> = { pending: 'Pendiente', confirmed: 'Conforme', rejected: 'No conforme' };
const eventTypes: Record<string, string> = { goal: 'Gol', own_goal: 'Autogol', yellow_card: 'Tarjeta amarilla', second_yellow: 'Segunda amarilla', red_card: 'Tarjeta roja', substitution: 'Sustitución', injury: 'Lesión', incident: 'Incidente' };
const periods: Record<string, string> = { first_half: 'Primer tiempo', halftime: 'Medio tiempo', second_half: 'Segundo tiempo', extra_time: 'Tiempo extra', penalties: 'Penales', post_match: 'Después del partido', match: 'Partido' };
const officialRoles: Record<string, string> = { central: 'Árbitro central', assistant_1: 'Asistente 1', assistant_2: 'Asistente 2', fourth: 'Cuarto árbitro' };

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
</script>

<template>
    <Head title="Cédulas arbitrales" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 12</p><h1 class="mt-1 text-3xl font-semibold">Cédulas arbitrales</h1><p class="mt-2 max-w-3xl text-slate-600">Captura los hechos del encuentro, solicita la conformidad de ambos representantes y envía el resultado a revisión administrativa.</p></div><div class="stat-card min-w-40"><span class="stat-label">Partidos visibles</span><span class="stat-value">{{ matches.total }}</span></div></div>
            <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900"><strong>Flujo:</strong> borrador → conformidad local y visitante → revisión → aprobación. El marcador oficial solo se registra al aprobar la cédula.</div>
            <div v-if="!matches.data.length" class="card mt-7 p-12 text-center text-slate-600">No hay partidos disponibles para tu rol.</div>
            <div v-else class="mt-7 space-y-5">
                <article v-for="match in matches.data" :key="match.id" class="card overflow-hidden">
                    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50 p-5 md:flex-row md:items-center"><div><h2 class="font-semibold">{{ match.matchday.name }} · {{ match.competition.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ localDate(match.scheduled_at) }}<span v-if="match.field"> · {{ match.field.venue.name }} / {{ match.field.name }}</span></p></div><span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700">{{ match.report ? statuses[match.report.status] : 'Sin cédula' }}</span></header>
                    <div class="p-5">
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 text-center"><div><strong>{{ match.home.name }}</strong><p class="text-xs text-slate-500">Local</p></div><div class="rounded-xl bg-slate-900 px-5 py-3 text-xl font-bold text-white">{{ match.report?.home_score ?? 0 }} – {{ match.report?.away_score ?? 0 }}</div><div><strong>{{ match.away.name }}</strong><p class="text-xs text-slate-500">Visitante</p></div></div>
                        <div v-if="match.report?.folio" class="mt-4 text-center text-xs font-semibold text-slate-500">Folio {{ match.report.folio }}</div>
                        <div v-if="match.report?.confirmations.length" class="mt-5 grid gap-3 sm:grid-cols-2"><div v-for="item in match.report.confirmations" :key="item.team" class="rounded-xl border border-slate-200 p-3 text-sm"><div class="flex justify-between gap-2"><strong>{{ item.team }}</strong><span>{{ decisions[item.decision] }}</span></div><p class="mt-1 text-xs text-slate-500">{{ item.representative ?? 'Representante no disponible' }}<span v-if="item.decided_at"> · {{ localDate(item.decided_at) }}</span></p><p v-if="item.notes" class="mt-2 text-xs text-slate-600">{{ item.notes }}</p></div></div>
                        <p v-if="match.report?.review_notes" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"><strong>Observación:</strong> {{ match.report.review_notes }}</p>
                        <p v-if="!match.permissions.capture_available && !match.report" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">La captura estará disponible cuando llegue la fecha y hora programada del partido.</p>
                        <div class="mt-5 flex flex-wrap gap-2"><button v-if="match.permissions.view" class="btn-secondary" @click="openViewer(match)">{{ match.permissions.confirm ? 'Revisar antes de confirmar' : 'Ver cédula' }}</button><button v-if="match.permissions.edit" class="btn-secondary" @click="openEditor(match)">{{ match.report ? 'Editar borrador' : 'Capturar cédula' }}</button><button v-if="match.permissions.submit && match.report" class="btn-primary" @click="submit(match)">Enviar a conformidad</button><template v-if="match.permissions.review"><button class="btn-primary" @click="review(match, 'approve')">Aprobar cédula</button><button class="btn-secondary" @click="review(match, 'return')">Devolver para corrección</button></template><a v-if="match.permissions.download && match.report" class="btn-primary" :href="`/liga/cedulas/${match.report.id}.pdf`">Descargar PDF aprobado</a><span v-else-if="match.report?.folio" class="self-center text-xs text-slate-500">El PDF se habilitará después de la aprobación.</span></div>
                    </div>
                </article>
            </div>
            <nav v-if="matches.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-2"><Link v-for="link in matches.links" :key="link.label" :href="link.url ?? ''" class="rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'border-league-600 bg-league-600 text-white' : 'border-slate-300 bg-white'" v-html="link.label" /></nav>
        </section>

        <div v-if="viewing?.report" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeViewer">
            <article class="max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white">
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white p-5 sm:p-6">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-league-600">Vista de solo lectura</p><h2 class="mt-1 text-xl font-semibold">Cédula: {{ viewing.home.name }} vs {{ viewing.away.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ viewing.competition.name }} · {{ viewing.matchday.name }} · Folio {{ viewing.report.folio ?? 'pendiente' }}</p></div>
                    <button type="button" class="text-2xl text-slate-400" aria-label="Cerrar" @click="closeViewer">×</button>
                </header>
                <div class="space-y-6 p-5 sm:p-6">
                    <section class="grid gap-4 md:grid-cols-[1fr_auto_1fr] md:items-center">
                        <div class="rounded-2xl bg-slate-50 p-5 text-center"><strong class="text-lg">{{ viewing.home.name }}</strong><p class="text-xs text-slate-500">Equipo local</p></div>
                        <div class="rounded-2xl bg-slate-900 px-7 py-4 text-center text-3xl font-bold text-white">{{ viewing.report.home_score }} – {{ viewing.report.away_score }}</div>
                        <div class="rounded-2xl bg-slate-50 p-5 text-center"><strong class="text-lg">{{ viewing.away.name }}</strong><p class="text-xs text-slate-500">Equipo visitante</p></div>
                    </section>

                    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 p-4"><span class="text-xs font-semibold uppercase text-slate-500">Fecha y cancha</span><p class="mt-2 text-sm">{{ localDate(viewing.scheduled_at) }}<br><span v-if="viewing.field">{{ viewing.field.venue.name }} / {{ viewing.field.name }}</span><span v-else>Sin cancha registrada</span></p></div>
                        <div class="rounded-xl border border-slate-200 p-4"><span class="text-xs font-semibold uppercase text-slate-500">Estado</span><p class="mt-2 text-sm font-semibold">{{ statuses[viewing.report.status] }}</p><p v-if="viewing.report.submitted_at" class="mt-1 text-xs text-slate-500">Enviada: {{ localDate(viewing.report.submitted_at) }}</p></div>
                        <div class="rounded-xl border border-slate-200 p-4"><span class="text-xs font-semibold uppercase text-slate-500">Cuerpo arbitral</span><p v-for="official in viewing.officials" :key="official.role" class="mt-2 text-sm"><strong>{{ officialRoles[official.role] ?? official.role }}:</strong> {{ official.name }}</p><p v-if="!viewing.officials.length" class="mt-2 text-sm text-slate-500">Sin árbitros registrados.</p></div>
                    </section>

                    <section v-if="viewing.report.home_no_show || viewing.report.away_no_show" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Incomparecencia:</strong> {{ viewing.report.home_no_show ? viewing.home.name : viewing.away.name }}</section>

                    <section><h3 class="font-semibold">Alineaciones registradas</h3><div class="mt-3 grid gap-4 lg:grid-cols-2"><article v-for="team in [viewing.home, viewing.away]" :key="team.id" class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><strong>{{ team.name }}</strong><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="team.has_submitted_lineup ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">{{ team.has_submitted_lineup ? 'Alineación enviada' : 'Plantilla activa' }}</span></div><template v-if="team.has_submitted_lineup"><p class="mt-3 text-xs font-semibold uppercase text-slate-500">Titulares ({{ lineupPlayers(team, 'starter').length }})</p><div class="mt-2 grid gap-1.5 sm:grid-cols-2"><span v-for="player in lineupPlayers(team, 'starter')" :key="player.id" class="rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ playerLabel(player) }}</span></div><template v-if="lineupPlayers(team, 'substitute').length"><p class="mt-4 text-xs font-semibold uppercase text-slate-500">Suplentes ({{ lineupPlayers(team, 'substitute').length }})</p><div class="mt-2 grid gap-1.5 sm:grid-cols-2"><span v-for="player in lineupPlayers(team, 'substitute')" :key="player.id" class="rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ playerLabel(player) }}</span></div></template></template><p v-else class="mt-3 text-sm text-slate-500">No se envió una alineación digital para este equipo.</p></article></div></section>

                    <section><h3 class="font-semibold">Eventos del encuentro</h3><div class="mt-3 overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Minuto</th><th class="px-4 py-3">Periodo</th><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Equipo</th><th class="px-4 py-3">Jugador</th><th class="px-4 py-3">Detalle</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="event in viewing.report.events" :key="event.id"><td class="px-4 py-3">{{ event.minute ?? '—' }}</td><td class="px-4 py-3">{{ periods[event.period] ?? event.period }}</td><td class="px-4 py-3 font-medium">{{ eventTypes[event.type] ?? event.type }}</td><td class="px-4 py-3">{{ event.team_name ?? 'General' }}</td><td class="px-4 py-3">{{ event.player_name ?? '—' }}<span v-if="event.related_player_name"> → {{ event.related_player_name }}</span></td><td class="px-4 py-3">{{ event.notes || '—' }}</td></tr><tr v-if="!viewing.report.events.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Sin eventos registrados.</td></tr></tbody></table></div></section>

                    <section class="grid gap-4 lg:grid-cols-2"><div class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold">Resumen del encuentro</h3><p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ viewing.report.summary || 'Sin resumen.' }}</p></div><div class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold">Incidentes y observaciones</h3><p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ viewing.report.incidents || 'Sin incidentes.' }}</p></div></section>

                    <section><h3 class="font-semibold">Conformidad de representantes</h3><div class="mt-3 grid gap-3 sm:grid-cols-2"><div v-for="item in viewing.report.confirmations" :key="item.team" class="rounded-xl border border-slate-200 p-4 text-sm"><div class="flex justify-between gap-3"><strong>{{ item.team }}</strong><span>{{ decisions[item.decision] }}</span></div><p class="mt-1 text-xs text-slate-500">{{ item.representative ?? 'Representante no disponible' }}<span v-if="item.decided_at"> · {{ localDate(item.decided_at) }}</span></p><p v-if="item.notes" class="mt-2 text-slate-700">{{ item.notes }}</p></div></div></section>
                    <section v-if="viewing.report.review_notes" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Revisión administrativa:</strong> {{ viewing.report.review_notes }}</section>
                </div>
                <footer class="sticky bottom-0 flex flex-wrap justify-end gap-3 border-t border-slate-200 bg-white p-5 sm:p-6"><button type="button" class="btn-secondary" @click="closeViewer">Cerrar</button><template v-if="viewing.permissions.confirm"><button type="button" class="btn-secondary" @click="confirmReport(viewing, 'rejected')">No estoy conforme</button><button type="button" class="btn-primary" @click="confirmReport(viewing, 'confirmed')">Estoy conforme</button></template><a v-if="viewing.permissions.download" class="btn-primary" :href="`/liga/cedulas/${viewing.report.id}.pdf`">Descargar PDF aprobado</a></footer>
            </article>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeEditor"><form class="max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="save"><div class="flex justify-between gap-4"><div><h2 class="text-xl font-semibold">Cédula: {{ editing.home.name }} vs {{ editing.away.name }}</h2><p class="mt-1 text-sm text-slate-500">Registra todos los hechos antes de enviarla.</p></div><button type="button" class="text-2xl text-slate-400" @click="closeEditor">×</button></div>
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
