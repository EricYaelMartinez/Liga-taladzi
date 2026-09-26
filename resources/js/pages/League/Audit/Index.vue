<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Entry = { id: number; action: string; old_values: Record<string, unknown> | null; new_values: Record<string, unknown> | null; reason: string | null; ip_address: string | null; created_at: string; actor: { name: string; email: string } | null };
type PaginationLink = { url: string | null; label: string; active: boolean };
defineProps<{ entries: { data: Entry[]; links: PaginationLink[]; total: number } }>();
const formatDate = (value: string) => new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
</script>

<template>
    <Head title="Bitácora" />
    <AppLayout>
        <section><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Auditoría</p><h1 class="mt-1 text-3xl font-semibold">Bitácora de cambios</h1><p class="mt-2 text-slate-600">Autor, fecha, motivo y valores modificados dentro de esta liga.</p></section>
        <div class="card mt-7 overflow-hidden">
            <div v-if="!entries.data.length" class="p-12 text-center text-slate-600">Todavía no existen movimientos.</div>
            <div v-else class="divide-y divide-slate-200">
                <article v-for="entry in entries.data" :key="entry.id" class="p-5 sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-semibold">{{ entry.action }}</p><p class="mt-1 text-sm text-slate-500">{{ entry.actor?.name ?? 'Sistema' }} · {{ entry.actor?.email ?? 'Sin usuario' }}</p></div><time class="text-sm text-slate-500">{{ formatDate(entry.created_at) }}</time></div>
                    <p v-if="entry.reason" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm"><strong>Motivo:</strong> {{ entry.reason }}</p>
                    <details v-if="entry.old_values || entry.new_values" class="mt-4 text-sm"><summary class="cursor-pointer font-medium text-league-700">Ver valores registrados</summary><div class="mt-3 grid gap-3 md:grid-cols-2"><pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">{{ JSON.stringify(entry.old_values, null, 2) }}</pre><pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100">{{ JSON.stringify(entry.new_values, null, 2) }}</pre></div></details>
                </article>
            </div>
        </div>
        <nav v-if="entries.total > entries.data.length" class="mt-6 flex flex-wrap gap-1"><Link v-for="link in entries.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg px-3 py-2 text-sm" :class="[link.active ? 'bg-league-700 text-white' : 'bg-white text-slate-600', { 'pointer-events-none opacity-40': !link.url }]" v-html="link.label" /></nav>
    </AppLayout>
</template>
