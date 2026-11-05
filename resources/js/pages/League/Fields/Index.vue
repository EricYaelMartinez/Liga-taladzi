<script setup lang="ts">
import AppIcon from '../../../components/AppIcon.vue';
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
const expandedVenueIds = ref<number[]>(props.venues.map((venue) => venue.id));

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
function venueScheduledCount(venue: Venue): number { return venue.fields.filter((field) => field.availabilities.length > 0).length; }
function isVenueExpanded(venueId: number): boolean { return expandedVenueIds.value.includes(venueId); }
function toggleVenue(venueId: number) {
    expandedVenueIds.value = isVenueExpanded(venueId)
        ? expandedVenueIds.value.filter((id) => id !== venueId)
        : [...expandedVenueIds.value, venueId];
}
function venueStatusClass(status: string): string {
    return status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700';
}
function fieldStatusClass(status: string): string {
    return ({ active: 'bg-emerald-100 text-emerald-800', maintenance: 'bg-amber-100 text-amber-800', inactive: 'bg-slate-200 text-slate-700' } as Record<string, string>)[status] ?? 'bg-slate-200 text-slate-700';
}
function surfaceIcon(surface: string): string {
    return ({ natural_grass: 'field', synthetic: 'field', dirt: 'field', concrete: 'field', other: 'field' } as Record<string, string>)[surface] ?? 'field';
}

function newVenue() { venueOpen.value = true; editingVenue.value = null; venueForm.reset(); venueForm.clearErrors(); venueForm.status = 'active'; }
function closeVenueModal() { venueOpen.value = false; editingVenue.value = null; venueForm.reset(); venueForm.clearErrors(); }
function editVenue(venue: Venue) {
    venueOpen.value = true; editingVenue.value = venue; venueForm.clearErrors();
    Object.assign(venueForm, { name: venue.name, address: venue.address, latitude: venue.latitude ?? '', longitude: venue.longitude ?? '', contact_name: venue.contact_name ?? '', phone: venue.phone ?? '', notes: venue.notes ?? '', status: venue.status, reason: '' });
}
function submitVenue() {
    const options = { preserveScroll: true, onSuccess: () => closeVenueModal() };
    if (editingVenue.value) venueForm.put(`/liga/instalaciones/${editingVenue.value.id}`, options);
    else venueForm.post('/liga/instalaciones', options);
}
function newField(venue: Venue) { fieldVenue.value = venue; editingField.value = null; fieldForm.reset(); fieldForm.clearErrors(); fieldForm.surface = 'synthetic'; fieldForm.status = 'active'; }
function closeFieldModal() { fieldVenue.value = null; editingField.value = null; fieldForm.reset(); fieldForm.clearErrors(); }
function editField(venue: Venue, field: PlayingField) {
    fieldVenue.value = venue; editingField.value = field; fieldForm.clearErrors();
    Object.assign(fieldForm, { name: field.name, surface: field.surface, has_lighting: field.has_lighting, capacity: field.capacity, notes: field.notes ?? '', status: field.status, division_ids: field.divisions.map((division) => division.id), reason: '' });
}
function submitField() {
    if (!fieldVenue.value) return;
    const options = { preserveScroll: true, onSuccess: () => closeFieldModal() };
    if (editingField.value) fieldForm.put(`/liga/canchas/${editingField.value.id}`, options);
    else fieldForm.post(`/liga/instalaciones/${fieldVenue.value.id}/canchas`, options);
}
function newAvailability(field: PlayingField) { availabilityField.value = field; availabilityForm.reset(); availabilityForm.clearErrors(); availabilityForm.weekday = 6; availabilityForm.starts_at = '08:00'; availabilityForm.ends_at = '18:00'; }
function closeAvailabilityModal() { availabilityField.value = null; availabilityForm.reset(); availabilityForm.clearErrors(); }
function submitAvailability() { if (availabilityField.value) availabilityForm.post(`/liga/canchas/${availabilityField.value.id}/disponibilidades`, { preserveScroll: true, onSuccess: () => closeAvailabilityModal() }); }
function deleteAvailability(item: Availability) { const reason = prompt('Motivo para eliminar esta disponibilidad:'); if (reason?.trim()) router.delete(`/liga/disponibilidades-campo/${item.id}`, { data: { reason }, preserveScroll: true }); }
function newBlock(field: PlayingField) { blockField.value = field; blockForm.reset(); blockForm.clearErrors(); blockForm.type = 'maintenance'; }
function closeBlockModal() { blockField.value = null; blockForm.reset(); blockForm.clearErrors(); }
function submitBlock() { if (blockField.value) blockForm.post(`/liga/canchas/${blockField.value.id}/bloqueos`, { preserveScroll: true, onSuccess: () => closeBlockModal() }); }
function deleteBlock(item: Block) { const reason = prompt('Motivo para eliminar este bloqueo:'); if (reason?.trim()) router.delete(`/liga/bloqueos-campo/${item.id}`, { data: { reason }, preserveScroll: true }); }
</script>

<template>
    <Head title="Campos y disponibilidad" />
    <AppLayout>
        <section class="fields-page">
            <div class="fields-hero">
                <div class="fields-hero__content">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl">
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-gold-400">Módulo 8 · Participantes</p>
                            <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Campos y disponibilidad</h1>
                            <p class="mt-3 max-w-2xl text-sm text-white/80 sm:text-base">Administra instalaciones, canchas, horarios recurrentes y periodos de inactividad.</p>
                        </div>
                        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-league-700 shadow-lg transition hover:bg-slate-50" @click="newVenue">
                            <AppIcon name="building" :size="18" />
                            Nueva instalación
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <div class="premium-stat-card">
                    <div class="premium-stat-card__icon"><AppIcon name="building" variant="illustrated" :size="36" /></div>
                    <div>
                        <p class="premium-stat-card__label">Instalaciones</p>
                        <div class="premium-stat-card__value">{{ venues.length }}</div>
                        <p class="premium-stat-card__caption">Total registradas en la liga</p>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="premium-stat-card__icon"><AppIcon name="field" variant="illustrated" :size="36" /></div>
                    <div>
                        <p class="premium-stat-card__label">Canchas registradas</p>
                        <div class="premium-stat-card__value">{{ fieldCount }}</div>
                        <p class="premium-stat-card__caption">Canchas configuradas</p>
                    </div>
                </div>
                <div class="premium-stat-card premium-stat-card--success">
                    <div class="premium-stat-card__icon"><AppIcon name="calendar" variant="illustrated" :size="36" /></div>
                    <div>
                        <div class="flex items-center gap-3">
                            <div>
                                <p class="premium-stat-card__label">Canchas operativas</p>
                                <div class="premium-stat-card__value">{{ activeFieldCount }}</div>
                                <p class="premium-stat-card__caption">Disponibles para programación</p>
                            </div>
                            <span v-if="fieldCount" class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">{{ Math.round((activeFieldCount / fieldCount) * 100) }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <AppIcon name="badge" :size="18" class="mt-0.5 text-blue-700" />
                <p>Las divisiones seleccionadas son recomendaciones para el calendario, no restricciones. <span v-if="scheduleBufferMinutes">El margen configurado entre partidos es de {{ scheduleBufferMinutes }} minutos.</span><span v-else>No hay margen obligatorio configurado entre partidos.</span></p>
            </div>

            <div v-if="!venues.length" class="mt-6 rounded-[28px] border border-dashed border-slate-300 bg-white/90 px-6 py-14 text-center shadow-sm">
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-league-50 text-league-700"><AppIcon name="building" variant="illustrated" :size="34" /></div>
                <h2 class="mt-5 text-xl font-bold text-slate-900">No hay instalaciones registradas</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">Crea la primera instalación para comenzar a organizar canchas, horarios recurrentes y bloqueos de mantenimiento.</p>
                <button class="btn-primary mt-6" @click="newVenue">Registrar primera instalación</button>
            </div>

            <div v-else class="mt-6 space-y-5">
                <article v-for="venue in venues" :key="venue.id" class="venue-card">
                    <div class="venue-card__main">
                        <div class="venue-card__hero">
                            <div class="venue-card__thumb">
                                <div class="venue-card__thumb-overlay"></div>
                                <AppIcon name="field" :size="30" class="relative z-10 text-white" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ venue.name }}</h2>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="venueStatusClass(venue.status)">{{ statuses[venue.status] }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
                                    <span class="inline-flex items-center gap-1.5"><AppIcon name="field" :size="15" class="text-league-600" />{{ venue.fields.length }} canchas</span>
                                    <span class="inline-flex items-center gap-1.5"><AppIcon name="clock" :size="15" class="text-league-600" />{{ venueScheduledCount(venue) ? 'Con horarios configurados' : 'Sin horarios' }}</span>
                                    <span class="inline-flex items-center gap-1.5"><AppIcon name="mail" :size="15" class="text-league-600" />{{ venue.contact_name || 'Sin contacto asignado' }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ venue.address }}</p>
                            </div>
                        </div>

                        <div class="venue-card__actions">
                            <button class="icon-button" type="button" title="Opciones">
                                <AppIcon name="menu" :size="18" />
                            </button>
                            <button class="btn-secondary" @click="editVenue(venue)">Editar</button>
                            <button class="btn-primary" @click="newField(venue)">Agregar cancha</button>
                            <button class="icon-button" type="button" :title="isVenueExpanded(venue.id) ? 'Contraer' : 'Expandir'" @click="toggleVenue(venue.id)">
                                <span class="text-lg leading-none">{{ isVenueExpanded(venue.id) ? '⌃' : '⌄' }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!isVenueExpanded(venue.id)" class="venue-card__collapsed">{{ venue.fields.length }} cancha(s) registradas · {{ venueScheduledCount(venue) ? 'con horarios configurados' : 'sin horarios configurados' }}</div>

                    <div v-else class="venue-card__body">
                        <div v-if="!venue.fields.length" class="venue-card__empty">
                            <AppIcon name="field" variant="illustrated" :size="32" />
                            <p>Esta instalación todavía no tiene canchas.</p>
                        </div>

                        <div v-else class="grid gap-5 xl:grid-cols-3">
                            <section v-for="field in venue.fields" :key="field.id" class="field-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-xl font-bold text-slate-900">{{ field.name }}</h3>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="fieldStatusClass(field.status)">{{ statuses[field.status] }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                                            <span class="chip"><AppIcon :name="surfaceIcon(field.surface)" :size="14" />{{ surfaces[field.surface] }}</span>
                                            <span class="chip"><AppIcon name="bell" :size="14" />{{ field.has_lighting ? 'Con iluminación' : 'Sin iluminación' }}</span>
                                            <span v-if="field.capacity" class="chip"><AppIcon name="users" :size="14" />{{ field.capacity }} personas</span>
                                        </div>
                                    </div>
                                    <button class="text-sm font-semibold text-league-700 transition hover:text-league-900" @click="editField(venue, field)">Editar</button>
                                </div>

                                <div class="mt-4 rounded-2xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Divisiones recomendadas</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="division in field.divisions" :key="division.id" class="rounded-full bg-league-100 px-3 py-1 text-xs font-semibold text-league-800">{{ division.name }}</span>
                                        <span v-if="!field.divisions.length" class="text-xs text-slate-500">Apta para cualquier división</span>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-bold text-slate-900">Disponibilidad semanal</h4>
                                        <button class="text-sm font-semibold text-league-700" @click="newAvailability(field)">＋ Horario</button>
                                    </div>
                                    <div v-if="!field.availabilities.length" class="mt-3 text-sm text-slate-500">Sin horarios recurrentes configurados.</div>
                                    <div v-else class="mt-3 space-y-2.5">
                                        <div v-for="item in field.availabilities" :key="item.id" class="schedule-row">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-800">{{ days[item.weekday] }}</div>
                                                <div class="text-sm text-slate-600">{{ time(item.starts_at) }} - {{ time(item.ends_at) }}</div>
                                            </div>
                                            <button class="text-sm font-medium text-rose-600" @click="deleteAvailability(item)">Quitar</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-bold text-slate-900">Bloqueos próximos</h4>
                                        <button class="text-sm font-semibold text-league-700" @click="newBlock(field)">＋ Bloqueo</button>
                                    </div>
                                    <div v-if="!field.blocks.length" class="mt-3 text-sm text-slate-500">Sin mantenimiento o inactividad programada.</div>
                                    <div v-else class="mt-3 space-y-2.5">
                                        <div v-for="item in field.blocks" :key="item.id" class="block-row">
                                            <div>
                                                <div class="text-sm font-semibold text-amber-900">{{ blockTypes[item.type] }}</div>
                                                <div class="mt-1 text-xs text-amber-900/90">{{ localDate(item.starts_at) }} — {{ localDate(item.ends_at) }}</div>
                                                <p class="mt-1 text-xs text-slate-600">{{ item.reason }}</p>
                                            </div>
                                            <button class="text-sm font-medium text-rose-600" @click="deleteBlock(item)">Quitar</button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="venueOpen" class="modal-backdrop" @click.self="closeVenueModal()">
            <form class="premium-modal premium-modal--wide" @submit.prevent="submitVenue">
                <div class="premium-modal__header">
                    <div class="premium-modal__title">
                        <span class="premium-modal__icon"><AppIcon name="building" variant="illustrated" :size="34" /></span>
                        <div>
                            <h2>{{ editingVenue ? 'Editar instalación' : 'Nueva instalación' }}</h2>
                            <p>{{ editingVenue ? 'Actualiza la información de la instalación.' : 'Registra una nueva instalación en la liga.' }}</p>
                        </div>
                    </div>
                    <button type="button" class="modal-close" @click="closeVenueModal()">×</button>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="building" :size="18" /> Información general</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="form-label">Nombre <span class="form-required">*</span></label>
                            <input v-model="venueForm.name" class="form-input" placeholder="Ej. Unidad Deportiva Municipal" required>
                            <FormError :message="venueForm.errors.name" />
                        </div>
                        <div>
                            <label class="form-label">Estado <span class="form-required">*</span></label>
                            <select v-model="venueForm.status" class="form-input"><option value="active">Activa</option><option value="inactive">Inactiva</option></select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Dirección <span class="form-required">*</span></label>
                            <input v-model="venueForm.address" class="form-input" placeholder="Ej. Calle, número, colonia, ciudad, estado" required maxlength="500">
                            <FormError :message="venueForm.errors.address" />
                        </div>
                    </div>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="badge" :size="18" /> Ubicación (opcional)</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="form-label">Latitud</label><input v-model="venueForm.latitude" class="form-input" type="number" step="0.0000001" placeholder="Ej. 17.0732"></div>
                        <div><label class="form-label">Longitud</label><input v-model="venueForm.longitude" class="form-input" type="number" step="0.0000001" placeholder="Ej. -96.7266"></div>
                    </div>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="user" :size="18" /> Contacto (opcional)</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="form-label">Nombre de contacto</label><input v-model="venueForm.contact_name" class="form-input" placeholder="Ej. Juan Pérez"></div>
                        <div><label class="form-label">Teléfono</label><input v-model="venueForm.phone" class="form-input" placeholder="Ej. 951 123 4567"></div>
                    </div>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="file" :size="18" /> Notas (opcional)</div>
                    <textarea v-model="venueForm.notes" class="form-input min-h-24" placeholder="Información adicional sobre la instalación..."></textarea>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="badge" :size="18" /> Motivo</div>
                    <textarea v-model="venueForm.reason" class="form-input min-h-20" placeholder="Ej. Nueva sede, ampliación, etc." maxlength="500" required></textarea>
                </div>

                <div class="premium-modal__footer">
                    <button type="button" class="btn-secondary" @click="closeVenueModal()">Cancelar</button>
                    <button class="btn-primary" :disabled="venueForm.processing">{{ editingVenue ? 'Guardar cambios' : 'Guardar instalación' }}</button>
                </div>
            </form>
        </div>

        <div v-if="fieldVenue" class="modal-backdrop" @click.self="closeFieldModal()">
            <form class="premium-modal premium-modal--wide" @submit.prevent="submitField">
                <div class="premium-modal__header">
                    <div class="premium-modal__title">
                        <span class="premium-modal__icon"><AppIcon name="field" variant="illustrated" :size="34" /></span>
                        <div>
                            <h2>{{ editingField ? 'Editar cancha' : 'Agregar cancha' }}</h2>
                            <p>{{ editingField ? 'Actualiza la información de la cancha.' : 'Crea una nueva cancha dentro de esta instalación.' }}</p>
                        </div>
                    </div>
                    <button type="button" class="modal-close" @click="closeFieldModal()">×</button>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="field" :size="18" /> Información general</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="form-label">Nombre de la cancha <span class="form-required">*</span></label>
                            <input v-model="fieldForm.name" class="form-input" placeholder="Ej. Campo 1, Cancha Principal" required>
                            <FormError :message="fieldForm.errors.name" />
                        </div>
                        <div>
                            <label class="form-label">Estado <span class="form-required">*</span></label>
                            <select v-model="fieldForm.status" class="form-input"><option value="active">Activa</option><option value="maintenance">Mantenimiento</option><option value="inactive">Inactiva</option></select>
                        </div>
                        <div>
                            <label class="form-label">Superficie <span class="form-required">*</span></label>
                            <select v-model="fieldForm.surface" class="form-input"><option v-for="(label, value) in surfaces" :key="value" :value="value">{{ label }}</option></select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Capacidad (opcional)</label>
                            <input v-model="fieldForm.capacity" class="form-input" type="number" min="0" placeholder="Ej. 200">
                        </div>
                    </div>
                </div>

                <div class="premium-form-section">
                    <label class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-4">
                        <input v-model="fieldForm.has_lighting" type="checkbox" class="mt-1">
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Cuenta con iluminación</span>
                            <span class="block text-xs text-slate-500">Disponible para partidos nocturnos</span>
                        </span>
                    </label>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="sliders" :size="18" /> Divisiones recomendadas</div>
                    <p class="mb-4 text-sm text-slate-500">Selecciona las divisiones que pueden utilizar esta cancha.</p>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label v-for="division in divisions" :key="division.id" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                            <input v-model="fieldForm.division_ids" type="checkbox" :value="division.id">
                            <span>{{ division.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="file" :size="18" /> Notas (opcional)</div>
                    <textarea v-model="fieldForm.notes" class="form-input min-h-24" placeholder="Información adicional sobre la cancha..."></textarea>
                </div>

                <div class="premium-form-section">
                    <div class="premium-form-section__head"><AppIcon name="badge" :size="18" /> Motivo</div>
                    <textarea v-model="fieldForm.reason" class="form-input min-h-20" placeholder="Ej. Uso, mantenimiento, etc." maxlength="500" required></textarea>
                </div>

                <div class="premium-modal__footer">
                    <button type="button" class="btn-secondary" @click="closeFieldModal()">Cancelar</button>
                    <button class="btn-primary" :disabled="fieldForm.processing">{{ editingField ? 'Guardar cancha' : 'Guardar cancha' }}</button>
                </div>
            </form>
        </div>

        <div v-if="availabilityField" class="modal-backdrop" @click.self="closeAvailabilityModal()">
            <form class="premium-modal premium-modal--compact" @submit.prevent="submitAvailability">
                <div class="premium-modal__header">
                    <div class="premium-modal__title">
                        <span class="premium-modal__icon"><AppIcon name="clock" variant="illustrated" :size="34" /></span>
                        <div>
                            <h2>Agregar horario</h2>
                            <p>{{ availabilityField.name }}</p>
                        </div>
                    </div>
                    <button type="button" class="modal-close" @click="closeAvailabilityModal()">×</button>
                </div>

                <div class="premium-form-section">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="form-label">Día</label>
                            <select v-model="availabilityForm.weekday" class="form-input"><option v-for="(label, value) in days" :key="value" :value="Number(value)">{{ label }}</option></select>
                        </div>
                        <div></div>
                        <div><label class="form-label">Desde <span class="form-required">*</span></label><input v-model="availabilityForm.starts_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.starts_at" /></div>
                        <div><label class="form-label">Hasta <span class="form-required">*</span></label><input v-model="availabilityForm.ends_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.ends_at" /></div>
                        <div><label class="form-label">Vigente desde (opcional)</label><input v-model="availabilityForm.valid_from" type="date" class="form-input"></div>
                        <div><label class="form-label">Vigente hasta (opcional)</label><input v-model="availabilityForm.valid_until" type="date" class="form-input"><FormError :message="availabilityForm.errors.valid_until" /></div>
                        <div class="md:col-span-2"><label class="form-label">Motivo</label><textarea v-model="availabilityForm.reason" class="form-input min-h-20" required maxlength="500"></textarea></div>
                    </div>
                </div>

                <div class="premium-modal__footer">
                    <button type="button" class="btn-secondary" @click="closeAvailabilityModal()">Cancelar</button>
                    <button class="btn-primary" :disabled="availabilityForm.processing">Agregar</button>
                </div>
            </form>
        </div>

        <div v-if="blockField" class="modal-backdrop" @click.self="closeBlockModal()">
            <form class="premium-modal premium-modal--compact" @submit.prevent="submitBlock">
                <div class="premium-modal__header">
                    <div class="premium-modal__title">
                        <span class="premium-modal__icon"><AppIcon name="calendar" variant="illustrated" :size="34" /></span>
                        <div>
                            <h2>Bloquear cancha</h2>
                            <p>{{ blockField.name }}</p>
                        </div>
                    </div>
                    <button type="button" class="modal-close" @click="closeBlockModal()">×</button>
                </div>

                <div class="premium-form-section">
                    <div class="space-y-4">
                        <div><label class="form-label">Tipo</label><select v-model="blockForm.type" class="form-input"><option value="maintenance">Mantenimiento</option><option value="unavailable">No disponible</option><option value="event">Evento externo</option></select></div>
                        <div class="grid gap-4 md:grid-cols-2"><div><label class="form-label">Desde <span class="form-required">*</span></label><input v-model="blockForm.starts_at" type="datetime-local" class="form-input" required><FormError :message="blockForm.errors.starts_at" /></div><div><label class="form-label">Hasta <span class="form-required">*</span></label><input v-model="blockForm.ends_at" type="datetime-local" class="form-input" required><FormError :message="blockForm.errors.ends_at" /></div></div>
                        <div><label class="form-label">Motivo o descripción</label><textarea v-model="blockForm.reason" class="form-input min-h-20" required maxlength="500"></textarea></div>
                    </div>
                </div>

                <div class="premium-modal__footer">
                    <button type="button" class="btn-secondary" @click="closeBlockModal()">Cancelar</button>
                    <button class="btn-primary" :disabled="blockForm.processing">Registrar bloqueo</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.fields-page {
    padding-bottom: 1rem;
}

.fields-hero {
    position: relative;
    overflow: hidden;
    border-radius: 30px;
    background:
        linear-gradient(90deg, rgba(4, 25, 67, 0.78) 0%, rgba(13, 53, 125, 0.68) 45%, rgba(8, 97, 144, 0.58) 100%),
        url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="420" viewBox="0 0 1600 420"><defs><linearGradient id="sky" x1="0" x2="0" y1="0" y2="1"><stop stop-color="%23132c58"/><stop offset="1" stop-color="%230e1f40"/></linearGradient><linearGradient id="stand" x1="0" x2="0" y1="0" y2="1"><stop stop-color="%23425d84" stop-opacity=".7"/><stop offset="1" stop-color="%23111f36" stop-opacity=".95"/></linearGradient><linearGradient id="grass" x1="0" x2="0" y1="0" y2="1"><stop stop-color="%2327a56b" stop-opacity=".85"/><stop offset="1" stop-color="%23146b4c" stop-opacity=".95"/></linearGradient></defs><rect width="1600" height="420" fill="url(%23sky)"/><ellipse cx="800" cy="185" rx="760" ry="175" fill="url(%23stand)" opacity=".9"/><ellipse cx="800" cy="198" rx="660" ry="128" fill="none" stroke="%239ab4d6" stroke-opacity=".26" stroke-width="2"/><ellipse cx="800" cy="220" rx="560" ry="96" fill="none" stroke="%239ab4d6" stroke-opacity=".18" stroke-width="2"/><rect x="0" y="285" width="1600" height="135" fill="url(%23grass)"/><path d="M0 320 C 280 260, 1320 260, 1600 320 L 1600 420 L 0 420 Z" fill="%2311533d" opacity=".62"/><path d="M720 286 L880 286 L945 420 L655 420 Z" fill="none" stroke="%23dff3e9" stroke-opacity=".45" stroke-width="3"/><line x1="800" y1="286" x2="800" y2="420" stroke="%23dff3e9" stroke-opacity=".42" stroke-width="3"/><g stroke="%23ffffff" stroke-opacity=".38" stroke-width="3" fill="none"><path d="M70 120 l120 36"/><path d="M1410 120 l-120 36"/><path d="M110 95 l100 26"/><path d="M1490 95 l-100 26"/></g><g fill="%23ffffff"><circle cx="108" cy="109" r="5" opacity=".92"/><circle cx="150" cy="122" r="4.5" opacity=".88"/><circle cx="1492" cy="109" r="5" opacity=".92"/><circle cx="1450" cy="122" r="4.5" opacity=".88"/></g><g fill="%23ffffff" opacity=".10"><circle cx="220" cy="120" r="48"/><circle cx="1370" cy="105" r="42"/></g></svg>');
    background-size: cover;
    background-position: center;
    box-shadow: 0 22px 48px rgba(15, 44, 93, 0.16);
}

.fields-hero::after {
    content: '';
    position: absolute;
    inset: auto 0 0 0;
    height: 42%;
    background: linear-gradient(to top, rgba(2, 16, 39, 0.22), transparent);
    pointer-events: none;
}

.fields-hero__content {
    position: relative;
    z-index: 1;
    padding: 1.75rem 1.9rem;
}

.premium-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    border-radius: 24px;
    border: 1px solid rgba(207, 220, 236, 0.95);
    background: rgba(255, 255, 255, 0.94);
    padding: 1.25rem;
    box-shadow: 0 14px 30px rgba(15, 44, 93, 0.08);
}

.premium-stat-card--success {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(244, 252, 247, 0.98));
}

.premium-stat-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.premium-stat-card__label {
    font-size: .95rem;
    font-weight: 700;
    color: #1b2a4a;
}

.premium-stat-card__value {
    margin-top: .125rem;
    font-size: 2rem;
    line-height: 1;
    font-weight: 800;
    color: #10213e;
}

.premium-stat-card__caption {
    margin-top: .25rem;
    font-size: .875rem;
    color: #64748b;
}

.venue-card {
    overflow: hidden;
    border-radius: 30px;
    border: 1px solid rgba(214, 224, 236, 0.95);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 16px 38px rgba(15, 44, 93, 0.08);
}

.venue-card__main {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.2rem;
}

@media (min-width: 1024px) {
    .venue-card__main {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.venue-card__hero {
    display: flex;
    min-width: 0;
    gap: 1rem;
    align-items: center;
}

.venue-card__thumb {
    position: relative;
    width: 96px;
    height: 72px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 18px;
    background:
        linear-gradient(135deg, rgba(38, 97, 191, 0.9), rgba(12, 140, 114, 0.7)),
        radial-gradient(circle at 25% 30%, rgba(255,255,255,.45), transparent 26%),
        linear-gradient(to bottom, rgba(255,255,255,.16), rgba(255,255,255,.02));
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.18);
}

.venue-card__thumb::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        linear-gradient(to bottom, rgba(255,255,255,.14), transparent 42%),
        repeating-linear-gradient(90deg, transparent 0, transparent 11px, rgba(255,255,255,.12) 11px, rgba(255,255,255,.12) 12px),
        linear-gradient(to top, rgba(14, 65, 117, 0.2), transparent);
}

.venue-card__thumb-overlay {
    position: absolute;
    inset: auto 0 10px 0;
    height: 18px;
    border-top: 1px solid rgba(255,255,255,.5);
}

.venue-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
    align-items: center;
}

.venue-card__collapsed {
    border-top: 1px solid #e2e8f0;
    padding: .95rem 1.2rem 1.2rem;
    font-size: .9rem;
    color: #64748b;
}

.venue-card__body {
    border-top: 1px solid #e2e8f0;
    padding: 1.2rem;
    background: linear-gradient(180deg, rgba(248, 251, 255, 0.95), rgba(255, 255, 255, 0.98));
}

.venue-card__empty {
    display: flex;
    gap: .8rem;
    align-items: center;
    justify-content: center;
    border: 1px dashed #cbd5e1;
    border-radius: 20px;
    background: rgba(255,255,255,.92);
    padding: 1.2rem;
    font-size: .95rem;
    color: #64748b;
}

.field-card {
    border-radius: 26px;
    border: 1px solid rgba(216, 226, 238, 0.98);
    background: rgba(255, 255, 255, 0.98);
    padding: 1.1rem;
    box-shadow: 0 10px 28px rgba(15, 44, 93, 0.06);
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    border-radius: 999px;
    background: #eff4fb;
    padding: .42rem .7rem;
    font-weight: 500;
}

.schedule-row,
.block-row {
    display: flex;
    justify-content: space-between;
    gap: .8rem;
    align-items: flex-start;
    border-radius: 16px;
    background: #f8fafc;
    padding: .8rem .9rem;
}

.block-row {
    border: 1px solid #fde6bf;
    background: #fff7e9;
}

.icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: .9rem;
    border: 1px solid #d6e0ec;
    background: #fff;
    color: #315e91;
    transition: .2s ease;
}

.icon-button:hover {
    background: #f8fbff;
    color: #173d73;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.6);
    padding: 1rem;
    backdrop-filter: blur(3px);
}

.premium-modal {
    width: 100%;
    max-height: 92vh;
    overflow-y: auto;
    border-radius: 28px;
    background: #fff;
    padding: 1.2rem;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.premium-modal--wide { max-width: 960px; }
.premium-modal--compact { max-width: 640px; }

.premium-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.premium-modal__title {
    display: flex;
    gap: .95rem;
    align-items: flex-start;
}

.premium-modal__title h2 {
    font-size: 1.95rem;
    line-height: 1.05;
    font-weight: 800;
    color: #132445;
}

.premium-modal__title p {
    margin-top: .3rem;
    font-size: .95rem;
    color: #64748b;
}

.premium-modal__icon {
    flex: 0 0 auto;
}

.modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 999px;
    color: #64748b;
    font-size: 1.8rem;
    line-height: 1;
}

.premium-form-section {
    margin-top: 1rem;
    border-radius: 22px;
    border: 1px solid #e2e8f0;
    background: #fff;
    padding: 1rem;
}

.premium-form-section__head {
    display: flex;
    align-items: center;
    gap: .55rem;
    margin: -.1rem -.1rem 1rem;
    border-radius: 16px;
    background: #f4f7fb;
    padding: .82rem .9rem;
    font-size: .95rem;
    font-weight: 700;
    color: #1e3a6d;
}

.premium-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    margin-top: 1.15rem;
}

@media (max-width: 640px) {
    .fields-hero__content {
        padding: 1.4rem;
    }

    .premium-modal__title h2 {
        font-size: 1.45rem;
    }

    .premium-modal__footer {
        flex-direction: column;
    }

    .premium-modal__footer :deep(.btn-secondary),
    .premium-modal__footer :deep(.btn-primary) {
        width: 100%;
    }
}
</style>
