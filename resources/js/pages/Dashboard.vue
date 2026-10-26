<script setup lang="ts">
import AppLayout from '../layouts/AppLayout.vue';
import AppIcon from '../components/AppIcon.vue';
import type { AppPageProps } from '../types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type DashboardSummary = { pendingTasks: number; upcomingMatches: number; notifications: number; activeTeams: number };
type UpcomingMatch = { id: number; competition: string | null; matchday: string; homeTeam: string; awayTeam: string; scheduledAt: string | null; field: string };
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
const progress = (value: number, maximum: number) => `${Math.min(100, Math.round((value / Math.max(maximum, 1)) * 100))}%`;
</script>

<template>
    <Head title="Panel" />
    <AppLayout>
        <section class="dashboard-hero overflow-hidden rounded-[1.75rem] text-white shadow-2xl shadow-slate-950/20">
            <div class="relative z-10 px-6 py-7 sm:px-8 lg:px-10 lg:py-9">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Panel de administración</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Hola, {{ page.props.auth.user?.name }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-white/75 sm:text-base">
                            {{ context ? `Consulta el estado de ${context.league.name} y continúa con las tareas prioritarias.` : 'Selecciona una liga y un rol para comenzar a trabajar.' }}
                        </p>
                    </div>
                    <Link href="/seleccionar-acceso" class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-white/25 bg-white/12 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20 lg:self-auto">
                        <AppIcon name="switch" :size="18" /> Cambiar liga o rol
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="context" class="relative z-10 mx-3 -mt-4 rounded-2xl border border-white/80 bg-white/95 px-5 py-4 shadow-lg shadow-slate-900/10 backdrop-blur sm:mx-6 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-league-50 text-lg font-black text-league-700">
                        <img v-if="context.league.logoUrl" :src="context.league.logoUrl" :alt="`Logo de ${context.league.name}`" class="h-full w-full object-contain p-1">
                        <span v-else>{{ context.league.name.charAt(0) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Acceso activo</p>
                        <p class="truncate font-semibold text-slate-900">{{ context.league.name }} · {{ context.role.name }}</p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">
                    <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span>
                    Contexto aislado y activo
                </span>
            </div>
        </section>

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores principales">
            <article class="dashboard-stat">
                <div class="flex items-start justify-between"><AppIcon name="clipboard" variant="illustrated" :size="58" /><span class="dashboard-stat-trend">Por atender</span></div>
                <div class="mt-5"><strong class="text-3xl font-bold text-slate-900">{{ props.summary.pendingTasks }}</strong><p class="mt-1 text-sm font-semibold text-slate-600">Tareas pendientes</p></div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gold-500" :style="{ width: progress(props.summary.pendingTasks, 10) }"></div></div>
            </article>
            <article class="dashboard-stat">
                <div class="flex items-start justify-between"><AppIcon name="calendar" variant="illustrated" :size="58" /><span class="dashboard-stat-trend">Próximos</span></div>
                <div class="mt-5"><strong class="text-3xl font-bold text-slate-900">{{ props.summary.upcomingMatches }}</strong><p class="mt-1 text-sm font-semibold text-slate-600">Partidos programados</p></div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-league-600" :style="{ width: progress(props.summary.upcomingMatches, 8) }"></div></div>
            </article>
            <Link v-if="context && can('notifications.view')" href="/liga/avisos" class="dashboard-stat transition hover:-translate-y-0.5 hover:border-league-600">
                <div class="flex items-start justify-between"><AppIcon name="bell" variant="illustrated" :size="58" /><span class="dashboard-stat-trend">Sin leer</span></div>
                <div class="mt-5"><strong class="text-3xl font-bold text-slate-900">{{ props.summary.notifications }}</strong><p class="mt-1 text-sm font-semibold text-slate-600">Notificaciones</p></div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-rose-500" :style="{ width: progress(props.summary.notifications, 10) }"></div></div>
            </Link>
            <article v-else class="dashboard-stat">
                <div class="flex items-start justify-between"><AppIcon name="bell" variant="illustrated" :size="58" /></div>
                <div class="mt-5"><strong class="text-3xl font-bold text-slate-900">{{ props.summary.notifications }}</strong><p class="mt-1 text-sm font-semibold text-slate-600">Notificaciones</p></div>
            </article>
            <article class="dashboard-stat">
                <div class="flex items-start justify-between"><AppIcon name="team" variant="illustrated" :size="58" /><span class="dashboard-stat-trend">Liga activa</span></div>
                <div class="mt-5"><strong class="text-3xl font-bold text-slate-900">{{ props.summary.activeTeams }}</strong><p class="mt-1 text-sm font-semibold text-slate-600">Equipos activos</p></div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-sky-500" :style="{ width: progress(props.summary.activeTeams, 20) }"></div></div>
            </article>
        </section>

        <section v-if="context && quickActions.length" class="mt-8">
            <div class="mb-4"><p class="section-eyebrow">Atajos</p><h2 class="section-title">Accesos rápidos</h2></div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="group flex items-center gap-4 rounded-2xl border border-white/80 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-gold-400 hover:shadow-lg">
                    <AppIcon :name="action.icon" variant="illustrated" :size="48" class="transition duration-200 group-hover:scale-105" />
                    <span><strong class="block text-sm text-slate-900">{{ action.label }}</strong><small class="mt-0.5 block text-xs text-slate-500">{{ action.description }}</small></span>
                </Link>
            </div>
        </section>

        <section class="mt-8 grid gap-6 xl:grid-cols-[1.35fr_.85fr]">
            <article class="dashboard-panel">
                <div class="flex items-end justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div><p class="section-eyebrow">Calendario</p><h2 class="section-title">Próximos encuentros</h2></div>
                    <Link v-if="context && can('schedule.view')" href="/liga/calendario" class="text-sm font-bold text-league-700 hover:underline">Ver calendario</Link>
                </div>
                <div v-if="props.upcomingMatches.length" class="divide-y divide-slate-100">
                    <div v-for="match in props.upcomingMatches" :key="match.id" class="grid gap-4 px-5 py-4 transition hover:bg-slate-50/80 sm:grid-cols-[8rem_1fr_auto] sm:items-center sm:px-6">
                        <div><p class="text-xs font-bold uppercase tracking-wide text-league-700">{{ formatDate(match.scheduledAt, { weekday: 'short', day: 'numeric', month: 'short' }) }}</p><p class="mt-1 text-lg font-bold text-slate-900">{{ formatDate(match.scheduledAt, { hour: '2-digit', minute: '2-digit', hour12: true }) }}</p></div>
                        <div><p class="font-semibold text-slate-900">{{ match.homeTeam }} <span class="px-1 text-xs font-normal text-slate-400">VS</span> {{ match.awayTeam }}</p><p class="mt-1 text-xs text-slate-500">{{ match.matchday }} · {{ match.competition ?? 'Competencia' }}</p></div>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ match.field }}</span>
                    </div>
                </div>
                <div v-else class="px-6 py-12 text-center"><AppIcon name="football" variant="illustrated" :size="54" class="mx-auto" /><p class="mt-3 font-semibold text-slate-700">No hay partidos próximos</p><p class="mt-1 text-sm text-slate-500">Los encuentros programados aparecerán aquí.</p></div>
            </article>

            <article class="dashboard-panel">
                <div class="flex items-end justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div><p class="section-eyebrow">Seguimiento</p><h2 class="section-title">Actividad reciente</h2></div>
                    <Link v-if="context && can('audit.view')" href="/liga/bitacora" class="text-sm font-bold text-league-700 hover:underline">Ver bitácora</Link>
                </div>
                <div v-if="props.recentActivity.length" class="px-5 py-2 sm:px-6">
                    <div v-for="(entry, index) in props.recentActivity" :key="entry.id" class="relative flex gap-3 py-3.5">
                        <div v-if="index < props.recentActivity.length - 1" class="absolute left-[7px] top-7 h-[calc(100%-1rem)] w-px bg-slate-200"></div>
                        <span class="relative mt-1.5 h-3.5 w-3.5 shrink-0 rounded-full border-2 border-white bg-gold-500 ring-2 ring-gold-400/25"></span>
                        <div class="min-w-0"><p class="text-sm font-semibold leading-snug text-slate-800">{{ activityLabel(entry.action) }}</p><p class="mt-1 text-xs text-slate-500">{{ entry.actor }} · {{ formatDate(entry.createdAt, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p></div>
                    </div>
                </div>
                <div v-else class="px-6 py-12 text-center"><p class="font-semibold text-slate-700">Sin actividad reciente</p><p class="mt-1 text-sm text-slate-500">Los movimientos de la liga se mostrarán aquí.</p></div>
            </article>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../css/app.css";

.dashboard-hero {
    position: relative;
    background-image: linear-gradient(105deg, color-mix(in srgb, var(--color-league-900) 94%, #07152b), color-mix(in srgb, var(--color-league-600) 70%, transparent)), url('/images/public/league-stadium.jpg');
    background-position: center 58%;
    background-size: cover;
}
.dashboard-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(4, 15, 30, .24), transparent 65%); }
.dashboard-stat { @apply min-h-48 rounded-2xl border border-white/80 bg-white/95 p-5 shadow-md shadow-slate-900/5 backdrop-blur; }
.dashboard-stat-icon { @apply grid h-10 w-10 place-items-center rounded-xl bg-league-50 font-bold text-league-700; }
.dashboard-stat-trend { @apply rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500; }
.dashboard-panel { @apply overflow-hidden rounded-2xl border border-white/80 bg-white/95 shadow-md shadow-slate-900/5 backdrop-blur; }
.section-eyebrow { @apply text-[10px] font-bold uppercase tracking-[0.2em] text-gold-500; }
.section-title { @apply mt-1 text-xl font-bold text-slate-900; }
</style>
