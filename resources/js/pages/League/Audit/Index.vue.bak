<script setup lang="ts">
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

type Values = Record<string, unknown> | null;
type Entry = {
    id: number;
    action: string;
    auditable_type: string;
    auditable_id: number | null;
    old_values: Values;
    new_values: Values;
    reason: string | null;
    ip_address: string | null;
    created_at: string;
    actor: { id: number; name: string; email: string } | null;
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type Filters = { search?: string; action?: string; actor_id?: number | string; date_from?: string; date_to?: string; ip_address?: string };
type DiffRow = { key: string; before: string; after: string; changed: boolean };

const props = defineProps<{
    entries: { data: Entry[]; links: PaginationLink[]; total: number };
    filters: Filters;
    options: { actions: string[]; actors: { id: number; name: string; email: string }[] };
}>();

const form = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    actor_id: props.filters.actor_id ? String(props.filters.actor_id) : '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    ip_address: props.filters.ip_address ?? '',
});
const openedEntry = ref<number | null>(props.entries.data[0]?.id ?? null);
const openedDiff = ref<number | null>(props.entries.data[0]?.id ?? null);
const query = computed(() => Object.fromEntries(Object.entries(form).filter(([, value]) => String(value).trim() !== '')));
const exportUrl = computed(() => `/liga/bitacora/exportar?${new URLSearchParams(query.value).toString()}`);
const applyFilters = () => router.get('/liga/bitacora', query.value, { preserveState: true, preserveScroll: true });
const clearFilters = () => {
    Object.assign(form, { search: '', action: '', actor_id: '', date_from: '', date_to: '', ip_address: '' });
    router.get('/liga/bitacora', {}, { preserveScroll: true });
};

const actionTitles: Record<string, string> = {
    'lineup.saved': 'Alineación guardada',
    'lineup.submitted': 'Alineación enviada',
    'lineup.confirmed': 'Alineación confirmada',
    'player.credentials.downloaded': 'Descarga de credenciales',
    'player.credentials.previewed': 'Vista previa de credenciales',
    'match_report.saved': 'Cédula arbitral guardada',
    'match_report.confirmed': 'Cédula arbitral confirmada',
    'match_report.approved': 'Cédula arbitral aprobada',
};
const fieldNames: Record<string, string> = {
    status: 'Estado', captain_id: 'Capitán', formation: 'Formación',
    player_id: 'Jugador', team_id: 'Equipo', team_participation_id: 'Participación',
    notes: 'Observaciones', starts_at: 'Inicio', ends_at: 'Fin',
    created_at: 'Creado', updated_at: 'Actualizado', approved_at: 'Aprobación',
    score_home: 'Marcador local', score_away: 'Marcador visitante',
};
const statusNames: Record<string, string> = {
    draft: 'Borrador', submitted: 'Enviada', approved: 'Aprobada',
    rejected: 'Rechazada', active: 'Activo', inactive: 'Inactivo',
    suspended: 'Suspendido', pending: 'Pendiente', completed: 'Completada',
};
const formatDate = (value: string): string => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};
const titleFor = (action: string): string => actionTitles[action]
    ?? action.split('.').slice(-2).join(' ').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const categoryFor = (action: string): string => {
    const key = action.toLowerCase();
    if (key.includes('credential')) return 'Credenciales';
    if (key.includes('lineup')) return 'Alineaciones';
    if (key.includes('report')) return 'Cédulas arbitrales';
    if (key.includes('player')) return 'Jugadores';
    if (key.includes('match') || key.includes('schedule')) return 'Partidos';
    if (key.includes('payment') || key.includes('finance')) return 'Finanzas';
    if (key.includes('team')) return 'Equipos';
    if (key.includes('sanction') || key.includes('appeal')) return 'Disciplina';
    return 'Actividad';
};
const iconFor = (action: string): string => {
    const cat = categoryFor(action);
    if (cat === 'Alineaciones') return 'users';
    if (cat === 'Credenciales') return action.includes('download') ? 'download' : 'id';
    if (cat === 'Partidos') return 'football';
    if (cat === 'Finanzas') return 'wallet';
    if (cat === 'Disciplina') return 'gavel';
    if (cat === 'Equipos') return 'team';
    return 'file';
};
const classFor = (action: string): string => {
    const cat = categoryFor(action);
    if (cat === 'Credenciales') return 'audit-pill--purple';
    if (cat === 'Disciplina') return 'audit-pill--amber';
    if (cat === 'Finanzas') return 'audit-pill--green';
    return 'audit-pill--blue';
};
const operationFor = (action: string): string => {
    if (/download|export/i.test(action)) return 'Descarga';
    if (/preview|view|read/i.test(action)) return 'Consulta';
    if (/delete|removed|revoked/i.test(action)) return 'Eliminación';
    if (/created|assigned|registered/i.test(action)) return 'Creación';
    if (/saved|updated|submitted|approved|changed|confirmed/i.test(action)) return 'Actualización';
    return 'Operación';
};
const subject = (entry: Entry): string => `${entry.auditable_type?.split('\\').pop() || 'Registro'}${entry.auditable_id !== null ? ` #${entry.auditable_id}` : ''}`;
const isExpanded = (id: number): boolean => openedEntry.value === id;
const toggleEntry = (id: number) => { openedEntry.value = openedEntry.value === id ? null : id; };
const toggleDiff = (id: number) => { openedDiff.value = openedDiff.value === id ? null : id; };
const humanField = (field: string): string => field.split('.').map((part) => fieldNames[part] ?? part.replaceAll('_', ' ')).join(' · ');
const formatValue = (value: unknown): string => {
    if (value === undefined || value === null) return '—';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (typeof value === 'string') return statusNames[value] ?? value;
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};
const diffFor = (entry: Entry): DiffRow[] => {
    const oldValues = entry.old_values ?? {};
    const newValues = entry.new_values ?? {};
    const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];
    return keys.map((key) => {
        const before = formatValue(oldValues[key]);
        const after = formatValue(newValues[key]);
        return { key, before, after, changed: JSON.stringify(oldValues[key]) !== JSON.stringify(newValues[key]) };
    });
};
</script>

<template>
    <Head title="Bitácora de cambios" />
    <AppLayout>
        <section class="mx-auto max-w-[1480px] pb-8">
            <header class="mb-6">
                <p class="text-xs font-extrabold uppercase tracking-[.19em] text-league-700">Módulo 19 · Auditoría</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">Bitácora de cambios</h1>
                <p class="mt-2 text-sm text-slate-600">Consulta quién realizó cada operación, cuándo ocurrió y qué información fue modificada dentro de esta liga.</p>
            </header>

            <form class="audit-filter-card" @submit.prevent="applyFilters">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <p class="shrink-0 text-base font-bold text-slate-900">{{ entries.total }} movimiento{{ entries.total === 1 ? '' : 's' }} encontrado{{ entries.total === 1 ? '' : 's' }}</p>
                    <div class="relative w-full max-w-3xl">
                        <span class="audit-search-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg></span>
                        <input v-model="form.search" class="form-input pl-11" type="search" placeholder="Buscar por usuario, acción, entidad o IP..." aria-label="Buscar movimientos" />
                    </div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[1fr_1fr_1.35fr_1fr_auto] xl:items-end">
                    <div><label class="form-label">Acción</label><select v-model="form.action" class="form-input"><option value="">Todas</option><option v-for="action in options.actions" :key="action" :value="action">{{ titleFor(action) }} ({{ action }})</option></select></div>
                    <div><label class="form-label">Usuario</label><select v-model="form.actor_id" class="form-input"><option value="">Todos</option><option v-for="actor in options.actors" :key="actor.id" :value="actor.id">{{ actor.name }}</option></select></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="form-label">Desde</label><input v-model="form.date_from" class="form-input" type="date" /></div>
                        <div><label class="form-label">Hasta</label><input v-model="form.date_to" class="form-input" type="date" :min="form.date_from || undefined" /></div>
                    </div>
                    <div><label class="form-label">Dirección IP</label><input v-model="form.ip_address" class="form-input" maxlength="45" placeholder="Ej. 192.168.0.1" /></div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-1 xl:flex-nowrap">
                        <a :href="exportUrl" class="audit-secondary-button"><AppIcon name="download" :size="17" /> Exportar CSV</a>
                        <button class="audit-secondary-button" type="button" @click="clearFilters">Limpiar</button>
                        <button class="audit-primary-button" type="submit"><AppIcon name="sliders" :size="17" /> Aplicar filtros</button>
                    </div>
                </div>
            </form>

            <div v-if="!entries.data.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-600">No existen movimientos para los filtros seleccionados.</div>
            <div v-else class="mt-6 space-y-3">
                <article v-for="entry in entries.data" :key="entry.id" class="audit-entry" :class="{ 'audit-entry--open': isExpanded(entry.id) }">
                    <button type="button" class="audit-entry__header" :aria-expanded="isExpanded(entry.id)" @click="toggleEntry(entry.id)">
                        <span class="audit-entry__illustration"><AppIcon :name="iconFor(entry.action)" :size="25" /></span>
                        <span class="min-w-0 flex-1 text-left">
                            <span class="flex flex-wrap items-center gap-2.5">
                                <span class="text-base font-bold text-slate-900">{{ titleFor(entry.action) }}</span>
                                <span class="audit-pill" :class="classFor(entry.action)">{{ categoryFor(entry.action) }}</span>
                                <span class="audit-pill audit-pill--green">{{ operationFor(entry.action) }}</span>
                            </span>
                            <span class="mt-1 block text-xs text-slate-500">{{ entry.action }}</span>
                        </span>
                        <span v-if="!isExpanded(entry.id)" class="audit-collapsed-meta hidden xl:inline">{{ entry.actor?.name ?? 'Sistema' }} · {{ subject(entry) }} · {{ entry.ip_address ?? 'IP no disponible' }} · {{ formatDate(entry.created_at) }}</span>
                        <svg class="h-5 w-5 shrink-0 text-slate-500 transition-transform" :class="{ 'rotate-180': isExpanded(entry.id) }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div v-if="isExpanded(entry.id)" class="audit-entry__body">
                        <div class="audit-meta-grid">
                            <div class="audit-meta-cell"><AppIcon name="user" :size="20" /><div class="min-w-0"><p class="audit-meta-label">Usuario</p><p class="font-semibold text-slate-900">{{ entry.actor?.name ?? 'Sistema' }}</p><p class="break-all text-xs text-slate-500">{{ entry.actor?.email ?? 'Proceso automático' }}</p></div></div>
                            <div class="audit-meta-cell"><AppIcon name="database" :size="20" /><div><p class="audit-meta-label">Entidad / referencia</p><p class="break-all font-semibold text-slate-900">{{ subject(entry) }}</p></div></div>
                            <div class="audit-meta-cell"><AppIcon name="building" :size="20" /><div><p class="audit-meta-label">Dirección IP</p><p class="font-semibold text-slate-900">{{ entry.ip_address ?? 'No disponible' }}</p></div></div>
                            <div class="audit-meta-cell"><AppIcon name="calendar" :size="20" /><div><p class="audit-meta-label">Fecha y hora</p><p class="font-semibold text-slate-900">{{ formatDate(entry.created_at) }}</p></div></div>
                        </div>
                        <div v-if="entry.reason" class="audit-reason"><AppIcon name="file" :size="18" /><span><strong>Motivo:</strong> {{ entry.reason }}</span></div>
                        <div v-if="entry.old_values || entry.new_values" class="audit-diff-shell">
                            <button type="button" class="flex w-full items-center gap-3 text-left text-sm font-bold text-league-700" :aria-expanded="openedDiff === entry.id" @click="toggleDiff(entry.id)">
                                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': openedDiff === entry.id }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                Comparar cambios
                                <span class="rounded-lg bg-blue-100 px-2.5 py-1 text-xs text-blue-700">{{ diffFor(entry).filter((row) => row.changed).length }} cambios</span>
                            </button>
                            <div v-if="openedDiff === entry.id" class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                                <table class="w-full min-w-[550px] border-collapse text-left text-sm">
                                    <thead class="bg-slate-100 text-xs font-semibold text-slate-600"><tr><th class="px-4 py-3">Campo</th><th class="px-4 py-3">Valor anterior</th><th class="px-4 py-3 text-center"></th><th class="px-4 py-3">Valor nuevo</th></tr></thead>
                                    <tbody>
                                        <tr v-for="row in diffFor(entry)" :key="row.key" class="border-t border-slate-200"><td class="px-4 py-2"><span class="mb-1 block text-xs text-slate-500">{{ humanField(row.key) }}</span><code class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ row.key }}</code></td><td class="w-[32%] px-4 py-2"><div class="audit-diff-value" :class="row.changed ? 'audit-diff-before' : 'audit-diff-unchanged'">{{ row.before }}</div></td><td class="text-center text-slate-400">→</td><td class="w-[32%] px-4 py-2"><div class="audit-diff-value" :class="row.changed ? 'audit-diff-after' : 'audit-diff-unchanged'">{{ row.after }}</div></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            <nav v-if="entries.total > entries.data.length" class="mt-6 flex flex-wrap gap-2" aria-label="Paginación de movimientos">
                <Link v-for="(link, index) in entries.links" :key="index" :href="link.url ?? '#'" class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm" :class="[link.active ? 'border-league-700 bg-league-700 text-white' : 'bg-white text-slate-600', { 'pointer-events-none opacity-40': !link.url }]" v-html="link.label" />
            </nav>
        </section>
    </AppLayout>
</template>

<style scoped>
.audit-filter-card { border: 1px solid #dbe5f0; background: rgba(255,255,255,.96); padding: 20px 22px; border-radius: 20px; box-shadow: 0 10px 26px rgba(16,38,74,.07); }
.audit-search-icon { position:absolute; width:18px; height:18px; left:15px; top:50%; transform:translateY(-50%); color:#71819b; pointer-events:none; }
.audit-search-icon svg { width:100%; height:100%; }
.audit-secondary-button,.audit-primary-button { display:inline-flex; min-height:42px; white-space:nowrap; align-items:center; justify-content:center; gap:8px; border-radius:11px; padding:9px 14px; font-size:13px; font-weight:700; transition:background .2s ease,transform .2s ease; }
.audit-secondary-button { border:1px solid #d3dfed; color:#243a61; background:#fff; box-shadow:0 2px 4px rgba(15,35,65,.04); }
.audit-secondary-button:hover { background:#f3f7fd; }
.audit-primary-button { border:1px solid #2269d8; background:#1766dc; color:#fff; }
.audit-primary-button:hover { background:#1055be; }
.audit-entry { overflow:hidden; border:1px solid #dce5f0; background:rgba(255,255,255,.97); border-radius:16px; box-shadow:0 5px 16px rgba(19,43,82,.055); }
.audit-entry--open { border-color:#79a9fa; box-shadow:0 5px 19px rgba(25,92,184,.09); }
.audit-entry__header { display:flex; width:100%; align-items:center; gap:15px; padding:14px 18px; text-align:left; }
.audit-entry__illustration { width:50px; height:50px; flex:0 0 50px; display:grid; place-items:center; background:#eaf2ff; color:#0c5fd7; border-radius:13px; }
.audit-collapsed-meta { max-width:50%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; color:#64748b; }
.audit-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:11px; font-weight:700; white-space:nowrap; }
.audit-pill--blue { background:#e9f2ff; color:#145bb8; }
.audit-pill--purple { background:#f0eaff; color:#5b31b7; }
.audit-pill--amber { background:#fff3da; color:#915909; }
.audit-pill--green { background:#e5f7e9; color:#146834; }
.audit-entry__body { border-top:1px solid #e6edf5; padding:16px 18px 18px; }
.audit-meta-grid { display:grid; gap:14px; grid-template-columns:repeat(4,minmax(0,1fr)); }
.audit-meta-cell { display:flex; min-width:0; align-items:flex-start; gap:12px; padding:2px 12px; color:#516782; font-size:13px; }
.audit-meta-cell + .audit-meta-cell { border-left:1px solid #dce5ef; }
.audit-meta-label { margin-bottom:2px; font-size:12px; color:#71809a; }
.audit-reason { margin-top:16px; display:flex; align-items:center; gap:12px; padding:11px 14px; background:#f1f5fa; color:#253751; font-size:13px; border-radius:10px; }
.audit-diff-shell { margin-top:12px; border:1px solid #e1e9f3; background:#f7faff; padding:13px 15px; border-radius:12px; }
.audit-diff-value { overflow-wrap:anywhere; padding:6px 10px; border-radius:7px; font-size:12px; font-weight:600; }
.audit-diff-before { background:#fde9e9; color:#93221c; }
.audit-diff-after { background:#e2f6e6; color:#1b6336; }
.audit-diff-unchanged { background:#f1f5f9; color:#475569; }
@media(max-width:960px) { .audit-meta-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .audit-meta-cell:nth-child(3) { border-left:none; } }
@media(max-width:600px) { .audit-meta-grid { grid-template-columns:1fr; } .audit-meta-cell { border-left:none!important; padding:6px 0; } .audit-entry__header { gap:10px; padding:12px; } .audit-entry__body { padding:12px; } }
</style>
