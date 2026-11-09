<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import AppIcon from '../../components/AppIcon.vue';
import type { RoleOption } from '../../types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LeagueOption = {
    league: { id: number; name: string; logo_url: string | null; primary_color: string | null; secondary_color: string | null };
    role: RoleOption;
};
type LeagueGroup = { league: LeagueOption['league']; roles: RoleOption[] };

const props = defineProps<{ canUseSystemAdministration: boolean; options: LeagueOption[] }>();
const page = usePage();
const form = useForm({ scope: 'league', league_id: null as number | null, role_id: null as number | null });
const pendingKey = ref<string | null>(null);
const failedLogos = ref<number[]>([]);

const groups = computed<LeagueGroup[]>(() => {
    const byId = new Map<number, LeagueGroup>();
    for (const option of props.options) {
        let group = byId.get(option.league.id);
        if (!group) {
            group = { league: option.league, roles: [] };
            byId.set(option.league.id, group);
        }
        if (!group.roles.some((role) => role.id === option.role.id)) group.roles.push(option.role);
    }
    return [...byId.values()].sort((a, b) => a.league.name.localeCompare(b.league.name, 'es'));
});
const activeContext = computed(() => (page.props as { activeContext?: { league?: { id: number }; role?: { id: number } } | null }).activeContext);

const iconFor = (slug: string) => {
    if (slug.includes('referee') || slug.includes('arbit')) return 'badge';
    if (slug.includes('player') || slug.includes('jugador')) return 'football';
    if (slug.includes('representative') || slug.includes('owner') || slug.includes('represent')) return 'clipboard';
    return 'settings';
};
const roleDescription = (slug: string) => {
    if (slug.includes('referee') || slug.includes('arbit')) return 'Gestión de designaciones, reportes de partidos y actas.';
    if (slug.includes('player') || slug.includes('jugador')) return 'Consulta de calendarios, resultados e información deportiva.';
    if (slug.includes('representative') || slug.includes('owner') || slug.includes('represent')) return 'Gestión de equipo, plantillas y participación deportiva.';
    if (slug.includes('league_admin') || slug.includes('admin')) return 'Gestión completa de la liga, equipos, calendario y configuración.';
    return 'Accede a las funciones habilitadas para este rol.';
};
const currentRole = (leagueId: number, roleId: number) => activeContext.value?.league?.id === leagueId && activeContext.value?.role?.id === roleId;
const canShowLogo = (league: LeagueGroup['league']) => Boolean(league.logo_url) && !failedLogos.value.includes(league.id);
const initials = (name: string) => name.split(/\s+/).slice(0, 2).map((s) => s.charAt(0)).join('').toUpperCase();
const colors = (league: LeagueGroup['league']) => ({
    '--league-accent': /^#[0-9a-fA-F]{6}$/.test(league.primary_color ?? '') ? league.primary_color! : '#2464aa',
});

const selectSystem = () => {
    if (form.processing) return;
    pendingKey.value = 'system';
    form.scope = 'system'; form.league_id = null; form.role_id = null;
    form.post('/seleccionar-acceso', { onFinish: () => pendingKey.value = null });
};
const selectLeague = (leagueId: number, roleId: number) => {
    if (form.processing) return;
    pendingKey.value = `${leagueId}-${roleId}`;
    form.scope = 'league'; form.league_id = leagueId; form.role_id = roleId;
    form.post('/seleccionar-acceso', { onFinish: () => pendingKey.value = null });
};
</script>

<template>
    <Head title="Seleccionar liga y rol" />
    <AppLayout>
        <section class="access-selector mx-auto max-w-6xl pb-12">
            <header class="mb-7">
                <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-emerald-700">Contexto de trabajo</p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Selecciona una liga y un rol</h1>
                <p class="mt-2 text-base text-slate-600">Los permisos y la información visible cambiarán según tu selección.</p>
                <div class="mt-5 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <AppIcon name="badge" :size="20" class="text-slate-600" />
                    <span>Primero elige una liga y, después, selecciona el rol con el que deseas entrar.</span>
                </div>
            </header>

            <div v-if="form.hasErrors" role="alert" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                No fue posible cambiar el acceso. Comprueba tus permisos e inténtalo nuevamente.
                <span v-for="(error, field) in form.errors" :key="field" class="block">{{ error }}</span>
            </div>

            <div v-if="canUseSystemAdministration" class="mb-6 rounded-[1.4rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700"><AppIcon name="settings" :size="28" /></div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Administración del sistema</h2>
                            <p class="mt-1 text-sm text-slate-600">Gestiona ligas, usuarios y la configuración general de ProLiga.</p>
                        </div>
                    </div>
                    <button type="button" class="system-action" :disabled="form.processing" @click="selectSystem">
                        {{ pendingKey === 'system' ? 'Ingresando…' : 'Entrar a administración' }} <span aria-hidden="true">→</span>
                    </button>
                </div>
            </div>

            <div v-if="groups.length" class="space-y-6">
                <article v-for="group in groups" :key="group.league.id" class="league-panel" :style="colors(group.league)">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="league-logo">
                                <img v-if="canShowLogo(group.league)" :src="group.league.logo_url!" :alt="`Escudo de ${group.league.name}`" class="h-full w-full object-contain" @error="failedLogos.push(group.league.id)">
                                <span v-else class="text-xl font-black" aria-hidden="true">{{ initials(group.league.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-xl font-extrabold leading-tight text-slate-950 sm:text-2xl">{{ group.league.name }}</h2>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span><span class="font-semibold text-emerald-700">Liga activa</span><span aria-hidden="true">·</span>{{ group.roles.length }} {{ group.roles.length === 1 ? 'rol disponible' : 'roles disponibles' }}</p>
                            </div>
                        </div>
                        <span class="count-pill"><AppIcon name="clipboard" :size="17" /> {{ group.roles.length }} {{ group.roles.length === 1 ? 'rol' : 'roles' }} {{ group.roles.length === 1 ? 'disponible' : 'disponibles' }}</span>
                    </div>

                    <div class="grid gap-4" :class="group.roles.length === 1 ? 'grid-cols-1' : group.roles.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3'">
                        <button v-for="role in group.roles" :key="role.id" type="button" class="role-card group text-left" :class="{ 'role-card--current': currentRole(group.league.id, role.id) }" :disabled="form.processing" :aria-label="`Entrar a ${group.league.name} como ${role.name}`" @click="selectLeague(group.league.id, role.id)">
                            <div class="mb-4 flex items-start gap-3">
                                <span class="role-icon" :class="iconFor(role.slug)"><AppIcon :name="iconFor(role.slug)" :size="25" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-lg font-extrabold text-slate-950">{{ role.name }}</span>
                                    <span class="mt-1 block text-sm leading-relaxed text-slate-600">{{ roleDescription(role.slug) }}</span>
                                </span>
                                <span v-if="currentRole(group.league.id, role.id)" class="current-check" title="Acceso activo"><AppIcon name="check" :size="15" /></span>
                            </div>
                            <span class="role-action" :class="{ 'role-action--current': currentRole(group.league.id, role.id) }">
                                {{ pendingKey === `${group.league.id}-${role.id}` ? 'Ingresando…' : `Entrar como ${role.name}` }}
                                <span aria-hidden="true">→</span>
                            </span>
                        </button>
                    </div>
                </article>
            </div>
            <div v-else-if="!canUseSystemAdministration" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><AppIcon name="shield" :size="28" /></div>
                <h2 class="text-lg font-bold text-slate-900">Todavía no tienes acceso asignado a una liga</h2>
                <p class="mt-2 text-sm text-slate-600">Solicita al administrador que te asigne una liga y un rol.</p>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.access-selector { color: #10203a; }
.league-panel { border: 1px solid #d7e0ed; border-radius: 23px; background: #fff; padding: 22px; box-shadow: 0 12px 30px rgba(27,42,74,.07); }
.league-logo { display:flex; align-items:center;justify-content:center; flex:0 0 92px; height:86px; padding:8px; border:1px solid #e1e8f2; border-radius:17px; color:var(--league-accent); background:#f8fafc; }
.count-pill { display:inline-flex; flex-shrink:0; align-items:center; gap:7px; align-self:flex-start; border-radius:99px; padding:9px 14px; background:#f1f5f9; color:#334155; font-size:12px; font-weight:700; }
.role-card { display:flex; flex-direction:column; min-height:185px; border:1px solid #dbe3ef; border-radius:17px; padding:17px; background:#fbfcff; box-shadow:0 2px 3px rgba(28,45,71,.04); transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease; }
.role-card:hover:not(:disabled), .role-card:focus-visible { transform:translateY(-2px); border-color:var(--league-accent); box-shadow:0 9px 22px rgba(27,42,74,.1); outline:none; }
.role-card--current { border-color:#16a34a; background:#f0fdf4; box-shadow:inset 0 0 0 1px rgba(22,163,74,.1); }
.role-card--current:hover:not(:disabled) { border-color:#15803d; }
.role-icon { display:flex; align-items:center;justify-content:center; width:48px; height:48px; border-radius:14px; background:#dbeafe; color:#1d64c8; flex:0 0 auto; }
.role-icon.badge { background:#d1fae5; color:#047857; }
.role-icon.football { background:#f3e8ff; color:#7e22ce; }
.role-icon.clipboard { background:#fef3c7; color:#a16207; }
.role-action { margin-top:auto; display:flex; min-height:42px; justify-content:center; align-items:center; gap:9px; border-radius:11px; background:#eef2f8; color:#162a4b; font-size:13px; font-weight:750; transition:background .16s; }
.role-card:hover .role-action { background:#dbe7f6; }
.role-action--current { color:#fff; background:linear-gradient(115deg,#047835,#129348); }
.role-card:hover .role-action--current { background:#06783a; }
.current-check {display:flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:#15803d; color:white; flex:0 0 auto;}
.system-action {display:inline-flex;justify-content:center;align-items:center;gap:9px;flex-shrink:0;border-radius:12px;background:#1d4c8e;color:white;padding:12px 18px;font-size:13px;font-weight:700;}
.system-action:hover {background:#153866;}
.role-card:disabled, .system-action:disabled {cursor:wait;opacity:.7;}
@media(max-width:640px){.league-panel{padding:16px}.league-logo{flex-basis:68px;height:68px}.role-card{min-height:160px}}
@media(prefers-reduced-motion:reduce){.role-card{transition:none}.role-card:hover:not(:disabled){transform:none}}
</style>
