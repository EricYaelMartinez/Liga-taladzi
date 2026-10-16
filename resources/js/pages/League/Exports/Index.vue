<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type ReportType = { value: string; label: string; description: string; requires_competition?: boolean };
type Option = { id: number; name: string };
type Tournament = Option & { season_id: number };
type Competition = Option & { season_id: number; tournament_id: number; division_id: number; division: string; category: string };
type Matchday = Option & { competition_id: number; number: number };
type Options = { seasons: Option[]; tournaments: Tournament[]; competitions: Competition[]; divisions: Option[]; matchdays: Matchday[]; teams: Option[]; players: Option[]; referees: Option[] };

const props = defineProps<{ reportTypes: ReportType[]; options: Options }>();
const filters = reactive({ type: 'teams', format: 'pdf', season_id: '', tournament_id: '', competition_id: '', division_id: '', matchday_id: '', team_id: '', player_id: '', referee_id: '', date_from: '', date_to: '' });
const selectedReport = computed(() => props.reportTypes.find((item) => item.value === filters.type) ?? props.reportTypes[0]);
const tournaments = computed(() => props.options.tournaments.filter((item) => !filters.season_id || item.season_id === Number(filters.season_id)));
const competitions = computed(() => props.options.competitions.filter((item) => (!filters.season_id || item.season_id === Number(filters.season_id)) && (!filters.tournament_id || item.tournament_id === Number(filters.tournament_id)) && (!filters.division_id || item.division_id === Number(filters.division_id))));
const matchdays = computed(() => props.options.matchdays.filter((item) => !filters.competition_id || item.competition_id === Number(filters.competition_id)));
const canDownload = computed(() => !selectedReport.value?.requires_competition || Boolean(filters.competition_id));
const downloadUrl = computed(() => {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => { if (value !== '') query.set(key, value); });
    return `/liga/reportes/exportar?${query.toString()}`;
});

function selectReport(type: string): void { filters.type = type; }
function changeSeason(): void { filters.tournament_id = ''; filters.competition_id = ''; filters.matchday_id = ''; }
function changeTournament(): void { filters.competition_id = ''; filters.matchday_id = ''; }
function changeCompetition(): void { filters.matchday_id = ''; }
function clearFilters(): void {
    const type = filters.type;
    const format = filters.format;
    Object.assign(filters, { type, format, season_id: '', tournament_id: '', competition_id: '', division_id: '', matchday_id: '', team_id: '', player_id: '', referee_id: '', date_from: '', date_to: '' });
}
</script>

<template>
    <Head title="Reportes y exportaciones" />
    <AppLayout>
        <header class="mb-7">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-league-600">Módulo 18</p>
            <h1 class="mt-1 text-3xl font-semibold text-slate-900">Reportes y exportaciones</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Genera documentos oficiales en PDF o libros de Excel. Cada descarga conserva un folio y queda registrada en la bitácora.</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <button v-for="report in reportTypes" :key="report.value" type="button" class="rounded-2xl border p-5 text-left transition" :class="filters.type === report.value ? 'border-league-600 bg-league-50 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300'" @click="selectReport(report.value)">
                <div class="flex items-start justify-between gap-3"><h2 class="font-semibold text-slate-900">{{ report.label }}</h2><span v-if="filters.type === report.value" class="rounded-full bg-league-600 px-2 py-0.5 text-xs font-bold text-white">Seleccionado</span></div>
                <p class="mt-2 text-sm leading-5 text-slate-500">{{ report.description }}</p>
            </button>
        </section>

        <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 sm:px-6"><h2 class="text-lg font-semibold">Filtros del reporte</h2><p class="mt-1 text-sm text-slate-500">Los filtros vacíos incluyen todos los registros autorizados de la liga actual.</p></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3 xl:grid-cols-4">
                <div><label class="form-label">Temporada</label><select v-model="filters.season_id" class="form-input" @change="changeSeason"><option value="">Todas</option><option v-for="item in options.seasons" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Torneo</label><select v-model="filters.tournament_id" class="form-input" @change="changeTournament"><option value="">Todos</option><option v-for="item in tournaments" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">División</label><select v-model="filters.division_id" class="form-input" @change="changeTournament"><option value="">Todas</option><option v-for="item in options.divisions" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Competencia <span v-if="selectedReport?.requires_competition" class="text-red-600">*</span></label><select v-model="filters.competition_id" class="form-input" @change="changeCompetition"><option value="">Todas</option><option v-for="item in competitions" :key="item.id" :value="item.id">{{ item.name }} · {{ item.division }} / {{ item.category }}</option></select><p v-if="selectedReport?.requires_competition && !filters.competition_id" class="mt-1 text-xs text-red-600">Obligatoria para este reporte.</p></div>
                <div><label class="form-label">Jornada</label><select v-model="filters.matchday_id" class="form-input"><option value="">Todas</option><option v-for="item in matchdays" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Equipo</label><select v-model="filters.team_id" class="form-input"><option value="">Todos</option><option v-for="item in options.teams" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Jugador</label><select v-model="filters.player_id" class="form-input"><option value="">Todos</option><option v-for="item in options.players" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Árbitro</label><select v-model="filters.referee_id" class="form-input"><option value="">Todos</option><option v-for="item in options.referees" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <div><label class="form-label">Desde</label><input v-model="filters.date_from" class="form-input" type="date"></div>
                <div><label class="form-label">Hasta</label><input v-model="filters.date_to" class="form-input" type="date" :min="filters.date_from || undefined"></div>
                <div class="sm:col-span-2"><label class="form-label">Formato</label><div class="grid grid-cols-2 gap-3"><label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3" :class="filters.format === 'pdf' ? 'border-league-600 bg-league-50' : 'border-slate-200'"><input v-model="filters.format" type="radio" value="pdf"> <span><strong class="block text-sm">PDF</strong><small class="text-slate-500">Documento listo para imprimir</small></span></label><label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3" :class="filters.format === 'xlsx' ? 'border-league-600 bg-league-50' : 'border-slate-200'"><input v-model="filters.format" type="radio" value="xlsx"> <span><strong class="block text-sm">Excel</strong><small class="text-slate-500">Libro .xlsx con filtros</small></span></label></div></div>
            </div>
            <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"><p class="text-sm text-slate-600"><strong>{{ selectedReport?.label }}</strong> · la exportación solo incluye información de la liga activa.</p><div class="flex gap-3"><button type="button" class="btn-secondary" @click="clearFilters">Limpiar filtros</button><a v-if="canDownload" :href="downloadUrl" class="btn-primary">Descargar {{ filters.format === 'pdf' ? 'PDF' : 'Excel' }}</a><button v-else type="button" class="btn-primary" disabled>Selecciona una competencia</button></div></footer>
        </section>

        <aside class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900"><strong>Privacidad:</strong> estos reportes son administrativos. No se publican en el portal público y solo los pueden generar usuarios con permiso de exportación.</aside>
    </AppLayout>
</template>
