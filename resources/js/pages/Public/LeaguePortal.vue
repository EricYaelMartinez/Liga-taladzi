<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { CSSProperties } from 'vue';

type League = { name: string; slug: string; logo_url: string | null; primary_color: string; secondary_color: string };
type Competition = { id: number; name: string; status: string; season: { id: number; name: string; status: string }; tournament: string; division: { id: number; name: string }; category: string };
type PublicMatch = { id: number; matchday: { id: number; name: string; number: number }; home_team: string; away_team: string; scheduled_at: string | null; status: string; field: { name: string; venue: string | null; address: string | null } | null; public_notes: string | null; score: { home: number; away: number } | null };
type Standing = { position: number; team_participation_id: number; team: string; played: number; won: number; drawn: number; lost: number; goals_for: number; goals_against: number; goal_difference: number; points: number; points_deducted: number };
type Result = { match_id: number; matchday: string | null; scheduled_at: string | null; home_team: string; away_team: string; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean };
type Scorer = { registration_id: number; player: string; team: string; goals: number };
type Discipline = { registration_id: number; player: string; team: string; yellow_cards: number; second_yellows: number; red_cards: number };
type Statistics = { standings: Standing[]; results: Result[]; scorers: Scorer[]; discipline: Discipline[]; summary: { official_matches: number; total_goals: number; average_goals: number; registered_teams: number } };
type PublicSanction = { id: number; subject_type: string; subject_name: string; team: string | null; kind: string; matches_remaining: number | null; issued_at: string | null };

const props = defineProps<{ league: League; competitions: Competition[]; selectedCompetitionId: number | null; schedule: PublicMatch[]; statistics: Statistics | null; activeSanctions: PublicSanction[] }>();
const activeTab = ref<'schedule' | 'results' | 'standings' | 'scorers' | 'discipline'>('schedule');
const selectedCompetitionId = ref<number | null>(props.selectedCompetitionId);
const selectedCompetition = computed(() => props.competitions.find((item) => item.id === selectedCompetitionId.value) ?? null);
const selectedSeasonId = ref<number | null>(selectedCompetition.value?.season.id ?? null);
const selectedDivisionId = ref<number | null>(selectedCompetition.value?.division.id ?? null);
const seasons = computed(() => [...new Map(props.competitions.map((item) => [item.season.id, item.season])).values()]);
const divisions = computed(() => [...new Map(props.competitions.filter((item) => !selectedSeasonId.value || item.season.id === selectedSeasonId.value).map((item) => [item.division.id, item.division])).values()]);
const filteredCompetitions = computed(() => props.competitions.filter((item) => (!selectedSeasonId.value || item.season.id === selectedSeasonId.value) && (!selectedDivisionId.value || item.division.id === selectedDivisionId.value)));
const groupedSchedule = computed(() => groupBy(props.schedule, (match) => `${match.matchday.number}|${match.matchday.name}`));
const groupedResults = computed(() => groupBy(props.statistics?.results ?? [], (result) => result.matchday ?? 'Sin jornada'));
const tabs = { schedule: 'Calendario', results: 'Resultados', standings: 'Posiciones', scorers: 'Goleadores', discipline: 'Disciplina' } as const;
const statusLabels: Record<string, string> = { scheduled: 'Programado', in_progress: 'En juego', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Pospuesto', cancelled: 'Cancelado' };
const brandStyle = computed<CSSProperties>(() => ({
    '--color-league-50': `color-mix(in srgb, ${props.league.primary_color} 8%, white)`,
    '--color-league-100': `color-mix(in srgb, ${props.league.primary_color} 16%, white)`,
    '--color-league-600': props.league.primary_color,
    '--color-league-700': props.league.primary_color,
    '--color-league-900': `color-mix(in srgb, ${props.league.primary_color} 75%, black)`,
    '--color-gold-400': props.league.secondary_color,
    '--color-gold-500': props.league.secondary_color,
}) as CSSProperties);

function groupBy<T>(items: T[], key: (item: T) => string): { name: string; items: T[] }[] {
    const groups = new Map<string, T[]>();
    for (const item of items) groups.set(key(item), [...(groups.get(key(item)) ?? []), item]);
    return [...groups.entries()].map(([raw, grouped]) => ({ name: raw.includes('|') ? raw.split('|')[1] : raw, items: grouped }));
}
function dateTime(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Horario por confirmar'; }
function changeSeason(): void {
    const first = props.competitions.find((item) => item.season.id === selectedSeasonId.value);
    selectedDivisionId.value = first?.division.id ?? null;
    selectedCompetitionId.value = first?.id ?? null;
    loadCompetition();
}
function changeDivision(): void {
    const first = props.competitions.find((item) => item.season.id === selectedSeasonId.value && item.division.id === selectedDivisionId.value);
    selectedCompetitionId.value = first?.id ?? null;
    loadCompetition();
}
function loadCompetition(): void {
    if (!selectedCompetitionId.value) return;
    router.get(`/ligas/${props.league.slug}`, { competition_id: selectedCompetitionId.value }, { preserveState: false, preserveScroll: false });
}
</script>

<template>
    <Head :title="`${league.name} · Portal público`"><meta name="description" :content="`Calendario, resultados y estadísticas oficiales de ${league.name}.`"></Head>
    <div class="min-h-screen bg-slate-100" :style="brandStyle">
        <header class="text-white" style="background: linear-gradient(135deg, var(--color-league-900), var(--color-league-600))">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <Link href="/" class="text-sm font-semibold text-white/80 hover:text-white">← Todas las ligas</Link>
                <Link href="/iniciar-sesion" class="rounded-xl border border-white/30 px-4 py-2 text-sm font-semibold hover:bg-white/10">Acceso privado</Link>
            </div>
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 pb-9 pt-4 sm:flex-row sm:items-center sm:px-8 sm:pb-12">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg"><img v-if="league.logo_url" :src="league.logo_url" alt="" class="h-full w-full object-contain p-1"><span v-else class="text-3xl font-bold text-league-700">{{ league.name.slice(0, 1) }}</span></div>
                <div><p class="text-xs font-bold uppercase tracking-[0.22em] text-gold-400">Portal público oficial</p><h1 class="mt-2 text-3xl font-semibold sm:text-4xl">{{ league.name }}</h1><p class="mt-2 text-white/75">Información deportiva actualizada a partir de registros oficiales.</p></div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-5 py-7 sm:px-8 sm:py-10">
            <section v-if="competitions.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-3">
                    <div><label class="form-label">Temporada</label><select v-model.number="selectedSeasonId" class="form-input" @change="changeSeason"><option v-for="season in seasons" :key="season.id" :value="season.id">{{ season.name }}</option></select></div>
                    <div><label class="form-label">División</label><select v-model.number="selectedDivisionId" class="form-input" @change="changeDivision"><option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option></select></div>
                    <div><label class="form-label">Competencia</label><select v-model.number="selectedCompetitionId" class="form-input" @change="loadCompetition"><option v-for="competition in filteredCompetitions" :key="competition.id" :value="competition.id">{{ competition.name }} · {{ competition.category }}</option></select></div>
                </div>
            </section>

            <div v-if="!competitions.length" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-600">Esta liga todavía no tiene competencias públicas activas o finalizadas.</div>
            <template v-else-if="selectedCompetition && statistics">
                <section class="mt-5 flex flex-col justify-between gap-3 rounded-2xl border border-league-100 bg-league-50 p-5 sm:flex-row sm:items-center"><div><h2 class="text-xl font-semibold text-league-900">{{ selectedCompetition.name }}</h2><p class="mt-1 text-sm text-slate-600">{{ selectedCompetition.season.name }} · {{ selectedCompetition.tournament }} · {{ selectedCompetition.division.name }} / {{ selectedCompetition.category }}</p></div><span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-bold uppercase text-league-700">{{ selectedCompetition.status === 'active' ? 'En curso' : 'Finalizada' }}</span></section>

                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"><div class="stat-card"><span class="stat-label">Partidos oficiales</span><strong class="stat-value">{{ statistics.summary.official_matches }}</strong></div><div class="stat-card"><span class="stat-label">Goles</span><strong class="stat-value">{{ statistics.summary.total_goals }}</strong></div><div class="stat-card"><span class="stat-label">Promedio</span><strong class="stat-value">{{ statistics.summary.average_goals }}</strong></div><div class="stat-card"><span class="stat-label">Equipos</span><strong class="stat-value">{{ statistics.summary.registered_teams }}</strong></div></div>

                <nav class="mt-6 flex gap-2 overflow-x-auto pb-2" aria-label="Secciones públicas"><button v-for="(label, key) in tabs" :key="key" type="button" class="whitespace-nowrap rounded-xl border px-4 py-2.5 text-sm font-semibold" :class="activeTab === key ? 'border-league-600 bg-league-600 text-white' : 'border-slate-200 bg-white text-slate-700'" @click="activeTab = key">{{ label }}</button></nav>

                <section v-if="activeTab === 'schedule'" class="mt-4 space-y-5">
                    <div v-if="!groupedSchedule.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">Todavía no hay jornadas publicadas.</div>
                    <article v-for="group in groupedSchedule" :key="group.name" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><header class="border-b border-slate-200 bg-slate-50 px-5 py-4"><h3 class="font-semibold">{{ group.name }}</h3></header><div class="divide-y divide-slate-200"><div v-for="match in group.items" :key="match.id" class="p-5"><div class="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-center"><strong class="text-center md:text-right">{{ match.home_team }}</strong><div class="text-center"><div v-if="match.score" class="inline-flex rounded-xl bg-slate-900 px-5 py-2 text-xl font-bold text-white">{{ match.score.home }} – {{ match.score.away }}</div><div v-else class="inline-flex rounded-xl bg-league-50 px-4 py-2 text-sm font-bold text-league-900">{{ dateTime(match.scheduled_at) }}</div><p class="mt-1 text-xs font-semibold text-slate-500">{{ statusLabels[match.status] ?? match.status }}</p></div><strong class="text-center md:text-left">{{ match.away_team }}</strong></div><div v-if="match.field || match.public_notes" class="mt-3 text-center text-xs text-slate-500"><p v-if="match.field">{{ match.field.venue }} · {{ match.field.name }}<span v-if="match.field.address"> · {{ match.field.address }}</span></p><p v-if="match.public_notes" class="mt-1 font-medium text-slate-700">{{ match.public_notes }}</p></div></div></div></article>
                </section>

                <section v-if="activeTab === 'results'" class="mt-4 space-y-5"><div v-if="!groupedResults.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">Todavía no hay resultados oficiales.</div><article v-for="group in groupedResults" :key="group.name" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><header class="border-b border-slate-200 bg-slate-50 px-5 py-4"><h3 class="font-semibold">{{ group.name }}</h3></header><div class="divide-y divide-slate-200"><div v-for="result in group.items" :key="result.match_id" class="grid gap-3 p-5 md:grid-cols-[1fr_auto_1fr] md:items-center"><strong class="text-center md:text-right">{{ result.home_team }}</strong><div class="text-center"><div class="inline-flex rounded-xl bg-slate-900 px-5 py-2 text-xl font-bold text-white">{{ result.home_score }} – {{ result.away_score }}</div><p class="mt-1 text-xs text-slate-500">{{ dateTime(result.scheduled_at) }}</p></div><strong class="text-center md:text-left">{{ result.away_team }}</strong></div></div></article></section>

                <section v-if="activeTab === 'standings'" class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-semibold">Tabla de posiciones</h3><p class="mt-1 text-sm text-slate-500">JJ jugados · JG ganados · JE empatados · JP perdidos · GF a favor · GC en contra · DG diferencia.</p></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Pos.</th><th class="px-4 py-3 text-left">Equipo</th><th class="px-3 py-3">JJ</th><th class="px-3 py-3">JG</th><th class="px-3 py-3">JE</th><th class="px-3 py-3">JP</th><th class="px-3 py-3">GF</th><th class="px-3 py-3">GC</th><th class="px-3 py-3">DG</th><th class="px-4 py-3">Pts.</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="row in statistics.standings" :key="row.team_participation_id" :class="row.position <= 3 ? 'bg-emerald-50/40' : ''"><td class="px-4 py-3 text-center font-bold">{{ row.position }}</td><td class="px-4 py-3 font-semibold">{{ row.team }}</td><td class="px-3 py-3 text-center">{{ row.played }}</td><td class="px-3 py-3 text-center">{{ row.won }}</td><td class="px-3 py-3 text-center">{{ row.drawn }}</td><td class="px-3 py-3 text-center">{{ row.lost }}</td><td class="px-3 py-3 text-center">{{ row.goals_for }}</td><td class="px-3 py-3 text-center">{{ row.goals_against }}</td><td class="px-3 py-3 text-center">{{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}</td><td class="px-4 py-3 text-center text-lg font-bold text-league-700">{{ row.points }}</td></tr></tbody></table></div></section>

                <section v-if="activeTab === 'scorers'" class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-semibold">Goleadores</h3></div><div v-if="!statistics.scorers.length" class="p-10 text-center text-slate-500">Todavía no hay goles individuales registrados.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Pos.</th><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-5 py-3">Goles</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="(scorer, index) in statistics.scorers" :key="scorer.registration_id"><td class="px-5 py-4 text-center font-bold">{{ index + 1 }}</td><td class="px-5 py-4 font-semibold">{{ scorer.player }}</td><td class="px-5 py-4">{{ scorer.team }}</td><td class="px-5 py-4 text-center text-lg font-bold text-league-700">{{ scorer.goals }}</td></tr></tbody></table></div></section>

                <section v-if="activeTab === 'discipline'" class="mt-4 space-y-5"><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-semibold">Tarjetas registradas</h3></div><div v-if="!statistics.discipline.length" class="p-10 text-center text-slate-500">No hay tarjetas en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-4 py-3">Amarillas</th><th class="px-4 py-3">2.ª amarilla</th><th class="px-4 py-3">Rojas</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="player in statistics.discipline" :key="player.registration_id"><td class="px-5 py-4 font-semibold">{{ player.player }}</td><td class="px-5 py-4">{{ player.team }}</td><td class="px-4 py-4 text-center">{{ player.yellow_cards }}</td><td class="px-4 py-4 text-center">{{ player.second_yellows }}</td><td class="px-4 py-4 text-center font-bold text-red-700">{{ player.red_cards }}</td></tr></tbody></table></div></article><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-semibold">Sanciones deportivas vigentes</h3><p class="mt-1 text-sm text-slate-500">Solo se muestran datos deportivos necesarios; los expedientes y motivos son privados.</p></div><div v-if="!activeSanctions.length" class="p-10 text-center text-slate-500">No hay suspensiones o descalificaciones vigentes.</div><div v-else class="divide-y divide-slate-200"><div v-for="sanction in activeSanctions" :key="sanction.id" class="flex flex-col justify-between gap-2 p-5 sm:flex-row sm:items-center"><div><strong>{{ sanction.subject_name }}</strong><p class="text-sm text-slate-500">{{ sanction.team ?? (sanction.subject_type === 'team' ? 'Equipo' : 'Sin equipo') }}</p></div><span class="w-fit rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">{{ sanction.kind === 'suspension' ? `${sanction.matches_remaining} partido(s) pendiente(s)` : 'Descalificación vigente' }}</span></div></div></article></section>
            </template>
        </main>
        <footer class="border-t border-slate-200 bg-white px-5 py-6 text-center text-sm text-slate-500">{{ league.name }} · Portal público oficial</footer>
    </div>
</template>
