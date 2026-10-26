<script setup lang="ts">
import type { AppPageProps } from '../types';
import AppIcon from '../components/AppIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { CSSProperties } from 'vue';

const page = usePage<AppPageProps>();
const mobileOpen = ref(false);
const desktopMenuOpen = ref<string | null>(null);
const user = computed(() => page.props.auth.user);
const brand = computed(() => page.props.brand);
const userInitials = computed(() => (user.value?.name ?? 'Usuario')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join(''));

const closeDesktopMenus = () => {
    desktopMenuOpen.value = null;
};

const toggleDesktopMenu = (menu: string, event: Event) => {
    const details = event.currentTarget as HTMLDetailsElement;
    if (details.open) {
        desktopMenuOpen.value = menu;
    } else if (desktopMenuOpen.value === menu) {
        desktopMenuOpen.value = null;
    }
};

const closeMenusOnOutsideClick = (event: MouseEvent) => {
    const target = event.target as Element | null;
    if (!target?.closest('[data-desktop-menu]')) closeDesktopMenus();
};

let removeNavigationListener: (() => void) | undefined;

onMounted(() => {
    document.addEventListener('click', closeMenusOnOutsideClick);
    removeNavigationListener = router.on('start', () => closeDesktopMenus());
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeMenusOnOutsideClick);
    removeNavigationListener?.();
});
const canManageUsers = computed(() => user.value?.permissions.includes('users.view') ?? false);
const canManageLeagues = computed(() => user.value?.permissions.includes('leagues.view') ?? false);
const canManageBackups = computed(() => user.value?.permissions.includes('backups.manage') ?? false);
const canViewSettings = computed(() => user.value?.permissions.includes('league.settings.view') ?? false);
const canViewMembers = computed(() => user.value?.permissions.includes('league.members.view') ?? false);
const canViewCompetitions = computed(() => user.value?.permissions.includes('competitions.view') ?? false);
const canViewTeams = computed(() => user.value?.permissions.includes('teams.view') ?? false);
const canViewPlayers = computed(() => user.value?.permissions.includes('players.view') ?? false);
const canViewFields = computed(() => user.value?.permissions.includes('fields.view') ?? false);
const canViewReferees = computed(() => user.value?.permissions.includes('referees.view') ?? false);
const canViewSchedule = computed(() => user.value?.permissions.includes('schedule.view') ?? false);
const canViewLineups = computed(() => user.value?.permissions.includes('lineups.view') ?? false);
const canViewReports = computed(() => user.value?.permissions.includes('reports.view') ?? false);
const canViewStatistics = computed(() => user.value?.permissions.includes('statistics.view') ?? false);
const canViewExports = computed(() => user.value?.permissions.includes('exports.view') ?? false);
const canViewDiscipline = computed(() => user.value?.permissions.includes('discipline.view') ?? false);
const canViewFinance = computed(() => user.value?.permissions.includes('finance.view') ?? false);
const canViewNotices = computed(() => user.value?.permissions.includes('notifications.view') ?? false);
const canViewAudit = computed(() => user.value?.permissions.includes('audit.view') ?? false);
const context = computed(() => page.props.activeContext);
const hasAdministrationMenu = computed(() => canManageUsers.value || canManageLeagues.value || canManageBackups.value);
const hasConfigurationMenu = computed(() => Boolean(context.value) && (
    canViewMembers.value || canViewSettings.value || canViewCompetitions.value
));
const hasParticipantsMenu = computed(() => Boolean(context.value) && (canViewTeams.value || canViewPlayers.value));
const hasOperationsMenu = computed(() => Boolean(context.value) && (
    canViewFields.value || canViewReferees.value || canViewSchedule.value
));
const hasMatchMenu = computed(() => Boolean(context.value) && (
    canViewLineups.value || canViewReports.value || canViewStatistics.value
));
const hasControlMenu = computed(() => Boolean(context.value) && (
    canViewDiscipline.value || canViewFinance.value || canViewNotices.value || canViewExports.value || canViewAudit.value
));

const administrationActive = computed(() => page.url.startsWith('/administracion/'));
const configurationActive = computed(() => (
    page.url.startsWith('/liga/miembros')
    || page.url.startsWith('/liga/configuracion')
    || page.url.startsWith('/liga/parametros')
    || page.url.startsWith('/liga/competencias')
));
const participantsActive = computed(() => (
    page.url.startsWith('/liga/equipos')
    || page.url.startsWith('/liga/jugadores')
    || page.url.startsWith('/liga/plantillas')
));
const operationsActive = computed(() => (
    page.url.startsWith('/liga/campos')
    || page.url.startsWith('/liga/canchas')
    || page.url.startsWith('/liga/instalaciones')
    || page.url.startsWith('/liga/arbitros')
    || page.url.startsWith('/liga/disponibilidades-arbitro')
    || page.url.startsWith('/liga/calendario')
    || page.url.startsWith('/liga/jornadas')
    || page.url.startsWith('/liga/partidos')
));
const matchActive = computed(() => (
    page.url.startsWith('/liga/alineaciones')
    || page.url.startsWith('/liga/cedulas')
    || page.url.startsWith('/liga/resultados')
));
const controlActive = computed(() => (
    page.url.startsWith('/liga/disciplina')
    || page.url.startsWith('/liga/sanciones')
    || page.url.startsWith('/liga/apelaciones')
    || page.url.startsWith('/liga/finanzas')
    || page.url.startsWith('/liga/pagos')
    || page.url.startsWith('/liga/avisos')
    || page.url.startsWith('/liga/reportes')
    || page.url.startsWith('/liga/bitacora')
));
const brandStyle = computed<CSSProperties>(() => {
    const primary = context.value?.league.primaryColor;
    const secondary = context.value?.league.secondaryColor;
    if (!primary || !secondary) return {};

    return {
        '--color-league-50': `color-mix(in srgb, ${primary} 8%, white)`,
        '--color-league-100': `color-mix(in srgb, ${primary} 16%, white)`,
        '--color-league-600': primary,
        '--color-league-700': primary,
        '--color-league-900': `color-mix(in srgb, ${primary} 75%, black)`,
        '--color-gold-400': secondary,
        '--color-gold-500': secondary,
    } as CSSProperties;
});
</script>

<template>
    <div class="admin-shell min-h-screen" :style="brandStyle">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-xl">
            <div class="mx-auto flex min-h-16 max-w-[1500px] items-center justify-between gap-4 px-4 py-2 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-5">
                    <Link href="/panel" class="flex min-w-0 shrink-0 items-center gap-2.5 text-league-900">
                        <img :src="brand.logoUrl" :alt="`Logotipo de ${brand.name}`" class="h-10 w-10 shrink-0 object-contain">
                        <span class="min-w-0 max-w-44 leading-tight"><strong class="block text-base font-black tracking-tight">{{ brand.name }}</strong><small v-if="context" class="block truncate text-[10px] font-bold uppercase tracking-wider text-slate-400">Liga administrada</small></span>
                    </Link>
                    <nav class="hidden items-center gap-0.5 xl:flex" aria-label="Navegación principal">
                        <Link href="/panel" class="nav-link" :class="{ 'nav-link-active': page.url === '/panel' }"><AppIcon name="home" variant="illustrated" :size="27" /> Panel</Link>

                        <details v-if="hasAdministrationMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'administration'" @toggle="toggleDesktopMenu('administration', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': administrationActive }"><AppIcon name="building" variant="illustrated" :size="27" /> Administración</summary>
                            <div class="nav-menu-panel" @click="closeDesktopMenus">
                                <Link v-if="canManageUsers" href="/administracion/usuarios" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/administracion/usuarios') }"><span class="menu-icon"><AppIcon name="users" /></span>Usuarios</Link>
                                <Link v-if="canManageLeagues" href="/administracion/ligas" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/administracion/ligas') }"><span class="menu-icon"><AppIcon name="building" /></span>Ligas</Link>
                                <Link v-if="canManageBackups" href="/administracion/respaldos" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/administracion/respaldos') }"><span class="menu-icon"><AppIcon name="database" /></span>Respaldos</Link>
                            </div>
                        </details>

                        <details v-if="hasConfigurationMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'configuration'" @toggle="toggleDesktopMenu('configuration', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': configurationActive }"><AppIcon name="settings" variant="illustrated" :size="27" /> Configuración</summary>
                            <div class="nav-menu-panel" @click="closeDesktopMenus">
                                <Link v-if="canViewMembers" href="/liga/miembros" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/miembros') }"><span class="menu-icon"><AppIcon name="users" /></span>Miembros</Link>
                                <Link v-if="canViewSettings" href="/liga/configuracion" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/configuracion') }"><span class="menu-icon"><AppIcon name="palette" /></span>Identidad visual</Link>
                                <Link v-if="canViewSettings" href="/liga/parametros" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/parametros') }"><span class="menu-icon"><AppIcon name="sliders" /></span>Parámetros generales</Link>
                                <Link v-if="canViewCompetitions" href="/liga/competencias" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/competencias') }"><span class="menu-icon"><AppIcon name="trophy" /></span>Temporadas y competencias</Link>
                            </div>
                        </details>

                        <details v-if="hasParticipantsMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'participants'" @toggle="toggleDesktopMenu('participants', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': participantsActive }"><AppIcon name="users" variant="illustrated" :size="27" /> Participantes</summary>
                            <div class="nav-menu-panel" @click="closeDesktopMenus">
                                <Link v-if="canViewTeams" href="/liga/equipos" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/equipos') }"><span class="menu-icon"><AppIcon name="team" /></span>Equipos</Link>
                                <Link v-if="canViewPlayers" href="/liga/jugadores" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/jugadores') || page.url.startsWith('/liga/plantillas') }"><span class="menu-icon"><AppIcon name="user" /></span>Jugadores y plantillas</Link>
                            </div>
                        </details>

                        <details v-if="hasOperationsMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'operations'" @toggle="toggleDesktopMenu('operations', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': operationsActive }"><AppIcon name="chart" variant="illustrated" :size="27" /> Operación</summary>
                            <div class="nav-menu-panel" @click="closeDesktopMenus">
                                <Link v-if="canViewFields" href="/liga/campos" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/campos') || page.url.startsWith('/liga/canchas') || page.url.startsWith('/liga/instalaciones') }"><span class="menu-icon"><AppIcon name="field" /></span>Campos y disponibilidad</Link>
                                <Link v-if="canViewReferees" href="/liga/arbitros" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/arbitros') || page.url.startsWith('/liga/disponibilidades-arbitro') }"><span class="menu-icon"><AppIcon name="badge" /></span>Árbitros y disponibilidad</Link>
                                <Link v-if="canViewSchedule" href="/liga/calendario" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/calendario') || page.url.startsWith('/liga/jornadas') || page.url.startsWith('/liga/partidos') }"><span class="menu-icon"><AppIcon name="calendar" /></span>Jornadas y calendario</Link>
                            </div>
                        </details>

                        <details v-if="hasMatchMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'match'" @toggle="toggleDesktopMenu('match', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': matchActive }"><AppIcon name="football" variant="illustrated" :size="27" /> Partido</summary>
                            <div class="nav-menu-panel" @click="closeDesktopMenus">
                                <Link v-if="canViewLineups" href="/liga/alineaciones" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/alineaciones') }"><span class="menu-icon"><AppIcon name="lineup" /></span>Alineaciones</Link>
                                <Link v-if="canViewReports" href="/liga/cedulas" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/cedulas') }"><span class="menu-icon"><AppIcon name="clipboard" /></span>Cédulas arbitrales</Link>
                                <Link v-if="canViewStatistics" href="/liga/resultados" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/resultados') }"><span class="menu-icon"><AppIcon name="chart" /></span>Resultados y estadísticas</Link>
                            </div>
                        </details>

                        <details v-if="hasControlMenu" class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'control'" @toggle="toggleDesktopMenu('control', $event)">
                            <summary class="nav-link nav-menu-trigger" :class="{ 'nav-link-active': controlActive }"><AppIcon name="clipboard" variant="illustrated" :size="27" /> Control</summary>
                            <div class="nav-menu-panel right-0 left-auto" @click="closeDesktopMenus">
                                <Link v-if="canViewDiscipline" href="/liga/disciplina" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/disciplina') || page.url.startsWith('/liga/sanciones') || page.url.startsWith('/liga/apelaciones') }"><span class="menu-icon"><AppIcon name="gavel" /></span>Sanciones y apelaciones</Link>
                                <Link v-if="canViewFinance" href="/liga/finanzas" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/finanzas') || page.url.startsWith('/liga/pagos') }"><span class="menu-icon"><AppIcon name="wallet" /></span>Pagos y recibos</Link>
                                <Link v-if="canViewNotices" href="/liga/avisos" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/avisos') }"><span class="menu-icon"><AppIcon name="bell" /></span>Avisos y notificaciones</Link>
                                <Link v-if="canViewExports" href="/liga/reportes" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/reportes') }"><span class="menu-icon"><AppIcon name="download" /></span>Reportes y exportaciones</Link>
                                <Link v-if="canViewAudit" href="/liga/bitacora" class="nav-menu-link" :class="{ 'nav-menu-link-active': page.url.startsWith('/liga/bitacora') }"><span class="menu-icon"><AppIcon name="history" /></span>Bitácora</Link>
                            </div>
                        </details>
                    </nav>
                </div>
                <div class="hidden shrink-0 items-center gap-3 xl:flex">
                    <Link v-if="context && canViewNotices" href="/liga/avisos" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-league-600 hover:text-league-700" aria-label="Abrir avisos">
                        <AppIcon name="bell" variant="illustrated" :size="28" />
                        <span v-if="page.props.notificationSummary.unreadCount" class="absolute -right-2 -top-2 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[10px] font-bold text-white">{{ page.props.notificationSummary.unreadCount > 99 ? '99+' : page.props.notificationSummary.unreadCount }}</span>
                    </Link>
                    <details class="nav-menu" data-desktop-menu :open="desktopMenuOpen === 'profile'" @toggle="toggleDesktopMenu('profile', $event)">
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 shadow-sm transition hover:border-league-600">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-league-900 text-xs font-black text-white">{{ userInitials }}</span>
                            <span class="hidden max-w-40 text-left 2xl:block"><strong class="block truncate text-sm text-slate-800">{{ user?.name }}</strong><small class="block truncate text-[11px] text-slate-500">{{ context?.role.name ?? 'Administración del sistema' }}</small></span>
                            <span class="text-xs text-slate-400" aria-hidden="true">▾</span>
                        </summary>
                        <div class="nav-menu-panel right-0 left-auto min-w-64" @click="closeDesktopMenus">
                            <div class="border-b border-slate-100 px-3 py-2.5"><p class="truncate text-sm font-semibold text-slate-900">{{ user?.name }}</p><p class="truncate text-xs text-slate-500">{{ user?.email }}</p></div>
                            <Link href="/seleccionar-acceso" class="nav-menu-link"><span class="menu-icon"><AppIcon name="switch" /></span>Cambiar liga o rol</Link>
                            <Link href="/cambiar-contrasena" class="nav-menu-link"><span class="menu-icon"><AppIcon name="lock" /></span>Cambiar contraseña</Link>
                            <Link href="/cerrar-sesion" method="post" as="button" class="nav-menu-link w-full text-left text-red-700"><span class="menu-icon"><AppIcon name="logout" /></span>Cerrar sesión</Link>
                        </div>
                    </details>
                </div>
                <button class="rounded-lg p-2 text-slate-600 xl:hidden" type="button" :aria-expanded="mobileOpen" aria-controls="mobile-navigation" aria-label="Abrir navegación" @click="mobileOpen = !mobileOpen">
                    <AppIcon name="menu" :size="25" />
                </button>
            </div>
            <nav v-if="mobileOpen" id="mobile-navigation" class="max-h-[calc(100vh-5rem)] overflow-y-auto border-t border-slate-200 px-4 py-3 xl:hidden">
                <div class="mx-auto grid max-w-7xl gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <section>
                        <p class="mobile-nav-heading">Principal</p>
                        <Link href="/panel" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="home" variant="illustrated" :size="30" />Panel</Link>
                        <Link v-if="canManageUsers" href="/administracion/usuarios" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="users" variant="illustrated" :size="30" />Usuarios</Link>
                        <Link v-if="canManageLeagues" href="/administracion/ligas" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="building" variant="illustrated" :size="30" />Ligas</Link>
                        <Link v-if="canManageBackups" href="/administracion/respaldos" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="database" variant="illustrated" :size="30" />Respaldos</Link>
                    </section>
                    <section v-if="hasConfigurationMenu">
                        <p class="mobile-nav-heading">Configuración</p>
                        <Link v-if="canViewMembers" href="/liga/miembros" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="users" variant="illustrated" :size="30" />Miembros</Link>
                        <Link v-if="canViewSettings" href="/liga/configuracion" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="palette" variant="illustrated" :size="30" />Identidad visual</Link>
                        <Link v-if="canViewSettings" href="/liga/parametros" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="sliders" variant="illustrated" :size="30" />Parámetros generales</Link>
                        <Link v-if="canViewCompetitions" href="/liga/competencias" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="trophy" variant="illustrated" :size="30" />Temporadas y competencias</Link>
                    </section>
                    <section v-if="hasParticipantsMenu || hasOperationsMenu">
                        <p class="mobile-nav-heading">Organización</p>
                        <Link v-if="canViewTeams" href="/liga/equipos" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="team" variant="illustrated" :size="30" />Equipos</Link>
                        <Link v-if="canViewPlayers" href="/liga/jugadores" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="user" variant="illustrated" :size="30" />Jugadores y plantillas</Link>
                        <Link v-if="canViewFields" href="/liga/campos" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="field" variant="illustrated" :size="30" />Campos y disponibilidad</Link>
                        <Link v-if="canViewReferees" href="/liga/arbitros" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="badge" variant="illustrated" :size="30" />Árbitros y disponibilidad</Link>
                    </section>
                    <section v-if="hasOperationsMenu || hasMatchMenu">
                        <p class="mobile-nav-heading">Competencia</p>
                        <Link v-if="canViewSchedule" href="/liga/calendario" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="calendar" variant="illustrated" :size="30" />Jornadas y calendario</Link>
                        <Link v-if="canViewLineups" href="/liga/alineaciones" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="lineup" variant="illustrated" :size="30" />Alineaciones</Link>
                        <Link v-if="canViewReports" href="/liga/cedulas" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="clipboard" variant="illustrated" :size="30" />Cédulas arbitrales</Link>
                        <Link v-if="canViewStatistics" href="/liga/resultados" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="chart" variant="illustrated" :size="30" />Resultados y estadísticas</Link>
                    </section>
                    <section v-if="hasControlMenu">
                        <p class="mobile-nav-heading">Control</p>
                        <Link v-if="canViewDiscipline" href="/liga/disciplina" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="gavel" variant="illustrated" :size="30" />Sanciones y apelaciones</Link>
                        <Link v-if="canViewFinance" href="/liga/finanzas" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="wallet" variant="illustrated" :size="30" />Pagos y recibos</Link>
                        <Link v-if="canViewNotices" href="/liga/avisos" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="bell" variant="illustrated" :size="30" />Avisos y notificaciones <span v-if="page.props.notificationSummary.unreadCount" class="ml-1 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">{{ page.props.notificationSummary.unreadCount }}</span></Link>
                        <Link v-if="canViewExports" href="/liga/reportes" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="download" variant="illustrated" :size="30" />Reportes y exportaciones</Link>
                        <Link v-if="canViewAudit" href="/liga/bitacora" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="history" variant="illustrated" :size="30" />Bitácora</Link>
                    </section>
                    <section>
                        <p class="mobile-nav-heading">Sesión</p>
                        <p class="px-3 pb-1 text-xs text-slate-500">{{ user?.name }}</p>
                        <Link href="/seleccionar-acceso" class="mobile-nav-link" @click="mobileOpen = false"><AppIcon name="switch" variant="illustrated" :size="30" />Cambiar liga o rol</Link>
                        <Link href="/cerrar-sesion" method="post" as="button" class="mobile-nav-link w-full text-left"><AppIcon name="logout" variant="illustrated" :size="30" />Cerrar sesión</Link>
                    </section>
                </div>
            </nav>
        </header>

        <div v-if="page.props.flash.success" class="mx-auto mt-5 max-w-[1500px] px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
        </div>
        <div v-if="page.props.flash.error" class="mx-auto mt-5 max-w-[1500px] px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ page.props.flash.error }}</div>
        </div>

        <main class="relative mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.admin-shell {
    background-color: #eef2f6;
    background-image: linear-gradient(rgba(238, 242, 246, .78), rgba(238, 242, 246, .88)), url('/images/public/league-stadium.jpg');
    background-attachment: fixed;
    background-position: center;
    background-size: cover;
}
</style>
