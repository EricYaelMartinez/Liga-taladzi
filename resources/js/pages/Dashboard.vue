<script setup lang="ts">
import AppLayout from '../layouts/AppLayout.vue';
import type { AppPageProps } from '../types';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{ summary: { pendingTasks: number; upcomingMatches: number; notifications: number } }>();
const page = usePage<AppPageProps>();
</script>

<template>
    <Head title="Panel" />
    <AppLayout>
        <section>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Panel principal</p>
            <h1 class="mt-2 text-3xl font-semibold">Bienvenido, {{ page.props.auth.user?.name }}</h1>
            <p class="mt-2 text-slate-600">
                {{ page.props.activeContext ? `${page.props.activeContext.league.name} · ${page.props.activeContext.role.name}` : 'Administración general de la plataforma.' }}
            </p>
        </section>
        <section class="mt-8 grid gap-5 sm:grid-cols-3">
            <article class="stat-card"><span class="stat-label">Tareas pendientes</span><strong class="stat-value">{{ summary.pendingTasks }}</strong></article>
            <article class="stat-card"><span class="stat-label">Próximos partidos</span><strong class="stat-value">{{ summary.upcomingMatches }}</strong></article>
            <article class="stat-card"><span class="stat-label">Notificaciones</span><strong class="stat-value">{{ summary.notifications }}</strong></article>
        </section>
        <section class="card mt-8 p-6">
            <h2 class="text-lg font-semibold">Acceso actual</h2>
            <p class="mt-2 text-sm text-slate-600">{{ page.props.activeContext ? 'Los datos y permisos están aislados para esta liga y este rol.' : 'Estás trabajando en el contexto general del sistema.' }}</p>
            <Link href="/seleccionar-acceso" class="btn-secondary mt-5">Cambiar liga o rol</Link>
        </section>
    </AppLayout>
</template>
