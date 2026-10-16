<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Entry = { id: number; action: string; auditable_type: string; auditable_id: number | null; old_values: Record<string, unknown> | null; new_values: Record<string, unknown> | null; reason: string | null; ip_address: string | null; created_at: string; actor: { id: number; name: string; email: string } | null };
type PaginationLink = { url: string | null; label: string; active: boolean };
type Filters = { action?: string; actor_id?: number | string; date_from?: string; date_to?: string; ip_address?: string };
const props = defineProps<{ entries: { data: Entry[]; links: PaginationLink[]; total: number }; filters: Filters; options: { actions: string[]; actors: { id: number; name: string; email: string }[] } }>();
const form = reactive({ action: props.filters.action ?? '', actor_id: props.filters.actor_id ? String(props.filters.actor_id) : '', date_from: props.filters.date_from ?? '', date_to: props.filters.date_to ?? '', ip_address: props.filters.ip_address ?? '' });
const query = computed(() => Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')));
const exportUrl = computed(() => `/liga/bitacora/exportar?${new URLSearchParams(query.value).toString()}`);
const applyFilters = () => router.get('/liga/bitacora', query.value, { preserveState: true, preserveScroll: true });
const clearFilters = () => { Object.assign(form, { action: '', actor_id: '', date_from: '', date_to: '', ip_address: '' }); router.get('/liga/bitacora'); };
const formatDate = (value: string) => new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const subject = (entry: Entry) => `${entry.auditable_type.split('\\').pop() ?? entry.auditable_type}${entry.auditable_id ? ` #${entry.auditable_id}` : ''}`;
</script>

<template>
    <Head title="Bitácora" />
    <AppLayout>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 19 · Auditoría</p><h1 class="mt-1 text-3xl font-semibold">Bitácora de cambios</h1><p class="mt-2 text-slate-600">Consulta quién realizó cada operación, cuándo ocurrió y qué información fue modificada dentro de esta liga.</p></div><a :href="exportUrl" class="btn-secondary">Exportar CSV</a></header>

        <form class="card mt-7 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
            <div><label class="form-label">Acción</label><select v-model="form.action" class="form-input"><option value="">Todas</option><option v-for="action in options.actions" :key="action" :value="action">{{ action }}</option></select></div>
            <div><label class="form-label">Usuario</label><select v-model="form.actor_id" class="form-input"><option value="">Todos</option><option v-for="actor in options.actors" :key="actor.id" :value="actor.id">{{ actor.name }}</option></select></div>
            <div><label class="form-label">Desde</label><input v-model="form.date_from" class="form-input" type="date"></div>
            <div><label class="form-label">Hasta</label><input v-model="form.date_to" class="form-input" type="date" :min="form.date_from || undefined"></div>
            <div><label class="form-label">Dirección IP</label><input v-model="form.ip_address" class="form-input" maxlength="45" placeholder="Ej. 192.168."></div>
            <div class="flex gap-3 sm:col-span-2 lg:col-span-5"><button type="submit" class="btn-primary">Aplicar filtros</button><button type="button" class="btn-secondary" @click="clearFilters">Limpiar</button></div>
        </form>

        <div class="card mt-7 overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-600">{{ entries.total }} movimiento{{ entries.total === 1 ? '' : 's' }} encontrado{{ entries.total === 1 ? '' : 's' }}</div>
            <div v-if="!entries.data.length" class="p-12 text-center text-slate-600">No existen movimientos para los filtros seleccionados.</div>
            <div v-else class="divide-y divide-slate-200">
                <article v-for="entry in entries.data" :key="entry.id" class="p-5 sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-semibold">{{ entry.action }}</p><p class="mt-1 text-sm text-slate-500">{{ entry.actor?.name ?? 'Sistema' }} · {{ entry.actor?.email ?? 'Proceso automático' }}</p><p class="mt-1 text-xs text-slate-400">{{ subject(entry) }} · IP {{ entry.ip_address ?? 'no disponible' }}</p></div><time class="text-sm text-slate-500">{{ formatDate(entry.created_at) }}</time></div>
                    <p v-if="entry.reason" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm"><strong>Motivo:</strong> {{ entry.reason }}</p>
                    <details v-if="entry.old_values || entry.new_values" class="mt-4 text-sm"><summary class="cursor-pointer font-medium text-league-700">Ver valores registrados</summary><div class="mt-3 grid gap-3 md:grid-cols-2"><div><p class="mb-1 text-xs font-semibold uppercase text-slate-500">Antes</p><pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">{{ JSON.stringify(entry.old_values, null, 2) }}</pre></div><div><p class="mb-1 text-xs font-semibold uppercase text-slate-500">Después</p><pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">{{ JSON.stringify(entry.new_values, null, 2) }}</pre></div></div></details>
                </article>
            </div>
        </div>
        <nav v-if="entries.total > entries.data.length" class="mt-6 flex flex-wrap gap-1"><Link v-for="link in entries.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg px-3 py-2 text-sm" :class="[link.active ? 'bg-league-700 text-white' : 'bg-white text-slate-600', { 'pointer-events-none opacity-40': !link.url }]" v-html="link.label" /></nav>
    </AppLayout>
</template>
