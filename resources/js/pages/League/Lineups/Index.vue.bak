<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import AppIcon from '../../../components/AppIcon.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type AvailablePlayer = { id: number; name: string; jersey_number: number; position: string };
type LineupPlayer = { player_registration_id: number; name: string; jersey_number: number; position: string; role: 'starter' | 'substitute'; is_captain: boolean };
type Lineup = { id: number; status: 'draft' | 'submitted'; submitted_at: string | null; submitted_by: { id: number; name: string } | null; players: LineupPlayer[] };
type MatchTeam = { participation_id: number; name: string; team: { id: number; name: string; short_name: string; crest_url: string | null }; editable: boolean; lineup: Lineup | null; available_players: AvailablePlayer[] };
type Match = { id: number; status: string; scheduled_at: string | null; competition: { id: number; name: string }; matchday: { id: number; name: string; number: number }; field: { id: number; name: string; venue: { id: number; name: string } } | null; teams: MatchTeam[] };
type PageLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{ matches: { data: Match[]; links: PageLink[]; total: number } }>();
const editMatch = ref<Match | null>(null);
const editTeam = ref<MatchTeam | null>(null);
const expandedMatches = ref(new Set<number>());
const competitionFilter = ref('all');
const searchText = ref('');
const editorTab = ref<'starter' | 'substitute' | 'details'>('starter');
const lineupForm = useForm<{ players: Array<{ player_registration_id: number; role: 'starter' | 'substitute'; is_captain: boolean }>; reason: string }>({ players: [], reason: '' });

const positions: Record<string, string> = { goalkeeper: 'Portero', defender: 'Defensa', midfielder: 'Mediocampista', forward: 'Delantero' };
const matchStatuses: Record<string, string> = { scheduled: 'Programado', in_progress: 'En curso', finished: 'Finalizado', suspended: 'Suspendido', postponed: 'Aplazado' };
const selectedCount = computed(() => lineupForm.players.length);
const startersCount = computed(() => lineupForm.players.filter((item) => item.role === 'starter').length);
const substitutesCount = computed(() => lineupForm.players.filter((item) => item.role === 'substitute').length);
const validStarterCount = computed(() => startersCount.value >= 8 && startersCount.value <= 11);
const submittedLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => team.lineup?.status === 'submitted').length);
const draftLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => team.lineup?.status === 'draft').length);
const uniqueCompetitions = computed(() => Array.from(new Map(props.matches.data.map((match) => [match.competition.id, match.competition])).values()));
const filteredMatches = computed(() => props.matches.data.filter((match) => (competitionFilter.value === 'all' || String(match.competition.id) === competitionFilter.value) && (!searchText.value.trim() || `${match.matchday.name} ${match.competition.name} ${match.teams.map((team) => team.name).join(' ')}`.toLocaleLowerCase('es').includes(searchText.value.trim().toLocaleLowerCase('es')))));
const pendingLineups = computed(() => props.matches.data.flatMap((match) => match.teams).filter((team) => !team.lineup).length);

function localDateTime(value: string | null): string {
    return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin horario';
}
function openEditor(match: Match, team: MatchTeam): void {
    editMatch.value = match; editTeam.value = team; editorTab.value = 'starter'; lineupForm.clearErrors();
    lineupForm.players = (team.lineup?.players ?? []).map((player) => ({ player_registration_id: player.player_registration_id, role: player.role, is_captain: player.is_captain }));
    lineupForm.reason = team.lineup ? 'Actualización del borrador de alineación' : 'Captura inicial de alineación';
}
function closeEditor(): void { editMatch.value = null; editTeam.value = null; lineupForm.reset(); }
function lineupState(team: MatchTeam): string { return team.lineup?.status === 'submitted' ? 'Cerrada' : team.lineup ? 'Borrador' : 'Pendiente'; }
function readyTeams(match: Match): number { return match.teams.filter((team) => team.lineup?.status === 'submitted').length; }
function teamCrest(team: MatchTeam | undefined): string | null { return team?.team.crest_url ?? null; }
function selection(id: number) { return lineupForm.players.find((item) => item.player_registration_id === id); }
function togglePlayer(player: AvailablePlayer, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked && !selection(player.id)) lineupForm.players.push({ player_registration_id: player.id, role: editorTab.value === 'substitute' || startersCount.value >= 11 ? 'substitute' : 'starter', is_captain: false });
    else lineupForm.players = lineupForm.players.filter((item) => item.player_registration_id !== player.id);
}
function selectAllStarters(): void {
    if (!editTeam.value) return;
    for (const player of editTeam.value.available_players) {
        if (startersCount.value >= 11) break;
        const existing = selection(player.id);
        if (existing) { existing.role = 'starter'; continue; }
        lineupForm.players.push({ player_registration_id: player.id, role: 'starter', is_captain: false });
    }
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
        <section class="lineups-premium">
            <template v-if="!editMatch || !editTeam">
                <header class="lp-hero">
                    <div class="lp-hero-main">
                        <div>
                            <p class="lp-eyebrow">Módulo 11 · Operación del partido</p>
                            <h1>Alineaciones</h1>
                            <p>Prepara titulares, suplentes y capitán antes del encuentro.</p>
                        </div>
                        <div class="lp-rule"><span>Regla de titulares</span><strong>8 mínimo · 11 máximo</strong></div>
                    </div>
                </header>
                <div class="lp-kpis">
                    <article class="lp-kpi"><span class="lp-kpi-icon"><AppIcon name="calendar" variant="illustrated" :size="42" /></span><div><p>Partidos visibles</p><strong>{{ matches.total }}</strong></div></article>
                    <article class="lp-kpi"><span class="lp-kpi-icon"><AppIcon name="check" variant="illustrated" :size="42" /></span><div><p>Alineaciones cerradas</p><strong>{{ submittedLineups }}</strong></div></article>
                    <article class="lp-kpi"><span class="lp-kpi-icon"><AppIcon name="edit" variant="illustrated" :size="42" /></span><div><p>Borradores guardados</p><strong>{{ draftLineups }}</strong></div></article>
                    <article class="lp-kpi"><span class="lp-kpi-icon"><AppIcon name="clock" variant="illustrated" :size="42" /></span><div><p>Pendientes de captura</p><strong>{{ pendingLineups }}</strong></div></article>
                </div>
                <div class="lp-notice"><AppIcon name="badge" color="currentColor" :size="21"/><p><strong>Proceso recomendado:</strong> captura la alineación, guarda el borrador y verifica que existan entre 8 y 11 titulares antes de enviarla.</p></div>
                <div class="lp-filters">
                    <label><span>Competencia</span><select v-model="competitionFilter" class="form-input"><option value="all">Todas las competencias</option><option v-for="comp in uniqueCompetitions" :key="comp.id" :value="String(comp.id)">{{ comp.name }}</option></select></label>
                    <label><span>Buscar jornada o equipo</span><div class="lp-search"><AppIcon name="sliders" :size="18" /><input v-model="searchText" type="search" placeholder="Buscar jornada o equipo..." /></div></label>
                </div>
                <div v-if="!filteredMatches.length" class="lp-empty"><AppIcon name="lineup" variant="illustrated" :size="56"/><h2>No se encontraron partidos</h2><p>Prueba con otra competencia o búsqueda.</p></div>
                <div v-else class="lp-match-list">
                    <article v-for="match in filteredMatches" :key="match.id" class="lp-match" :class="{ 'lp-match--open': expandedMatches.has(match.id) }">
                        <button class="lp-match-header" type="button" :aria-expanded="expandedMatches.has(match.id)" @click="toggleMatch(match.id)">
                            <span class="lp-match-chevron"><AppIcon name="menu" :size="18" /></span>
                            <span class="lp-match-info"><span class="lp-match-title">{{ match.matchday.name }} <span class="lp-status" :class="matchStatusClass(match.status)">{{ matchStatuses[match.status] || match.status }}</span></span><span class="lp-match-competition">{{ match.competition.name }}</span><span class="lp-match-meta"><span><AppIcon name="calendar" :size="15"/> {{ localDateTime(match.scheduled_at) }}</span><span v-if="match.field"><AppIcon name="field" :size="15"/> {{ match.field.venue.name }} · {{ match.field.name }}</span></span></span>
                            <span class="lp-match-progress"><strong>{{ readyTeams(match) }} de {{ match.teams.length }} equipos</strong><span class="lp-progress-track"><span :style="{ width: `${match.teams.length ? (readyTeams(match) / match.teams.length) * 100 : 0}%` }"></span></span></span>
                            <span class="lp-expand"><span class="lp-chevron-glyph" :class="expandedMatches.has(match.id) ? 'lp-rotated' : ''"></span></span>
                        </button>
                        <div v-if="expandedMatches.has(match.id)" class="lp-match-body">
                            <div class="lp-match-versus"><section v-for="(team, i) in match.teams" :key="team.participation_id" class="lp-team-tile">
                                <div class="lp-team-line"><span class="lp-crest"><img v-if="teamCrest(team)" :src="teamCrest(team)!" :alt="`Escudo de ${team.name}`" @error="($event.target as HTMLImageElement).style.display = 'none'"/><AppIcon name="shield" :size="32" /></span><span><strong>{{ team.name }}</strong><small>{{ i === 0 ? 'Local' : 'Visitante' }}</small></span></div>
                                <span class="lp-state" :class="team.lineup?.status === 'submitted' ? 'lp-state-done' : team.lineup?.status === 'draft' ? 'lp-state-draft' : ''">{{ lineupState(team) }}</span>
                                <p>{{ team.lineup ? `${lineupPlayers(team,'starter').length} titulares · ${lineupPlayers(team,'substitute').length} suplentes` : 'Aún no se ha capturado la alineación.' }}</p>
                                <div class="lp-tile-actions"><button v-if="team.editable" class="lp-primary" @click="openEditor(match,team)"><AppIcon name="user" :size="16"/> {{ team.lineup ? 'Editar borrador' : 'Capturar alineación' }}</button><button v-if="team.editable && team.lineup?.status === 'draft'" class="lp-outline" :disabled="!canSubmit(team)" @click="submit(match,team)">Enviar y cerrar</button><span v-if="!team.editable && team.lineup?.status === 'submitted'" class="lp-frozen"><AppIcon name="check" :size="15"/> Enviada</span></div>
                            </section><span class="lp-vs">VS</span></div>
                        </div>
                    </article>
                </div>
                <nav v-if="matches.links.length > 3" class="lp-pagination"><Link v-for="link in matches.links" :key="link.label" :href="link.url ?? ''" :class="{ 'lp-page-current': link.active, 'lp-page-disabled': !link.url }" v-html="link.label" /></nav>
            </template>
            <template v-else>
                <nav class="lp-breadcrumb"><button type="button" @click="closeEditor">Alineaciones</button><span>›</span><strong>Captura de alineación</strong></nav>
                <header class="lp-hero lp-hero--editor"><div class="lp-hero-main"><div><p class="lp-eyebrow">Módulo 11 · Operación del partido</p><h1>Captura de alineación</h1><p>Registra titulares, suplentes y capitán para el partido.</p></div><div class="lp-rule"><span>Regla de titulares</span><strong>8 mínimo · 11 máximo</strong></div></div></header>
                <div class="lp-editor-grid">
                    <div class="lp-editor-main">
                        <section class="lp-editor-match"><div><span class="lp-pill">{{ editMatch.matchday.name }}</span><strong>{{ editMatch.competition.name }}</strong><small><AppIcon name="calendar" :size="14"/> {{ localDateTime(editMatch.scheduled_at) }} <span v-if="editMatch.field">· {{ editMatch.field.venue.name }} · {{ editMatch.field.name }}</span></small></div><div class="lp-match-team-pair"><template v-for="(team,i) in editMatch.teams" :key="team.participation_id"><span v-if="i > 0" class="lp-vs">VS</span><span class="lp-team-inline"><span class="lp-crest lp-crest-small"><AppIcon name="shield" :size="25"/><img v-if="teamCrest(team)" :src="teamCrest(team)!" :alt="`Escudo de ${team.name}`" @error="($event.target as HTMLImageElement).style.display = 'none'"/></span><strong>{{ team.name }}</strong></span></template></div></section>
                        <section class="lp-editor-team"><div class="lp-editor-team-header"><div class="lp-team-line"><span class="lp-crest"><AppIcon name="shield" :size="31"/><img v-if="teamCrest(editTeam)" :src="teamCrest(editTeam)!" :alt="`Escudo de ${editTeam.name}`" @error="($event.target as HTMLImageElement).style.display = 'none'"/></span><span><strong>{{ editTeam.name }}</strong><small>Edición de alineación · {{ editTeam.lineup?.status === 'draft' ? 'Borrador' : 'Nueva captura' }}</small></span></div><span class="lp-state lp-state-draft">{{ editTeam.lineup ? 'Borrador' : 'Pendiente' }}</span></div>
                            <div class="lp-editor-tabs"><button type="button" :class="{active:editorTab==='starter'}" @click="editorTab='starter'"><AppIcon name="users" :size="17"/> Titulares ({{ startersCount }})</button><button type="button" :class="{active:editorTab==='substitute'}" @click="editorTab='substitute'"><AppIcon name="user" :size="17"/> Suplentes ({{ substitutesCount }})</button><button type="button" :class="{active:editorTab==='details'}" @click="editorTab='details'"><AppIcon name="file" :size="17"/> Detalle</button></div>
                            <div v-if="editorTab==='details'" class="lp-details"><p>Selecciona jugadores en Titulares o Suplentes. Cada jugador puede tener una sola función y únicamente un capitán. La alineación definitiva debe tener de 8 a 11 titulares.</p><p><strong>Seleccionados:</strong> {{ selectedCount }} · <strong>Titulares:</strong> {{ startersCount }} · <strong>Suplentes:</strong> {{ substitutesCount }}</p><p><strong>Capitán:</strong> {{ editTeam.available_players.find(p=>selection(p.id)?.is_captain)?.name || 'Sin designar' }}</p></div>
                            <template v-else><div class="lp-roster-head"><span>{{ editorTab === 'starter' ? 'Jugadores titulares' : 'Jugadores suplentes' }}</span><span>{{ editorTab === 'starter' ? `${startersCount}/11` : substitutesCount }} seleccionados</span></div><div v-if="!editTeam.available_players.length" class="lp-empty"><p>No hay jugadores activos y aprobados disponibles para esta plantilla.</p></div><div v-else class="lp-roster"><div class="lp-roster-header"><span>Incluir</span><span>#</span><span>Jugador</span><span>Posición</span><span>Capitán</span></div><div v-for="player in editTeam.available_players.filter(p => selection(p.id)?.role === editorTab || !selection(p.id))" :key="player.id" class="lp-roster-row"><span><input type="checkbox" :checked="!!selection(player.id)" @change="togglePlayer(player,$event)" /></span><span class="lp-shirt-num">{{ player.jersey_number ?? '—' }}</span><strong>{{ player.name }}</strong><span>{{ positions[player.position] || player.position }}</span><span><button v-if="selection(player.id)?.role==='starter'" type="button" class="lp-captain" :class="{'lp-captain-active':selection(player.id)?.is_captain}" @click="setCaptain(player.id)" :aria-label="`Designar capitán a ${player.name}`"><AppIcon name="shield" :size="17"/></button><span v-else>—</span></span></div></div><div class="lp-tab-footer"><button type="button" class="lp-outline" @click="editorTab = editorTab==='starter' ? 'substitute':'starter'">Ver {{ editorTab==='starter'?'suplentes':'titulares' }}</button><span v-if="editorTab==='starter'" :class="validStarterCount?'text-emerald-700':'text-amber-700'">{{ validStarterCount?'Cantidad válida de titulares':'Se requieren entre 8 y 11 titulares' }}</span></div></template>
                        </section>
                        <section class="lp-editor-extra"><label for="lineup-reason" class="form-label">Motivo del guardado</label><input id="lineup-reason" v-model="lineupForm.reason" class="form-input" maxlength="500" required/><FormError :message="lineupForm.errors.reason"/><FormError :message="lineupForm.errors.players || (lineupForm.errors as Record<string,string>).lineup"/></section>
                    </div>
                    <aside class="lp-editor-aside"><section class="lp-side-card"><h3><AppIcon name="chart" :size="20"/> Progreso del partido</h3><p>Tu alineación: {{ startersCount }} titulares</p><div class="lp-progress-track"><span :style="{width: `${Math.min(100,(startersCount/11)*100)}%`}"></span></div><p class="lp-side-caption">{{ validStarterCount?'Cumple el rango de titulares':'Objetivo: entre 8 y 11 titulares' }}</p></section><section class="lp-side-card"><h3><AppIcon name="check" :size="20"/> Checklist</h3><div class="lp-check"><AppIcon :name="validStarterCount?'check':'clock'" :size="20"/><span><strong>{{ editTeam.name }}</strong><small>{{ startersCount }} titulares seleccionados</small></span></div><div class="lp-check"><AppIcon :name="lineupForm.players.some(p=>p.is_captain)?'check':'clock'" :size="20"/><span><strong>Capitán</strong><small>{{ lineupForm.players.some(p=>p.is_captain)?'Asignado':'Pendiente de asignación' }}</small></span></div></section><section class="lp-side-card"><h3><AppIcon name="badge" :size="20"/> Acciones</h3><button type="button" class="lp-primary lp-full" :disabled="lineupForm.processing || !selectedCount" @click="save"><AppIcon name="check" :size="17"/> Guardar borrador</button><button type="button" class="lp-outline lp-full" @click="editorTab='details'"><AppIcon name="file" :size="17"/> Revisar detalle</button><button type="button" class="lp-outline lp-full" @click="closeEditor"><AppIcon name="history" :size="17"/> Volver a alineaciones</button><p class="lp-side-caption">Primero guarda el borrador. Después podrás enviarlo y cerrarlo desde la tarjeta del partido.</p></section></aside>
                </div>
            </template>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.lineups-premium{max-width:1540px;margin:auto;padding-bottom:2rem;color:#10254b}.lp-hero{border-radius:26px;overflow:hidden;color:#fff;background:linear-gradient(100deg,rgba(8,43,95,.88),rgba(17,78,157,.70)),url('/images/public/league-stadium.jpg') center 55%/cover;box-shadow:0 16px 32px rgba(12,39,74,.13)}.lp-hero-main{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.8rem 2rem;flex-wrap:wrap}.lp-eyebrow{font-size:11px;letter-spacing:.21em;text-transform:uppercase;font-weight:800;color:#b9d8ff}.lp-hero h1{font-size:clamp(1.8rem,3vw,2.5rem);line-height:1.15;font-weight:800;margin:.5rem 0}.lp-hero p:not(.lp-eyebrow){font-size:15px;color:#eef5ff}.lp-rule{padding:13px 24px;background:linear-gradient(135deg,#fff,#e9f1fb);color:#152b4d;border-radius:17px;box-shadow:0 6px 18px #071a3040}.lp-rule span{display:block;font-size:13px;font-weight:700}.lp-rule strong{display:block;font-size:21px;color:#1253a6;margin-top:4px}.lp-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-top:18px}.lp-kpi{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid #dfebf8;border-radius:17px;min-height:99px;padding:17px 20px;box-shadow:0 6px 20px #0e284014}.lp-kpi-icon{flex-shrink:0}.lp-kpi p{font-size:14px;font-weight:600}.lp-kpi strong{font-size:29px;font-weight:800;color:#104caa;line-height:1.3}.lp-notice{display:flex;gap:15px;align-items:center;padding:15px 18px;margin:16px 0;background:#f0f7ff;border:1px solid #cddffa;border-radius:16px;color:#224878;font-size:14px}.lp-notice strong{color:#0e56ae}.lp-filters{display:grid;grid-template-columns:minmax(230px,370px) minmax(260px,430px);justify-content:space-between;gap:24px;margin:20px 0}.lp-filters label{display:grid;gap:6px;font-size:13px;font-weight:700}.lp-search{display:flex;align-items:center;gap:9px;border:1px solid #cfdceb;background:white;border-radius:11px;padding:0 13px;height:43px}.lp-search input{min-width:0;width:100%;outline:0;background:transparent;font-size:14px}.lp-match-list{display:grid;gap:12px}.lp-match{background:#fff;border:1px solid #dce6f5;border-radius:18px;box-shadow:0 5px 17px #0e284018;overflow:hidden}.lp-match--open{border-color:#86b4ff}.lp-match-header{width:100%;display:flex;align-items:center;gap:19px;text-align:left;padding:17px 20px;min-height:88px}.lp-match-chevron{color:#24569a}.lp-match-info{display:grid;gap:5px;min-width:0;flex:1}.lp-match-title{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:18px;font-weight:800}.lp-match-competition{font-size:13px;color:#344d72}.lp-match-meta{display:flex;flex-wrap:wrap;gap:15px;font-size:12px;color:#4d668d}.lp-match-meta span{display:inline-flex;align-items:center;gap:5px}.lp-status{font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px}.lp-match-progress{width:230px;display:flex;align-items:center;gap:14px;font-size:13px;white-space:nowrap}.lp-progress-track{height:9px;border-radius:30px;background:#e6ecf4;display:block;flex:1;overflow:hidden}.lp-progress-track>span{height:100%;display:block;border-radius:inherit;background:#1574ef;transition:width .3s}.lp-expand{display:grid;place-items:center;width:37px;height:37px;border:1px solid #cedceb;border-radius:11px;color:#194879;flex-shrink:0}.lp-chevron-glyph{width:10px;height:10px;display:block;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .2s}.lp-chevron-glyph.lp-rotated{transform:rotate(225deg)}.lp-match-body{padding:0 20px 20px}.lp-match-versus{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:40px;padding-top:12px}.lp-team-tile{border:1px solid #dce5f1;border-radius:15px;padding:20px 20px 16px;position:relative;min-width:0}.lp-team-line{display:flex;align-items:center;gap:15px}.lp-crest{display:grid;place-items:center;width:56px;height:56px;border-radius:14px;background:#f1f6fe;border:1px solid #e0eaf8;flex-shrink:0;color:#1d5db6;overflow:hidden}.lp-crest{position:relative}.lp-crest img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:4px;background:#f1f6fe}.lp-team-line strong{display:block;font-size:17px;font-weight:800}.lp-team-line small{display:block;font-size:13px;color:#667f9e;margin-top:3px}.lp-state{display:inline-block;border-radius:15px;padding:4px 11px;background:#e8edf3;color:#405b80;font-size:11px;font-weight:750;position:absolute;right:15px;top:17px}.lp-state-done{background:#dff8e9;color:#087b4a}.lp-state-draft{background:#fff1d5;color:#986000}.lp-team-tile>p{font-size:13px;color:#657b98;margin:11px 0}.lp-tile-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.lp-primary,.lp-outline{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;font-size:13px;font-weight:700;min-height:37px;padding:8px 14px}.lp-primary{color:#fff;background:linear-gradient(135deg,#1777f3,#0952bf);box-shadow:0 4px 8px #1b61b52a}.lp-outline{color:#184c94;border:1px solid #c6d8ee;background:white}.lp-primary:disabled,.lp-outline:disabled{opacity:.5;cursor:not-allowed}.lp-frozen{color:#087b4a;font-size:12px;display:inline-flex;gap:4px;align-items:center}.lp-vs{position:absolute;z-index:1;top:50%;left:50%;transform:translate(-50%,-50%);font-size:13px;font-weight:800;background:#e9f1fc;color:#26599e;border-radius:10px;padding:8px 12px}.lp-empty{text-align:center;display:grid;justify-items:center;gap:10px;padding:52px 20px;border:1px dashed #d5e1ef;background:#fff;border-radius:16px}.lp-empty h2{font-weight:800;font-size:19px}.lp-empty p{font-size:13px;color:#607b9b}.lp-pagination{display:flex;gap:9px;justify-content:center;margin:25px 0;flex-wrap:wrap}.lp-pagination a{border:1px solid #ceddec;padding:8px 12px;border-radius:9px;background:white}.lp-page-current{background:#1465d4!important;color:white}.lp-page-disabled{pointer-events:none;opacity:.4}.lp-breadcrumb{display:flex;gap:12px;align-items:center;color:#597499;font-size:13px;margin:0 0 18px}.lp-breadcrumb button{color:#1b5ebd}.lp-breadcrumb strong{color:#18375d}.lp-hero--editor{margin-bottom:20px}.lp-editor-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.75fr);gap:18px}.lp-editor-main{min-width:0;display:grid;align-content:start;gap:16px}.lp-editor-match,.lp-editor-team,.lp-editor-extra,.lp-side-card{background:#fff;border:1px solid #dfe8f4;border-radius:16px;box-shadow:0 5px 20px #14365a12}.lp-editor-match{padding:18px;display:flex;align-items:center;justify-content:space-between;gap:18px}.lp-editor-match>div:first-child{display:grid;gap:5px}.lp-pill{background:#e9f3ff;color:#1465d4;padding:6px 11px;border-radius:10px;font-size:12px;font-weight:700;width:max-content}.lp-editor-match strong{font-size:15px}.lp-editor-match small{display:flex;align-items:center;gap:5px;font-size:12px;color:#55759d}.lp-match-team-pair{display:flex;align-items:center;gap:14px}.lp-team-inline{display:flex;align-items:center;gap:7px;font-size:12px}.lp-crest-small{width:40px;height:40px}.lp-match-team-pair .lp-vs{position:static;transform:none}.lp-editor-team-header{display:flex;align-items:center;justify-content:space-between;padding:18px;gap:12px}.lp-editor-team-header .lp-state{position:static}.lp-editor-tabs{display:flex;gap:4px;margin:0 17px;border:1px solid #d8e4f4;border-radius:10px;overflow:hidden}.lp-editor-tabs button{display:flex;align-items:center;justify-content:center;gap:9px;flex:1;padding:12px 8px;font-size:13px;font-weight:700;color:#405a7f}.lp-editor-tabs button.active{color:#075cc5;background:#eaf3ff;border-bottom:3px solid #2378f0}.lp-roster-head{display:flex;justify-content:space-between;align-items:center;margin:20px 18px 10px;font-weight:800;font-size:13px;color:#1c3861}.lp-roster-head span:last-child{font-weight:500;color:#607b9b}.lp-roster{margin:0 17px 18px;border:1px solid #ebf0f6;border-radius:12px;overflow:hidden}.lp-roster-header,.lp-roster-row{display:grid;grid-template-columns:64px 43px minmax(140px,1fr) minmax(105px,.65fr) 72px;align-items:center;gap:8px;padding:10px 13px}.lp-roster-header{background:#f5f8fc;color:#4e6586;font-size:12px;font-weight:800}.lp-roster-row{border-top:1px solid #e6edf7;font-size:13px}.lp-roster-row strong{font-weight:700}.lp-roster-row>span:nth-child(4){color:#507096}.lp-shirt-num{background:#e9f3ff;padding:4px;text-align:center;border-radius:7px;font-weight:800}.lp-captain{border:1px solid #cfdceb;border-radius:100px;width:29px;height:29px;display:grid;place-items:center;color:#a2b2c8}.lp-captain-active{color:#0c65d5;background:#e5f1ff;border-color:#7bb4ff}.lp-tab-footer{padding:0 17px 17px;display:flex;align-items:center;gap:15px;justify-content:space-between;font-size:12px}.lp-details{padding:30px;display:grid;gap:14px;color:#4a6487;font-size:14px}.lp-editor-extra{padding:17px}.lp-editor-aside{display:grid;align-content:start;gap:16px}.lp-side-card{padding:19px;display:grid;gap:14px}.lp-side-card h3{display:flex;align-items:center;gap:9px;font-weight:800;font-size:17px}.lp-side-card>p{font-size:13px;color:#456384}.lp-side-card .lp-progress-track{height:10px}.lp-side-caption{font-size:12px!important;color:#6b82a1!important}.lp-check{display:flex;align-items:center;gap:13px;border-bottom:1px solid #e8eff7;padding-bottom:13px}.lp-check strong{font-size:13px;display:block}.lp-check small{font-size:12px;color:#6983a3}.lp-full{width:100%}@media(max-width:1050px){.lp-kpis{grid-template-columns:repeat(2,1fr)}.lp-editor-grid{grid-template-columns:1fr}.lp-match-progress{width:155px}.lp-editor-aside{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.lp-hero-main{padding:1.3rem}.lp-filters,.lp-match-versus,.lp-editor-aside{grid-template-columns:1fr}.lp-match-header{flex-wrap:wrap}.lp-match-progress{width:100%;order:3}.lp-match-versus{gap:14px}.lp-match-versus>.lp-vs{position:static;transform:none;justify-self:center}.lp-kpis{grid-template-columns:1fr 1fr}.lp-editor-match,.lp-match-team-pair{flex-wrap:wrap}.lp-roster{overflow-x:auto}.lp-roster-header,.lp-roster-row{min-width:610px}}@media(max-width:450px){.lp-kpis{grid-template-columns:1fr}.lp-team-tile{padding:16px}.lp-state{position:static;margin-top:9px}}
</style>
