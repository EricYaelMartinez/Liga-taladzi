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
    <Head title="Ligas oficiales de fútbol"><meta name="description" content="Consulta calendarios, resultados, posiciones y estadísticas oficiales de las ligas disponibles en ProLiga."></Head>
    <div class="directory-page relative min-h-screen overflow-hidden text-white">
        <div class="directory-backdrop pointer-events-none absolute inset-0"></div>
        <div class="directory-vignette pointer-events-none absolute inset-0"></div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="directory-header border-b border-white/15 backdrop-blur-md">
                <div class="mx-auto flex min-h-[76px] max-w-[1320px] items-center justify-between gap-4 px-5 sm:px-8">
                    <Link href="/" class="flex items-center gap-3" aria-label="Inicio de ProLiga">
                        <img :src="brand.logoUrl" :alt="`Logotipo de ${brand.name}`" class="h-14 w-14 object-contain">
                        <span><strong class="block text-2xl font-extrabold tracking-tight">{{ brand.name }}</strong><small class="block text-[10px] font-extrabold uppercase tracking-[0.22em] text-[#dfb76b]">Gestión deportiva</small></span>
                    </Link>
                    <Link href="/iniciar-sesion" class="access-button inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-6 py-2.5 text-sm font-extrabold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-yellow-400/30">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                        Acceso
                    </Link>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1320px] flex-1 px-5 pb-12 pt-9 sm:px-8 sm:pb-14">
                <section class="directory-hero mx-auto max-w-[1150px] text-center">
                    <p class="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-500/10 px-4 py-2 text-xs font-extrabold uppercase tracking-wide text-emerald-200"><span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,.65)]"></span> Plataforma oficial de ligas</p>
                    <h1 class="mx-auto mt-5 max-w-[1150px] text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-[3.35rem]">Consulta la actividad oficial de tu liga</h1>
                    <p class="mx-auto mt-3 max-w-[800px] text-base leading-relaxed text-slate-200 sm:text-lg">Calendarios, resultados, tablas de posiciones, goleadores y disciplina deportiva en tiempo real, sin necesidad de iniciar sesión.</p>
                    <p class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-300">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 2 8 3v6c0 5-3.3 8-8 11-4.7-3-8-6-8-11V5z"/><path d="M12 7v9m-3-3 3 3 3-3"/></svg>
                        Acceso público a la actividad oficial de ligas, con información confiable y actualizada.
                    </p>
                </section>

                <section class="directory-search mx-auto mt-7 flex max-w-[1130px] flex-col gap-4 rounded-[19px] border border-white/15 p-4 shadow-xl shadow-black/15 backdrop-blur-md lg:flex-row lg:items-center">
                    <label class="relative block min-w-0 flex-1">
                        <span class="sr-only">Buscar liga, división o categoría</span>
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                        <input v-model="search" type="search" class="directory-input min-h-[47px] w-full rounded-full border border-white/15 py-3 pl-12 pr-4 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-[#dfb76b] focus:ring-4 focus:ring-yellow-400/10" placeholder="Buscar por liga, división o categoría...">
                    </label>
                    <div class="hidden h-10 w-px bg-white/15 lg:block"></div>
                    <div class="flex flex-wrap items-center justify-center gap-2" role="group" aria-label="Filtrar ligas por rama">
                        <button v-for="filter in filters" :key="filter.value" type="button" :aria-pressed="gender === filter.value" class="directory-filter min-h-10 rounded-full border px-5 py-2 text-xs font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-yellow-400/20" :class="gender === filter.value ? 'directory-filter--active' : 'border-white/15 bg-[#0f1b30]/65 text-slate-200 hover:border-white/30 hover:text-white'" @click="gender = filter.value">{{ filter.label }}</button>
                    </div>
                </section>

                <section class="mx-auto mt-9 max-w-[1260px]">
                    <div class="mb-5 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <h2 class="flex items-center gap-3 text-xl font-extrabold sm:text-2xl"><span class="text-[#ecc269]" aria-hidden="true">🏆</span> Ligas disponibles</h2>
                        <p class="text-sm text-slate-300">Mostrando {{ filteredLeagues.length }} de {{ leagues.length }} ligas activas</p>
                    </div>

                    <div v-if="!filteredLeagues.length" class="rounded-2xl border border-white/15 bg-[#16233a]/90 p-12 text-center shadow-xl backdrop-blur-md">
                        <p class="text-lg font-semibold">No encontramos ligas con esos filtros.</p>
                        <button type="button" class="mt-3 text-sm font-bold text-[#ecc269] hover:underline" @click="search = ''; gender = 'all'">Limpiar búsqueda</button>
                    </div>

                    <div v-else class="grid gap-4 lg:grid-cols-2">
                        <article v-for="league in filteredLeagues" :key="league.id" class="league-card group relative flex min-w-0 flex-col overflow-hidden rounded-[19px] border border-white/15 shadow-xl shadow-black/15 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:border-white/30 hover:shadow-2xl" :style="leagueStyle(league)">
                            <div class="league-card__accent absolute inset-y-0 left-0 w-[6px]" aria-hidden="true"></div>
                            <div class="flex min-w-0 flex-1 flex-col gap-4 p-4 pl-6 sm:p-5 sm:pl-7">
                                <div class="flex min-w-0 items-center gap-4">
                                    <div class="league-logo flex h-[73px] w-[73px] shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/15 shadow-inner">
                                        <img v-if="league.logo_url" :src="league.logo_url" :alt="`Logotipo de ${league.name}`" class="h-full w-full object-contain p-1.5">
                                        <span v-else class="text-3xl font-black" style="color: var(--league-secondary)">{{ league.name.slice(0, 1) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="status-pill" :class="league.featured_competition ? (league.featured_competition.status === 'active' ? 'status-pill--active' : 'status-pill--pending') : 'status-pill--active'">{{ league.featured_competition ? statusLabel(league.featured_competition) : 'Liga activa' }}</span>
                                            <span v-if="league.featured_competition?.season" class="max-w-[120px] truncate text-xs text-slate-400">{{ league.featured_competition.season }}</span>
                                        </div>
                                        <h3 class="mt-1 line-clamp-2 text-lg font-extrabold leading-snug text-white">{{ league.name }}</h3>
                                        <p class="mt-1 truncate text-xs text-slate-400">{{ league.featured_competition?.name ?? 'Información pública de la liga' }}</p>
                                    </div>
                                    <Link :href="`/ligas/${league.slug}`" :aria-label="`Ver liga ${league.name}`" class="league-visit hidden shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-extrabold text-white shadow-lg shadow-black/10 transition hover:brightness-110 sm:inline-flex">Ver liga <span aria-hidden="true">→</span></Link>
                                </div>
                                <div v-if="league.featured_competition" class="league-details grid grid-cols-3 gap-x-3 gap-y-3 rounded-2xl border border-white/10 px-4 py-3 sm:grid-cols-6">
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">División</small><strong class="block truncate text-xs" :title="league.featured_competition.division">{{ league.featured_competition.division }}</strong></div>
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">Categoría</small><strong class="block truncate text-xs" :title="league.featured_competition.category">{{ league.featured_competition.category }}</strong></div>
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">Formato</small><strong class="block truncate text-xs">{{ formatLabel(league.featured_competition.format) }}</strong></div>
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">Género</small><strong class="block truncate text-xs">{{ genderLabel(league.featured_competition.gender) }}</strong></div>
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">Inicio</small><strong class="block text-xs">{{ dateLabel(league.featured_competition.starts_on) }}</strong></div>
                                    <div class="min-w-0"><small class="block text-[10px] text-slate-400">Fin</small><strong class="block text-xs">{{ dateLabel(league.featured_competition.ends_on) }}</strong></div>
                                </div>
                                <div v-else class="league-details flex min-h-[64px] flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 px-4 py-3 text-xs text-slate-300">
                                    <span class="flex items-center gap-2"><svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></svg> Próxima competencia por publicar</span>
                                    <span class="flex flex-wrap gap-2"><span v-for="item in league.genders" :key="item" class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold">{{ genderLabel(item) }}</span></span>
                                </div>
                                <Link :href="`/ligas/${league.slug}`" class="league-visit inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-110 sm:hidden">Ver liga <span aria-hidden="true">→</span></Link>
                            </div>
                        </article>
                    </div>
                </section>
            </main>

            <footer class="directory-footer border-t border-white/10 px-5 py-6 text-center text-xs text-slate-400 backdrop-blur-md">
                <p>© {{ currentYear }} {{ brand.name }}. Todos los derechos reservados.</p>
                <p class="mt-1">Información deportiva oficial publicada por cada liga autorizada.</p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.directory-page { background: #0b192d; }
.directory-backdrop { background-image: url('/images/auth/proliga-stadium.jpg'); background-size: cover; background-position: center 60%; opacity: .37; }
.directory-vignette { background: linear-gradient(180deg, rgba(8,19,35,.95) 0%, rgba(10,27,49,.72) 32%, rgba(7,20,37,.83) 68%, rgba(8,19,34,.94) 100%), radial-gradient(ellipse at center 37%, transparent 20%, rgba(4,12,25,.43) 90%); }
.directory-header, .directory-footer { background: rgba(8,18,34,.75); }
.access-button { background: linear-gradient(135deg, #d2a45b, #ecc671); color: #11213b; box-shadow: 0 5px 20px rgba(0,0,0,.23); }
.directory-search { background: linear-gradient(120deg,rgba(35,50,76,.77),rgba(19,35,59,.78)); }
.directory-input { background: rgba(8,20,39,.65); }
.directory-filter--active { border-color:#d5a958; background:linear-gradient(135deg,#cfa154,#e4bc72); color:#101d33; box-shadow: 0 4px 14px rgba(0,0,0,.12); }
.league-card { background: linear-gradient(130deg,rgba(32,47,73,.93),rgba(14,28,49,.96)); }
.league-card__accent { background: linear-gradient(180deg, var(--league-primary,#265db2), var(--league-secondary,#28a65f)); }
.league-logo { background: rgba(7,18,34,.86); }
.league-details { background: rgba(12,25,45,.7); }
.status-pill { display:inline-flex; align-items:center; border-radius:8px; border:1px solid rgba(255,255,255,.14); padding:4px 9px; font-size:10px; font-weight:800; text-transform:uppercase; }
.status-pill--active { background:rgba(22,163,74,.22); border-color:rgba(74,222,128,.23); color:#9bf0b5; }
.status-pill--pending { background:rgba(220,156,45,.18); border-color:rgba(234,179,8,.25); color:#ffd68b; }
.league-visit { background: linear-gradient(135deg,#129c53,#04763b); }
@media (prefers-reduced-motion: reduce) { .league-card, .league-visit { transition:none; } }
</style>
