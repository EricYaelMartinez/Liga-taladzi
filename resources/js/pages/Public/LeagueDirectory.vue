<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { CSSProperties } from 'vue';

type PublicLeague = { id: number; name: string; slug: string; logo_url: string | null; primary_color: string; secondary_color: string };
defineProps<{ leagues: PublicLeague[] }>();

function leagueStyle(league: PublicLeague): CSSProperties {
    return { '--league-primary': league.primary_color, '--league-secondary': league.secondary_color } as CSSProperties;
}
</script>

<template>
    <Head title="Ligas de fútbol" />
    <main class="min-h-screen bg-slate-100">
        <header class="bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                <Link href="/" class="font-semibold tracking-wide">Liga Taladzi</Link>
                <Link href="/iniciar-sesion" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">Acceso privado</Link>
            </div>
            <div class="mx-auto max-w-7xl px-5 pb-14 pt-10 sm:px-8 sm:pb-20 sm:pt-16">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-400">Portal público</p>
                <h1 class="mt-3 max-w-3xl text-4xl font-semibold leading-tight sm:text-5xl">Consulta la actividad oficial de tu liga</h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">Calendarios, resultados, posiciones, goleadores y disciplina deportiva, sin necesidad de iniciar sesión.</p>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14">
            <div class="mb-7"><p class="text-sm font-bold uppercase tracking-wider text-slate-500">Ligas disponibles</p><h2 class="mt-1 text-2xl font-semibold">Selecciona una liga</h2></div>
            <div v-if="!leagues.length" class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-600 shadow-sm">No hay ligas públicas disponibles en este momento.</div>
            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="league in leagues" :key="league.id" :href="`/ligas/${league.slug}`" class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl" :style="leagueStyle(league)">
                    <div class="h-2" style="background: linear-gradient(90deg, var(--league-primary), var(--league-secondary))"></div>
                    <div class="flex items-center gap-4 p-6">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100">
                            <img v-if="league.logo_url" :src="league.logo_url" alt="" class="h-full w-full object-contain p-1">
                            <span v-else class="text-2xl font-bold" style="color: var(--league-primary)">{{ league.name.slice(0, 1) }}</span>
                        </div>
                        <div class="min-w-0"><h3 class="text-lg font-semibold text-slate-900">{{ league.name }}</h3><p class="mt-1 text-sm font-semibold transition group-hover:translate-x-1" style="color: var(--league-primary)">Consultar información →</p></div>
                    </div>
                </Link>
            </div>
        </section>

        <footer class="border-t border-slate-200 bg-white px-5 py-6 text-center text-sm text-slate-500">Información deportiva oficial publicada por cada liga.</footer>
    </main>
</template>
