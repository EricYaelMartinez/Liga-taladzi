<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Competition = { id: number; name: string; format: string; status: string; tournament: { name: string; season: { name: string; status: string } }; division: { name: string }; category: { name: string } };
type Standing = { position: number; team_participation_id: number; team: string; short_name: string; played: number; won: number; drawn: number; lost: number; goals_for: number; goals_against: number; goal_difference: number; points: number; points_deducted: number; fair_play_points: number };
type Result = { match_id: number; matchday: string | null; scheduled_at: string | null; home_team: string; away_team: string; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; folio: string | null; approved_at: string | null };
type Scorer = { registration_id: number; player: string; team: string; goals: number };
type Discipline = { registration_id: number; player: string; team: string; yellow_cards: number; second_yellows: number; red_cards: number; fair_play_points: number };
type Statistics = { standings: Standing[]; results: Result[]; scorers: Scorer[]; discipline: Discipline[]; summary: { official_matches: number; total_goals: number; average_goals: number; registered_teams: number }; tiebreakers: { criterion: string; priority: number }[] };

const props = defineProps<{ competitions: Competition[]; selectedCompetitionId: number | null; statistics: Statistics | null }>();
const selected = ref<number | null>(props.selectedCompetitionId);
const activeTab = ref<'standings' | 'results' | 'scorers' | 'discipline'>('standings');
const selectedCompetition = computed(() => props.competitions.find((item) => item.id === selected.value) ?? null);
const groupedResults = computed(() => {
    const groups = new Map<string, Result[]>();
    for (const result of props.statistics?.results ?? []) {
        const key = result.matchday ?? 'Sin jornada';
        groups.set(key, [...(groups.get(key) ?? []), result]);
    }
    return [...groups.entries()].map(([matchday, results]) => ({ matchday, results }));
});
const tabs = { standings: 'Tabla de posiciones', results: 'Resultados oficiales', scorers: 'Goleadores', discipline: 'Amonestados y expulsados' } as const;
const tiebreakerLabels: Record<string, string> = { points: 'Puntos', goal_difference: 'Diferencia de goles', goals_for: 'Goles a favor', head_to_head: 'Resultado entre equipos empatados', fair_play: 'Juego limpio', administrative_decision: 'Decisión administrativa', draw_lots: 'Sorteo' };
const formats: Record<string, string> = { round_robin: 'Todos contra todos', double_round_robin: 'Ida y vuelta', knockout: 'Eliminación directa', groups_knockout: 'Grupos y eliminatoria', manual: 'Manual' };

function loadCompetition(): void {
    if (!selected.value) return;
    router.get('/liga/resultados', { competition_id: selected.value }, { preserveState: false, preserveScroll: true });
}
function localDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin horario';
}
</script>

<template>
    <Head title="Resultados y estadísticas" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 13</p><h1 class="mt-1 text-3xl font-semibold">Resultados y estadísticas</h1><p class="mt-2 max-w-3xl text-slate-600">Información oficial calculada exclusivamente a partir de cédulas aprobadas.</p></div>
                <div v-if="competitions.length" class="w-full max-w-xl"><label class="form-label">Competencia</label><select v-model.number="selected" class="form-input" @change="loadCompetition"><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }} · {{ competition.division.name }} / {{ competition.category.name }}</option></select></div>
            </div>

            <div v-if="!competitions.length" class="card mt-7 p-12 text-center text-slate-600">Todavía no existen competencias para consultar.</div>
            <template v-else-if="statistics && selectedCompetition">
                <div class="mt-6 rounded-2xl border border-league-100 bg-white p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold">{{ selectedCompetition.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ selectedCompetition.tournament.season.name }} · {{ selectedCompetition.tournament.name }} · {{ formats[selectedCompetition.format] ?? selectedCompetition.format }}</p></div><span class="rounded-full bg-league-100 px-3 py-1 text-xs font-semibold text-league-900">{{ selectedCompetition.status }}</span></div></div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div class="stat-card"><span class="stat-label">Partidos oficiales</span><span class="stat-value">{{ statistics.summary.official_matches }}</span></div><div class="stat-card"><span class="stat-label">Goles</span><span class="stat-value">{{ statistics.summary.total_goals }}</span></div><div class="stat-card"><span class="stat-label">Promedio por partido</span><span class="stat-value">{{ statistics.summary.average_goals }}</span></div><div class="stat-card"><span class="stat-label">Equipos</span><span class="stat-value">{{ statistics.summary.registered_teams }}</span></div></div>

                <nav class="mt-6 flex gap-2 overflow-x-auto pb-2"><button v-for="(label, key) in tabs" :key="key" type="button" class="whitespace-nowrap rounded-xl border px-4 py-2.5 text-sm font-semibold" :class="activeTab === key ? 'border-league-600 bg-league-600 text-white' : 'border-slate-200 bg-white text-slate-700'" @click="activeTab = key">{{ label }}</button></nav>

                <section v-if="activeTab === 'standings'" class="card mt-4 overflow-hidden">
                    <div class="border-b border-slate-200 p-5"><h2 class="text-xl font-semibold">Tabla de posiciones</h2><p class="mt-1 text-sm text-slate-500">JJ: jugados, JG: ganados, JE: empatados, JP: perdidos, GF: a favor, GC: en contra, DG: diferencia, JL: juego limpio, PD: puntos descontados.</p></div>
                    <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-center">Pos.</th><th class="px-4 py-3 text-left">Equipo</th><th class="px-3 py-3 text-center">JJ</th><th class="px-3 py-3 text-center">JG</th><th class="px-3 py-3 text-center">JE</th><th class="px-3 py-3 text-center">JP</th><th class="px-3 py-3 text-center">GF</th><th class="px-3 py-3 text-center">GC</th><th class="px-3 py-3 text-center">DG</th><th class="px-3 py-3 text-center">JL</th><th class="px-3 py-3 text-center">PD</th><th class="px-4 py-3 text-center">Pts.</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="row in statistics.standings" :key="row.team_participation_id" :class="row.position <= 3 ? 'bg-emerald-50/40' : ''"><td class="px-4 py-3 text-center font-bold">{{ row.position }}</td><td class="px-4 py-3 font-semibold">{{ row.team }}</td><td class="px-3 py-3 text-center">{{ row.played }}</td><td class="px-3 py-3 text-center">{{ row.won }}</td><td class="px-3 py-3 text-center">{{ row.drawn }}</td><td class="px-3 py-3 text-center">{{ row.lost }}</td><td class="px-3 py-3 text-center">{{ row.goals_for }}</td><td class="px-3 py-3 text-center">{{ row.goals_against }}</td><td class="px-3 py-3 text-center" :class="row.goal_difference > 0 ? 'text-emerald-700' : row.goal_difference < 0 ? 'text-red-700' : ''">{{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}</td><td class="px-3 py-3 text-center">{{ row.fair_play_points }}</td><td class="px-3 py-3 text-center text-red-700">{{ row.points_deducted ? `-${row.points_deducted}` : '0' }}</td><td class="px-4 py-3 text-center text-base font-bold text-league-700">{{ row.points }}</td></tr></tbody></table></div>
                    <div class="border-t border-slate-200 bg-slate-50 px-5 py-4 text-xs text-slate-600"><strong>Orden de desempate:</strong> {{ statistics.tiebreakers.map((item) => tiebreakerLabels[item.criterion] ?? item.criterion).join(' → ') }}<p v-if="statistics.tiebreakers.some((item) => ['administrative_decision', 'draw_lots'].includes(item.criterion))" class="mt-1">Las decisiones administrativas y los sorteos no se ejecutan automáticamente; los empates pendientes conservan un orden alfabético provisional.</p></div>
                </section>

                <section v-if="activeTab === 'results'" class="mt-4 space-y-5"><div v-if="!groupedResults.length" class="card p-10 text-center text-slate-500">Todavía no hay cédulas aprobadas en esta competencia.</div><article v-for="group in groupedResults" :key="group.matchday" class="card overflow-hidden"><header class="border-b border-slate-200 bg-slate-50 px-5 py-4"><h2 class="font-semibold">{{ group.matchday }}</h2></header><div class="divide-y divide-slate-200"><div v-for="result in group.results" :key="result.match_id" class="grid gap-3 p-5 md:grid-cols-[1fr_auto_1fr] md:items-center"><div class="text-center md:text-right"><strong>{{ result.home_team }}</strong><span v-if="result.home_no_show" class="ml-2 text-xs font-semibold text-red-700">Incomparecencia</span></div><div class="text-center"><div class="inline-flex rounded-xl bg-slate-900 px-5 py-2 text-xl font-bold text-white">{{ result.home_score }} – {{ result.away_score }}</div><p class="mt-1 text-xs text-slate-500">{{ localDate(result.scheduled_at) }} · {{ result.folio }}</p></div><div class="text-center md:text-left"><strong>{{ result.away_team }}</strong><span v-if="result.away_no_show" class="ml-2 text-xs font-semibold text-red-700">Incomparecencia</span></div></div></div></article></section>

                <section v-if="activeTab === 'scorers'" class="card mt-4 overflow-hidden"><div class="border-b border-slate-200 p-5"><h2 class="text-xl font-semibold">Tabla de goleadores</h2><p class="mt-1 text-sm text-slate-500">Los autogoles no se acreditan como goles individuales.</p></div><div v-if="!statistics.scorers.length" class="p-10 text-center text-slate-500">Todavía no hay goleadores registrados en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3 text-center">Pos.</th><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-5 py-3 text-center">Goles</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="(player, index) in statistics.scorers" :key="player.registration_id"><td class="px-5 py-4 text-center font-bold">{{ index + 1 }}</td><td class="px-5 py-4 font-semibold">{{ player.player }}</td><td class="px-5 py-4">{{ player.team }}</td><td class="px-5 py-4 text-center text-lg font-bold text-league-700">{{ player.goals }}</td></tr></tbody></table></div></section>

                <section v-if="activeTab === 'discipline'" class="card mt-4 overflow-hidden"><div class="border-b border-slate-200 p-5"><h2 class="text-xl font-semibold">Amonestados y expulsados</h2><p class="mt-1 text-sm text-slate-500">Esta tabla es informativa. El cumplimiento y arrastre de sanciones se administrará en el módulo disciplinario.</p></div><div v-if="!statistics.discipline.length" class="p-10 text-center text-slate-500">No hay tarjetas registradas en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-4 py-3 text-center">Amarillas</th><th class="px-4 py-3 text-center">2.ª amarilla</th><th class="px-4 py-3 text-center">Rojas</th><th class="px-4 py-3 text-center">Puntos JL</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="player in statistics.discipline" :key="player.registration_id"><td class="px-5 py-4 font-semibold">{{ player.player }}</td><td class="px-5 py-4">{{ player.team }}</td><td class="px-4 py-4 text-center"><span class="inline-flex min-w-7 justify-center rounded bg-yellow-300 px-2 py-1 font-bold">{{ player.yellow_cards }}</span></td><td class="px-4 py-4 text-center"><span class="inline-flex min-w-7 justify-center rounded bg-amber-500 px-2 py-1 font-bold text-white">{{ player.second_yellows }}</span></td><td class="px-4 py-4 text-center"><span class="inline-flex min-w-7 justify-center rounded bg-red-600 px-2 py-1 font-bold text-white">{{ player.red_cards }}</span></td><td class="px-4 py-4 text-center font-semibold">{{ player.fair_play_points }}</td></tr></tbody></table></div></section>
            </template>
        </section>
    </AppLayout>
</template>
