<script setup lang="ts">
import type { AppPageProps } from '../types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { CSSProperties } from 'vue';

const page = usePage<AppPageProps>();
const mobileOpen = ref(false);
const user = computed(() => page.props.auth.user);
const canManageUsers = computed(() => user.value?.permissions.includes('users.view') ?? false);
const canManageLeagues = computed(() => user.value?.permissions.includes('leagues.view') ?? false);
const canViewSettings = computed(() => user.value?.permissions.includes('league.settings.view') ?? false);
const canViewMembers = computed(() => user.value?.permissions.includes('league.members.view') ?? false);
const canViewAudit = computed(() => user.value?.permissions.includes('audit.view') ?? false);
const context = computed(() => page.props.activeContext);
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
    <div class="min-h-screen bg-slate-100" :style="brandStyle">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link href="/panel" class="flex items-center gap-2 font-semibold text-league-900">
                        <img v-if="context?.league.logoUrl" :src="context.league.logoUrl" alt="" class="h-8 w-8 rounded-lg object-contain">
                        <span>{{ context?.league.name ?? 'Liga Taladzi' }}</span>
                    </Link>
                    <nav class="hidden items-center gap-2 md:flex">
                        <Link href="/panel" class="nav-link" :class="{ 'nav-link-active': page.url === '/panel' }">Panel</Link>
                        <Link v-if="canManageUsers" href="/administracion/usuarios" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/administracion/usuarios') }">Usuarios</Link>
                        <Link v-if="canManageLeagues" href="/administracion/ligas" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/administracion/ligas') }">Ligas</Link>
                        <Link v-if="context && canViewMembers" href="/liga/miembros" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/liga/miembros') }">Miembros</Link>
                        <Link v-if="context && canViewSettings" href="/liga/configuracion" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/liga/configuracion') }">Configuración</Link>
                        <Link v-if="context && canViewAudit" href="/liga/bitacora" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/liga/bitacora') }">Bitácora</Link>
                    </nav>
                </div>
                <div class="hidden items-center gap-4 md:flex">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-800">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ context?.role.name ?? 'Administración del sistema' }}</p>
                    </div>
                    <Link href="/seleccionar-acceso" class="btn-secondary">Cambiar acceso</Link>
                    <Link href="/cerrar-sesion" method="post" as="button" class="btn-secondary">Salir</Link>
                </div>
                <button class="rounded-lg p-2 text-slate-600 md:hidden" type="button" aria-label="Abrir navegación" @click="mobileOpen = !mobileOpen">
                    <span class="block text-2xl">☰</span>
                </button>
            </div>
            <nav v-if="mobileOpen" class="border-t border-slate-200 px-4 py-3 md:hidden">
                <Link href="/panel" class="mobile-nav-link">Panel</Link>
                <Link v-if="canManageUsers" href="/administracion/usuarios" class="mobile-nav-link">Usuarios</Link>
                <Link v-if="canManageLeagues" href="/administracion/ligas" class="mobile-nav-link">Ligas</Link>
                <Link v-if="context && canViewMembers" href="/liga/miembros" class="mobile-nav-link">Miembros</Link>
                <Link v-if="context && canViewSettings" href="/liga/configuracion" class="mobile-nav-link">Configuración</Link>
                <Link v-if="context && canViewAudit" href="/liga/bitacora" class="mobile-nav-link">Bitácora</Link>
                <Link href="/seleccionar-acceso" class="mobile-nav-link">Cambiar liga o rol</Link>
                <Link href="/cerrar-sesion" method="post" as="button" class="mobile-nav-link w-full text-left">Cerrar sesión</Link>
            </nav>
        </header>

        <div v-if="page.props.flash.success" class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
        </div>
        <div v-if="page.props.flash.error" class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ page.props.flash.error }}</div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
