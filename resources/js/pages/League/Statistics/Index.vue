<script setup lang="ts">
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Competition = { id: number; name: string; format: string; status: string; tournament: { name: string; season: { name: string; status: string } }; division: { name: string }; category: { name: string } };
type Standing = { position: number; team_participation_id: number; team: string; short_name: string; crest_url: string | null; played: number; won: number; drawn: number; lost: number; goals_for: number; goals_against: number; goal_difference: number; points: number; points_deducted: number; fair_play_points: number };
type Result = { match_id: number; matchday: string | null; scheduled_at: string | null; home_team: string; away_team: string; home_crest_url: string | null; away_crest_url: string | null; home_score: number; away_score: number; home_no_show: boolean; away_no_show: boolean; folio: string | null; approved_at: string | null };
type Scorer = { registration_id: number; player: string; team: string; crest_url: string | null; goals: number };
type Discipline = { registration_id: number; player: string; team: string; crest_url: string | null; yellow_cards: number; second_yellows: number; red_cards: number; fair_play_points: number };
type Statistics = { standings: Standing[]; results: Result[]; scorers: Scorer[]; discipline: Discipline[]; summary: { official_matches: number; total_goals: number; average_goals: number; registered_teams: number }; tiebreakers: { criterion: string; priority: number }[] };
type Tab = 'standings' | 'results' | 'scorers' | 'discipline';
const props = defineProps<{ competitions: Competition[]; selectedCompetitionId: number | null; statistics: Statistics | null }>();
const selected = ref<number | null>(props.selectedCompetitionId);
const activeTab = ref<Tab>('standings');
const resultMatchday = ref('all');
const resultTeam = ref('all');
const showTiebreakers = ref(false);
const brokenCrests = ref<string[]>([]);
const tabs: { key: Tab; label: string; icon: string }[] = [
    { key: 'standings', label: 'Tabla de posiciones', icon: 'chart' },
    { key: 'results', label: 'Resultados oficiales', icon: 'calendar' },
    { key: 'scorers', label: 'Goleadores', icon: 'football' },
    { key: 'discipline', label: 'Amonestados y expulsados', icon: 'file' },
];
const selectedCompetition = computed(() => props.competitions.find((item) => item.id === selected.value) ?? null);
const stats = computed(() => props.statistics);
const matchdays = computed(() => [...new Set((stats.value?.results ?? []).map((result) => result.matchday ?? 'Sin jornada'))]);
const resultTeams = computed(() => [...new Set((stats.value?.results ?? []).flatMap((result) => [result.home_team, result.away_team]))].sort((a, b) => a.localeCompare(b, 'es')));
const filteredResults = computed(() => (stats.value?.results ?? []).filter((result) =>
    (resultMatchday.value === 'all' || (result.matchday ?? 'Sin jornada') === resultMatchday.value) &&
    (resultTeam.value === 'all' || result.home_team === resultTeam.value || result.away_team === resultTeam.value),
));
const groupedResults = computed(() => {
    const groups = new Map<string, Result[]>();
    for (const result of filteredResults.value) {
        const key = result.matchday ?? 'Sin jornada';
        groups.set(key, [...(groups.get(key) ?? []), result]);
    }
    return [...groups.entries()].map(([matchday, results]) => ({ matchday, results }));
});
const maxGoals = computed(() => Math.max(1, ...(stats.value?.scorers ?? []).map((player) => player.goals)));
const tiebreakerLabels: Record<string, string> = { points: 'Puntos', goal_difference: 'Diferencia de goles', goals_for: 'Goles a favor', head_to_head: 'Resultado entre equipos empatados', fair_play: 'Juego limpio', administrative_decision: 'Decisión administrativa', draw_lots: 'Sorteo' };
const tableHeaders = [
    { abbr: 'JJ', title: 'Juegos jugados' }, { abbr: 'JG', title: 'Juegos ganados' }, { abbr: 'JE', title: 'Juegos empatados' },
    { abbr: 'JP', title: 'Juegos perdidos' }, { abbr: 'GF', title: 'Goles a favor' }, { abbr: 'GC', title: 'Goles en contra' },
    { abbr: 'DG', title: 'Diferencia de goles' }, { abbr: 'JL', title: 'Puntos de juego limpio' }, { abbr: 'PD', title: 'Puntos descontados' }, { abbr: 'PTS', title: 'Puntos totales' },
];
function loadCompetition(): void {
    if (!selected.value) return;
    resultMatchday.value = 'all'; resultTeam.value = 'all';
    router.get('/liga/resultados', { competition_id: selected.value }, { preserveState: false, preserveScroll: true });
}
function localDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin horario';
}
function allowCrest(url: string | null): boolean { return !!url && !brokenCrests.value.includes(url); }
function crestFailed(url: string | null): void { if (url && !brokenCrests.value.includes(url)) brokenCrests.value = [...brokenCrests.value, url]; }
function initials(value: string): string { return value.trim().split(/\s+/).slice(0, 2).map((word) => word[0] ?? '').join('').toUpperCase(); }
function standingValue(row: Standing, abbr: string): number {
    const fields: Record<string, keyof Standing> = { JJ: 'played', JG: 'won', JE: 'drawn', JP: 'lost', GF: 'goals_for', GC: 'goals_against', DG: 'goal_difference', JL: 'fair_play_points', PD: 'points_deducted', PTS: 'points' };
    return row[fields[abbr]] as number;
}
</script>

<template>
    <Head title="Resultados y estadísticas" />
    <AppLayout>
        <section class="stats-page space-y-5 pb-8">
            <header class="stats-hero">
                <div class="stats-hero__inner">
                    <div class="min-w-0">
                        <p class="stats-overline">Módulo 13 · <span>Resultados y estadísticas</span></p>
                        <h1>Resultados y estadísticas</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/85 sm:text-base">Información oficial calculada exclusivamente a partir de cédulas aprobadas.</p>
                    </div>
                    <div v-if="competitions.length" class="stats-selector">
                        <label for="stats-competition" class="mb-2 flex items-center gap-2 text-sm font-semibold"><AppIcon name="trophy" :size="19" /> Competencia</label>
                        <select id="stats-competition" v-model.number="selected" class="form-input w-full bg-white font-semibold text-slate-900" @change="loadCompetition">
                            <option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.name }} · {{ competition.division.name }} / {{ competition.category.name }}</option>
                        </select>
                    </div>
                </div>
            </header>

            <div v-if="!competitions.length" class="stats-panel p-12 text-center text-slate-600">Todavía no existen competencias para consultar.</div>
            <template v-else-if="stats && selectedCompetition">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="metric-card"><AppIcon name="calendar" variant="illustrated" :size="52" /><div><p>Partidos oficiales</p><strong>{{ stats.summary.official_matches }}</strong><span class="metric-tag metric-tag--green">Cédulas aprobadas</span></div></article>
                    <article class="metric-card"><AppIcon name="football" variant="illustrated" :size="52" /><div><p>Goles</p><strong>{{ stats.summary.total_goals }}</strong><span class="metric-decoration" aria-hidden="true">▂ ▄ ▅ ▆ █</span></div></article>
                    <article class="metric-card"><AppIcon name="chart" variant="illustrated" :size="52" /><div><p>Promedio por partido</p><strong>{{ Number(stats.summary.average_goals).toFixed(2) }}</strong><span class="metric-tag metric-tag--blue">Goles oficiales por encuentro</span></div></article>
                    <article class="metric-card"><AppIcon name="users" variant="illustrated" :size="52" /><div><p>Equipos</p><strong>{{ stats.summary.registered_teams }}</strong><span class="metric-tag metric-tag--blue">Competencia seleccionada</span></div></article>
                </div>

                <nav class="stats-tabs" aria-label="Vistas de resultados">
                    <button v-for="tab in tabs" :key="tab.key" type="button" class="stats-tab" :class="activeTab === tab.key ? 'stats-tab--active' : ''" :aria-current="activeTab === tab.key ? 'page' : undefined" @click="activeTab = tab.key"><AppIcon :name="tab.icon" :size="19" /> {{ tab.label }}</button>
                </nav>

                <section v-if="activeTab === 'standings'" class="stats-panel">
                    <div class="panel-head">
                        <div class="flex items-start gap-3"><AppIcon name="trophy" variant="illustrated" :size="45" /><div><h2>Tabla de posiciones</h2><p>Clasificación general de {{ selectedCompetition.tournament.name }}.</p></div></div>
                        <div class="flex flex-wrap gap-2"><span class="data-pill"><AppIcon name="users" :size="16" /> {{ stats.summary.registered_teams }} equipos</span><span class="data-pill"><AppIcon name="calendar" :size="16" /> {{ stats.summary.official_matches }} partidos</span><span class="data-pill"><AppIcon name="football" :size="16" /> {{ stats.summary.total_goals }} goles</span></div>
                    </div>
                    <div class="overflow-x-auto"><table class="stats-table standings-table"><thead><tr><th scope="col" class="text-center">#</th><th scope="col" class="text-left">Equipo</th><th v-for="column in tableHeaders" :key="column.abbr" scope="col" class="text-center" :class="column.abbr === 'PTS' ? 'points-header' : ''" :title="column.title">{{ column.abbr }} <span class="header-info" aria-hidden="true">ⓘ</span></th></tr></thead>
                        <tbody><tr v-for="row in stats.standings" :key="row.team_participation_id"><td class="position-cell" :class="row.position <= 3 ? 'position-cell--top' : ''">{{ row.position }}</td><td class="team-cell"><div class="team-name"><img v-if="allowCrest(row.crest_url)" :src="row.crest_url!" :alt="`Escudo de ${row.team}`" class="team-crest" @error="crestFailed(row.crest_url)"><span v-else class="crest-fallback">{{ initials(row.team) }}</span><strong>{{ row.team }}</strong></div></td><td v-for="column in tableHeaders" :key="column.abbr" class="text-center" :class="[column.abbr === 'PTS' ? 'points-cell' : '', column.abbr === 'DG' && row.goal_difference > 0 ? 'text-emerald-700 font-bold' : '', column.abbr === 'DG' && row.goal_difference < 0 ? 'text-rose-600 font-bold' : '']">{{ column.abbr === 'DG' && row.goal_difference > 0 ? '+' : '' }}{{ column.abbr === 'PD' && row.points_deducted ? '-' : '' }}{{ standingValue(row, column.abbr) }}</td></tr></tbody></table></div>
                    <div class="standings-footer"><p class="text-xs text-slate-600"><strong>Desempate:</strong> {{ stats.tiebreakers.map((item) => tiebreakerLabels[item.criterion] ?? item.criterion).join(' → ') }}</p><button type="button" class="flex items-center gap-1 text-xs font-semibold text-league-700" @click="showTiebreakers = !showTiebreakers">{{ showTiebreakers ? 'Ocultar detalles' : 'Ver detalles' }} <span :class="showTiebreakers ? 'rotate-180' : ''">⌄</span></button></div>
                    <p v-if="showTiebreakers" class="border-t border-slate-100 px-5 py-3 text-xs leading-5 text-slate-600">Las posiciones se calculan utilizando el reglamento de la competencia. El resaltado de las primeras posiciones es únicamente visual; no supone clasificación automática a liguilla o descenso. Los empates que requieran decisión administrativa conservan el orden provisional existente.</p>
                </section>

                <section v-if="activeTab === 'results'" class="stats-panel">
                    <div class="panel-head"><div class="flex items-start gap-3"><AppIcon name="calendar" variant="illustrated" :size="45" /><div><h2>Resultados oficiales</h2><p>Marcadores registrados en cédulas aprobadas.</p></div></div><div class="flex flex-wrap gap-2"><label class="mini-filter">Jornada<select v-model="resultMatchday" class="form-input"><option value="all">Todas</option><option v-for="day in matchdays" :key="day" :value="day">{{ day }}</option></select></label><label class="mini-filter">Equipo<select v-model="resultTeam" class="form-input"><option value="all">Todos</option><option v-for="team in resultTeams" :key="team" :value="team">{{ team }}</option></select></label></div></div>
                    <div v-if="!groupedResults.length" class="empty-state">No hay resultados oficiales con los filtros seleccionados.</div>
                    <div v-else class="space-y-3 px-4 pb-5 sm:px-6"><article v-for="group in groupedResults" :key="group.matchday" class="result-group"><h3 class="result-group__heading"><AppIcon name="calendar" :size="17" /> {{ group.matchday }}</h3><div v-for="result in group.results" :key="result.match_id" class="result-row"><div class="result-team result-team--home"><img v-if="allowCrest(result.home_crest_url)" :src="result.home_crest_url!" :alt="`Escudo de ${result.home_team}`" class="team-crest" @error="crestFailed(result.home_crest_url)"><span v-else class="crest-fallback">{{ initials(result.home_team) }}</span><strong>{{ result.home_team }}</strong><span v-if="result.home_no_show" class="text-xs text-rose-700">Incomparecencia</span></div><div class="result-score"><strong>{{ result.home_score }} – {{ result.away_score }}</strong><span>{{ localDate(result.scheduled_at) }} · {{ result.folio ?? 'Sin folio' }}</span></div><div class="result-team result-team--away"><img v-if="allowCrest(result.away_crest_url)" :src="result.away_crest_url!" :alt="`Escudo de ${result.away_team}`" class="team-crest" @error="crestFailed(result.away_crest_url)"><span v-else class="crest-fallback">{{ initials(result.away_team) }}</span><strong>{{ result.away_team }}</strong><span v-if="result.away_no_show" class="text-xs text-rose-700">Incomparecencia</span></div><span class="official-badge"><AppIcon name="check" :size="14" /> Oficial</span></div></article></div>
                </section>

                <section v-if="activeTab === 'scorers'" class="stats-panel"><div class="panel-head"><div class="flex items-start gap-3"><AppIcon name="football" variant="illustrated" :size="45" /><div><h2>Tabla de goleadores</h2><p>Jugadores con más goles oficiales de la competencia.</p></div></div></div><div v-if="!stats.scorers.length" class="empty-state">Todavía no hay goleadores registrados en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="stats-table scorers-table"><thead><tr><th class="text-center">#</th><th class="text-left">Jugador</th><th class="text-left">Equipo</th><th class="text-left">Goles</th></tr></thead><tbody><tr v-for="(player, index) in stats.scorers" :key="player.registration_id"><td class="text-center font-bold">{{ index + 1 }}</td><td class="font-semibold">{{ player.player }}</td><td><div class="team-name"><img v-if="allowCrest(player.crest_url)" :src="player.crest_url!" :alt="`Escudo de ${player.team}`" class="team-crest team-crest--small" @error="crestFailed(player.crest_url)"><span v-else class="crest-fallback crest-fallback--small">{{ initials(player.team) }}</span>{{ player.team }}</div></td><td><div class="goal-track"><strong>{{ player.goals }}</strong><span class="goal-track__base"><span :style="{ width: `${(player.goals / maxGoals) * 100}%` }"></span></span></div></td></tr></tbody></table></div><p class="px-5 py-3 text-xs text-slate-500">Los autogoles no se atribuyen a la tabla de goleadores.</p></section>

                <section v-if="activeTab === 'discipline'" class="stats-panel"><div class="panel-head"><div class="flex items-start gap-3"><AppIcon name="file" variant="illustrated" :size="45" /><div><h2>Amonestados y expulsados</h2><p>Registro de tarjetas y puntos disciplinarios.</p></div></div></div><div v-if="!stats.discipline.length" class="empty-state">No hay tarjetas registradas en cédulas aprobadas.</div><div v-else class="overflow-x-auto"><table class="stats-table disciplinary-table"><thead><tr><th class="text-left">Jugador</th><th class="text-left">Equipo</th><th class="text-center">Amarillas</th><th class="text-center">2.ª amarilla</th><th class="text-center">Rojas</th><th class="text-center" title="Puntos de juego limpio">Puntos JL</th></tr></thead><tbody><tr v-for="player in stats.discipline" :key="player.registration_id"><td class="font-semibold">{{ player.player }}</td><td><div class="team-name"><img v-if="allowCrest(player.crest_url)" :src="player.crest_url!" :alt="`Escudo de ${player.team}`" class="team-crest team-crest--small" @error="crestFailed(player.crest_url)"><span v-else class="crest-fallback crest-fallback--small">{{ initials(player.team) }}</span>{{ player.team }}</div></td><td class="text-center"><span class="discipline-count discipline-count--yellow">{{ player.yellow_cards }}</span></td><td class="text-center"><span class="discipline-count discipline-count--orange">{{ player.second_yellows }}</span></td><td class="text-center"><span class="discipline-count discipline-count--red">{{ player.red_cards }}</span></td><td class="text-center font-bold">{{ player.fair_play_points }}</td></tr></tbody></table></div><p class="px-5 py-3 text-xs text-slate-500">La gestión del cumplimiento y arrastre de sanciones corresponde al módulo disciplinario.</p></section>
            </template>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.stats-hero{overflow:hidden;border-radius:1.65rem;background:linear-gradient(100deg,rgba(3,29,79,.94),rgba(6,66,144,.72) 58%,rgba(8,47,110,.73)),url('/images/public/league-stadium.jpg') center 52%/cover;box-shadow:0 14px 32px rgba(6,33,79,.15);color:white}
.stats-hero__inner{display:flex;justify-content:space-between;align-items:center;gap:2rem;flex-wrap:wrap;padding:1.7rem 2rem}
.stats-hero h1{font-size:clamp(1.7rem,2.5vw,2.55rem);line-height:1.15;font-weight:800;letter-spacing:-.035em;margin-top:.35rem}
.stats-overline{font-size:.73rem;text-transform:uppercase;font-weight:800;letter-spacing:.11em;color:#dbeaff}
.stats-overline span{color:#ff6cc9}
.stats-selector{width:min(100%,440px);padding:.9rem 1rem;background:rgba(10,38,85,.48);border:1px solid rgba(255,255,255,.5);border-radius:1rem;backdrop-filter:blur(5px)}
.stats-page{max-width:1500px;margin-inline:auto}
.metric-card{min-height:118px;display:flex;gap:1rem;align-items:center;border:1px solid #dbe4f3;border-radius:1.3rem;padding:1rem 1.2rem;background:linear-gradient(180deg,#fff,#f9fbff);box-shadow:0 8px 20px rgba(15,47,88,.07);position:relative;overflow:hidden}
.metric-card p{font-size:.88rem;font-weight:750;color:#13294b}.metric-card strong{display:block;font-size:2rem;line-height:1.15;font-weight:850;color:#0c224b}.metric-tag{display:inline-block;font-size:.7rem;font-weight:700;border-radius:999px;padding:.19rem .5rem;margin-top:.35rem}.metric-tag--green{color:#086b34;background:#d6f9df}.metric-tag--blue{color:#194c9b;background:#e6efff}.metric-decoration{position:absolute;right:1rem;bottom:1rem;letter-spacing:.12rem;color:#508deb;font-weight:800;font-size:1.15rem;opacity:.5}
.stats-tabs{display:flex;gap:.45rem;overflow-x:auto;padding:.4rem;border:1px solid #d6e2f2;border-radius:1.25rem;background:rgba(255,255,255,.9)}.stats-tab{min-height:44px;white-space:nowrap;display:inline-flex;justify-content:center;align-items:center;gap:.55rem;padding:.6rem 1.15rem;border-radius:.9rem;color:#213961;font-size:.86rem;font-weight:750;transition:background .15s}.stats-tab:hover{background:#eaf2ff}.stats-tab--active,.stats-tab--active:hover{background:linear-gradient(130deg,#287eff,#0755c8);color:white;box-shadow:0 4px 9px rgba(20,98,200,.22)}
.stats-panel{overflow:hidden;background:#fff;border:1px solid #dce5f1;border-radius:1.45rem;box-shadow:0 12px 24px rgba(18,49,90,.06)}.panel-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;padding:1.3rem 1.45rem}.panel-head h2{color:#102142;font-size:1.25rem;font-weight:800;line-height:1.3}.panel-head p{margin-top:.25rem;color:#60769c;font-size:.82rem}.data-pill{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #d8e5f6;border-radius:999px;padding:.5rem .8rem;font-size:.75rem;font-weight:700;color:#244471;background:#fbfcff}
.stats-table{width:100%;border-collapse:separate;border-spacing:0;font-size:.86rem}.stats-table thead th{background:#edf3fb;color:#2e4670;text-transform:uppercase;font-weight:800;font-size:.7rem;letter-spacing:.02em;padding:.8rem .9rem;white-space:nowrap}.stats-table tbody td{border-bottom:1px solid #e2e9f3;padding:.75rem .9rem;color:#182e53}.stats-table tbody tr:nth-child(even){background:#f9fbfe}.stats-table tbody tr:last-child td{border-bottom:0}.standings-table{min-width:930px}.standings-table thead th:first-child,.standings-table tbody td:first-child{padding-left:1.2rem}.standings-table .points-header{color:white;background:#2778f0;border-radius:9px 9px 0 0}.standings-table .points-cell{background:#e7f0ff;color:#0548bc;font-size:1.1rem;font-weight:900;border-bottom-color:#b5d2ff}.position-cell{text-align:center;font-weight:800;position:relative}.position-cell--top:before{content:'';position:absolute;left:0;top:.25rem;bottom:.25rem;width:5px;border-radius:99px;background:#32bd66}.team-name{display:flex;align-items:center;gap:.7rem;min-width:0}.team-name strong{min-width:0}.team-crest{height:36px;width:36px;flex:none;object-fit:contain}.team-crest--small{width:26px;height:26px}.crest-fallback{height:36px;width:36px;display:inline-grid;flex:none;place-items:center;border-radius:9px;background:#e7effb;color:#23559a;font-size:.7rem;font-weight:800;border:1px solid #d5e4f8}.crest-fallback--small{height:26px;width:26px;font-size:.55rem}.header-info{font-size:.72rem;color:#6c87ae}.standings-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:.95rem 1.2rem;border-top:1px solid #e3ebf6;background:#fbfcff}
.mini-filter{display:flex;flex-direction:column;gap:.3rem;font-size:.75rem;font-weight:700;color:#2a4677}.mini-filter .form-input{min-width:135px;padding:.5rem .7rem;font-size:.83rem}.result-group{overflow:hidden;border:1px solid #e0e8f5;border-radius:1rem}.result-group__heading{display:flex;align-items:center;gap:.5rem;padding:.6rem 1rem;color:#19345d;background:#eef4fc;font-weight:750;font-size:.86rem}.result-row{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr) auto;align-items:center;gap:1.3rem;padding:.85rem 1.1rem;border-top:1px solid #e9eff8}.result-team{display:flex;align-items:center;gap:.6rem;min-width:0;font-size:.85rem}.result-team--home{justify-content:flex-end}.result-team--away{justify-content:flex-start}.result-score{text-align:center;min-width:165px}.result-score strong{display:inline-block;padding:.25rem .95rem;border-radius:.65rem;background:#102447;color:#fff;font-size:1.2rem}.result-score span{display:block;font-size:.65rem;color:#667c9d;margin-top:.35rem}.official-badge{display:flex;align-items:center;gap:.3rem;border-radius:999px;background:#def9e7;color:#087339;font-size:.7rem;font-weight:750;padding:.35rem .65rem}
.scorers-table,.disciplinary-table{min-width:640px}.goal-track{display:flex;align-items:center;gap:.9rem}.goal-track strong{min-width:18px}.goal-track__base{height:9px;width:min(170px,100%);min-width:90px;background:#e0e9f8;border-radius:999px;overflow:hidden}.goal-track__base span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#1461e6,#418efe)}.discipline-count{display:inline-flex;align-items:center;justify-content:center;min-width:26px;min-height:27px;border-radius:5px;padding:0 .3rem;font-size:.85rem;font-weight:900}.discipline-count--yellow{background:#ffdf00;color:#17243d}.discipline-count--orange{background:#ff8f0c;color:#fff}.discipline-count--red{background:#e00015;color:white}.empty-state{padding:2.8rem 1rem;text-align:center;color:#697d99;font-size:.9rem}
@media(max-width:760px){.stats-hero__inner{padding:1.4rem}.stats-panel .panel-head{padding:1rem}.result-row{grid-template-columns:1fr auto 1fr;gap:.5rem}.result-team{flex-direction:column;text-align:center}.result-score{min-width:95px}.official-badge{grid-column:1/-1;justify-self:center}.stats-tab{padding:.55rem .85rem}.metric-card{min-height:108px}.stats-table thead th,.stats-table tbody td{padding:.65rem .6rem}}
</style>
