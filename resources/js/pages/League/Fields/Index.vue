<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Division = { id: number; name: string };
type Availability = { id: number; weekday: number; starts_at: string; ends_at: string; valid_from: string | null; valid_until: string | null };
type Block = { id: number; type: string; starts_at: string; ends_at: string; reason: string };
type PlayingField = { id: number; name: string; surface: string; has_lighting: boolean; capacity: number | null; notes: string | null; status: string; divisions: Division[]; availabilities: Availability[]; blocks: Block[] };
type Venue = { id: number; name: string; address: string; latitude: string | null; longitude: string | null; contact_name: string | null; phone: string | null; notes: string | null; status: string; fields: PlayingField[] };

const props = defineProps<{ venues: Venue[]; divisions: Division[]; scheduleBufferMinutes: number | null }>();
const venueOpen = ref(false);
const editingVenue = ref<Venue | null>(null);
const fieldVenue = ref<Venue | null>(null);
const editingField = ref<PlayingField | null>(null);
const availabilityField = ref<PlayingField | null>(null);
const blockField = ref<PlayingField | null>(null);

const venueForm = useForm({ name: '', address: '', latitude: '', longitude: '', contact_name: '', phone: '', notes: '', status: 'active', reason: '' });
const fieldForm = useForm({ name: '', surface: 'synthetic', has_lighting: false, capacity: null as number | null, notes: '', status: 'active', division_ids: [] as number[], reason: '' });
const availabilityForm = useForm({ weekday: 6, starts_at: '08:00', ends_at: '18:00', valid_from: '', valid_until: '', reason: '' });
const blockForm = useForm({ type: 'maintenance', starts_at: '', ends_at: '', reason: '' });

const fieldCount = computed(() => props.venues.reduce((total, venue) => total + venue.fields.length, 0));
const activeFieldCount = computed(() => props.venues.flatMap((venue) => venue.fields).filter((field) => field.status === 'active' && venueActive(field)).length);
const days: Record<number, string> = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };
const surfaces: Record<string, string> = { natural_grass: 'Pasto natural', synthetic: 'Pasto sintético', dirt: 'Tierra', concrete: 'Concreto', other: 'Otro' };
const statuses: Record<string, string> = { active: 'Activo', maintenance: 'Mantenimiento', inactive: 'Inactivo' };
const blockTypes: Record<string, string> = { maintenance: 'Mantenimiento', unavailable: 'No disponible', event: 'Evento externo' };

function venueActive(field: PlayingField): boolean { return props.venues.some((venue) => venue.status === 'active' && venue.fields.some((item) => item.id === field.id)); }
function localDate(value: string): string { return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
function time(value: string): string { return value.slice(0, 5); }

function newVenue() { venueOpen.value = true; editingVenue.value = null; venueForm.reset(); venueForm.clearErrors(); venueForm.status = 'active'; }
function editVenue(venue: Venue) {
    venueOpen.value = true; editingVenue.value = venue; venueForm.clearErrors();
    Object.assign(venueForm, { name: venue.name, address: venue.address, latitude: venue.latitude ?? '', longitude: venue.longitude ?? '', contact_name: venue.contact_name ?? '', phone: venue.phone ?? '', notes: venue.notes ?? '', status: venue.status, reason: '' });
}
function submitVenue() {
    const options = { preserveScroll: true, onSuccess: () => { venueOpen.value = false; editingVenue.value = null; venueForm.reset(); } };
    if (editingVenue.value) venueForm.put(`/liga/instalaciones/${editingVenue.value.id}`, options);
    else venueForm.post('/liga/instalaciones', options);
}
function newField(venue: Venue) { fieldVenue.value = venue; editingField.value = null; fieldForm.reset(); fieldForm.clearErrors(); fieldForm.surface = 'synthetic'; fieldForm.status = 'active'; }
function editField(venue: Venue, field: PlayingField) {
    fieldVenue.value = venue; editingField.value = field; fieldForm.clearErrors();
    Object.assign(fieldForm, { name: field.name, surface: field.surface, has_lighting: field.has_lighting, capacity: field.capacity, notes: field.notes ?? '', status: field.status, division_ids: field.divisions.map((division) => division.id), reason: '' });
}
function submitField() {
    if (!fieldVenue.value) return;
    const options = { preserveScroll: true, onSuccess: () => { fieldVenue.value = null; editingField.value = null; fieldForm.reset(); } };
    if (editingField.value) fieldForm.put(`/liga/canchas/${editingField.value.id}`, options);
    else fieldForm.post(`/liga/instalaciones/${fieldVenue.value.id}/canchas`, options);
}
function newAvailability(field: PlayingField) { availabilityField.value = field; availabilityForm.reset(); availabilityForm.clearErrors(); availabilityForm.weekday = 6; availabilityForm.starts_at = '08:00'; availabilityForm.ends_at = '18:00'; }
function submitAvailability() { if (availabilityField.value) availabilityForm.post(`/liga/canchas/${availabilityField.value.id}/disponibilidades`, { preserveScroll: true, onSuccess: () => { availabilityField.value = null; availabilityForm.reset(); } }); }
function deleteAvailability(item: Availability) { const reason = prompt('Motivo para eliminar esta disponibilidad:'); if (reason?.trim()) router.delete(`/liga/disponibilidades-campo/${item.id}`, { data: { reason }, preserveScroll: true }); }
function newBlock(field: PlayingField) { blockField.value = field; blockForm.reset(); blockForm.clearErrors(); blockForm.type = 'maintenance'; }
function submitBlock() { if (blockField.value) blockForm.post(`/liga/canchas/${blockField.value.id}/bloqueos`, { preserveScroll: true, onSuccess: () => { blockField.value = null; blockForm.reset(); } }); }
function deleteBlock(item: Block) { const reason = prompt('Motivo para eliminar este bloqueo:'); if (reason?.trim()) router.delete(`/liga/bloqueos-campo/${item.id}`, { data: { reason }, preserveScroll: true }); }
</script>

<template>
    <Head title="Campos y disponibilidad" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 8</p><h1 class="mt-1 text-3xl font-semibold">Campos y disponibilidad</h1><p class="mt-2 text-slate-600">Administra instalaciones, canchas, horarios recurrentes y periodos de inactividad.</p></div>
                <button class="btn-primary" @click="newVenue">Nueva instalación</button>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-3"><div class="stat-card"><span class="stat-label">Instalaciones</span><span class="stat-value">{{ venues.length }}</span></div><div class="stat-card"><span class="stat-label">Canchas registradas</span><span class="stat-value">{{ fieldCount }}</span></div><div class="stat-card"><span class="stat-label">Canchas operativas</span><span class="stat-value">{{ activeFieldCount }}</span></div></div>
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">Las divisiones seleccionadas son recomendaciones para el calendario, no restricciones. <span v-if="scheduleBufferMinutes">El margen configurado entre partidos es de {{ scheduleBufferMinutes }} minutos.</span><span v-else>No hay margen obligatorio configurado entre partidos.</span></div>

            <div v-if="!venues.length" class="card mt-7 p-12 text-center text-slate-600">No hay instalaciones registradas. Crea una para comenzar.</div>
            <div v-else class="mt-7 space-y-6">
                <article v-for="venue in venues" :key="venue.id" class="card overflow-hidden">
                    <header class="flex flex-col justify-between gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-start"><div><div class="flex flex-wrap items-center gap-3"><h2 class="text-2xl font-semibold">{{ venue.name }}</h2><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="venue.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">{{ statuses[venue.status] }}</span></div><p class="mt-2 text-sm text-slate-600">{{ venue.address }}</p><p v-if="venue.contact_name || venue.phone" class="mt-1 text-xs text-slate-500">Contacto: {{ venue.contact_name || '—' }} · {{ venue.phone || '—' }}</p></div><div class="flex gap-2"><button class="btn-secondary" @click="editVenue(venue)">Editar</button><button class="btn-primary" @click="newField(venue)">Agregar cancha</button></div></header>

                    <div v-if="!venue.fields.length" class="p-8 text-center text-sm text-slate-500">Esta instalación todavía no tiene canchas.</div>
                    <div v-else class="grid gap-5 p-5 lg:grid-cols-2">
                        <section v-for="field in venue.fields" :key="field.id" class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-xl font-semibold">{{ field.name }}</h3><span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold">{{ statuses[field.status] }}</span></div><p class="mt-1 text-sm text-slate-600">{{ surfaces[field.surface] }} · {{ field.has_lighting ? 'Con iluminación' : 'Sin iluminación' }}<span v-if="field.capacity"> · {{ field.capacity }} personas</span></p></div><button class="text-sm font-semibold text-league-700 underline" @click="editField(venue, field)">Editar</button></div>
                            <div class="mt-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Divisiones recomendadas</p><div class="mt-2 flex flex-wrap gap-2"><span v-for="division in field.divisions" :key="division.id" class="rounded-full bg-league-100 px-3 py-1 text-xs font-medium text-league-900">{{ division.name }}</span><span v-if="!field.divisions.length" class="text-xs text-slate-500">Apta para cualquier división</span></div></div>
                            <div class="mt-5 border-t border-slate-200 pt-4"><div class="flex items-center justify-between"><h4 class="font-semibold">Disponibilidad semanal</h4><button class="text-sm font-semibold text-league-700" @click="newAvailability(field)">+ Horario</button></div><div v-if="!field.availabilities.length" class="mt-2 text-xs text-amber-700">Sin horarios: no podrá programarse.</div><div v-else class="mt-2 space-y-2"><div v-for="item in field.availabilities" :key="item.id" class="flex justify-between gap-3 rounded-lg bg-white p-3 text-sm"><div><span class="font-medium">{{ days[item.weekday] }}</span> · {{ time(item.starts_at) }}–{{ time(item.ends_at) }}<p v-if="item.valid_from || item.valid_until" class="text-xs text-slate-500">Vigencia: {{ item.valid_from ?? 'sin inicio' }} a {{ item.valid_until ?? 'sin fin' }}</p></div><button class="text-red-700" @click="deleteAvailability(item)">Quitar</button></div></div></div>
                            <div class="mt-5 border-t border-slate-200 pt-4"><div class="flex items-center justify-between"><h4 class="font-semibold">Bloqueos próximos</h4><button class="text-sm font-semibold text-league-700" @click="newBlock(field)">+ Bloqueo</button></div><div v-if="!field.blocks.length" class="mt-2 text-xs text-slate-500">Sin mantenimiento o inactividad programada.</div><div v-else class="mt-2 space-y-2"><div v-for="item in field.blocks" :key="item.id" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold text-amber-900">{{ blockTypes[item.type] }}</span><button class="text-red-700" @click="deleteBlock(item)">Quitar</button></div><p class="mt-1 text-xs text-amber-900">{{ localDate(item.starts_at) }} — {{ localDate(item.ends_at) }}</p><p class="mt-1 text-xs text-slate-600">{{ item.reason }}</p></div></div></div>
                        </section>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="venueOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="venueOpen = false; editingVenue = null; venueForm.reset()"><form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitVenue"><h2 class="text-xl font-semibold">{{ editingVenue ? 'Editar instalación' : 'Nueva instalación' }}</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="venueForm.name" class="form-input" required><FormError :message="venueForm.errors.name" /></div><div><label class="form-label">Estado</label><select v-model="venueForm.status" class="form-input"><option value="active">Activa</option><option value="inactive">Inactiva</option></select></div><div class="sm:col-span-2"><label class="form-label">Dirección</label><input v-model="venueForm.address" class="form-input" required maxlength="500"><FormError :message="venueForm.errors.address" /></div><div><label class="form-label">Latitud (opcional)</label><input v-model="venueForm.latitude" class="form-input" type="number" step="0.0000001"></div><div><label class="form-label">Longitud (opcional)</label><input v-model="venueForm.longitude" class="form-input" type="number" step="0.0000001"></div><div><label class="form-label">Contacto</label><input v-model="venueForm.contact_name" class="form-input"></div><div><label class="form-label">Teléfono</label><input v-model="venueForm.phone" class="form-input"></div><div class="sm:col-span-2"><label class="form-label">Notas</label><textarea v-model="venueForm.notes" class="form-input"></textarea></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="venueForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="venueOpen = false; editingVenue = null; venueForm.reset()">Cancelar</button><button class="btn-primary" :disabled="venueForm.processing">Guardar</button></div></form></div>

        <div v-if="fieldVenue" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="fieldVenue = null"><form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitField"><h2 class="text-xl font-semibold">{{ editingField ? 'Editar cancha' : 'Agregar cancha' }}</h2><p class="mt-1 text-sm text-slate-500">{{ fieldVenue.name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Nombre</label><input v-model="fieldForm.name" class="form-input" required><FormError :message="fieldForm.errors.name" /></div><div><label class="form-label">Estado</label><select v-model="fieldForm.status" class="form-input"><option value="active">Activa</option><option value="maintenance">Mantenimiento</option><option value="inactive">Inactiva</option></select></div><div><label class="form-label">Superficie</label><select v-model="fieldForm.surface" class="form-input"><option v-for="(label, value) in surfaces" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Capacidad (opcional)</label><input v-model="fieldForm.capacity" class="form-input" type="number" min="0"></div><label class="flex items-center gap-3 rounded-xl bg-slate-50 p-4"><input v-model="fieldForm.has_lighting" type="checkbox"><span class="text-sm font-medium">Cuenta con iluminación</span></label><div class="sm:col-span-2"><p class="form-label">Divisiones recomendadas</p><div class="grid gap-2 sm:grid-cols-2"><label v-for="division in divisions" :key="division.id" class="flex items-center gap-2"><input v-model="fieldForm.division_ids" type="checkbox" :value="division.id"><span class="text-sm">{{ division.name }}</span></label></div></div><div class="sm:col-span-2"><label class="form-label">Notas</label><textarea v-model="fieldForm.notes" class="form-input"></textarea></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="fieldForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="fieldVenue = null">Cancelar</button><button class="btn-primary" :disabled="fieldForm.processing">Guardar</button></div></form></div>

        <div v-if="availabilityField" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="availabilityField = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitAvailability"><h2 class="text-xl font-semibold">Agregar disponibilidad</h2><p class="mt-1 text-sm text-slate-500">{{ availabilityField.name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Día</label><select v-model="availabilityForm.weekday" class="form-input"><option v-for="(label, value) in days" :key="value" :value="Number(value)">{{ label }}</option></select></div><div></div><div><label class="form-label">Desde</label><input v-model="availabilityForm.starts_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.starts_at" /></div><div><label class="form-label">Hasta</label><input v-model="availabilityForm.ends_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.ends_at" /></div><div><label class="form-label">Vigente desde (opcional)</label><input v-model="availabilityForm.valid_from" type="date" class="form-input"></div><div><label class="form-label">Vigente hasta (opcional)</label><input v-model="availabilityForm.valid_until" type="date" class="form-input"><FormError :message="availabilityForm.errors.valid_until" /></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="availabilityForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="availabilityField = null">Cancelar</button><button class="btn-primary" :disabled="availabilityForm.processing">Agregar</button></div></form></div>

        <div v-if="blockField" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="blockField = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitBlock"><h2 class="text-xl font-semibold">Bloquear cancha</h2><p class="mt-1 text-sm text-slate-500">{{ blockField.name }}</p><div class="mt-5 space-y-4"><div><label class="form-label">Tipo</label><select v-model="blockForm.type" class="form-input"><option value="maintenance">Mantenimiento</option><option value="unavailable">No disponible</option><option value="event">Evento externo</option></select></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Desde</label><input v-model="blockForm.starts_at" type="datetime-local" class="form-input" required><FormError :message="blockForm.errors.starts_at" /></div><div><label class="form-label">Hasta</label><input v-model="blockForm.ends_at" type="datetime-local" class="form-input" required><FormError :message="blockForm.errors.ends_at" /></div></div><div><label class="form-label">Motivo o descripción</label><textarea v-model="blockForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="blockField = null">Cancelar</button><button class="btn-primary" :disabled="blockForm.processing">Registrar bloqueo</button></div></form></div>
    </AppLayout>
</template>
