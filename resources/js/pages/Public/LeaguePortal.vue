<script setup lang="ts">
import type { AppPageProps } from '../../types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { CSSProperties } from 'vue';

type League = { name: string; slug: string; logo_url: string | null; primary_color: string; secondary_color: string };
type Competition = { id: number; name: string; status: string; season: { id: number; name: string; status: string }; tournament: string; division: { id: number; name: string }; category: string };
type PublicMatch = { id: number; matchday: { id: number; name: string; number: number }; home_team: string; home_crest_url: string | null; away_team: string; away_crest_url: string | null; scheduled_at: string | null; status: string; field: { name: string; venue: string | null; address: string | null } | null; public_notes: string | null; score: { home: number; away: number } | null };
type Standing = { position: number; team_participation_id: number; team: string; played: number; won: number; drawn: number; lost: number; goals_for: number; goals_against: number; goal_difference: number; points: number; points_deducted: number };
type Result = { match_id: number; matchday: string | null; scheduled_at: string | null; home_team: string; away_team: string; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean };
type Scorer = { registration_id: number; player: string; team: string; goals: number };
type Discipline = { registration_id: number; player: string; team: string; yellow_cards: number; second_yellows: number; red_cards: number };
type Statistics = { standings: Standing[]; results: Result[]; scorers: Scorer[]; discipline: Discipline[]; summary: { official_matches: number; total_goals: number; average_goals: number; registered_teams: number } };
type PublicSanction = { id: number; subject_type: string; subject_name: string; team: string | null; kind: string; matches_remaining: number | null; issued_at: string | null };

const props = defineProps<{ league: League; competitions: Competition[]; selectedCompetitionId: number | null; schedule: PublicMatch[]; statistics: Statistics | null; activeSanctions: PublicSanction[] }>();
const page = usePage<AppPageProps>();
const platform = computed(() => page.props.brand);
const activeTab = ref<'schedule' | 'results' | 'standings' | 'scorers' | 'discipline'>('schedule');
const selectedMatch = ref<PublicMatch | null>(null);
const selectedCompetitionId = ref<number | null>(props.selectedCompetitionId);
const selectedCompetition = computed(() => props.competitions.find((item) => item.id === selectedCompetitionId.value) ?? null);
const selectedSeasonId = ref<number | null>(selectedCompetition.value?.season.id ?? null);
const selectedDivisionId = ref<number | null>(selectedCompetition.value?.division.id ?? null);
const seasons = computed(() => [...new Map(props.competitions.map((item) => [item.season.id, item.season])).values()]);
const divisions = computed(() => [...new Map(props.competitions.filter((item) => !selectedSeasonId.value || item.season.id === selectedSeasonId.value).map((item) => [item.division.id, item.division])).values()]);
const filteredCompetitions = computed(() => props.competitions.filter((item) => (!selectedSeasonId.value || item.season.id === selectedSeasonId.value) && (!selectedDivisionId.value || item.division.id === selectedDivisionId.value)));
const groupedSchedule = computed(() => groupBy(props.schedule, (match) => `${match.matchday.number}|${match.matchday.name}`));
const groupedResults = computed(() => groupBy(props.statistics?.results ?? [], (result) => result.matchday ?? 'Sin jornada'));
const teamCrests = computed(() => {
    const crests = new Map<string, string | null>();
    for (const match of props.schedule) {
        crests.set(match.home_team, match.home_crest_url);
        crests.set(match.away_team, match.away_crest_url);
    }
    return crests;
});
const tabs = { schedule: 'Calendario', results: 'Resultados', standings: 'Posiciones', scorers: 'Goleadores', discipline: 'Disciplina' } as const;
const statusLabels: Record<string, string> = { scheduled: 'Programado', in_progress: 'En juego', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Pospuesto', cancelled: 'Cancelado' };
const brandStyle = computed<CSSProperties>(() => ({
    '--color-league-50': `color-mix(in srgb, ${props.league.primary_color} 8%, white)`,
    '--color-league-100': `color-mix(in srgb, ${props.league.primary_color} 16%, white)`,
    '--color-league-600': props.league.primary_color,
    '--color-league-700': props.league.primary_color,
    '--color-league-900': `color-mix(in srgb, ${props.league.primary_color} 76%, black)`,
    '--color-gold-400': props.league.secondary_color,
    '--color-gold-500': props.league.secondary_color,
}) as CSSProperties);
const heroStyle = computed<CSSProperties>(() => ({
    backgroundImage: `linear-gradient(105deg, color-mix(in srgb, ${props.league.primary_color} 82%, #07152b) 0%, color-mix(in srgb, ${props.league.primary_color} 68%, transparent) 58%, color-mix(in srgb, ${props.league.secondary_color} 55%, transparent) 100%), linear-gradient(rgba(4, 15, 30, .34), rgba(4, 15, 30, .72)), url('/images/public/league-stadium.jpg')`,
}));

function groupBy<T>(items: T[], key: (item: T) => string): { name: string; items: T[] }[] {
    const groups = new Map<string, T[]>();
    for (const item of items) groups.set(key(item), [...(groups.get(key(item)) ?? []), item]);
    return [...groups.entries()].map(([raw, grouped]) => ({ name: raw.includes('|') ? raw.split('|')[1] : raw, items: grouped }));
}
function dateTime(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Horario por confirmar'; }
function dateOnly(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : 'Fecha por confirmar'; }
function timeOnly(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : ''; }
function crestFor(team: string): string | null { return teamCrests.value.get(team) ?? null; }
function initials(team: string): string { return team.split(/\s+/).slice(0, 2).map((word) => word.slice(0, 1)).join('').toUpperCase(); }
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
    <div class="portal-surface min-h-screen bg-slate-100 text-slate-900" :style="brandStyle">
        <header class="relative min-h-[360px] overflow-hidden bg-cover bg-center text-white shadow-xl" :style="heroStyle">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_105%,rgba(255,255,255,0.18),transparent_42%)]"></div>
            <div class="relative mx-auto max-w-6xl px-5 pb-24 pt-4 sm:px-8">
                <div class="grid grid-cols-[1fr_auto] items-center gap-3 sm:grid-cols-3">
                    <Link href="/" class="flex min-w-0 items-center gap-2 text-xs font-semibold text-white/90 transition hover:text-white sm:text-sm">
                        <img :src="platform.logoUrl" :alt="`Logotipo de ${platform.name}`" class="h-9 w-9 shrink-0 object-contain drop-shadow-lg">
                        <span class="truncate">{{ platform.name }} · Ligas administradas</span>
                    </Link>
                    <div class="order-3 col-span-2 mx-auto inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/15 px-4 py-2 text-xs font-bold shadow-lg backdrop-blur-md sm:order-none sm:col-span-1">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M12 2 4 5v6c0 5.1 3.4 9.4 8 11 4.6-1.6 8-5.9 8-11V5l-8-3Zm-1.1 13.4-3.2-3.2 1.4-1.4 1.8 1.8 4.2-4.2 1.4 1.4-5.6 5.6Z"/></svg>
                        Portal público oficial
                    </div>
                    <Link href="/iniciar-sesion" class="justify-self-end rounded-xl border border-white/30 bg-white/15 px-4 py-2 text-xs font-bold shadow-lg backdrop-blur-md transition hover:bg-white/25 sm:text-sm">Acceso privado</Link>
                </div>

                <div class="mx-auto mt-8 flex max-w-4xl flex-col items-center text-center">
                    <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-white shadow-2xl ring-4 ring-white/20 sm:h-28 sm:w-28">
                        <img v-if="league.logo_url" :src="league.logo_url" :alt="`Logotipo de ${league.name}`" class="h-full w-full object-contain p-1.5">
                        <span v-else class="text-4xl font-black text-league-700">{{ league.name.slice(0, 1) }}</span>
                    </div>
                    <h1 class="mt-5 text-3xl font-black uppercase leading-tight tracking-tight drop-shadow-lg sm:text-4xl">{{ league.name }}</h1>
                    <p class="mt-2 text-sm font-medium text-white/85 drop-shadow sm:text-base">Información deportiva actualizada a partir de registros oficiales.</p>
                </div>
            </div>
        </header>

        <main class="relative mx-auto -mt-16 max-w-5xl px-4 pb-12 sm:px-6">
            <section v-if="competitions.length" class="rounded-2xl border border-white/60 bg-white/90 p-4 shadow-2xl backdrop-blur-xl sm:p-5">
                <div class="grid gap-4 md:grid-cols-3">
                    <div><label class="mb-1.5 flex items-center gap-2 text-xs font-bold text-slate-600"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>Temporada</label><select v-model.number="selectedSeasonId" class="form-input bg-white" @change="changeSeason"><option v-for="season in seasons" :key="season.id" :value="season.id">{{ season.name }}</option></select></div>
                    <div><label class="mb-1.5 flex items-center gap-2 text-xs font-bold text-slate-600"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16M6 16V8m4 8V5m4 11V9m4 7V3"/></svg>División</label><select v-model.number="selectedDivisionId" class="form-input bg-white" @change="changeDivision"><option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option></select></div>
                    <div><label class="mb-1.5 flex items-center gap-2 text-xs font-bold text-slate-600"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 4h8v3a4 4 0 0 1-8 0V4Z"/><path d="M6 5H4v1a4 4 0 0 0 4 4m10-5h2v1a4 4 0 0 1-4 4M12 11v5m-4 4h8m-6-4h4"/></svg>Competencia</label><select v-model.number="selectedCompetitionId" class="form-input bg-white" @change="loadCompetition"><option v-for="competition in filteredCompetitions" :key="competition.id" :value="competition.id">{{ competition.name }} · {{ competition.category }}</option></select></div>
                </div>
            </section>

            <div v-if="!competitions.length" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-600 shadow-xl">Esta liga todavía no tiene competencias públicas activas o finalizadas.</div>
            <template v-else-if="selectedCompetition && statistics">
                <section class="mt-4 flex flex-col justify-between gap-3 rounded-2xl border border-league-100 bg-white/95 p-5 shadow-lg sm:flex-row sm:items-center"><div><h2 class="text-xl font-black text-league-900">{{ selectedCompetition.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ selectedCompetition.season.name }} · {{ selectedCompetition.tournament }} · {{ selectedCompetition.division.name }} / {{ selectedCompetition.category }}</p></div><span class="w-fit rounded-full border border-league-100 bg-league-50 px-3 py-1 text-xs font-black uppercase text-league-700">{{ selectedCompetition.status === 'active' ? 'En curso' : 'Finalizada' }}</span></section>

                <section class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="flex min-h-24 items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-md"><div><span class="text-xs font-bold text-slate-500">Partidos oficiales</span><strong class="mt-1 block text-3xl font-black">{{ statistics.summary.official_matches }}</strong></div><svg viewBox="0 0 24 24" class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></div>
                    <div class="flex min-h-24 items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-md"><div><span class="text-xs font-bold text-slate-500">Goles</span><strong class="mt-1 block text-3xl font-black">{{ statistics.summary.total_goals }}</strong></div><svg viewBox="0 0 24 24" class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="m9 9 3-2 3 2-1 4h-4l-1-4Zm-3-3 3 3m9-3-3 3m-6 8-2 2m8-2 2 2"/></svg></div>
                    <div class="flex min-h-24 items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-md"><div><span class="text-xs font-bold text-slate-500">Promedio</span><strong class="mt-1 block text-3xl font-black">{{ statistics.summary.average_goals }}</strong></div><svg viewBox="0 0 24 24" class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 4h8v3a4 4 0 0 1-8 0V4Z"/><path d="M6 5H4v1a4 4 0 0 0 4 4m10-5h2v1a4 4 0 0 1-4 4M12 11v5m-4 4h8"/></svg></div>
                    <div class="flex min-h-24 items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-md"><div><span class="text-xs font-bold text-slate-500">Equipos</span><strong class="mt-1 block text-3xl font-black">{{ statistics.summary.registered_teams }}</strong></div><svg viewBox="0 0 24 24" class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-6a5 5 0 0 1 5 5v1"/></svg></div>
                </section>

                <nav class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Secciones públicas"><button v-for="(label, key) in tabs" :key="key" type="button" class="whitespace-nowrap rounded-xl border px-4 py-2.5 text-sm font-bold shadow-sm transition" :class="activeTab === key ? 'border-league-600 bg-league-600 text-white shadow-md' : 'border-slate-200 bg-white text-slate-700 hover:border-league-100 hover:bg-league-50'" @click="activeTab = key">{{ label }}</button></nav>

                <section v-if="activeTab === 'schedule'" class="mt-3 space-y-5">
                    <div v-if="!groupedSchedule.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-md">Todavía no hay jornadas publicadas.</div>
                    <article v-for="group in groupedSchedule" :key="group.name">
                        <h3 class="mb-2 text-lg font-black text-slate-800">{{ group.name }}</h3>
                        <div class="space-y-3">
                            <div v-for="match in group.items" :key="match.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
                                <div class="grid items-center gap-4 md:grid-cols-[1fr_140px_1fr]">
                                    <div class="flex items-center justify-center gap-3 md:justify-end"><strong class="text-right text-sm sm:text-base">{{ match.home_team }}</strong><div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50"><img v-if="match.home_crest_url" :src="match.home_crest_url" alt="" class="h-full w-full object-contain p-1"><span v-else class="text-xs font-black text-slate-500">{{ initials(match.home_team) }}</span></div></div>
                                    <div class="text-center"><div class="inline-flex min-w-20 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xl font-black text-white shadow-lg"><template v-if="match.score">{{ match.score.home }} – {{ match.score.away }}</template><template v-else>VS</template></div><p class="mt-2 text-xs font-bold text-slate-500">{{ statusLabels[match.status] ?? match.status }}</p></div>
                                    <div class="flex items-center justify-center gap-3 md:justify-start"><div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50"><img v-if="match.away_crest_url" :src="match.away_crest_url" alt="" class="h-full w-full object-contain p-1"><span v-else class="text-xs font-black text-slate-500">{{ initials(match.away_team) }}</span></div><strong class="text-sm sm:text-base">{{ match.away_team }}</strong></div>
                                </div>
                                <div class="mt-4 border-t border-slate-100 pt-3 text-center text-xs text-slate-500"><p class="font-semibold text-slate-700">{{ dateOnly(match.scheduled_at) }}<span v-if="timeOnly(match.scheduled_at)"> · {{ timeOnly(match.scheduled_at) }}</span></p><p v-if="match.field" class="mt-1">{{ match.field.venue }} · {{ match.field.name }}<span v-if="match.field.address"> · {{ match.field.address }}</span></p><p v-if="match.public_notes" class="mt-1 font-medium text-slate-600">{{ match.public_notes }}</p><button type="button" class="mt-3 rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-bold text-slate-700 transition hover:border-league-600 hover:text-league-700" @click="selectedMatch = match">Detalles del partido</button></div>
                            </div>
                        </div>
                    </article>
                </section>

                <section v-if="activeTab === 'results'" class="mt-3 space-y-5">
                    <div v-if="!groupedResults.length" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-md">Todavía no hay resultados oficiales.</div>
                    <article v-for="group in groupedResults" :key="group.name"><h3 class="mb-2 text-lg font-black text-slate-800">{{ group.name }}</h3><div class="space-y-3"><div v-for="result in group.items" :key="result.match_id" class="grid items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-md md:grid-cols-[1fr_140px_1fr]"><div class="flex items-center justify-center gap-3 md:justify-end"><strong class="text-right">{{ result.home_team }}</strong><img v-if="crestFor(result.home_team)" :src="crestFor(result.home_team)!" alt="" class="h-11 w-11 rounded-full border border-slate-200 object-contain p-1"></div><div class="text-center"><div class="inline-flex rounded-xl bg-slate-900 px-5 py-2 text-xl font-black text-white">{{ result.home_score }} – {{ result.away_score }}</div><p class="mt-2 text-xs text-slate-500">{{ dateTime(result.scheduled_at) }}</p></div><div class="flex items-center justify-center gap-3 md:justify-start"><img v-if="crestFor(result.away_team)" :src="crestFor(result.away_team)!" alt="" class="h-11 w-11 rounded-full border border-slate-200 object-contain p-1"><strong>{{ result.away_team }}</strong></div></div></div></article>
                </section>

                <section v-if="activeTab === 'standings'" class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-black">Tabla de posiciones</h3><p class="mt-1 text-sm text-slate-500">JJ jugados · JG ganados · JE empatados · JP perdidos · GF a favor · GC en contra · DG diferencia.</p></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Pos.</th><th class="px-4 py-3 text-left">Equipo</th><th class="px-3 py-3">JJ</th><th class="px-3 py-3">JG</th><th class="px-3 py-3">JE</th><th class="px-3 py-3">JP</th><th class="px-3 py-3">GF</th><th class="px-3 py-3">GC</th><th class="px-3 py-3">DG</th><th class="px-4 py-3">Pts.</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="row in statistics.standings" :key="row.team_participation_id" :class="row.position <= 3 ? 'bg-league-50' : ''"><td class="px-4 py-3 text-center font-black">{{ row.position }}</td><td class="px-4 py-3 font-bold">{{ row.team }}</td><td class="px-3 py-3 text-center">{{ row.played }}</td><td class="px-3 py-3 text-center">{{ row.won }}</td><td class="px-3 py-3 text-center">{{ row.drawn }}</td><td class="px-3 py-3 text-center">{{ row.lost }}</td><td class="px-3 py-3 text-center">{{ row.goals_for }}</td><td class="px-3 py-3 text-center">{{ row.goals_against }}</td><td class="px-3 py-3 text-center">{{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}</td><td class="px-4 py-3 text-center text-lg font-black text-league-700">{{ row.points }}</td></tr></tbody></table></div></section>

                <section v-if="activeTab === 'scorers'" class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-black">Goleadores</h3></div><div v-if="!statistics.scorers.length" class="p-10 text-center text-slate-500">Todavía no hay goles individuales registrados.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Pos.</th><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-5 py-3">Goles</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="(scorer, index) in statistics.scorers" :key="scorer.registration_id"><td class="px-5 py-4 text-center font-black">{{ index + 1 }}</td><td class="px-5 py-4 font-bold">{{ scorer.player }}</td><td class="px-5 py-4">{{ scorer.team }}</td><td class="px-5 py-4 text-center text-lg font-black text-league-700">{{ scorer.goals }}</td></tr></tbody></table></div></section>

                <section v-if="activeTab === 'discipline'" class="mt-3 space-y-5"><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-black">Tarjetas registradas</h3></div><div v-if="!statistics.discipline.length" class="p-10 text-center text-slate-500">No hay tarjetas en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3 text-left">Jugador</th><th class="px-5 py-3 text-left">Equipo</th><th class="px-4 py-3">Amarillas</th><th class="px-4 py-3">2.ª amarilla</th><th class="px-4 py-3">Rojas</th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="player in statistics.discipline" :key="player.registration_id"><td class="px-5 py-4 font-bold">{{ player.player }}</td><td class="px-5 py-4">{{ player.team }}</td><td class="px-4 py-4 text-center">{{ player.yellow_cards }}</td><td class="px-4 py-4 text-center">{{ player.second_yellows }}</td><td class="px-4 py-4 text-center font-black text-red-700">{{ player.red_cards }}</td></tr></tbody></table></div></article><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md"><div class="border-b border-slate-200 p-5"><h3 class="text-xl font-black">Sanciones deportivas vigentes</h3><p class="mt-1 text-sm text-slate-500">Solo se muestran datos deportivos necesarios; los expedientes y motivos son privados.</p></div><div v-if="!activeSanctions.length" class="p-10 text-center text-slate-500">No hay suspensiones o descalificaciones vigentes.</div><div v-else class="divide-y divide-slate-200"><div v-for="sanction in activeSanctions" :key="sanction.id" class="flex flex-col justify-between gap-2 p-5 sm:flex-row sm:items-center"><div><strong>{{ sanction.subject_name }}</strong><p class="text-sm text-slate-500">{{ sanction.team ?? (sanction.subject_type === 'team' ? 'Equipo' : 'Sin equipo') }}</p></div><span class="w-fit rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-800">{{ sanction.kind === 'suspension' ? `${sanction.matches_remaining} partido(s) pendiente(s)` : 'Descalificación vigente' }}</span></div></div></article></section>
            </template>
        </main>
        <footer class="border-t border-slate-200 bg-white/90 px-5 py-6 text-center text-sm font-medium text-slate-500">{{ platform.name }} · Gestión deportiva · {{ league.name }}</footer>

        <Teleport to="body">
            <div v-if="selectedMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" @click.self="selectedMatch = null">
                <div class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between bg-slate-900 px-6 py-4 text-white"><div><p class="text-xs font-bold uppercase tracking-widest text-white/60">{{ selectedMatch.matchday.name }}</p><h2 class="mt-1 text-xl font-black">Detalles del partido</h2></div><button type="button" class="rounded-full p-2 text-white/70 hover:bg-white/10 hover:text-white" aria-label="Cerrar" @click="selectedMatch = null">✕</button></div>
                    <div class="p-6">
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center"><div><img v-if="selectedMatch.home_crest_url" :src="selectedMatch.home_crest_url" alt="" class="mx-auto h-16 w-16 object-contain"><strong class="mt-2 block text-sm">{{ selectedMatch.home_team }}</strong></div><div class="rounded-xl bg-slate-900 px-4 py-2 text-xl font-black text-white"><template v-if="selectedMatch.score">{{ selectedMatch.score.home }} – {{ selectedMatch.score.away }}</template><template v-else>VS</template></div><div><img v-if="selectedMatch.away_crest_url" :src="selectedMatch.away_crest_url" alt="" class="mx-auto h-16 w-16 object-contain"><strong class="mt-2 block text-sm">{{ selectedMatch.away_team }}</strong></div></div>
                        <dl class="mt-6 space-y-3 rounded-2xl bg-slate-50 p-4 text-sm"><div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Estado</dt><dd class="font-bold">{{ statusLabels[selectedMatch.status] ?? selectedMatch.status }}</dd></div><div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Fecha y hora</dt><dd class="text-right font-semibold">{{ dateTime(selectedMatch.scheduled_at) }}</dd></div><div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Sede</dt><dd class="text-right font-semibold">{{ selectedMatch.field ? `${selectedMatch.field.venue} · ${selectedMatch.field.name}` : 'Por confirmar' }}</dd></div><div v-if="selectedMatch.field?.address" class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Dirección</dt><dd class="text-right font-semibold">{{ selectedMatch.field.address }}</dd></div><div v-if="selectedMatch.public_notes" class="border-t border-slate-200 pt-3"><dt class="font-bold text-slate-500">Información adicional</dt><dd class="mt-1 font-semibold">{{ selectedMatch.public_notes }}</dd></div></dl>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.portal-surface {
    background-color: #f3f6f8;
    background-image:
        radial-gradient(circle at 0 38%, color-mix(in srgb, var(--color-league-600) 7%, transparent) 0, transparent 25rem),
        radial-gradient(circle at 100% 62%, color-mix(in srgb, var(--color-gold-500) 8%, transparent) 0, transparent 28rem);
}
</style>
