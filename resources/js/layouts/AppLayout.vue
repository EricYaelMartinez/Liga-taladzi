<script setup lang="ts">
import type { AppPageProps } from '../types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<AppPageProps>();
const mobileOpen = ref(false);
const user = computed(() => page.props.auth.user);
const canManageUsers = computed(() => user.value?.permissions.includes('users.view') ?? false);
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link href="/panel" class="font-semibold text-league-900">Liga Taladzi</Link>
                    <nav class="hidden items-center gap-2 md:flex">
                        <Link href="/panel" class="nav-link" :class="{ 'nav-link-active': page.url === '/panel' }">Panel</Link>
                        <Link v-if="canManageUsers" href="/administracion/usuarios" class="nav-link" :class="{ 'nav-link-active': page.url.startsWith('/administracion/usuarios') }">Usuarios</Link>
                    </nav>
                </div>
                <div class="hidden items-center gap-4 md:flex">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-800">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ user?.email }}</p>
                    </div>
                    <Link href="/cerrar-sesion" method="post" as="button" class="btn-secondary">Salir</Link>
                </div>
                <button class="rounded-lg p-2 text-slate-600 md:hidden" type="button" aria-label="Abrir navegación" @click="mobileOpen = !mobileOpen">
                    <span class="block text-2xl">☰</span>
                </button>
            </div>
            <nav v-if="mobileOpen" class="border-t border-slate-200 px-4 py-3 md:hidden">
                <Link href="/panel" class="mobile-nav-link">Panel</Link>
                <Link v-if="canManageUsers" href="/administracion/usuarios" class="mobile-nav-link">Usuarios</Link>
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
