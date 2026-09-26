<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import type { RoleOption } from '../../types';
import { Head, useForm } from '@inertiajs/vue3';

type LeagueOption = {
    league: { id: number; name: string; logo_path: string | null; primary_color: string; secondary_color: string };
    role: RoleOption;
};

defineProps<{ canUseSystemAdministration: boolean; options: LeagueOption[] }>();
const form = useForm({ scope: 'league', league_id: null as number | null, role_id: null as number | null });
const selectSystem = () => {
    form.scope = 'system';
    form.league_id = null;
    form.role_id = null;
    form.post('/seleccionar-acceso');
};
const selectLeague = (option: LeagueOption) => {
    form.scope = 'league';
    form.league_id = option.league.id;
    form.role_id = option.role.id;
    form.post('/seleccionar-acceso');
};
</script>

<template>
    <Head title="Seleccionar acceso" />
    <AppLayout>
        <section class="mx-auto max-w-4xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Contexto de trabajo</p>
            <h1 class="mt-1 text-3xl font-semibold">Selecciona una liga y un rol</h1>
            <p class="mt-2 text-slate-600">Los permisos y la información visible cambiarán según tu selección.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <button v-if="canUseSystemAdministration" type="button" class="card p-6 text-left transition hover:border-league-600 hover:shadow-md" :disabled="form.processing" @click="selectSystem">
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Sistema</span>
                    <h2 class="mt-4 text-xl font-semibold">Administración general</h2>
                    <p class="mt-2 text-sm text-slate-600">Gestionar usuarios y ligas de toda la plataforma.</p>
                </button>

                <button v-for="option in options" :key="`${option.league.id}-${option.role.id}`" type="button" class="card p-6 text-left transition hover:border-league-600 hover:shadow-md" :disabled="form.processing" @click="selectLeague(option)">
                    <div class="h-2 rounded-full" :style="{ backgroundColor: option.league.primary_color }"></div>
                    <h2 class="mt-4 text-xl font-semibold">{{ option.league.name }}</h2>
                    <p class="mt-2 text-sm font-medium text-league-700">{{ option.role.name }}</p>
                </button>
            </div>

            <div v-if="!canUseSystemAdministration && options.length === 0" class="card mt-8 p-8 text-center">
                <p class="font-semibold">Todavía no tienes acceso asignado a una liga.</p>
                <p class="mt-2 text-sm text-slate-600">Solicita al administrador que te asigne una liga y un rol.</p>
            </div>
        </section>
    </AppLayout>
</template>
