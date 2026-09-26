<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Named = { id: number; name: string };
type Tournament = Named & { status: string; competitions_count: number };
type Season = Named & { starts_on: string; ends_on: string; status: string; tournaments: Tournament[] };
type Division = Named & { sort_order: number; status: string };
type Category = Named & { minimum_age: number | null; maximum_age: number | null; gender: string; status: string };
type Tiebreaker = { criterion: string; priority: number };
type Regulation = Named & {
    version: number; status: string; points_win: number; points_draw: number; points_loss: number;
    walkover_home_goals: number; walkover_away_goals: number; fair_play_yellow_points: number;
    fair_play_second_yellow_points: number; fair_play_red_points: number; tiebreakers: Tiebreaker[];
};
type Competition = Named & {
    format: string; status: string; tournament: Tournament & { season: Season }; division: Division;
    category: Category; regulation: Regulation; minimum_roster_size: number; maximum_roster_size: number;
};

const props = defineProps<{
    hasOperationalSettings: boolean;
    seasons: Season[];
    divisions: Division[];
    categories: Category[];
    regulations: Regulation[];
    competitions: Competition[];
}>();

const activeTab = ref('resumen');
const tabs = [
    ['resumen', 'Resumen'], ['catalogos', 'Divisiones y categorías'], ['temporadas', 'Temporadas y torneos'],
    ['reglamentos', 'Reglamentos'], ['competencias', 'Competencias'],
];

const seasonForm = useForm({ name: '', starts_on: '', ends_on: '', registration_starts_at: '', registration_ends_at: '', status: 'planning' });
const divisionForm = useForm({ name: '', sort_order: 1 });
const categoryForm = useForm({ name: 'Libre', minimum_age: null as number | null, maximum_age: null as number | null, gender: 'mixed', requirements: '' });
const tournamentForm = useForm({ season_id: null as number | null, name: '' });
const defaultTiebreakers = ['points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play', 'administrative_decision'];
const regulationForm = useForm({
    name: 'Reglamento general', points_win: 3, points_draw: 1, points_loss: 0,
    walkover_home_goals: 3, walkover_away_goals: 0, fair_play_yellow_points: 1,
    fair_play_second_yellow_points: 2, fair_play_red_points: 3, tiebreakers: [...defaultTiebreakers],
});
const editingRegulationId = ref<number | null>(null);
const competitionForm = useForm({
    tournament_id: null as number | null, division_id: null as number | null, category_id: null as number | null,
    regulation_id: null as number | null, name: '', format: 'round_robin', regular_leg_count: 1,
    knockout_leg_count: 2, minimum_teams: null as number | null, maximum_teams: null as number | null,
    minimum_roster_size: 11, maximum_roster_size: 30, registration_starts_at: '', registration_ends_at: '',
});

const allTournaments = computed(() => props.seasons.flatMap((season) => season.tournaments.map((tournament) => ({ ...tournament, season }))));
const usableRegulations = computed(() => props.regulations.filter((regulation) => ['published', 'in_use'].includes(regulation.status)));
const labels: Record<string, string> = {
    planning: 'Planeación', registration: 'Inscripciones', active: 'Activa', finished: 'Finalizada', archived: 'Archivada', cancelled: 'Cancelada',
    draft: 'Borrador', published: 'Publicado', in_use: 'En uso', retired: 'Retirado', mixed: 'Mixta', male: 'Varonil', female: 'Femenil',
    round_robin: 'Todos contra todos', double_round_robin: 'Ida y vuelta', knockout: 'Eliminación directa', groups_knockout: 'Grupos + eliminatoria', manual: 'Manual',
    points: 'Puntos', goal_difference: 'Diferencia de goles', goals_for: 'Goles a favor', head_to_head: 'Resultado entre ambos', fair_play: 'Juego limpio', administrative_decision: 'Decisión administrativa', draw_lots: 'Sorteo',
};

function submitSeason() { seasonForm.post('/liga/temporadas', { preserveScroll: true, onSuccess: () => seasonForm.reset() }); }
function submitDivision() { divisionForm.post('/liga/divisiones', { preserveScroll: true, onSuccess: () => divisionForm.reset('name') }); }
function submitCategory() { categoryForm.post('/liga/categorias', { preserveScroll: true, onSuccess: () => categoryForm.reset() }); }
function submitTournament() { tournamentForm.post('/liga/torneos', { preserveScroll: true, onSuccess: () => tournamentForm.reset('name') }); }
function submitCompetition() { competitionForm.post('/liga/competencias', { preserveScroll: true, onSuccess: () => competitionForm.reset() }); }
function submitRegulation() {
    const options = { preserveScroll: true, onSuccess: () => { regulationForm.reset(); regulationForm.tiebreakers = [...defaultTiebreakers]; editingRegulationId.value = null; } };
    if (editingRegulationId.value) regulationForm.put(`/liga/reglamentos/${editingRegulationId.value}`, options);
    else regulationForm.post('/liga/reglamentos', options);
}
function editRegulation(regulation: Regulation) {
    editingRegulationId.value = regulation.id;
    regulationForm.name = regulation.name;
    regulationForm.points_win = regulation.points_win;
    regulationForm.points_draw = regulation.points_draw;
    regulationForm.points_loss = regulation.points_loss;
    regulationForm.walkover_home_goals = regulation.walkover_home_goals;
    regulationForm.walkover_away_goals = regulation.walkover_away_goals;
    regulationForm.fair_play_yellow_points = regulation.fair_play_yellow_points;
    regulationForm.fair_play_second_yellow_points = regulation.fair_play_second_yellow_points;
    regulationForm.fair_play_red_points = regulation.fair_play_red_points;
    regulationForm.tiebreakers = regulation.tiebreakers.map((item) => item.criterion);
}
function moveCriterion(index: number, direction: number) {
    const target = index + direction;
    if (target < 0 || target >= regulationForm.tiebreakers.length) return;
    const reordered = [...regulationForm.tiebreakers];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
    regulationForm.tiebreakers = reordered;
}
function publishRegulation(regulation: Regulation) {
    if (confirm(`¿Publicar ${regulation.name} v${regulation.version}? Después no podrá modificarse.`)) router.post(`/liga/reglamentos/${regulation.id}/publicar`, {}, { preserveScroll: true });
}
function transitionSeason(season: Season, status: string) {
    if (confirm(`¿Cambiar la temporada a “${labels[status]}”?`)) router.put(`/liga/temporadas/${season.id}/estado`, { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Temporadas y competencias" />
    <AppLayout>
        <section>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 4</p>
            <h1 class="mt-1 text-3xl font-semibold">Temporadas y competencias</h1>
            <p class="mt-2 text-slate-600">Organiza la estructura deportiva de la liga y protege las reglas históricas.</p>

            <div v-if="!hasOperationalSettings" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Antes de crear reglamentos, guarda la duración del partido en <a href="/liga/parametros" class="font-semibold underline">Parámetros</a>.
            </div>

            <div class="mt-7 overflow-x-auto border-b border-slate-200">
                <div class="flex min-w-max gap-1">
                    <button v-for="tab in tabs" :key="tab[0]" type="button" class="rounded-t-xl px-4 py-3 text-sm font-semibold" :class="activeTab === tab[0] ? 'bg-league-700 text-white' : 'text-slate-600 hover:bg-slate-200'" @click="activeTab = tab[0]">{{ tab[1] }}</button>
                </div>
            </div>

            <div v-if="activeTab === 'resumen'" class="mt-7 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="stat-card"><span class="stat-label">Temporadas</span><span class="stat-value">{{ seasons.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Torneos</span><span class="stat-value">{{ allTournaments.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Divisiones</span><span class="stat-value">{{ divisions.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Reglamentos</span><span class="stat-value">{{ regulations.length }}</span></div>
                    <div class="stat-card"><span class="stat-label">Competencias</span><span class="stat-value">{{ competitions.length }}</span></div>
                </div>
                <div class="card p-6">
                    <h2 class="text-xl font-semibold">Orden recomendado</h2>
                    <ol class="mt-4 grid gap-3 text-sm sm:grid-cols-5">
                        <li v-for="(step, index) in ['Crear divisiones y categorías', 'Crear temporada', 'Agregar torneo', 'Configurar y publicar reglamento', 'Crear competencia']" :key="step" class="rounded-xl bg-slate-50 p-4"><span class="font-bold text-league-700">{{ index + 1 }}.</span> {{ step }}</li>
                    </ol>
                </div>
            </div>

            <div v-if="activeTab === 'catalogos'" class="mt-7 grid gap-6 lg:grid-cols-2">
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">Divisiones</h2>
                    <form class="mt-5 grid gap-4 sm:grid-cols-[1fr_8rem_auto]" @submit.prevent="submitDivision">
                        <div><label class="form-label">Nombre</label><input v-model="divisionForm.name" class="form-input" required placeholder="Primera fuerza"><FormError :message="divisionForm.errors.name" /></div>
                        <div><label class="form-label">Orden</label><input v-model.number="divisionForm.sort_order" class="form-input" type="number" min="0" max="999" required><FormError :message="divisionForm.errors.sort_order" /></div>
                        <button class="btn-primary self-end" :disabled="divisionForm.processing">Agregar</button>
                    </form>
                    <ul class="mt-6 divide-y divide-slate-200"><li v-for="division in divisions" :key="division.id" class="flex justify-between py-3 text-sm"><span>{{ division.name }}</span><span class="text-slate-500">Orden {{ division.sort_order }}</span></li><li v-if="!divisions.length" class="py-4 text-sm text-slate-500">Aún no hay divisiones.</li></ul>
                </section>
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">Categorías</h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitCategory">
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="categoryForm.name" class="form-input" required><FormError :message="categoryForm.errors.name" /></div><div><label class="form-label">Género</label><select v-model="categoryForm.gender" class="form-input"><option value="mixed">Mixta</option><option value="male">Varonil</option><option value="female">Femenil</option></select></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Edad mínima (opcional)</label><input v-model.number="categoryForm.minimum_age" class="form-input" type="number" min="1" max="100"><FormError :message="categoryForm.errors.minimum_age" /></div><div><label class="form-label">Edad máxima (opcional)</label><input v-model.number="categoryForm.maximum_age" class="form-input" type="number" min="1" max="100"><FormError :message="categoryForm.errors.maximum_age" /></div></div>
                        <div><label class="form-label">Otros requisitos (opcional)</label><textarea v-model="categoryForm.requirements" class="form-input min-h-20" maxlength="1000"></textarea></div>
                        <button class="btn-primary" :disabled="categoryForm.processing">Agregar categoría</button>
                    </form>
                    <ul class="mt-6 divide-y divide-slate-200"><li v-for="category in categories" :key="category.id" class="flex justify-between py-3 text-sm"><span>{{ category.name }}</span><span class="text-slate-500">{{ labels[category.gender] }}<template v-if="category.minimum_age || category.maximum_age"> · {{ category.minimum_age ?? '—' }} a {{ category.maximum_age ?? '—' }} años</template></span></li><li v-if="!categories.length" class="py-4 text-sm text-slate-500">Aún no hay categorías.</li></ul>
                </section>
            </div>

            <div v-if="activeTab === 'temporadas'" class="mt-7 grid gap-6 lg:grid-cols-2">
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">Nueva temporada</h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitSeason">
                        <div><label class="form-label">Nombre</label><input v-model="seasonForm.name" class="form-input" required placeholder="Temporada 2027"><FormError :message="seasonForm.errors.name" /></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Fecha inicial</label><input v-model="seasonForm.starts_on" class="form-input" type="date" required></div><div><label class="form-label">Fecha final</label><input v-model="seasonForm.ends_on" class="form-input" type="date" required><FormError :message="seasonForm.errors.ends_on" /></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Apertura de inscripciones</label><input v-model="seasonForm.registration_starts_at" class="form-input" type="datetime-local"></div><div><label class="form-label">Cierre de inscripciones</label><input v-model="seasonForm.registration_ends_at" class="form-input" type="datetime-local"><FormError :message="seasonForm.errors.registration_ends_at" /></div></div>
                        <div><label class="form-label">Estado inicial</label><select v-model="seasonForm.status" class="form-input"><option value="planning">Planeación</option><option value="registration">Inscripciones</option><option value="active">Activa</option></select></div>
                        <button class="btn-primary" :disabled="seasonForm.processing">Crear temporada</button>
                    </form>
                </section>
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">Nuevo torneo</h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitTournament">
                        <div><label class="form-label">Temporada</label><select v-model="tournamentForm.season_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="season in seasons.filter((item) => !['archived', 'cancelled'].includes(item.status))" :key="season.id" :value="season.id">{{ season.name }}</option></select><FormError :message="tournamentForm.errors.season_id" /></div>
                        <div><label class="form-label">Nombre del torneo</label><input v-model="tournamentForm.name" class="form-input" required placeholder="Torneo de Liga"><FormError :message="tournamentForm.errors.name" /></div>
                        <button class="btn-primary" :disabled="tournamentForm.processing || !seasons.length">Crear torneo</button>
                    </form>
                </section>
                <section class="card p-6 lg:col-span-2">
                    <h2 class="text-xl font-semibold">Temporadas registradas</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <article v-for="season in seasons" :key="season.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold">{{ season.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ season.starts_on }} — {{ season.ends_on }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ labels[season.status] }}</span></div>
                            <ul class="mt-4 space-y-2 text-sm"><li v-for="tournament in season.tournaments" :key="tournament.id" class="flex justify-between"><span>{{ tournament.name }}</span><span class="text-slate-500">{{ tournament.competitions_count }} competencia(s)</span></li><li v-if="!season.tournaments.length" class="text-slate-500">Sin torneos.</li></ul>
                            <div class="mt-4 flex flex-wrap gap-2"><button v-if="season.status === 'planning'" class="btn-secondary" @click="transitionSeason(season, 'registration')">Abrir inscripciones</button><button v-if="['planning', 'registration'].includes(season.status)" class="btn-secondary" @click="transitionSeason(season, 'active')">Activar</button><button v-if="season.status === 'active'" class="btn-secondary" @click="transitionSeason(season, 'finished')">Finalizar</button><button v-if="['finished', 'cancelled'].includes(season.status)" class="btn-secondary" @click="transitionSeason(season, 'archived')">Archivar</button></div>
                        </article>
                        <p v-if="!seasons.length" class="text-sm text-slate-500">Aún no hay temporadas.</p>
                    </div>
                </section>
            </div>

            <div v-if="activeTab === 'reglamentos'" class="mt-7 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">{{ editingRegulationId ? 'Editar borrador' : 'Nuevo reglamento' }}</h2>
                    <p class="mt-2 text-sm text-slate-600">Al publicar se congelan las reglas y la copia de los parámetros generales.</p>
                    <form class="mt-5 space-y-5" @submit.prevent="submitRegulation">
                        <div><label class="form-label">Nombre</label><input v-model="regulationForm.name" class="form-input" required><FormError :message="regulationForm.errors.name" /></div>
                        <div class="grid gap-4 sm:grid-cols-3"><div><label class="form-label">Victoria</label><input v-model.number="regulationForm.points_win" class="form-input" type="number" min="-20" max="20" required></div><div><label class="form-label">Empate</label><input v-model.number="regulationForm.points_draw" class="form-input" type="number" min="-20" max="20" required></div><div><label class="form-label">Derrota</label><input v-model.number="regulationForm.points_loss" class="form-input" type="number" min="-20" max="20" required></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Marcador local por incomparecencia</label><input v-model.number="regulationForm.walkover_home_goals" class="form-input" type="number" min="0" max="99" required></div><div><label class="form-label">Marcador visitante</label><input v-model.number="regulationForm.walkover_away_goals" class="form-input" type="number" min="0" max="99" required></div></div>
                        <div class="grid gap-4 sm:grid-cols-3"><div><label class="form-label">Amarilla</label><input v-model.number="regulationForm.fair_play_yellow_points" class="form-input" type="number" min="0" max="20" required></div><div><label class="form-label">Doble amarilla</label><input v-model.number="regulationForm.fair_play_second_yellow_points" class="form-input" type="number" min="0" max="20" required></div><div><label class="form-label">Roja directa</label><input v-model.number="regulationForm.fair_play_red_points" class="form-input" type="number" min="0" max="20" required></div></div>
                        <div><label class="form-label">Orden de desempate</label><div class="space-y-2"><div v-for="(criterion, index) in regulationForm.tiebreakers" :key="criterion" class="flex items-center gap-2 rounded-xl bg-slate-50 p-3"><span class="w-7 font-bold text-league-700">{{ index + 1 }}</span><span class="flex-1 text-sm">{{ labels[criterion] }}</span><button type="button" class="btn-secondary px-3" :disabled="index === 0" @click="moveCriterion(index, -1)" aria-label="Subir criterio">↑</button><button type="button" class="btn-secondary px-3" :disabled="index === regulationForm.tiebreakers.length - 1" @click="moveCriterion(index, 1)" aria-label="Bajar criterio">↓</button></div></div><FormError :message="regulationForm.errors.tiebreakers" /></div>
                        <div class="flex gap-3"><button class="btn-primary" :disabled="regulationForm.processing || !hasOperationalSettings">{{ editingRegulationId ? 'Guardar borrador' : 'Crear borrador' }}</button><button v-if="editingRegulationId" type="button" class="btn-secondary" @click="editingRegulationId = null; regulationForm.reset()">Cancelar</button></div>
                    </form>
                </section>
                <section class="space-y-4">
                    <article v-for="regulation in regulations" :key="regulation.id" class="card p-5">
                        <div class="flex justify-between gap-3"><div><h3 class="font-semibold">{{ regulation.name }} · v{{ regulation.version }}</h3><p class="mt-1 text-sm text-slate-500">{{ regulation.points_win }}/{{ regulation.points_draw }}/{{ regulation.points_loss }} puntos · Incomparecencia {{ regulation.walkover_home_goals }}-{{ regulation.walkover_away_goals }}</p></div><span class="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ labels[regulation.status] }}</span></div>
                        <p class="mt-3 text-xs text-slate-500">Desempates: {{ regulation.tiebreakers.map((item) => labels[item.criterion]).join(' → ') }}</p>
                        <div v-if="regulation.status === 'draft'" class="mt-4 flex gap-2"><button class="btn-secondary" @click="editRegulation(regulation)">Editar</button><button class="btn-primary" @click="publishRegulation(regulation)">Publicar</button></div>
                        <p v-else class="mt-4 text-xs font-medium text-emerald-700">Contenido protegido; para cambiarlo crea una nueva versión.</p>
                    </article>
                    <div v-if="!regulations.length" class="card p-6 text-sm text-slate-500">Aún no hay reglamentos.</div>
                </section>
            </div>

            <div v-if="activeTab === 'competencias'" class="mt-7 grid gap-6 xl:grid-cols-[1fr_1fr]">
                <section class="card p-6">
                    <h2 class="text-xl font-semibold">Nueva competencia</h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitCompetition">
                        <div><label class="form-label">Nombre</label><input v-model="competitionForm.name" class="form-input" required placeholder="Primera fuerza · Libre"><FormError :message="competitionForm.errors.name" /></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Torneo</label><select v-model="competitionForm.tournament_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="tournament in allTournaments" :key="tournament.id" :value="tournament.id">{{ tournament.season.name }} · {{ tournament.name }}</option></select></div><div><label class="form-label">Formato</label><select v-model="competitionForm.format" class="form-input"><option value="round_robin">Todos contra todos</option><option value="double_round_robin">Ida y vuelta</option><option value="knockout">Eliminación directa</option><option value="groups_knockout">Grupos + eliminatoria</option><option value="manual">Manual</option></select></div></div>
                        <div class="grid gap-4 sm:grid-cols-3"><div><label class="form-label">División</label><select v-model="competitionForm.division_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option></select><FormError :message="competitionForm.errors.division_id" /></div><div><label class="form-label">Categoría</label><select v-model="competitionForm.category_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div><div><label class="form-label">Reglamento</label><select v-model="competitionForm.regulation_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="regulation in usableRegulations" :key="regulation.id" :value="regulation.id">{{ regulation.name }} v{{ regulation.version }}</option></select><FormError :message="competitionForm.errors.regulation_id" /></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Vueltas de fase regular</label><input v-model.number="competitionForm.regular_leg_count" class="form-input" type="number" min="1" max="4" required></div><div><label class="form-label">Partidos por eliminatoria</label><input v-model.number="competitionForm.knockout_leg_count" class="form-input" type="number" min="1" max="2" required></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Mínimo de equipos (opcional)</label><input v-model.number="competitionForm.minimum_teams" class="form-input" type="number" min="2" max="200"></div><div><label class="form-label">Máximo de equipos (opcional)</label><input v-model.number="competitionForm.maximum_teams" class="form-input" type="number" min="2" max="200"><FormError :message="competitionForm.errors.maximum_teams" /></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Mínimo de jugadores</label><input v-model.number="competitionForm.minimum_roster_size" class="form-input" type="number" min="1" max="100" required></div><div><label class="form-label">Máximo de jugadores</label><input v-model.number="competitionForm.maximum_roster_size" class="form-input" type="number" min="1" max="100" required><FormError :message="competitionForm.errors.maximum_roster_size" /></div></div>
                        <div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Apertura de registro</label><input v-model="competitionForm.registration_starts_at" class="form-input" type="datetime-local"></div><div><label class="form-label">Cierre de registro</label><input v-model="competitionForm.registration_ends_at" class="form-input" type="datetime-local"><FormError :message="competitionForm.errors.registration_ends_at" /></div></div>
                        <button class="btn-primary" :disabled="competitionForm.processing || !allTournaments.length || !divisions.length || !categories.length || !usableRegulations.length">Crear competencia</button>
                    </form>
                </section>
                <section class="space-y-4">
                    <article v-for="competition in competitions" :key="competition.id" class="card p-5">
                        <div class="flex justify-between gap-3"><div><h3 class="font-semibold">{{ competition.name }}</h3><p class="mt-1 text-sm text-slate-500">{{ competition.tournament.season.name }} · {{ competition.tournament.name }}</p></div><span class="h-fit rounded-full bg-league-50 px-3 py-1 text-xs font-semibold text-league-700">{{ labels[competition.status] }}</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">División / categoría</dt><dd class="font-medium">{{ competition.division.name }} · {{ competition.category.name }}</dd></div><div><dt class="text-slate-500">Formato</dt><dd class="font-medium">{{ labels[competition.format] }}</dd></div><div><dt class="text-slate-500">Plantilla</dt><dd class="font-medium">{{ competition.minimum_roster_size }}–{{ competition.maximum_roster_size }}</dd></div><div><dt class="text-slate-500">Reglamento</dt><dd class="font-medium">{{ competition.regulation.name }} v{{ competition.regulation.version }}</dd></div></dl>
                    </article>
                    <div v-if="!competitions.length" class="card p-6 text-sm text-slate-500">Aún no hay competencias.</div>
                </section>
            </div>
        </section>
    </AppLayout>
</template>
