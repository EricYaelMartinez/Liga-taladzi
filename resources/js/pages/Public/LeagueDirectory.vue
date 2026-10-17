<script setup lang="ts">
import type { AppPageProps } from '../../types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { CSSProperties } from 'vue';

type FeaturedCompetition = {
    name: string;
    status: string;
    division: string;
    category: string;
    gender: string;
    format: string;
    season: string;
    starts_on: string | null;
    ends_on: string | null;
};
type PublicLeague = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    primary_color: string;
    secondary_color: string;
    genders: string[];
    featured_competition: FeaturedCompetition | null;
};

const props = defineProps<{ leagues: PublicLeague[] }>();
const page = usePage<AppPageProps>();
const brand = computed(() => page.props.brand);
const search = ref('');
const gender = ref<'all' | 'male' | 'female' | 'mixed'>('all');
const currentYear = new Date().getFullYear();

const filters = [
    { value: 'all', label: 'Todas' },
    { value: 'male', label: 'Varonil' },
    { value: 'female', label: 'Femenil' },
    { value: 'mixed', label: 'Libre' },
] as const;

const filteredLeagues = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    return props.leagues.filter((league) => {
        const competition = league.featured_competition;
        const searchable = [league.name, competition?.name, competition?.division, competition?.category, competition?.season]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('es');
        const matchesSearch = !term || searchable.includes(term);
        const matchesGender = gender.value === 'all' || league.genders.includes(gender.value);
        return matchesSearch && matchesGender;
    });
});

function leagueStyle(league: PublicLeague): CSSProperties {
    return { '--league-primary': league.primary_color, '--league-secondary': league.secondary_color } as CSSProperties;
}

function formatLabel(value: string): string {
    return ({ round_robin: 'Liga', double_round_robin: 'Liga ida/vuelta', knockout: 'Eliminación', groups_knockout: 'Grupos y final', manual: 'Manual' } as Record<string, string>)[value] ?? value;
}

function genderLabel(value: string): string {
    return ({ male: 'Varonil', female: 'Femenil', mixed: 'Libre' } as Record<string, string>)[value] ?? 'Libre';
}

function statusLabel(competition: FeaturedCompetition | null): string {
    if (!competition) return 'Liga activa';
    return competition.status === 'active' ? 'En curso' : 'Finalizada';
}

function dateLabel(value: string | null): string {
    if (!value) return 'Por definir';
    const [year, month, day] = value.split('-');
    return `${day}/${month}/${year.slice(-2)}`;
}
</script>

<template>
    <Head title="Ligas de fútbol"><meta name="description" content="Consulta calendarios, resultados, posiciones y estadísticas oficiales de las ligas disponibles en ProLiga."></Head>
    <div class="relative min-h-screen overflow-hidden bg-[#0f1b31] text-white">
        <div class="pointer-events-none fixed inset-0 bg-cover bg-[position:center_68%] opacity-25" style="background-image: url('/images/auth/proliga-stadium.jpg')"></div>
        <div class="pointer-events-none fixed inset-0 bg-[linear-gradient(180deg,rgba(15,27,49,0.97)_0%,rgba(20,38,68,0.93)_52%,rgba(15,31,52,0.86)_100%)]"></div>
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_50%_45%,rgba(46,125,50,0.12),transparent_42%)]"></div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="border-t-2 border-green-600 border-b border-white/10 bg-[#0d192d]/85 backdrop-blur-md">
                <div class="mx-auto flex min-h-20 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
                    <Link href="/" class="flex items-center gap-3">
                        <img :src="brand.logoUrl" :alt="`Logotipo de ${brand.name}`" class="h-14 w-14 object-contain">
                        <span><strong class="block text-2xl font-bold tracking-tight">{{ brand.name }}</strong><small class="block text-[9px] font-bold uppercase tracking-[0.2em] text-gold-400">Gestión deportiva</small></span>
                    </Link>
                    <Link href="/iniciar-sesion" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-gold-500 px-5 py-2.5 text-sm font-bold text-league-900 shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-gold-400 focus:outline-none focus:ring-4 focus:ring-gold-400/30">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                        Acceso
                    </Link>
                </div>
            </header>

            <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-10 sm:px-8 sm:py-14">
                <section>
                    <p class="inline-flex items-center gap-2 rounded-full border border-green-400/25 bg-green-500/10 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-green-300"><span class="h-2 w-2 rounded-full bg-green-400"></span> Plataforma oficial de ligas</p>
                    <h1 class="mt-4 max-w-4xl text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">Consulta la actividad oficial de tu liga</h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">Calendarios, resultados, tablas de posiciones, goleadores y disciplina deportiva en tiempo real, sin necesidad de iniciar sesión.</p>
                </section>

                <section class="mt-9 flex flex-col gap-4 rounded-2xl border border-white/10 bg-[#172746]/80 p-4 shadow-xl shadow-black/10 backdrop-blur-md md:flex-row md:items-center md:justify-between">
                    <label class="relative block w-full md:max-w-sm">
                        <span class="sr-only">Buscar liga</span>
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                        <input v-model="search" type="search" class="min-h-11 w-full rounded-xl border border-white/15 bg-[#14223d] py-2.5 pl-12 pr-4 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-gold-400 focus:ring-4 focus:ring-gold-400/10" placeholder="Buscar por liga, división o categoría…">
                    </label>
                    <div class="flex flex-wrap gap-2" aria-label="Filtrar ligas">
                        <button v-for="filter in filters" :key="filter.value" type="button" class="min-h-10 rounded-xl border px-4 py-2 text-xs font-bold transition" :class="gender === filter.value ? 'border-gold-500 bg-gold-500 text-league-900' : 'border-white/10 bg-[#14223d] text-slate-300 hover:border-white/25 hover:text-white'" @click="gender = filter.value">{{ filter.label }}</button>
                    </div>
                </section>

                <section class="mt-8">
                    <div class="mb-5 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <h2 class="flex items-center gap-2 text-xl font-bold"><span class="text-gold-400">🏆</span> Ligas disponibles</h2>
                        <p class="text-xs text-slate-400">Mostrando {{ filteredLeagues.length }} de {{ leagues.length }} ligas activas</p>
                    </div>

                    <div v-if="!filteredLeagues.length" class="rounded-2xl border border-white/10 bg-[#172746]/85 p-12 text-center shadow-xl backdrop-blur-md">
                        <p class="text-lg font-semibold">No encontramos ligas con esos filtros.</p>
                        <button type="button" class="mt-3 text-sm font-bold text-gold-400 hover:underline" @click="search = ''; gender = 'all'">Limpiar búsqueda</button>
                    </div>

                    <div v-else class="space-y-4">
                        <article v-for="league in filteredLeagues" :key="league.id" class="group relative overflow-hidden rounded-2xl border border-white/10 bg-[#1a2c4f]/90 shadow-xl shadow-black/10 backdrop-blur-md transition hover:-translate-y-0.5 hover:border-white/20 hover:shadow-2xl" :style="leagueStyle(league)">
                            <div class="absolute inset-y-0 left-0 w-1.5" style="background: linear-gradient(180deg, var(--league-primary), var(--league-secondary))"></div>
                            <div class="grid gap-5 p-5 pl-7 md:grid-cols-[minmax(260px,1fr)_minmax(330px,1.15fr)_auto] md:items-center">
                                <div class="flex min-w-0 items-center gap-4">
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-[#0e1a30] shadow-inner">
                                        <img v-if="league.logo_url" :src="league.logo_url" :alt="`Logotipo de ${league.name}`" class="h-full w-full object-contain p-1.5">
                                        <span v-else class="text-2xl font-extrabold" style="color: var(--league-secondary)">{{ league.name.slice(0, 1) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2"><span class="rounded-md border px-2 py-1 text-[10px] font-extrabold uppercase" :class="league.featured_competition?.status === 'finished' ? 'border-blue-300/20 bg-blue-300/10 text-blue-200' : 'border-green-400/20 bg-green-500/15 text-green-300'">{{ statusLabel(league.featured_competition) }}</span><span v-if="league.featured_competition" class="truncate text-xs text-slate-400">{{ league.featured_competition.season }}</span></div>
                                        <h3 class="mt-1 truncate text-xl font-bold text-white">{{ league.name }}</h3>
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ league.featured_competition?.name ?? 'Información pública de la liga' }}</p>
                                    </div>
                                </div>

                                <div v-if="league.featured_competition" class="grid grid-cols-3 gap-x-4 gap-y-3 rounded-xl bg-[#111f39]/80 px-4 py-3 sm:grid-cols-6">
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">División</small><strong class="mt-0.5 block truncate text-xs">{{ league.featured_competition.division }}</strong></div>
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">Categoría</small><strong class="mt-0.5 block truncate text-xs">{{ league.featured_competition.category }}</strong></div>
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">Formato</small><strong class="mt-0.5 block truncate text-xs">{{ formatLabel(league.featured_competition.format) }}</strong></div>
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">Género</small><strong class="mt-0.5 block truncate text-xs">{{ genderLabel(league.featured_competition.gender) }}</strong></div>
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">Inicio</small><strong class="mt-0.5 block text-xs">{{ dateLabel(league.featured_competition.starts_on) }}</strong></div>
                                    <div><small class="block text-[9px] font-bold uppercase text-slate-500">Fin</small><strong class="mt-0.5 block text-xs">{{ dateLabel(league.featured_competition.ends_on) }}</strong></div>
                                </div>
                                <div v-else class="text-sm text-slate-400">Próxima competencia por publicar</div>

                                <Link :href="`/ligas/${league.slug}`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-green-700 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-black/10 transition hover:bg-green-600 focus:outline-none focus:ring-4 focus:ring-green-500/20">Ver liga <span aria-hidden="true">→</span></Link>
                            </div>
                        </article>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/10 bg-[#0c172a]/90 px-5 py-6 text-center text-xs text-slate-400 backdrop-blur-md">
                <p>© {{ currentYear }} {{ brand.name }}. Todos los derechos reservados.</p>
                <p class="mt-1">Información deportiva oficial publicada por cada liga autorizada.</p>
            </footer>
        </div>
    </div>
</template>
