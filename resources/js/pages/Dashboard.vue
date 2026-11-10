<script setup lang="ts">
import AppLayout from '../layouts/AppLayout.vue';
import AppIcon from '../components/AppIcon.vue';
import type { AppPageProps } from '../types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type DashboardSummary = { pendingTasks: number; upcomingMatches: number; notifications: number; activeTeams: number };
type UpcomingMatch = { id: number; competition: string | null; matchday: string; homeTeam: string; awayTeam: string; homeCrestUrl: string | null; awayCrestUrl: string | null; scheduledAt: string | null; field: string };
type ActivityEntry = { id: number; action: string; actor: string; createdAt: string | null };

const props = defineProps<{ summary: DashboardSummary; upcomingMatches: UpcomingMatch[]; recentActivity: ActivityEntry[] }>();
const page = usePage<AppPageProps>();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const can = (permission: string) => permissions.value.includes(permission);
const context = computed(() => page.props.activeContext);

const quickActions = computed(() => [
    { label: 'Crear o programar partido', description: 'Organiza la próxima jornada', href: '/liga/calendario', icon: 'calendar', show: can('schedule.manage') },
    { label: 'Validar jugador', description: 'Revisa registros pendientes', href: '/liga/jugadores', icon: 'badge', show: can('players.manage') },
    { label: 'Revisar cédula arbitral', description: 'Da seguimiento al partido', href: '/liga/cedulas', icon: 'clipboard', show: can('reports.view') },
    { label: 'Generar credenciales', description: 'Consulta plantillas por equipo', href: '/liga/jugadores', icon: 'id', show: can('credentials.manage') || can('players.manage') },
    { label: 'Publicar avisos', description: 'Informa a los participantes', href: '/liga/avisos', icon: 'bell', show: can('notifications.manage') },
    { label: 'Consultar estadísticas', description: 'Resultados y posiciones', href: '/liga/resultados', icon: 'chart', show: can('statistics.view') },
].filter((action) => action.show).slice(0, 4));

const actionLabels: Record<string, string> = {
    'team.created': 'Se registró un equipo',
    'team.updated': 'Se actualizaron los datos de un equipo',
    'team.participation.requested': 'Se solicitó una inscripción de equipo',
    'team.participation.status_changed': 'Cambió el estado de una inscripción',
    'player.created': 'Se registró un jugador',
    'player.registration.requested': 'Se solicitó el registro de un jugador',
    'player.registration.status_changed': 'Cambió el estado de un jugador',
    'player.credential.issued': 'Se emitió una credencial',
    'schedule.generated': 'Se generó un calendario',
    'schedule.match.created': 'Se creó un partido',
    'schedule.match.programmed': 'Se programó un partido',
    'schedule.match.rescheduled': 'Se reprogramó un partido',
    'schedule.matchday.published': 'Se publicó una jornada',
    'lineup.submitted': 'Se envió una alineación',
    'lineup.saved': 'Se guardó una alineación',
    'league.updated': 'Se actualizó la configuración de la liga',
    'league.identity.updated': 'Se actualizó la identidad de la liga',
    'player.credentials.downloaded': 'Se descargaron credenciales',
    'player.credentials.previewed': 'Se consultaron credenciales',
    'match_report.submitted': 'Se envió una cédula arbitral',
    'match_report.approved': 'Se aprobó una cédula arbitral',
    'finance.payment.received': 'Se registró un pago',
    'notification.notice.published': 'Se publicó un aviso',
};

const activityLabel = (action: string) => actionLabels[action]
    ?? action.replaceAll('.', ' ').replaceAll('_', ' ').replace(/^./, (character) => character.toUpperCase());
const formatDate = (value: string | null, options: Intl.DateTimeFormatOptions) => value
    ? new Intl.DateTimeFormat('es-MX', { timeZone: 'America/Mexico_City', ...options }).format(new Date(value))
    : 'Por definir';
const activityIcon = (action: string) => {
    if (action.includes('lineup') || action.includes('player')) return 'users';
    if (action.includes('credential')) return 'id';
    if (action.includes('match') || action.includes('schedule')) return 'calendar';
    if (action.includes('payment') || action.includes('finance')) return 'wallet';
    if (action.includes('notification')) return 'bell';
    return 'settings';
};
const teamInitials = (name: string) => name.trim().split(/\s+/).slice(0, 2).map(word => word[0]?.toUpperCase() ?? '').join('');
const brokenImages = ref<Record<string, boolean>>({});
const logoVisible = (key: string, url: string | null) => Boolean(url && !brokenImages.value[key]);
const markLogoBroken = (key: string) => { brokenImages.value[key] = true; };
const progress = (value: number, maximum: number) => `${Math.min(100, Math.round((value / Math.max(maximum, 1)) * 100))}%`;
</script>

<template>
    <Head title="Panel" />
    <AppLayout>
        <main class="dashboard-premium">
            <section class="dashboard-hero" aria-label="Contexto de administración">
                <div class="dashboard-hero__inner">
                    <div class="min-w-0">
                        <p class="dashboard-kicker">Panel de administración</p>
                        <h1>Hola, {{ page.props.auth.user?.name }}</h1>
                        <p class="dashboard-hero__description">{{ context ? `Consulta el estado de ${context.league.name} y continúa con las tareas prioritarias.` : 'Selecciona una liga y un rol para comenzar a trabajar.' }}</p>
                        <div v-if="context" class="dashboard-context">
                            <span class="dashboard-context__logo">
                                <img v-if="logoVisible('league', context.league.logoUrl)" :src="context.league.logoUrl!" :alt="`Escudo de ${context.league.name}`" @error="markLogoBroken('league')">
                                <span v-else>{{ teamInitials(context.league.name) }}</span>
                            </span>
                            <span class="dashboard-context__name">{{ context.league.name }}</span>
                            <span class="dashboard-context__separator" aria-hidden="true"></span>
                            <span class="dashboard-context__role">{{ context.role.name }}</span>
                            <span class="dashboard-context__status"><span class="dashboard-context__dot"></span> Contexto activo</span>
                        </div>
                    </div>
                    <Link href="/seleccionar-acceso" class="dashboard-context-switch"><AppIcon name="switch" :size="20" /> Cambiar liga o rol</Link>
                </div>
            </section>

            <section class="dashboard-kpis" aria-label="Indicadores principales">
                <article class="dashboard-kpi">
                    <div class="dashboard-kpi__top"><span class="dashboard-kpi__icon"><AppIcon name="clipboard" :size="25" /></span><span class="dashboard-kpi__tag">Por atender</span></div>
                    <strong>{{ summary.pendingTasks }}</strong><p>Tareas pendientes</p>
                </article>
                <article class="dashboard-kpi">
                    <div class="dashboard-kpi__top"><span class="dashboard-kpi__icon"><AppIcon name="calendar" :size="25" /></span><span class="dashboard-kpi__tag">Próximos</span></div>
                    <strong>{{ summary.upcomingMatches }}</strong><p>Partidos programados</p>
                </article>
                <Link v-if="context && can('notifications.view')" href="/liga/avisos" class="dashboard-kpi dashboard-kpi--link">
                    <div class="dashboard-kpi__top"><span class="dashboard-kpi__icon"><AppIcon name="bell" :size="25" /></span><span class="dashboard-kpi__tag">Sin leer</span></div>
                    <strong>{{ summary.notifications }}</strong><p>Notificaciones</p>
                </Link>
                <article v-else class="dashboard-kpi">
                    <div class="dashboard-kpi__top"><span class="dashboard-kpi__icon"><AppIcon name="bell" :size="25" /></span><span class="dashboard-kpi__tag">Sin leer</span></div>
                    <strong>{{ summary.notifications }}</strong><p>Notificaciones</p>
                </article>
                <article class="dashboard-kpi">
                    <div class="dashboard-kpi__top"><span class="dashboard-kpi__icon"><AppIcon name="team" :size="25" /></span><span class="dashboard-kpi__tag dashboard-kpi__tag--success">Liga activa</span></div>
                    <strong>{{ summary.activeTeams }}</strong><p>Equipos activos</p>
                </article>
            </section>

            <section v-if="context && quickActions.length" class="dashboard-shortcuts" aria-labelledby="shortcut-title">
                <h2 id="shortcut-title">Accesos rápidos</h2>
                <div class="dashboard-shortcuts__grid">
                    <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="dashboard-shortcut">
                        <span class="dashboard-shortcut__icon"><AppIcon :name="action.icon" :size="25" /></span>
                        <span class="dashboard-shortcut__text"><strong>{{ action.label }}</strong><small>{{ action.description }}</small></span>
                        <span class="dashboard-shortcut__arrow" aria-hidden="true">›</span>
                    </Link>
                </div>
            </section>

            <section class="dashboard-detail-grid" aria-label="Calendario y actividad reciente">
                <article class="dashboard-surface">
                    <div class="dashboard-surface__heading">
                        <div><p class="dashboard-surface__eyebrow"><AppIcon name="calendar" :size="16" /> Calendario</p><h2>Próximos encuentros</h2></div>
                        <Link v-if="context && can('schedule.view')" href="/liga/calendario" class="dashboard-surface__link">Ver calendario <span aria-hidden="true">→</span></Link>
                    </div>
                    <div v-if="upcomingMatches.length" class="dashboard-matches">
                        <div v-for="match in upcomingMatches" :key="match.id" class="dashboard-match">
                            <div class="dashboard-match__date">
                                <span>{{ formatDate(match.scheduledAt, { weekday: 'short' }) }}</span>
                                <strong>{{ formatDate(match.scheduledAt, { day: '2-digit' }) }}</strong>
                                <span>{{ formatDate(match.scheduledAt, { month: 'short' }) }}</span>
                            </div>
                            <div class="dashboard-match__time">{{ formatDate(match.scheduledAt, { hour: '2-digit', minute: '2-digit', hour12: true }) }}</div>
                            <div class="dashboard-match__teams">
                                <div class="dashboard-match__line">
                                    <div class="dashboard-match__team dashboard-match__team--home">
                                        <span class="dashboard-match__crest">
                                            <img v-if="logoVisible(`home-${match.id}`, match.homeCrestUrl)" :src="match.homeCrestUrl!" :alt="`Escudo de ${match.homeTeam}`" loading="lazy" @error="markLogoBroken(`home-${match.id}`)">
                                            <span v-else>{{ teamInitials(match.homeTeam) }}</span>
                                        </span>
                                        <strong>{{ match.homeTeam }}</strong>
                                    </div>
                                    <span class="dashboard-match__vs">VS</span>
                                    <div class="dashboard-match__team dashboard-match__team--away">
                                        <strong>{{ match.awayTeam }}</strong>
                                        <span class="dashboard-match__crest">
                                            <img v-if="logoVisible(`away-${match.id}`, match.awayCrestUrl)" :src="match.awayCrestUrl!" :alt="`Escudo de ${match.awayTeam}`" loading="lazy" @error="markLogoBroken(`away-${match.id}`)">
                                            <span v-else>{{ teamInitials(match.awayTeam) }}</span>
                                        </span>
                                    </div>
                                </div>
                                <p>{{ match.matchday }} · {{ match.competition ?? 'Competencia' }}</p>
                            </div>
                            <span class="dashboard-match__field" :title="match.field"><AppIcon name="field" :size="15" /> {{ match.field }}</span>
                        </div>
                    </div>
                    <div v-else class="dashboard-empty"><AppIcon name="calendar" :size="36" /><strong>No hay encuentros próximos</strong><p>Los partidos programados aparecerán aquí.</p></div>
                </article>

                <article class="dashboard-surface">
                    <div class="dashboard-surface__heading">
                        <div><p class="dashboard-surface__eyebrow"><AppIcon name="chart" :size="16" /> Seguimiento</p><h2>Actividad reciente</h2></div>
                        <Link v-if="context && can('audit.view')" href="/liga/bitacora" class="dashboard-surface__link">Ver bitácora <span aria-hidden="true">→</span></Link>
                    </div>
                    <div v-if="recentActivity.length" class="dashboard-timeline">
                        <div v-for="entry in recentActivity.slice(0, 5)" :key="entry.id" class="dashboard-timeline__entry">
                            <span class="dashboard-timeline__icon"><AppIcon :name="activityIcon(entry.action)" :size="21" /></span>
                            <div><strong>{{ activityLabel(entry.action) }}</strong><p>{{ entry.actor }} · {{ formatDate(entry.createdAt, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p></div>
                        </div>
                    </div>
                    <div v-else class="dashboard-empty"><AppIcon name="clipboard" :size="36" /><strong>Sin actividad reciente</strong><p>Los movimientos de esta liga aparecerán aquí.</p></div>
                </article>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
@reference "../../css/app.css";
.dashboard-premium { --dashboard-accent: var(--color-league-600, #6445bd); --dashboard-secondary: var(--color-gold-500, #a14bd6); display: grid; gap: 0; padding-bottom: 1.5rem; }
.dashboard-hero { position: relative; overflow: hidden; border-radius: 1.6rem; background: linear-gradient(100deg, color-mix(in srgb, var(--dashboard-accent) 84%, #07152b) 0%, color-mix(in srgb, var(--dashboard-accent) 76%, var(--dashboard-secondary)) 56%, color-mix(in srgb, var(--dashboard-accent) 72%, #07152b) 100%), url('/images/public/league-stadium.jpg'); background-position: center 58%; background-size: cover; box-shadow: 0 16px 34px rgba(18,35,68,.18); isolation: isolate; color: #fff; }
.dashboard-hero::before { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgba(12,18,49,.30), transparent 58%), url('/images/public/league-stadium.jpg') center 57% / cover; opacity: .23; pointer-events: none; }
.dashboard-hero__inner { display:flex; align-items:center; justify-content:space-between; gap:1.5rem; padding:1.55rem 2rem; min-height: 164px; }
.dashboard-kicker { color:rgba(255,255,255,.85); text-transform:uppercase; font-size:.72rem; font-weight:800; letter-spacing:.16em; }
.dashboard-hero h1 { margin-top:.35rem; font-size:clamp(1.65rem,2.6vw,2.35rem); line-height:1.18; font-weight:800; letter-spacing:-.03em; }
.dashboard-hero__description { margin-top:.35rem; font-size:.94rem; color:rgba(255,255,255,.88); }
.dashboard-context { display:flex; align-items:center; flex-wrap:wrap; gap:.7rem; margin-top:.95rem; font-size:.84rem; }
.dashboard-context__logo { display:grid; place-items:center; width:2.25rem; height:2.25rem; flex-shrink:0; background:white; color:#213c69; border-radius:.7rem; overflow:hidden; font-weight:800; }
.dashboard-context__logo img { width:100%; height:100%; object-fit:contain; padding:3px; }
.dashboard-context__name { font-weight:750; }.dashboard-context__role { color:rgba(255,255,255,.86); }.dashboard-context__separator { height:1.15rem; width:1px; background:rgba(255,255,255,.40); }
.dashboard-context__status { display:inline-flex; align-items:center; gap:.4rem; margin-left:.15rem; padding:.32rem .7rem; background:#c9f9dc; color:#136737; border-radius:999px; font-size:.73rem; font-weight:800; }
.dashboard-context__dot { height:.42rem; width:.42rem; border-radius:50%; background:#08a64e; }
.dashboard-context-switch { display:inline-flex; flex-shrink:0; align-items:center; justify-content:center; gap:.5rem; padding:.7rem 1rem; border:1px solid rgba(255,255,255,.82); color:white; font-size:.83rem; border-radius:.75rem; font-weight:750; background:rgba(8,23,64,.2); transition:background .18s; }
.dashboard-context-switch:hover { background:rgba(255,255,255,.18); }
.dashboard-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.9rem; margin-top:1rem; }
.dashboard-kpi { min-width:0; display:block; padding:1.15rem 1.25rem; background:rgba(255,255,255,.97); border:1px solid rgba(220,227,241,.95); border-radius:1rem; box-shadow:0 7px 21px rgba(35,55,95,.08); transition:transform .18s,border-color .18s; }
.dashboard-kpi--link:hover { transform:translateY(-2px); border-color:var(--dashboard-accent); }
.dashboard-kpi__top { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
.dashboard-kpi__icon,.dashboard-shortcut__icon { display:grid; place-items:center; width:2.75rem; height:2.75rem; flex-shrink:0; border-radius:.83rem; color:var(--dashboard-accent); background:color-mix(in srgb,var(--dashboard-accent) 10%,white); }
.dashboard-kpi__tag { color:var(--dashboard-accent); background:color-mix(in srgb,var(--dashboard-accent) 9%,white); border-radius:99px; padding:.27rem .65rem; font-weight:750; font-size:.67rem; white-space:nowrap; }
.dashboard-kpi__tag--success { color:#12783b; background:#d8f9e3; }
.dashboard-kpi strong { display:block; font-size:2rem; font-weight:800; line-height:1.1; margin-top:.63rem; color:#111b39; }
.dashboard-kpi p { color:#475569; font-size:.91rem; margin-top:.12rem; }
.dashboard-shortcuts { margin-top:1.2rem; }.dashboard-shortcuts h2 { font-size:1.25rem; font-weight:800; letter-spacing:-.03em; color:#111c3b; margin-bottom:.8rem; }
.dashboard-shortcuts__grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.85rem; }
.dashboard-shortcut { display:flex; align-items:center; gap:.75rem; min-width:0; padding:.9rem .95rem; border:1px solid #e1e7f1; border-radius:1rem; background:#fff; box-shadow:0 7px 17px rgba(35,55,95,.08); transition:transform .18s,border-color .18s; }
.dashboard-shortcut:hover { transform:translateY(-2px); border-color:var(--dashboard-accent); }
.dashboard-shortcut__icon { width:2.5rem; height:2.5rem; }.dashboard-shortcut__text { flex:1; min-width:0; }.dashboard-shortcut__text strong { display:block; color:#111c39; font-size:.81rem; line-height:1.2; }.dashboard-shortcut__text small { display:block; color:#64748b; font-size:.7rem; margin-top:.28rem; line-height:1.3; }.dashboard-shortcut__arrow { flex-shrink:0; color:#718096; }
.dashboard-detail-grid { display:grid; grid-template-columns:minmax(0,1.48fr) minmax(0,1fr); gap:1rem; margin-top:1.2rem; align-items:stretch; }
.dashboard-surface { min-width:0; min-height:270px; overflow:hidden; background:rgba(255,255,255,.97); border:1px solid #e0e7f1; border-radius:1.1rem; box-shadow:0 9px 24px rgba(35,55,95,.08); padding:1.1rem 1.15rem; }
.dashboard-surface__heading { display:flex; justify-content:space-between; gap:1rem; align-items:center; padding-bottom:.9rem; border-bottom:1px solid #e7edf5; }.dashboard-surface__eyebrow { display:flex; align-items:center; gap:.4rem; color:var(--dashboard-accent); text-transform:uppercase; letter-spacing:.11em; font-size:.7rem; font-weight:800; }.dashboard-surface h2 { color:#101a37; font-size:1.35rem; font-weight:800; line-height:1.2; margin-top:.2rem; }.dashboard-surface__link { display:inline-flex; align-items:center; gap:.25rem; flex-shrink:0; color:var(--dashboard-accent); font-size:.77rem; font-weight:750; }.dashboard-surface__link:hover { text-decoration:underline; }
.dashboard-matches { display:grid; gap:.65rem; padding-top:.95rem; }.dashboard-match { display:flex; gap:.65rem; align-items:center; min-width:0; border:1px solid #e0e7f2; border-radius:.85rem; padding:.75rem; background:linear-gradient(180deg,#fff,#fcfdff); }
.dashboard-match__date { flex-shrink:0; display:grid; justify-items:center; min-width:3.25rem; background:color-mix(in srgb,var(--dashboard-accent) 10%,white); border-radius:.7rem; padding:.4rem .35rem; color:var(--dashboard-accent); }.dashboard-match__date span { text-transform:uppercase; font-size:.64rem; font-weight:750; }.dashboard-match__date strong { font-size:1.4rem; font-weight:850; line-height:1.18; }.dashboard-match__time { white-space:nowrap; padding-right:.65rem; border-right:1px solid #dce4f1; font-size:.77rem; color:#162340; font-weight:800; }
.dashboard-match__teams { flex:1; min-width:0; }.dashboard-match__line { display:flex; align-items:center; gap:.35rem; min-width:0; }.dashboard-match__team { display:flex; align-items:center; gap:.4rem; min-width:0; flex:1; }.dashboard-match__team--away { justify-content:flex-end; text-align:right; }.dashboard-match__team strong { color:#17203b; font-size:.76rem; line-height:1.25; font-weight:800; overflow-wrap:anywhere; }.dashboard-match__crest { display:grid; flex-shrink:0; place-items:center; width:2.2rem; height:2.2rem; border:1px solid #dce3f0; border-radius:.65rem; background:#fff; overflow:hidden; font-size:.64rem; color:var(--dashboard-accent); font-weight:800; }.dashboard-match__crest img { width:100%; height:100%; object-fit:contain; padding:2px; }.dashboard-match__vs { font-size:.66rem; font-weight:800; color:#475569; border-radius:.55rem; padding:.35rem .48rem; background:#f0f3fa; }.dashboard-match__teams p { color:#64748b; font-size:.66rem; margin-top:.27rem; text-align:center; }.dashboard-match__field { flex-shrink:0; display:inline-flex; align-items:center; gap:.25rem; max-width:8rem; background:#f0f3f9; padding:.4rem .5rem; border-radius:99px; color:#475569; font-size:.65rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dashboard-timeline { padding:.45rem 0; }.dashboard-timeline__entry { position:relative; display:flex; align-items:center; gap:.65rem; padding:.55rem .2rem; }.dashboard-timeline__entry:not(:last-child) { border-bottom:1px solid #edf0f5; }.dashboard-timeline__entry:not(:last-child)::after { content:''; position:absolute; left:1.12rem; bottom:-.55rem; width:1px; height:1.15rem; background:color-mix(in srgb,var(--dashboard-accent) 30%,white); }.dashboard-timeline__icon { display:grid; flex-shrink:0; place-items:center; border-radius:50%; width:2.25rem; height:2.25rem; background:color-mix(in srgb,var(--dashboard-accent) 9%,white); color:var(--dashboard-accent); }.dashboard-timeline__entry strong { font-size:.79rem; color:#151f3a; font-weight:800; display:block; line-height:1.3; }.dashboard-timeline__entry p { font-size:.69rem; color:#64748b; margin-top:.12rem; }.dashboard-empty { display:flex; align-items:center; flex-direction:column; justify-content:center; min-height:180px; color:var(--dashboard-accent); text-align:center; gap:.4rem; }.dashboard-empty strong { color:#18243e; margin-top:.25rem; }.dashboard-empty p { color:#64748b; font-size:.78rem; }
@media (max-width:1250px) { .dashboard-shortcuts__grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.dashboard-match__field { display:none; } }
@media (max-width:950px) { .dashboard-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }.dashboard-detail-grid { grid-template-columns:1fr; } }
@media (max-width:650px) { .dashboard-hero__inner { padding:1.35rem; align-items:flex-start; flex-direction:column; }.dashboard-context-switch { width:100%; }.dashboard-context__separator { display:none; }.dashboard-kpis { gap:.65rem; }.dashboard-kpi { padding:.8rem; }.dashboard-kpi__tag { font-size:.59rem; padding:.2rem .4rem; }.dashboard-kpi__icon { height:2.25rem; width:2.25rem; }.dashboard-shortcuts__grid { grid-template-columns:1fr; }.dashboard-surface { padding:.9rem; }.dashboard-match { flex-wrap:wrap; }.dashboard-match__teams { flex-basis:calc(100% - 7rem); }.dashboard-match__field { display:inline-flex; max-width:100%; margin-left:3.5rem; }.dashboard-match__team strong { font-size:.72rem; } }
</style>
