<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Backup = { id: number; trigger: string; status: string; filename: string | null; checksum: string | null; size_bytes: number | null; started_at: string; completed_at: string | null; failure_message: string | null; requester: { name: string; email: string } | null };
type PaginationLink = { url: string | null; label: string; active: boolean };
defineProps<{ backups: { data: Backup[]; links: PaginationLink[]; total: number }; schedule: { day: string; time: string; retention: number } }>();
const form = useForm({});
const createBackup = () => { if (window.confirm('Se creará un respaldo completo. El proceso puede tardar algunos minutos.')) form.post('/administracion/respaldos'); };
const dateTime = (value: string | null) => value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const bytes = (value: number | null) => { if (!value) return '—'; const units = ['B', 'KB', 'MB', 'GB']; let size = value; let unit = 0; while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++; } return `${size.toFixed(unit ? 1 : 0)} ${units[unit]}`; };
const statusLabel = (status: string) => ({ queued: 'En espera', running: 'En proceso', completed: 'Completado', failed: 'Fallido' }[status] ?? status);
const triggerLabel = (trigger: string) => ({ scheduled: 'Semanal', manual: 'Manual', pre_restore: 'Previo a restauración' }[trigger] ?? trigger);
</script>

<template>
    <Head title="Respaldos" />
    <AppLayout>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-league-600">Módulo 19</p><h1 class="mt-1 text-3xl font-semibold text-slate-900">Respaldos del sistema</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Protege la base de datos y los archivos cargados. Esta sección es exclusiva del administrador del sistema.</p></div>
            <button type="button" class="btn-primary" :disabled="form.processing" @click="createBackup">{{ form.processing ? 'Creando respaldo…' : 'Crear respaldo ahora' }}</button>
        </header>

        <section class="mt-7 grid gap-4 sm:grid-cols-3">
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Frecuencia</p><p class="mt-2 text-xl font-semibold">Semanal</p><p class="mt-1 text-sm text-slate-500">{{ schedule.day }} · {{ schedule.time }}</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Retención</p><p class="mt-2 text-xl font-semibold">{{ schedule.retention }} respaldos</p><p class="mt-1 text-sm text-slate-500">Los más recientes se conservan automáticamente.</p></article>
            <article class="card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Contenido</p><p class="mt-2 text-xl font-semibold">Completo</p><p class="mt-1 text-sm text-slate-500">PostgreSQL, documentos, fotos y logotipos.</p></article>
        </section>

        <section class="card mt-7 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold">Historial de respaldos</h2></div>
            <div v-if="!backups.data.length" class="p-12 text-center text-slate-600">Todavía no se ha creado ningún respaldo.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Origen</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Tamaño</th><th class="px-5 py-3">Solicitó</th><th class="px-5 py-3 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="backup in backups.data" :key="backup.id">
                            <td class="whitespace-nowrap px-5 py-4"><strong class="block">{{ dateTime(backup.started_at) }}</strong><span class="text-xs text-slate-500">{{ backup.filename ?? `Respaldo #${backup.id}` }}</span></td>
                            <td class="px-5 py-4">{{ triggerLabel(backup.trigger) }}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="backup.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : backup.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'">{{ statusLabel(backup.status) }}</span><p v-if="backup.failure_message" class="mt-2 max-w-sm text-xs text-red-700">{{ backup.failure_message }}</p></td>
                            <td class="px-5 py-4">{{ bytes(backup.size_bytes) }}</td>
                            <td class="px-5 py-4">{{ backup.requester?.name ?? 'Sistema' }}</td>
                            <td class="px-5 py-4 text-right"><a v-if="backup.status === 'completed'" :href="`/administracion/respaldos/${backup.id}/descargar`" class="font-semibold text-league-700 hover:underline">Descargar</a><span v-else class="text-slate-400">No disponible</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <nav v-if="backups.total > backups.data.length" class="mt-6 flex flex-wrap gap-1"><Link v-for="link in backups.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg px-3 py-2 text-sm" :class="[link.active ? 'bg-league-700 text-white' : 'bg-white text-slate-600', { 'pointer-events-none opacity-40': !link.url }]" v-html="link.label" /></nav>

        <aside class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900"><strong>Restauración protegida:</strong> no existe un botón web para sobrescribir datos. La restauración requiere acceso al servidor, verificación SHA-256, un respaldo preventivo y el comando de consola con <code>--force</code>.</aside>
    </AppLayout>
</template>
