<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Named = { id: number; name: string };
type Tournament = Named & { status: string; competitions_count: number };
type Season = Named & { starts_on: string | null; ends_on: string | null; status: string; tournaments: Tournament[] };
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
    { id: 'resumen', label: 'Resumen', icon: 'chart' },
    { id: 'catalogos', label: 'Divisiones y categorías', icon: 'sliders' },
    { id: 'temporadas', label: 'Temporadas y torneos', icon: 'calendar' },
    { id: 'reglamentos', label: 'Reglamentos', icon: 'file' },
    { id: 'competencias', label: 'Competencias', icon: 'trophy' },
];
const competitionSearch = ref('');
const competitionStatus = ref('all');

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
const availableTournaments = computed(() => allTournaments.value.filter((tournament) => !['finished', 'archived', 'cancelled'].includes(tournament.status) && !['finished', 'archived', 'cancelled'].includes(tournament.season.status)));
const usableRegulations = computed(() => props.regulations.filter((regulation) => ['published', 'in_use'].includes(regulation.status)));
const activeSeasons = computed(() => props.seasons.filter((season) => ['registration', 'active'].includes(season.status)).length);
const activeCompetitions = computed(() => props.competitions.filter((competition) => competition.status === 'active').length);
const protectedRegulations = computed(() => props.regulations.filter((regulation) => ['published', 'in_use'].includes(regulation.status)).length);
const visibleCompetitions = computed(() => {
    const query = competitionSearch.value.trim().toLocaleLowerCase('es');
    return props.competitions.filter((competition) => {
        const matchesStatus = competitionStatus.value === 'all' || competition.status === competitionStatus.value;
        const searchable = [competition.name, competition.tournament.name, competition.tournament.season.name, competition.division.name, competition.category.name].join(' ').toLocaleLowerCase('es');
        return matchesStatus && (!query || searchable.includes(query));
    });
});
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
function statusClass(status: string): string {
    if (['active', 'in_use'].includes(status)) return 'competition-status competition-status-active';
    if (['registration', 'published'].includes(status)) return 'competition-status competition-status-registration';
    if (['finished', 'retired'].includes(status)) return 'competition-status competition-status-finished';
    if (['archived', 'cancelled'].includes(status)) return 'competition-status competition-status-muted';
    return 'competition-status competition-status-planning';
}
function parseSeasonDate(value: string | null): Date | null {
    if (!value) return null;
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value);
    return Number.isNaN(date.getTime()) ? null : date;
}
function formatDate(value: string | null): string {
    const date = parseSeasonDate(value);
    return date ? new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(date) : 'Sin fecha';
}
function seasonYear(value: string | null): string {
    const date = parseSeasonDate(value);
    return date ? String(date.getFullYear()) : '—';
}
</script>

<template>
    <Head title="Temporadas y competencias" />
    <AppLayout>
        <section class="competition-page">
            <header class="competition-hero">
                <div class="competition-hero-content">
                    <div class="max-w-3xl">
                        <span class="competition-eyebrow"><AppIcon name="trophy" :size="15" /> Gestión deportiva</span>
                        <h1 class="mt-4 text-3xl font-bold sm:text-4xl">Competencias y reglamentos</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">Construye la estructura competitiva de la liga, controla sus ciclos y conserva reglas históricas confiables.</p>
                    </div>
                    <div class="competition-hero-summary">
                        <span><strong>{{ activeSeasons }}</strong> temporadas vigentes</span>
                        <span><strong>{{ activeCompetitions }}</strong> competencias activas</span>
                    </div>
                </div>
            </header>

            <div v-if="!hasOperationalSettings" class="competition-alert mt-5">
                <AppIcon name="settings" :size="20" />
                <div><strong>Configuración pendiente</strong><p>Antes de crear reglamentos, guarda la duración del partido en <a href="/liga/parametros">Parámetros</a>.</p></div>
            </div>

            <nav class="competition-tabs" aria-label="Secciones de competencias">
                <button v-for="tab in tabs" :key="tab.id" type="button" class="competition-tab" :class="{ 'competition-tab-active': activeTab === tab.id }" @click="activeTab = tab.id">
                    <span class="competition-tab-icon"><AppIcon :name="tab.icon" variant="illustrated" :size="30" /></span><span>{{ tab.label }}</span>
                </button>
            </nav>

            <div v-if="activeTab === 'resumen'" class="mt-6 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="competition-stat"><span class="competition-stat-icon"><AppIcon name="calendar" variant="illustrated" :size="50" /></span><div><strong>{{ seasons.length }}</strong><p>Temporadas registradas</p><small>{{ activeSeasons }} vigentes</small></div></article>
                    <article class="competition-stat"><span class="competition-stat-icon"><AppIcon name="trophy" variant="illustrated" :size="50" /></span><div><strong>{{ competitions.length }}</strong><p>Competencias</p><small>{{ activeCompetitions }} activas</small></div></article>
                    <article class="competition-stat"><span class="competition-stat-icon"><AppIcon name="file" variant="illustrated" :size="50" /></span><div><strong>{{ regulations.length }}</strong><p>Reglamentos</p><small>{{ protectedRegulations }} publicados</small></div></article>
                    <article class="competition-stat"><span class="competition-stat-icon"><AppIcon name="sliders" variant="illustrated" :size="50" /></span><div><strong>{{ divisions.length + categories.length }}</strong><p>Catálogos deportivos</p><small>{{ divisions.length }} divisiones · {{ categories.length }} categorías</small></div></article>
                </div>
                <section class="competition-panel p-6 sm:p-8">
                    <div class="section-heading"><span class="section-heading-icon"><AppIcon name="chart" /></span><div><p class="section-kicker">Ruta de configuración</p><h2>Prepara una competencia en cinco pasos</h2><span>Completa la estructura de izquierda a derecha para evitar configuraciones incompletas.</span></div></div>
                    <ol class="competition-workflow">
                        <li v-for="(step, index) in ['Divisiones y categorías', 'Temporada', 'Torneo', 'Reglamento publicado', 'Competencia']" :key="step"><span>{{ index + 1 }}</span><div><strong>{{ step }}</strong><small>{{ index === 0 ? 'Define quién participa' : index === 1 ? 'Establece el ciclo' : index === 2 ? 'Agrupa competencias' : index === 3 ? 'Fija las reglas' : 'Integra la estructura' }}</small></div></li>
                    </ol>
                </section>
            </div>

            <div v-if="activeTab === 'catalogos'" class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="competition-panel overflow-hidden">
                    <div class="competition-panel-heading"><span><AppIcon name="sliders" /></span><div><p>Catálogo deportivo</p><h2>Divisiones</h2></div></div>
                    <div class="p-5 sm:p-6"><form class="grid gap-4 sm:grid-cols-[1fr_8rem_auto]" @submit.prevent="submitDivision"><div><label class="form-label">Nombre de la división</label><input v-model="divisionForm.name" class="form-input" required placeholder="Primera fuerza"><FormError :message="divisionForm.errors.name" /></div><div><label class="form-label">Orden</label><input v-model.number="divisionForm.sort_order" class="form-input" type="number" min="0" max="999" required><FormError :message="divisionForm.errors.sort_order" /></div><button class="btn-primary self-end" :disabled="divisionForm.processing"><AppIcon name="check" :size="16" /> Agregar</button></form>
                        <div class="catalog-list mt-6"><div v-for="division in divisions" :key="division.id" class="catalog-item"><span class="catalog-order">{{ division.sort_order }}</span><div><strong>{{ division.name }}</strong><small>División activa en el catálogo</small></div><span :class="statusClass(division.status)">{{ labels[division.status] ?? 'Activa' }}</span></div><div v-if="!divisions.length" class="empty-state">Aún no hay divisiones registradas.</div></div>
                    </div>
                </section>
                <section class="competition-panel overflow-hidden">
                    <div class="competition-panel-heading"><span><AppIcon name="users" /></span><div><p>Clasificación de participantes</p><h2>Categorías</h2></div></div>
                    <div class="p-5 sm:p-6"><form class="space-y-4" @submit.prevent="submitCategory"><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="categoryForm.name" class="form-input" required><FormError :message="categoryForm.errors.name" /></div><div><label class="form-label">Rama</label><select v-model="categoryForm.gender" class="form-input"><option value="mixed">Mixta</option><option value="male">Varonil</option><option value="female">Femenil</option></select></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Edad mínima</label><input v-model.number="categoryForm.minimum_age" class="form-input" type="number" min="1" max="100" placeholder="Opcional"><FormError :message="categoryForm.errors.minimum_age" /></div><div><label class="form-label">Edad máxima</label><input v-model.number="categoryForm.maximum_age" class="form-input" type="number" min="1" max="100" placeholder="Opcional"><FormError :message="categoryForm.errors.maximum_age" /></div></div><div><label class="form-label">Otros requisitos</label><textarea v-model="categoryForm.requirements" class="form-input min-h-20" maxlength="1000" placeholder="Condiciones adicionales de participación"></textarea></div><button class="btn-primary" :disabled="categoryForm.processing"><AppIcon name="check" :size="16" /> Agregar categoría</button></form>
                        <div class="catalog-list mt-6"><div v-for="category in categories" :key="category.id" class="catalog-item"><span class="catalog-order"><AppIcon name="team" :size="17" /></span><div><strong>{{ category.name }}</strong><small>{{ labels[category.gender] }}<template v-if="category.minimum_age || category.maximum_age"> · {{ category.minimum_age ?? '—' }} a {{ category.maximum_age ?? '—' }} años</template></small></div><span :class="statusClass(category.status)">{{ labels[category.status] ?? 'Activa' }}</span></div><div v-if="!categories.length" class="empty-state">Aún no hay categorías registradas.</div></div>
                    </div>
                </section>
            </div>

            <div v-if="activeTab === 'temporadas'" class="mt-6 space-y-6">
                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="competition-panel p-5 sm:p-6"><div class="section-heading compact"><span class="section-heading-icon"><AppIcon name="calendar" /></span><div><p class="section-kicker">Nuevo ciclo</p><h2>Crear temporada</h2></div></div><form class="mt-6 space-y-4" @submit.prevent="submitSeason"><div><label class="form-label">Nombre</label><input v-model="seasonForm.name" class="form-input" required placeholder="Temporada 2027"><FormError :message="seasonForm.errors.name" /></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Fecha inicial</label><input v-model="seasonForm.starts_on" class="form-input" type="date" required></div><div><label class="form-label">Fecha final</label><input v-model="seasonForm.ends_on" class="form-input" type="date" required><FormError :message="seasonForm.errors.ends_on" /></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Apertura de inscripciones</label><input v-model="seasonForm.registration_starts_at" class="form-input" type="datetime-local"></div><div><label class="form-label">Cierre de inscripciones</label><input v-model="seasonForm.registration_ends_at" class="form-input" type="datetime-local"><FormError :message="seasonForm.errors.registration_ends_at" /></div></div><div><label class="form-label">Estado inicial</label><select v-model="seasonForm.status" class="form-input"><option value="planning">Planeación</option><option value="registration">Inscripciones</option><option value="active">Activa</option></select></div><button class="btn-primary" :disabled="seasonForm.processing"><AppIcon name="check" :size="16" /> Crear temporada</button></form></section>
                    <section class="competition-panel p-5 sm:p-6"><div class="section-heading compact"><span class="section-heading-icon"><AppIcon name="trophy" /></span><div><p class="section-kicker">Organización</p><h2>Agregar torneo</h2></div></div><form class="mt-6 space-y-4" @submit.prevent="submitTournament"><div><label class="form-label">Temporada</label><select v-model="tournamentForm.season_id" class="form-input" required><option :value="null" disabled>Selecciona una temporada vigente</option><option v-for="season in seasons.filter((item) => !['finished', 'archived', 'cancelled'].includes(item.status))" :key="season.id" :value="season.id">{{ season.name }}</option></select><FormError :message="tournamentForm.errors.season_id" /></div><div><label class="form-label">Nombre del torneo</label><input v-model="tournamentForm.name" class="form-input" required placeholder="Torneo de Liga"><FormError :message="tournamentForm.errors.name" /></div><div class="form-information"><AppIcon name="shield" :size="18" /><p>El torneo heredará el estado de su temporada y podrá contener una o varias competencias.</p></div><button class="btn-primary" :disabled="tournamentForm.processing || !seasons.some((season) => !['finished', 'archived', 'cancelled'].includes(season.status))"><AppIcon name="check" :size="16" /> Crear torneo</button></form></section>
                </div>
                <section><div class="mb-4 flex items-end justify-between gap-4"><div><p class="section-kicker">Historial operativo</p><h2 class="text-2xl font-bold text-slate-900">Temporadas registradas</h2></div><span class="text-sm font-semibold text-slate-500">{{ seasons.length }} en total</span></div><div class="grid gap-5 lg:grid-cols-2"><article v-for="season in seasons" :key="season.id" class="season-card"><div class="season-card-top"><div class="season-calendar"><AppIcon name="calendar" /><strong>{{ seasonYear(season.starts_on) }}</strong></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3>{{ season.name }}</h3><p>{{ formatDate(season.starts_on) }} — {{ formatDate(season.ends_on) }}</p></div><span :class="statusClass(season.status)">{{ labels[season.status] }}</span></div></div></div><div class="season-tournaments"><div v-for="tournament in season.tournaments" :key="tournament.id" class="tournament-row"><span><AppIcon name="trophy" :size="17" /></span><div><strong>{{ tournament.name }}</strong><small>{{ tournament.competitions_count }} competencia(s)</small></div><span :class="statusClass(tournament.status)">{{ labels[tournament.status] }}</span></div><p v-if="!season.tournaments.length" class="empty-state">Esta temporada todavía no tiene torneos.</p></div><footer v-if="!['archived'].includes(season.status)" class="season-actions"><button v-if="season.status === 'planning'" class="btn-secondary" @click="transitionSeason(season, 'registration')">Abrir inscripciones</button><button v-if="['planning', 'registration'].includes(season.status)" class="btn-primary" @click="transitionSeason(season, 'active')">Activar temporada</button><button v-if="season.status === 'active'" class="btn-secondary" @click="transitionSeason(season, 'finished')">Finalizar</button><button v-if="['finished', 'cancelled'].includes(season.status)" class="btn-secondary" @click="transitionSeason(season, 'archived')">Archivar</button></footer></article><div v-if="!seasons.length" class="competition-panel empty-state lg:col-span-2">Aún no hay temporadas registradas.</div></div></section>
            </div>

            <div v-if="activeTab === 'reglamentos'" class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                <section class="competition-panel overflow-hidden"><div class="competition-panel-heading"><span><AppIcon name="file" /></span><div><p>{{ editingRegulationId ? 'Edición segura' : 'Nueva versión' }}</p><h2>{{ editingRegulationId ? 'Editar borrador' : 'Crear reglamento' }}</h2></div></div><form class="space-y-6 p-5 sm:p-7" @submit.prevent="submitRegulation"><div><label class="form-label">Nombre del reglamento</label><input v-model="regulationForm.name" class="form-input" required><FormError :message="regulationForm.errors.name" /></div><fieldset class="form-block"><legend>Sistema de puntuación</legend><p>Define los puntos asignados según el resultado del partido.</p><div class="mt-4 grid gap-4 sm:grid-cols-3"><div><label class="form-label">Victoria</label><input v-model.number="regulationForm.points_win" class="form-input" type="number" min="-20" max="20" required></div><div><label class="form-label">Empate</label><input v-model.number="regulationForm.points_draw" class="form-input" type="number" min="-20" max="20" required></div><div><label class="form-label">Derrota</label><input v-model.number="regulationForm.points_loss" class="form-input" type="number" min="-20" max="20" required></div></div></fieldset><fieldset class="form-block"><legend>Incomparecencia y juego limpio</legend><p>Establece el marcador administrativo y la penalización disciplinaria.</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Goles local</label><input v-model.number="regulationForm.walkover_home_goals" class="form-input" type="number" min="0" max="99" required></div><div><label class="form-label">Goles visitante</label><input v-model.number="regulationForm.walkover_away_goals" class="form-input" type="number" min="0" max="99" required></div></div><div class="mt-4 grid gap-4 sm:grid-cols-3"><div><label class="form-label">Amarilla</label><input v-model.number="regulationForm.fair_play_yellow_points" class="form-input" type="number" min="0" max="20" required></div><div><label class="form-label">Doble amarilla</label><input v-model.number="regulationForm.fair_play_second_yellow_points" class="form-input" type="number" min="0" max="20" required></div><div><label class="form-label">Roja directa</label><input v-model.number="regulationForm.fair_play_red_points" class="form-input" type="number" min="0" max="20" required></div></div></fieldset><fieldset class="form-block"><legend>Criterios de desempate</legend><p>Ordena la prioridad con los controles laterales.</p><div class="mt-4 space-y-2"><div v-for="(criterion, index) in regulationForm.tiebreakers" :key="criterion" class="tiebreaker-row"><span>{{ index + 1 }}</span><strong>{{ labels[criterion] }}</strong><div><button type="button" :disabled="index === 0" @click="moveCriterion(index, -1)" aria-label="Subir criterio">↑</button><button type="button" :disabled="index === regulationForm.tiebreakers.length - 1" @click="moveCriterion(index, 1)" aria-label="Bajar criterio">↓</button></div></div></div><FormError :message="regulationForm.errors.tiebreakers" /></fieldset><div class="flex flex-wrap gap-3"><button class="btn-primary" :disabled="regulationForm.processing || !hasOperationalSettings"><AppIcon name="check" :size="16" /> {{ editingRegulationId ? 'Guardar borrador' : 'Crear borrador' }}</button><button v-if="editingRegulationId" type="button" class="btn-secondary" @click="editingRegulationId = null; regulationForm.reset()">Cancelar edición</button></div></form></section>
                <section class="space-y-4"><div class="mb-5"><p class="section-kicker">Versiones disponibles</p><h2 class="text-2xl font-bold">Reglamentos de la liga</h2><p class="mt-1 text-sm text-slate-500">Los reglamentos publicados quedan protegidos para conservar el historial.</p></div><article v-for="regulation in regulations" :key="regulation.id" class="regulation-card"><div class="flex items-start gap-4"><span class="regulation-icon"><AppIcon name="shield" variant="illustrated" :size="46" /></span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3>{{ regulation.name }}</h3><p>Versión {{ regulation.version }}</p></div><span :class="statusClass(regulation.status)">{{ labels[regulation.status] }}</span></div><div class="regulation-score"><span><small>Victoria</small><strong>{{ regulation.points_win }}</strong></span><span><small>Empate</small><strong>{{ regulation.points_draw }}</strong></span><span><small>Derrota</small><strong>{{ regulation.points_loss }}</strong></span><span><small>Incomparecencia</small><strong>{{ regulation.walkover_home_goals }}–{{ regulation.walkover_away_goals }}</strong></span></div><div class="mt-4"><small class="font-bold uppercase tracking-wider text-slate-400">Prioridad de desempate</small><div class="mt-2 flex flex-wrap gap-1.5"><span v-for="(item, index) in regulation.tiebreakers" :key="item.criterion" class="criterion-chip">{{ index + 1 }} · {{ labels[item.criterion] }}</span></div></div><div v-if="regulation.status === 'draft'" class="mt-5 flex gap-2"><button class="btn-secondary" @click="editRegulation(regulation)"><AppIcon name="edit" :size="16" /> Editar</button><button class="btn-primary" @click="publishRegulation(regulation)"><AppIcon name="check" :size="16" /> Publicar</button></div><p v-else class="protected-note"><AppIcon name="lock" :size="15" /> Contenido protegido. Crea una nueva versión para modificarlo.</p></div></div></article><div v-if="!regulations.length" class="competition-panel empty-state">Aún no hay reglamentos.</div></section>
            </div>

            <div v-if="activeTab === 'competencias'" class="mt-6 space-y-6">
                <section class="competition-panel overflow-hidden"><div class="competition-panel-heading"><span><AppIcon name="trophy" /></span><div><p>Estructura competitiva</p><h2>Nueva competencia</h2></div></div><form class="grid gap-6 p-5 sm:p-7 xl:grid-cols-3" @submit.prevent="submitCompetition"><div class="form-block xl:col-span-2"><h3>Identidad y clasificación</h3><div class="mt-4 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label class="form-label">Nombre</label><input v-model="competitionForm.name" class="form-input" required placeholder="Primera fuerza · Libre"><FormError :message="competitionForm.errors.name" /></div><div><label class="form-label">Torneo</label><select v-model="competitionForm.tournament_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="tournament in availableTournaments" :key="tournament.id" :value="tournament.id">{{ tournament.season.name }} · {{ tournament.name }}</option></select></div><div><label class="form-label">Formato</label><select v-model="competitionForm.format" class="form-input"><option value="round_robin">Todos contra todos</option><option value="double_round_robin">Ida y vuelta</option><option value="knockout">Eliminación directa</option><option value="groups_knockout">Grupos + eliminatoria</option><option value="manual">Manual</option></select></div><div><label class="form-label">División</label><select v-model="competitionForm.division_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option></select><FormError :message="competitionForm.errors.division_id" /></div><div><label class="form-label">Categoría</label><select v-model="competitionForm.category_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div><div class="sm:col-span-2"><label class="form-label">Reglamento publicado</label><select v-model="competitionForm.regulation_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="regulation in usableRegulations" :key="regulation.id" :value="regulation.id">{{ regulation.name }} v{{ regulation.version }}</option></select><FormError :message="competitionForm.errors.regulation_id" /></div></div></div><div class="space-y-6"><div class="form-block"><h3>Formato de juego</h3><div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-1"><div><label class="form-label">Vueltas de fase regular</label><input v-model.number="competitionForm.regular_leg_count" class="form-input" type="number" min="1" max="4" required></div><div><label class="form-label">Partidos por eliminatoria</label><input v-model.number="competitionForm.knockout_leg_count" class="form-input" type="number" min="1" max="2" required></div></div></div></div><div class="form-block"><h3>Cupos y plantillas</h3><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Mínimo de equipos</label><input v-model.number="competitionForm.minimum_teams" class="form-input" type="number" min="2" max="200" placeholder="Opcional"></div><div><label class="form-label">Máximo de equipos</label><input v-model.number="competitionForm.maximum_teams" class="form-input" type="number" min="2" max="200" placeholder="Opcional"><FormError :message="competitionForm.errors.maximum_teams" /></div><div><label class="form-label">Mínimo de jugadores</label><input v-model.number="competitionForm.minimum_roster_size" class="form-input" type="number" min="1" max="100" required></div><div><label class="form-label">Máximo de jugadores</label><input v-model.number="competitionForm.maximum_roster_size" class="form-input" type="number" min="1" max="100" required><FormError :message="competitionForm.errors.maximum_roster_size" /></div></div></div><div class="form-block"><h3>Periodo de registro</h3><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Apertura</label><input v-model="competitionForm.registration_starts_at" class="form-input" type="datetime-local"></div><div><label class="form-label">Cierre</label><input v-model="competitionForm.registration_ends_at" class="form-input" type="datetime-local"><FormError :message="competitionForm.errors.registration_ends_at" /></div></div></div><div class="flex items-center justify-end xl:col-span-3"><button class="btn-primary min-w-48" :disabled="competitionForm.processing || !availableTournaments.length || !divisions.length || !categories.length || !usableRegulations.length"><AppIcon name="check" :size="16" /> Crear competencia</button></div></form></section>
                <section><div class="competition-toolbar"><div class="relative"><AppIcon name="chart" :size="17" class="absolute left-3.5 top-3 text-slate-400" /><input v-model="competitionSearch" class="form-input pl-10" placeholder="Buscar competencia, torneo o división"></div><select v-model="competitionStatus" class="form-input"><option value="all">Todos los estados</option><option value="planning">Planeación</option><option value="registration">Inscripciones</option><option value="active">Activas</option><option value="finished">Finalizadas</option><option value="archived">Archivadas</option><option value="cancelled">Canceladas</option></select><span>{{ visibleCompetitions.length }} resultado(s)</span></div><div class="mt-4 grid gap-5 lg:grid-cols-2"><article v-for="competition in visibleCompetitions" :key="competition.id" class="competition-card"><header><div class="competition-card-icon"><AppIcon name="trophy" variant="illustrated" :size="46" /></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3>{{ competition.name }}</h3><p>{{ competition.tournament.season.name }} · {{ competition.tournament.name }}</p></div><span :class="statusClass(competition.status)">{{ labels[competition.status] }}</span></div></div></header><dl><div><dt>División</dt><dd>{{ competition.division.name }}</dd></div><div><dt>Categoría</dt><dd>{{ competition.category.name }}</dd></div><div><dt>Formato</dt><dd>{{ labels[competition.format] }}</dd></div><div><dt>Plantilla</dt><dd>{{ competition.minimum_roster_size }}–{{ competition.maximum_roster_size }} jugadores</dd></div></dl><footer><span><AppIcon name="shield" :size="16" /> {{ competition.regulation.name }} · v{{ competition.regulation.version }}</span></footer></article><div v-if="!visibleCompetitions.length" class="competition-panel empty-state lg:col-span-2">No hay competencias que coincidan con los filtros.</div></div></section>
            </div>
        </section>
    </AppLayout>
</template>
