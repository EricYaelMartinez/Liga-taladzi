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
const lineupForm = useForm<{ players: Array<{ player_registration_id: number; role: 'starter' | 'substitute'; is_captain: boolean }>; reason: string }>({ players: [], reason: '' });

const positions: Record<string, string> = { goalkeeper: 'Portero', defender: 'Defensa', midfielder: 'Mediocampista', forward: 'Delantero' };
const matchStatuses: Record<string, string> = { scheduled: 'Programado', in_progress: 'En curso', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Aplazado' };
const selectedCount = computed(() => lineupForm.players.length);
const startersCount = computed(() => lineupForm.players.filter((item) => item.role === 'starter').length);
const substitutesCount = computed(() => lineupForm.players.filter((item) => item.role === 'substitute').length);
const validStarterCount = computed(() => startersCount.value >= 8 && startersCount.value <= 11);

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
</script>

<template>
    <Head title="Alineaciones" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 11</p>
                    <h1 class="mt-1 text-3xl font-semibold">Alineaciones</h1>
                    <p class="mt-2 max-w-3xl text-slate-600">Captura opcional de titulares y suplentes. Una vez enviada, la alineación queda cerrada y se conserva como parte del historial del partido.</p>
                </div>
                <div class="stat-card min-w-40"><span class="stat-label">Partidos visibles</span><span class="stat-value">{{ matches.total }}</span></div>
            </div>

            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Importante:</strong> guarda primero el borrador y revísalo. Para enviarlo debe contener entre 8 y 11 titulares. El envío es definitivo y no existe reapertura.</div>

            <div v-if="!matches.data.length" class="card mt-7 p-12 text-center text-slate-600">No hay partidos publicados disponibles para tu rol.</div>
            <div v-else class="mt-7 space-y-6">
                <article v-for="match in matches.data" :key="match.id" class="card overflow-hidden">
                    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50 p-5 sm:flex-row sm:items-center">
                        <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold">{{ match.matchday.name }}</h2><span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">{{ matchStatuses[match.status] }}</span></div><p class="mt-1 text-sm text-slate-600">{{ match.competition.name }} · {{ localDateTime(match.scheduled_at) }}</p><p v-if="match.field" class="mt-1 text-xs text-slate-500">{{ match.field.venue.name }} · {{ match.field.name }}</p></div>
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center"><strong>{{ match.teams[0]?.name }}</strong><span class="rounded-lg bg-slate-200 px-3 py-1 text-xs font-bold">VS</span><strong>{{ match.teams[1]?.name }}</strong></div>
                    </header>
                    <div class="grid divide-y divide-slate-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                        <section v-for="team in match.teams" :key="team.participation_id" class="p-5">
                            <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold">{{ team.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ team.lineup ? (team.lineup.status === 'submitted' ? 'Alineación enviada' : 'Borrador guardado') : 'Sin alineación enviada' }}</p></div><span v-if="team.lineup" class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="team.lineup.status === 'submitted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">{{ team.lineup.status === 'submitted' ? 'Cerrada' : 'Borrador' }}</span></div>
                            <template v-if="team.lineup">
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Titulares</h4><ul class="mt-2 space-y-1.5 text-sm"><li v-for="player in lineupPlayers(team, 'starter')" :key="player.player_registration_id" class="rounded-lg bg-slate-50 px-3 py-2"><strong>#{{ player.jersey_number }} {{ player.name }}</strong><span v-if="player.is_captain" class="ml-1 text-xs text-league-700">(C)</span><small class="block text-slate-500">{{ positions[player.position] }}</small></li><li v-if="!lineupPlayers(team, 'starter').length" class="text-slate-400">Sin titulares</li></ul></div>
                                    <div><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Suplentes</h4><ul class="mt-2 space-y-1.5 text-sm"><li v-for="player in lineupPlayers(team, 'substitute')" :key="player.player_registration_id" class="rounded-lg bg-slate-50 px-3 py-2"><strong>#{{ player.jersey_number }} {{ player.name }}</strong><span v-if="player.is_captain" class="ml-1 text-xs text-league-700">(C)</span><small class="block text-slate-500">{{ positions[player.position] }}</small></li><li v-if="!lineupPlayers(team, 'substitute').length" class="text-slate-400">Sin suplentes</li></ul></div>
                                </div>
                                <p v-if="team.lineup.submitted_at" class="mt-3 text-xs text-slate-500">Enviada {{ localDateTime(team.lineup.submitted_at) }}<span v-if="team.lineup.submitted_by"> por {{ team.lineup.submitted_by.name }}</span></p>
                            </template>
                            <div v-if="team.editable" class="mt-5 flex flex-wrap items-center gap-2"><button class="btn-secondary" @click="openEditor(match, team)">{{ team.lineup ? 'Editar borrador' : 'Capturar alineación' }}</button><button v-if="team.lineup?.status === 'draft'" class="btn-primary" :disabled="!canSubmit(team)" :title="canSubmit(team) ? 'Enviar alineación definitiva' : 'Se requieren entre 8 y 11 titulares'" @click="submit(match, team)">Enviar y cerrar</button><span v-if="team.lineup?.status === 'draft' && !canSubmit(team)" class="text-xs font-medium text-amber-700">Se requieren entre 8 y 11 titulares.</span></div>
                        </section>
                    </div>
                </article>
            </div>

            <nav v-if="matches.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-2"><Link v-for="link in matches.links" :key="link.label" :href="link.url ?? ''" class="rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'border-league-600 bg-league-600 text-white' : link.url ? 'border-slate-300 bg-white text-slate-700' : 'cursor-not-allowed border-slate-200 text-slate-400'" v-html="link.label" /></nav>
        </section>

        <div v-if="editMatch && editTeam" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeEditor">
            <form class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="save">
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
