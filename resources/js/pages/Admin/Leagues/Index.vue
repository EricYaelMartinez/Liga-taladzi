<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import type { AppPageProps, LeagueSummary, StatusOption } from '../../../types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

type PaginationLink = { url: string | null; label: string; active: boolean };
type Page = { data: LeagueSummary[]; links: PaginationLink[]; total: number };
const props = defineProps<{ leagues: Page; filters: { search: string }; statuses: StatusOption[] }>();
const page = usePage<AppPageProps>();
const filters = reactive({ search: props.filters.search });
const canCreate = page.props.auth.user?.permissions.includes('leagues.create') ?? false;
const canUpdate = page.props.auth.user?.permissions.includes('leagues.update') ?? false;
const canDelete = page.props.auth.user?.permissions.includes('leagues.delete') ?? false;
const label = (value: string) => props.statuses.find((item) => item.value === value)?.label ?? value;
const remove = (league: LeagueSummary) => {
    const reason = window.prompt(`Motivo para eliminar lógicamente “${league.name}”:`);
    if (reason?.trim()) router.delete(`/administracion/ligas/${league.id}`, { data: { reason }, preserveScroll: true });
};
</script>

<template>
    <Head title="Ligas" />
    <AppLayout>
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Administración general</p><h1 class="mt-1 text-3xl font-semibold">Ligas</h1><p class="mt-2 text-slate-600">Organizaciones independientes dentro de la plataforma.</p></div>
            <Link v-if="canCreate" href="/administracion/ligas/crear" class="btn-primary">Crear liga</Link>
        </section>

        <form class="card mt-7 flex flex-col gap-3 p-4 sm:flex-row" @submit.prevent="router.get('/administracion/ligas', filters, { preserveState: true })">
            <input v-model="filters.search" class="form-input" placeholder="Buscar por nombre">
            <button class="btn-primary" type="submit">Buscar</button>
            <button class="btn-secondary" type="button" @click="filters.search = ''; router.get('/administracion/ligas')">Limpiar</button>
        </form>

        <div v-if="leagues.data.length" class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="league in leagues.data" :key="league.id" class="card overflow-hidden">
                <div class="h-2" :style="{ background: `linear-gradient(90deg, ${league.primary_color}, ${league.secondary_color})` }"></div>
                <div class="p-6">
                    <div class="flex items-start justify-between gap-3"><h2 class="text-xl font-semibold">{{ league.name }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">{{ label(league.status) }}</span></div>
                    <p class="mt-2 text-sm text-slate-500">{{ league.slug }}</p>
                    <p class="mt-5 text-sm"><strong>{{ league.active_admins_count ?? 0 }}</strong> administradores activos</p>
                    <div class="mt-6 flex gap-2"><Link v-if="canUpdate" :href="`/administracion/ligas/${league.id}/editar`" class="btn-secondary">Editar</Link><button v-if="canDelete" type="button" class="btn-danger" @click="remove(league)">Eliminar</button></div>
                </div>
            </article>
        </div>
        <div v-else class="card mt-6 p-12 text-center text-slate-600">No se encontraron ligas.</div>

        <nav v-if="leagues.total > leagues.data.length" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="link in leagues.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg px-3 py-2 text-sm" :class="[link.active ? 'bg-league-700 text-white' : 'bg-white text-slate-600', { 'pointer-events-none opacity-40': !link.url }]" v-html="link.label" />
        </nav>
    </AppLayout>
</template>
