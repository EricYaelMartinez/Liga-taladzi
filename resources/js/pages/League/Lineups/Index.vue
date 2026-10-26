<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type AvailablePlayer = { id: number; name: string; jersey_number: number; position: string };
type LineupPlayer = { player_registration_id: number; name: string; jersey_number: number; position: string; role: 'starter' | 'substitute'; is_captain: boolean };
type Lineup = { id: number; status: 'draft' | 'submitted'; submitted_at: string | null; submitted_by: { id: number; name: string } | null; players: LineupPlayer[] };
type MatchTeam = { participation_id: number; name: string; team: { id: number; name: string; short_name: string }; editable: boolean; lineup: Lineup | null; available_players: AvailablePlayer[] };
type Match = { id: number; status: string; scheduled_at: string | null; competition: { id: number; name: string }; matchday: { id: number; name: string; number: number }; field: { id: number; name: string; venue: { id: number; name: string } } | null; teams: MatchTeam[] };
type PageLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{ matches: { data: Match[]; links: PageLink[]; total: number } }>();
const editMatch = ref<Match | null>(null);
const editTeam = ref<MatchTeam | null>(null);
const expandedMatches = ref(new Set<number>());
const lineupForm = useForm<{ players: Array<{ player_registration_id: number; role: 'starter' | 'substitute'; is_captain: boolean }>; reason: string }>({ players: [], reason: '' });

const positions: Record<string, string> = { goalkeeper: 'Portero', defender: 'Defensa', midfielder: 'Mediocampista', forward: 'Delantero' };
const matchStatuses: Record<string, string> = { scheduled: 'Programado', in_progress: 'En curso', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Aplazado' };
const selectedCount = computed(() => lineupForm.players.length);
const startersCount = computed(() => lineupForm.players.filter((item) => item.role === 'starter').length);
const substitutesCount = computed(() => lineupForm.players.filter((item) => item.role === 'substitute').length);
const validStarterCount = computed(() => startersCount.value >= 8 && startersCount.value <= 11);
const submittedLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => team.lineup?.status === 'submitted').length);
const draftLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => team.lineup?.status === 'draft').length);
const pendingLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => !team.lineup).length);

function localDateTime(value: string | null): string {
    return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin horario';
}
function openEditor(match: Match, team: MatchTeam): void {
    editMatch.value = match; editTeam.value = team; lineupForm.clearErrors();
    lineupForm.players = (team.lineup?.players ?? []).map((player) => ({ player_registration_id: player.player_registration_id, role: player.role, is_captain: player.is_captain }));
    lineupForm.reason = team.lineup ? 'Actualización del borrador de alineación' : 'Captura inicial de alineación';
}
function closeEditor(): void { editMatch.value = null; editTeam.value = null; lineupForm.reset(); }
function selection(id: number) { return lineupForm.players.find((item) => item.player_registration_id === id); }
function togglePlayer(player: AvailablePlayer, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) lineupForm.players.push({ player_registration_id: player.id, role: startersCount.value < 11 ? 'starter' : 'substitute', is_captain: false });
    else lineupForm.players = lineupForm.players.filter((item) => item.player_registration_id !== player.id);
}
function setCaptain(id: number): void {
    const selected = selection(id); if (!selected) return;
    const next = !selected.is_captain;
    lineupForm.players.forEach((item) => { item.is_captain = item.player_registration_id === id ? next : false; });
}
function save(): void {
    if (!editMatch.value || !editTeam.value) return;
    lineupForm.put(`/liga/partidos/${editMatch.value.id}/alineaciones/${editTeam.value.participation_id}`, {
        preserveScroll: true,
        onSuccess: closeEditor,
    });
}
function submit(match: Match, team: MatchTeam): void {
    const starterCount = lineupPlayers(team, 'starter').length;
    if (!team.lineup || starterCount < 8 || starterCount > 11 || !confirm('La alineación quedará cerrada definitivamente y no podrá volver a modificarse. ¿Deseas enviarla?')) return;
    router.post(`/liga/partidos/${match.id}/alineaciones/${team.participation_id}/enviar`, { reason: 'Envío definitivo de alineación' }, { preserveScroll: true });
}
function lineupPlayers(team: MatchTeam, role: string): LineupPlayer[] { return team.lineup?.players.filter((player) => player.role === role) ?? []; }
function canSubmit(team: MatchTeam): boolean { const count = lineupPlayers(team, 'starter').length; return count >= 8 && count <= 11; }
function toggleMatch(matchId: number): void {
    const next = new Set(expandedMatches.value);
    next.has(matchId) ? next.delete(matchId) : next.add(matchId);
    expandedMatches.value = next;
}
function matchStatusClass(status: string): string {
    if (status === 'in_progress') return 'match-status-ready';
    if (status === 'scheduled') return 'match-status-review';
    if (['suspended', 'postponed'].includes(status)) return 'match-status-alert';
    return 'match-status-neutral';
}
</script>

<template>
    <Head title="Alineaciones" />
    <AppLayout>
        <section class="match-operations-page">
            <header class="match-operations-hero">
                <div class="match-operations-hero-content">
                    <div><p class="text-xs font-black uppercase tracking-[0.2em] text-gold-400">Módulo 11 · Operación del partido</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">Alineaciones</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-white/80">Prepara titulares, suplentes y capitán antes del encuentro. Cada alineación enviada queda protegida como parte del historial oficial.</p></div>
                    <div class="rounded-2xl border border-white/20 bg-white/10 px-5 py-4 backdrop-blur-sm"><span class="text-xs font-bold uppercase tracking-wider text-white/60">Regla de titulares</span><strong class="mt-1 block text-2xl text-gold-400">8 mínimo · 11 máximo</strong></div>
                </div>
            </header>

            <div class="match-kpi-grid">
                <article class="match-kpi"><span class="match-kpi-icon">⚽</span><div><strong>{{ matches.total }}</strong><p>Partidos visibles</p></div></article>
                <article class="match-kpi"><span class="match-kpi-icon bg-emerald-700">✓</span><div><strong>{{ submittedLineups }}</strong><p>Alineaciones cerradas</p></div></article>
                <article class="match-kpi"><span class="match-kpi-icon bg-amber-600">✎</span><div><strong>{{ draftLineups }}</strong><p>Borradores guardados</p></div></article>
                <article class="match-kpi"><span class="match-kpi-icon bg-slate-600">◷</span><div><strong>{{ pendingLineups }}</strong><p>Pendientes de captura</p></div></article>
            </div>

            <div class="match-flow-note"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 font-black text-amber-800">!</span><p><strong>Proceso recomendado:</strong> captura la alineación, guarda el borrador, verifica que existan entre 8 y 11 titulares y después envíala. El cierre es definitivo.</p></div>

            <div v-if="!matches.data.length" class="match-empty-state"><span class="text-4xl">⚽</span><h2 class="mt-4 text-lg font-bold text-slate-900">Sin partidos disponibles</h2><p class="mt-2 text-sm">No hay partidos publicados disponibles para tu rol.</p></div>
            <div v-else class="space-y-6">
                <article v-for="match in matches.data" :key="match.id" class="match-card">
                    <header class="match-card-header">
                        <div><div class="flex flex-wrap items-center gap-3"><p class="text-xs font-black uppercase tracking-[0.14em] text-league-700">{{ match.matchday.name }}</p><span class="match-status" :class="matchStatusClass(match.status)">{{ matchStatuses[match.status] }}</span></div><h2 class="mt-2 text-xl font-black text-slate-900">{{ match.competition.name }}</h2><div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-600"><span>◷ {{ localDateTime(match.scheduled_at) }}</span><span v-if="match.field">◉ {{ match.field.venue.name }} · {{ match.field.name }}</span></div></div>
                        <div class="match-scoreboard min-w-0 sm:min-w-[360px]"><div class="match-scoreboard-team"><strong>{{ match.teams[0]?.name }}</strong><span class="mt-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Local</span></div><span class="match-scoreboard-center">VS</span><div class="match-scoreboard-team"><strong>{{ match.teams[1]?.name }}</strong><span class="mt-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Visitante</span></div></div>
                    </header>

                    <div class="match-collapse-bar">
                        <div class="flex flex-wrap gap-2"><span v-for="team in match.teams" :key="team.participation_id" class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-[11px] font-bold text-slate-700"><span class="h-2 w-2 rounded-full" :class="team.lineup?.status === 'submitted' ? 'bg-emerald-500' : team.lineup ? 'bg-amber-500' : 'bg-slate-400'"></span>{{ team.team.short_name }}: {{ team.lineup?.status === 'submitted' ? 'cerrada' : team.lineup ? 'borrador' : 'pendiente' }}</span></div>
                        <button type="button" class="match-collapse-button" :aria-expanded="expandedMatches.has(match.id)" :aria-controls="`lineups-${match.id}`" @click="toggleMatch(match.id)">{{ expandedMatches.has(match.id) ? 'Ocultar alineaciones' : 'Ver y capturar alineaciones' }}<span :class="expandedMatches.has(match.id) ? 'rotate-180' : ''">⌄</span></button>
                    </div>

                    <div v-show="expandedMatches.has(match.id)" :id="`lineups-${match.id}`" class="grid divide-y divide-white/80 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                        <section v-for="team in match.teams" :key="team.participation_id" class="lineup-team-panel">
                            <div class="flex items-start justify-between gap-3"><div><h3 class="text-lg font-black text-slate-900">{{ team.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ team.lineup ? (team.lineup.status === 'submitted' ? 'Alineación enviada y protegida' : 'Borrador disponible para revisión') : 'Aún no se ha capturado la alineación' }}</p></div><span class="match-status" :class="team.lineup?.status === 'submitted' ? 'match-status-ready' : team.lineup ? 'match-status-draft' : 'match-status-neutral'">{{ team.lineup?.status === 'submitted' ? 'Cerrada' : team.lineup ? 'Borrador' : 'Pendiente' }}</span></div>

                            <template v-if="team.lineup">
                                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                    <div><div class="flex items-center justify-between"><h4 class="text-xs font-black uppercase tracking-wide text-slate-500">Titulares</h4><span class="rounded-full bg-league-50 px-2 py-1 text-[10px] font-black text-league-700">{{ lineupPlayers(team, 'starter').length }} / 11</span></div><ul class="mt-3 space-y-2"><li v-for="player in lineupPlayers(team, 'starter')" :key="player.player_registration_id" class="lineup-player-row"><span class="lineup-number">{{ player.jersey_number }}</span><span class="min-w-0"><strong class="block truncate text-slate-900">{{ player.name }} <span v-if="player.is_captain" class="text-gold-500">(C)</span></strong><small class="text-slate-500">{{ positions[player.position] }}</small></span></li><li v-if="!lineupPlayers(team, 'starter').length" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400">Sin titulares</li></ul></div>
                                    <div><div class="flex items-center justify-between"><h4 class="text-xs font-black uppercase tracking-wide text-slate-500">Suplentes</h4><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">{{ lineupPlayers(team, 'substitute').length }}</span></div><ul class="mt-3 space-y-2"><li v-for="player in lineupPlayers(team, 'substitute')" :key="player.player_registration_id" class="lineup-player-row"><span class="lineup-number bg-slate-600">{{ player.jersey_number }}</span><span class="min-w-0"><strong class="block truncate text-slate-900">{{ player.name }} <span v-if="player.is_captain" class="text-gold-500">(C)</span></strong><small class="text-slate-500">{{ positions[player.position] }}</small></span></li><li v-if="!lineupPlayers(team, 'substitute').length" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400">Sin suplentes</li></ul></div>
                                </div>
                                <p v-if="team.lineup.submitted_at" class="mt-4 border-t border-white/80 pt-3 text-xs text-slate-500">Enviada {{ localDateTime(team.lineup.submitted_at) }}<span v-if="team.lineup.submitted_by"> por {{ team.lineup.submitted_by.name }}</span></p>
                            </template>

                            <div v-if="team.editable" class="mt-5 flex flex-wrap items-center gap-2"><button class="btn-secondary" @click="openEditor(match, team)">{{ team.lineup ? 'Editar borrador' : 'Capturar alineación' }}</button><button v-if="team.lineup?.status === 'draft'" class="btn-primary" :disabled="!canSubmit(team)" :title="canSubmit(team) ? 'Enviar alineación definitiva' : 'Se requieren entre 8 y 11 titulares'" @click="submit(match, team)">Enviar y cerrar</button><span v-if="team.lineup?.status === 'draft' && !canSubmit(team)" class="text-xs font-bold text-amber-700">Completa entre 8 y 11 titulares.</span></div>
                        </section>
                    </div>
                </article>
            </div>

            <nav v-if="matches.links.length > 3" class="flex flex-wrap justify-center gap-2"><Link v-for="link in matches.links" :key="link.label" :href="link.url ?? ''" class="rounded-lg border px-3 py-2 text-sm backdrop-blur-sm" :class="link.active ? 'border-league-600 bg-league-600 text-white' : link.url ? 'border-white/80 bg-white/75 text-slate-700' : 'cursor-not-allowed border-white/50 bg-white/40 text-slate-400'" v-html="link.label" /></nav>
        </section>

        <div v-if="editMatch && editTeam" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeEditor">
            <form class="match-modal-panel max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-[1.5rem] p-6" @submit.prevent="save">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-semibold">Alineación de {{ editTeam.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ editMatch.matchday.name }} · selecciona titulares, suplentes y capitán.</p></div><button type="button" class="text-2xl text-slate-400" @click="closeEditor">×</button></div>
                <div class="mt-5 grid grid-cols-3 gap-3"><div class="stat-card"><span class="stat-label">Seleccionados</span><span class="stat-value">{{ selectedCount }}</span></div><div class="stat-card"><span class="stat-label">Titulares</span><span class="stat-value">{{ startersCount }}</span></div><div class="stat-card"><span class="stat-label">Suplentes</span><span class="stat-value">{{ substitutesCount }}</span></div></div>
                <p class="mt-3 text-sm font-medium" :class="validStarterCount ? 'text-emerald-700' : 'text-amber-700'">{{ validStarterCount ? 'Cantidad válida: la alineación podrá enviarse después de guardar.' : 'Selecciona entre 8 y 11 titulares para poder enviar la alineación.' }}</p>
                <FormError class="mt-4" :message="lineupForm.errors.players || (lineupForm.errors as Record<string, string>).lineup" />
                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Incluir</th><th class="px-4 py-3">Jugador</th><th class="px-4 py-3">Función</th><th class="px-4 py-3">Capitán</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="player in editTeam.available_players" :key="player.id"><td class="px-4 py-3"><input type="checkbox" :checked="!!selection(player.id)" @change="togglePlayer(player, $event)"></td><td class="px-4 py-3"><strong>#{{ player.jersey_number }} {{ player.name }}</strong><small class="block text-slate-500">{{ positions[player.position] }}</small></td><td class="px-4 py-3"><select v-if="selection(player.id)" v-model="selection(player.id)!.role" class="form-input min-w-36"><option value="starter" :disabled="startersCount >= 11 && selection(player.id)?.role !== 'starter'">Titular</option><option value="substitute">Suplente</option></select><span v-else class="text-slate-400">—</span></td><td class="px-4 py-3"><button v-if="selection(player.id)" type="button" class="rounded-lg border px-3 py-2 text-xs font-semibold" :class="selection(player.id)?.is_captain ? 'border-league-600 bg-league-50 text-league-900' : 'border-slate-300'" @click="setCaptain(player.id)">{{ selection(player.id)?.is_captain ? 'Capitán' : 'Designar' }}</button><span v-else class="text-slate-400">—</span></td></tr></tbody></table></div>
                <div class="mt-5"><label class="form-label">Motivo del guardado</label><input v-model="lineupForm.reason" class="form-input" maxlength="500" required><FormError :message="lineupForm.errors.reason" /></div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="closeEditor">Cancelar</button><button class="btn-primary" :disabled="lineupForm.processing || !selectedCount">Guardar borrador</button></div>
            </form>
        </div>
    </AppLayout>
</template>
