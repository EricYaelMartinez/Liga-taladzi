<script setup lang="ts">
import type { AppPageProps } from '../types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<AppPageProps>();
const brand = computed(() => page.props.brand);
const currentYear = new Date().getFullYear();
</script>

<template>
    <main class="min-h-screen bg-[#f5f8fb] lg:grid lg:grid-cols-[minmax(440px,0.84fr)_minmax(560px,1.16fr)]">
        <section
            class="relative hidden min-h-screen overflow-hidden bg-cover bg-[position:center_58%] text-white lg:flex lg:flex-col lg:justify-between"
            style="background-image: url('/images/auth/proliga-stadium.jpg')"
        >
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(13,31,60,0.96),rgba(27,42,74,0.86)_50%,rgba(20,78,46,0.72))]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_8%,rgba(46,125,50,0.22),transparent_34%)]"></div>

            <div class="relative z-10 flex min-h-screen flex-col px-10 py-9 xl:px-14 xl:py-11">
                <Link href="/" class="flex w-fit items-center gap-3.5">
                    <img :src="brand.logoUrl" :alt="`Logotipo de ${brand.name}`" class="h-16 w-16 object-contain drop-shadow-lg">
                    <span>
                        <strong class="block text-2xl font-bold tracking-tight">{{ brand.name }}</strong>
                        <small class="block text-[10px] font-bold uppercase tracking-[0.18em] text-gold-400">Gestión integral de ligas</small>
                    </span>
                </Link>

                <div class="my-auto max-w-2xl py-12">
                    <p class="inline-flex items-center gap-2 rounded-full border border-gold-400/40 bg-gold-500/10 px-4 py-2 text-xs font-bold text-gold-400 backdrop-blur-sm">
                        <span aria-hidden="true">🏆</span> Sistema integral de gestión
                    </p>
                    <h1 class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight xl:text-5xl">La potencia detrás de <span class="text-gold-400">tu torneo</span></h1>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-200 xl:text-lg">Administra calendarios, posiciones, sanciones y estadísticas en un entorno seguro y profesional.</p>

                    <div class="mt-8 space-y-3">
                        <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4 backdrop-blur-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-green-400/30 bg-green-400/10 text-green-300">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" stroke-linecap="round"/></svg>
                            </span>
                            <span><strong class="block text-sm uppercase tracking-wide">Tablas automatizadas</strong><small class="mt-0.5 block text-sm text-slate-300">Posiciones, resultados y estadísticas siempre actualizados.</small></span>
                        </div>
                        <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4 backdrop-blur-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gold-400/30 bg-gold-400/10 text-gold-400">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.9 8.1 7 10 4.1-1.9 7-5.4 7-10V6l-7-3Z"/><path d="m9.5 12 1.6 1.6 3.5-3.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span><strong class="block text-sm uppercase tracking-wide">Control deportivo</strong><small class="mt-0.5 block text-sm text-slate-300">Jugadores, arbitraje, cédulas y disciplina en un solo lugar.</small></span>
                        </div>
                        <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4 backdrop-blur-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-300/30 bg-blue-300/10 text-blue-200">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3" stroke-linecap="round"/></svg>
                            </span>
                            <span><strong class="block text-sm uppercase tracking-wide">Información en tiempo real</strong><small class="mt-0.5 block text-sm text-slate-300">Consulta la actividad de la liga desde cualquier dispositivo.</small></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-white/15 pt-5 text-xs text-slate-300">
                    <span>{{ brand.tagline }}</span>
                    <span class="inline-flex items-center gap-1.5 text-green-300"><span class="h-2 w-2 rounded-full bg-green-400"></span> Plataforma disponible</span>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen flex-col px-5 py-7 sm:px-10 lg:px-12 xl:px-20">
            <div class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center py-6 lg:py-10">
                <Link href="/" class="mb-8 flex items-center justify-center gap-3 lg:hidden">
                    <img :src="brand.logoUrl" :alt="`Logotipo de ${brand.name}`" class="h-16 w-16 object-contain">
                    <span><strong class="block text-2xl text-league-900">{{ brand.name }}</strong><small class="block text-xs font-semibold text-league-700">{{ brand.tagline }}</small></span>
                </Link>
                <slot />
            </div>

            <footer class="mx-auto flex w-full max-w-4xl flex-col items-center justify-between gap-3 border-t border-slate-200 pt-5 text-center text-xs text-slate-400 sm:flex-row sm:text-left">
                <span>© {{ currentYear }} {{ brand.name }}. Todos los derechos reservados.</span>
                <Link href="/" class="font-semibold text-slate-500 transition hover:text-league-700">Portal público</Link>
            </footer>
        </section>
    </main>
</template>
