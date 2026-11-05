<script setup lang="ts">
import AppIcon from '../../../components/AppIcon.vue';
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Member = { id: number; name: string; email: string; phone: string | null };
type Availability = { id: number; weekday: number; starts_at: string; ends_at: string; valid_from: string | null; valid_until: string | null };
type Observation = { id: number; observed_on: string; observation: string; creator: { id: number; name: string } | null };
type Referee = {
    id: number; user_id: number; photo_url: string | null; contact_email: string | null; contact_phone: string | null;
    category_level: string; status: string; joined_on: string | null; notes?: string | null; user: Member;
    availabilities: Availability[]; observations: Observation[];
};

const props = defineProps<{ referees: Referee[]; eligibleMembers: Member[]; canManage: boolean }>();
const profileOpen = ref(false);
const editing = ref<Referee | null>(null);
const availabilityReferee = ref<Referee | null>(null);
const observationReferee = ref<Referee | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);

const profileForm = useForm({
    user_id: 0, photo: null as File | null, contact_email: '', contact_phone: '', category_level: '', status: 'active', joined_on: '', notes: '', reason: '',
});
const ownForm = useForm({ photo: null as File | null, contact_email: '', contact_phone: '', reason: '' });
const availabilityForm = useForm({ weekday: 6, starts_at: '08:00', ends_at: '18:00', valid_from: '', valid_until: '', reason: '' });
const observationForm = useForm({ observed_on: new Date().toISOString().slice(0, 10), observation: '', reason: '' });

const activeCount = computed(() => props.referees.filter((item) => item.status === 'active').length);
const totalAvailability = computed(() => props.referees.reduce((total, item) => total + item.availabilities.length, 0));
const search = ref('');
const statusFilter = ref('all');
const levelFilter = ref('all');
const selectedSections = ref<Record<number, 'availability' | 'information' | 'history' | 'observations'>>({});
const refereeLevels = computed(() => [...new Set(props.referees.map((item) => item.category_level).filter(Boolean))]);
const visibleReferees = computed(() => props.referees.filter((item) => {
    const query = search.value.trim().toLocaleLowerCase('es-MX');
    const contact = [item.user.name, item.user.email, item.contact_email ?? '', item.category_level].join(' ').toLocaleLowerCase('es-MX');
    return (!query || contact.includes(query)) && (statusFilter.value === 'all' || item.status === statusFilter.value) && (levelFilter.value === 'all' || item.category_level === levelFilter.value);
}));
function section(refereeId: number) { return selectedSections.value[refereeId] ?? 'availability'; }
function initials(name: string) { return name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase(); }

const days: Record<number, string> = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };

function newReferee() {
    editing.value = null; profileOpen.value = true; profileForm.reset(); profileForm.clearErrors(); profileForm.status = 'active';
    if (props.eligibleMembers.length) profileForm.user_id = props.eligibleMembers[0].id;
    if (photoInput.value) photoInput.value.value = '';
}
function editReferee(referee: Referee) {
    editing.value = referee; profileOpen.value = true; profileForm.clearErrors();
    Object.assign(profileForm, {
        user_id: referee.user_id, photo: null, contact_email: referee.contact_email ?? referee.user.email,
        contact_phone: referee.contact_phone ?? referee.user.phone ?? '', category_level: referee.category_level,
        status: referee.status, joined_on: referee.joined_on?.slice(0, 10) ?? '', notes: referee.notes ?? '', reason: '',
    });
    if (photoInput.value) photoInput.value.value = '';
}
function editOwn(referee: Referee) {
    editing.value = referee; profileOpen.value = true; ownForm.reset(); ownForm.clearErrors();
    ownForm.contact_email = referee.contact_email ?? referee.user.email;
    ownForm.contact_phone = referee.contact_phone ?? referee.user.phone ?? '';
    if (photoInput.value) photoInput.value.value = '';
}
function onPhoto(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (props.canManage) profileForm.photo = file;
    else ownForm.photo = file;
}
function submitProfile() {
    if (props.canManage) {
        const url = editing.value ? `/liga/arbitros/${editing.value.id}/actualizar` : '/liga/arbitros';
        profileForm.post(url, { forceFormData: true, preserveScroll: true, onSuccess: closeProfile });
    } else if (editing.value) {
        ownForm.post(`/liga/arbitros/${editing.value.id}/mi-perfil`, { forceFormData: true, preserveScroll: true, onSuccess: closeProfile });
    }
}
function closeProfile() { profileOpen.value = false; editing.value = null; profileForm.reset(); ownForm.reset(); }
function newAvailability(referee: Referee) {
    availabilityReferee.value = referee; availabilityForm.reset(); availabilityForm.clearErrors();
    availabilityForm.weekday = 6; availabilityForm.starts_at = '08:00'; availabilityForm.ends_at = '18:00';
}
function submitAvailability() {
    if (!availabilityReferee.value) return;
    availabilityForm.post(`/liga/arbitros/${availabilityReferee.value.id}/disponibilidades`, { preserveScroll: true, onSuccess: () => { availabilityReferee.value = null; availabilityForm.reset(); } });
}
function deleteAvailability(item: Availability) {
    const reason = prompt('Motivo para eliminar esta disponibilidad:');
    if (reason?.trim()) router.delete(`/liga/disponibilidades-arbitro/${item.id}`, { data: { reason }, preserveScroll: true });
}
function newObservation(referee: Referee) {
    observationReferee.value = referee; observationForm.reset(); observationForm.clearErrors();
    observationForm.observed_on = new Date().toISOString().slice(0, 10);
}
function submitObservation() {
    if (!observationReferee.value) return;
    observationForm.post(`/liga/arbitros/${observationReferee.value.id}/observaciones`, { preserveScroll: true, onSuccess: () => { observationReferee.value = null; observationForm.reset(); } });
}
function time(value: string): string { return value.slice(0, 5); }
</script>

<template>
    <Head title="Árbitros y disponibilidad" />
    <AppLayout>
        <section class="referees-page">
            <header class="referees-hero">
                <div class="referees-hero__inner">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-white/75">Módulo 9 · Operación</p>
                        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Árbitros y disponibilidad</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/90 sm:text-base">Expedientes, horarios disponibles y seguimiento interno del cuerpo arbitral.</p>
                    </div>
                    <button v-if="canManage" class="referees-hero__action" :disabled="!eligibleMembers.length" @click="newReferee">
                        <AppIcon name="user" :size="18" /> Registrar árbitro
                    </button>
                </div>
            </header>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="referee-stat">
                    <span class="referee-stat__icon referee-stat__icon--blue"><AppIcon name="users" variant="illustrated" :size="34" /></span>
                    <div><p class="referee-stat__label">Árbitros registrados</p><strong class="referee-stat__count">{{ referees.length }}</strong></div>
                </div>
                <div class="referee-stat">
                    <span class="referee-stat__icon referee-stat__icon--green"><AppIcon name="check" variant="illustrated" :size="34" /></span>
                    <div><p class="referee-stat__label">Árbitros activos</p><strong class="referee-stat__count">{{ activeCount }}</strong></div>
                </div>
                <div class="referee-stat">
                    <span class="referee-stat__icon referee-stat__icon--blue"><AppIcon name="calendar" variant="illustrated" :size="34" /></span>
                    <div><p class="referee-stat__label">Horarios registrados</p><strong class="referee-stat__count">{{ totalAvailability }}</strong></div>
                </div>
            </div>

            <div v-if="canManage && !eligibleMembers.length && !referees.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                Primero crea o asigna en <strong>Miembros</strong> un usuario con el rol Árbitro. Después podrás abrir su expediente aquí.
            </div>
            <div class="mt-4 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <AppIcon name="badge" :size="18" class="mt-0.5 shrink-0" />
                <p>La disponibilidad registrada se utiliza para evitar cruces de horario al asignar árbitros a partidos.</p>
            </div>

            <div class="referee-filters mt-5">
                <div class="min-w-0 flex-1">
                    <label for="referee-search" class="mb-2 block text-xs font-bold text-slate-700">Buscar árbitro</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-league-700"><AppIcon name="user" :size="19" /></span>
                        <input id="referee-search" v-model="search" class="form-input pl-11" type="search" placeholder="Nombre, correo o categoría...">
                    </div>
                </div>
                <div class="w-full sm:w-44">
                    <label for="referee-status-filter" class="mb-2 block text-xs font-bold text-slate-700">Estado</label>
                    <select id="referee-status-filter" v-model="statusFilter" class="form-input"><option value="all">Todos</option><option value="active">Activo</option><option value="inactive">Inactivo</option></select>
                </div>
                <div class="w-full sm:w-48">
                    <label for="referee-level-filter" class="mb-2 block text-xs font-bold text-slate-700">Categoría o nivel</label>
                    <select id="referee-level-filter" v-model="levelFilter" class="form-input"><option value="all">Todos</option><option v-for="level in refereeLevels" :key="level" :value="level">{{ level }}</option></select>
                </div>
            </div>

            <div v-if="!referees.length" class="referee-empty mt-5">
                <AppIcon name="users" variant="illustrated" :size="54" />
                <h2 class="mt-4 text-lg font-bold text-slate-900">{{ canManage ? 'No hay árbitros registrados' : 'No tienes expediente arbitral' }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ canManage ? 'Registra tu primer árbitro para comenzar a administrar su disponibilidad.' : 'Solicita al administrador que registre tu expediente.' }}</p>
            </div>
            <div v-else-if="!visibleReferees.length" class="referee-empty mt-5">
                <AppIcon name="users" variant="illustrated" :size="48" />
                <p class="mt-3 font-semibold text-slate-800">No hay árbitros que coincidan con los filtros.</p>
                <button class="mt-3 text-sm font-semibold text-league-700" @click="search = ''; statusFilter = 'all'; levelFilter = 'all'">Limpiar filtros</button>
            </div>
            <div v-else class="mt-5 grid items-start gap-5 lg:grid-cols-2">
                <article v-for="referee in visibleReferees" :key="referee.id" class="referee-card">
                    <header class="referee-card__header">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            <div class="referee-avatar">
                                <img v-if="referee.photo_url" :src="referee.photo_url" :alt="referee.user.name" class="h-full w-full object-cover">
                                <span v-else>{{ initials(referee.user.name) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold leading-6 text-slate-900">{{ referee.user.name }}</h2>
                                    <span class="referee-status" :class="referee.status === 'active' ? 'referee-status--active' : 'referee-status--inactive'">{{ referee.status === 'active' ? 'Activo' : 'Inactivo' }}</span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-league-700">{{ referee.category_level }}</p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span class="flex min-w-0 items-center gap-1.5"><AppIcon name="mail" :size="14" /><span class="break-all">{{ referee.contact_email || referee.user.email }}</span></span>
                                    <span class="flex items-center gap-1.5"><AppIcon name="phone" :size="14" />{{ referee.contact_phone || referee.user.phone || 'Sin teléfono' }}</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="referee-edit" @click="canManage ? editReferee(referee) : editOwn(referee)"><AppIcon name="edit" :size="14" />Editar</button>
                    </header>

                    <nav class="referee-tabs" :aria-label="`Información de ${referee.user.name}`">
                        <button type="button" :class="{'is-active': section(referee.id) === 'availability'}" @click="selectedSections[referee.id] = 'availability'">Disponibilidad</button>
                        <button type="button" :class="{'is-active': section(referee.id) === 'information'}" @click="selectedSections[referee.id] = 'information'">Información</button>
                        <button type="button" :class="{'is-active': section(referee.id) === 'history'}" @click="selectedSections[referee.id] = 'history'">Historial</button>
                        <button v-if="canManage" type="button" :class="{'is-active': section(referee.id) === 'observations'}" @click="selectedSections[referee.id] = 'observations'">Observaciones</button>
                    </nav>

                    <div class="referee-card__body">
                        <template v-if="section(referee.id) === 'availability'">
                            <section class="referee-inner-panel">
                                <div class="referee-panel-heading">
                                    <div class="flex items-center gap-2"><AppIcon name="clock" variant="illustrated" :size="30" /><h3>Disponibilidad semanal</h3></div>
                                    <button class="referee-add" @click="newAvailability(referee)"><span>＋</span> Agregar horario</button>
                                </div>
                                <p v-if="!referee.availabilities.length" class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Sin disponibilidad: no podrá asignarse a partidos.</p>
                                <div v-else class="mt-3 divide-y divide-slate-200">
                                    <div v-for="item in referee.availabilities" :key="item.id" class="referee-schedule-row">
                                        <span class="font-semibold text-slate-700">{{ days[item.weekday] }}</span>
                                        <div class="text-slate-600"><span>{{ time(item.starts_at) }} – {{ time(item.ends_at) }}</span><p v-if="item.valid_from || item.valid_until" class="mt-0.5 text-xs">Vigencia: {{ item.valid_from ?? 'sin inicio' }} a {{ item.valid_until ?? 'sin fin' }}</p></div>
                                        <button type="button" class="text-red-600 hover:text-red-800" aria-label="Eliminar horario" @click="deleteAvailability(item)">Quitar</button>
                                    </div>
                                </div>
                            </section>
                            <section v-if="canManage" class="referee-inner-panel mt-4">
                                <div class="referee-panel-heading">
                                    <div class="flex items-center gap-2"><AppIcon name="file" variant="illustrated" :size="30" /><h3>Observaciones internas</h3></div>
                                    <button class="referee-add" @click="newObservation(referee)"><span>＋</span> Agregar observación</button>
                                </div>
                                <p v-if="!referee.observations.length" class="referee-muted-note">Sin observaciones administrativas.</p>
                                <p v-else class="referee-muted-note">{{ referee.observations.length }} {{ referee.observations.length === 1 ? 'observación registrada' : 'observaciones registradas' }} · Consulta los detalles en la pestaña Observaciones.</p>
                            </section>
                        </template>
                        <section v-else-if="section(referee.id) === 'information'" class="referee-inner-panel">
                            <div class="referee-panel-heading"><div class="flex items-center gap-2"><AppIcon name="id" variant="illustrated" :size="30" /><h3>Información del expediente</h3></div></div>
                            <dl class="mt-4 space-y-3 text-sm"><div class="referee-info-row"><dt>Nivel arbitral</dt><dd>{{ referee.category_level }}</dd></div><div class="referee-info-row"><dt>Fecha de ingreso</dt><dd>{{ referee.joined_on?.slice(0, 10) || 'No registrada' }}</dd></div><div class="referee-info-row"><dt>Correo de contacto</dt><dd class="break-all">{{ referee.contact_email || referee.user.email }}</dd></div><div class="referee-info-row"><dt>Teléfono</dt><dd>{{ referee.contact_phone || referee.user.phone || 'No registrado' }}</dd></div></dl>
                            <div v-if="canManage && referee.notes" class="mt-4 border-t border-slate-200 pt-4"><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Notas privadas del expediente</h4><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ referee.notes }}</p></div>
                        </section>
                        <section v-else-if="section(referee.id) === 'history'" class="referee-inner-panel">
                            <div class="referee-panel-heading"><div class="flex items-center gap-2"><AppIcon name="history" variant="illustrated" :size="30" /><h3>Historial de partidos</h3></div></div>
                            <p class="referee-muted-note">El historial de asignaciones y partidos se consulta desde Programación y Partidos. Esta vista no recibe aún esos registros.</p>
                        </section>
                        <section v-else-if="canManage" class="referee-inner-panel">
                            <div class="referee-panel-heading"><div class="flex items-center gap-2"><AppIcon name="file" variant="illustrated" :size="30" /><h3>Observaciones internas</h3></div><button class="referee-add" @click="newObservation(referee)"><span>＋</span> Agregar observación</button></div>
                            <p v-if="!referee.observations.length" class="referee-muted-note">Sin observaciones administrativas.</p>
                            <div v-else class="mt-3 space-y-3"><div v-for="item in referee.observations" :key="item.id" class="referee-observation"><div class="flex flex-wrap justify-between gap-2 text-xs text-slate-500"><span>{{ item.observed_on }}</span><span>{{ item.creator?.name ?? 'Administrador' }}</span></div><p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ item.observation }}</p></div></div>
                        </section>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="profileOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeProfile">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitProfile">
                <h2 class="text-xl font-semibold">{{ canManage ? (editing ? 'Editar expediente arbitral' : 'Registrar árbitro') : 'Actualizar mis datos' }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <template v-if="canManage">
                        <div class="sm:col-span-2"><label class="form-label">Usuario con rol Árbitro</label><select v-model="profileForm.user_id" class="form-input" required><option v-if="editing" :value="editing.user_id">{{ editing.user.name }} — {{ editing.user.email }}</option><option v-for="member in eligibleMembers" :key="member.id" :value="member.id">{{ member.name }} — {{ member.email }}</option></select><FormError :message="profileForm.errors.user_id" /></div>
                    </template>
                    <div class="sm:col-span-2"><label class="form-label">Fotografía (JPG, PNG o WebP; máximo 4 MB)</label><input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="form-input" @change="onPhoto"><FormError :message="canManage ? profileForm.errors.photo : ownForm.errors.photo" /></div>
                    <div><label class="form-label">Correo de contacto</label><input v-if="canManage" v-model="profileForm.contact_email" type="email" class="form-input"><input v-else v-model="ownForm.contact_email" type="email" class="form-input"><FormError :message="canManage ? profileForm.errors.contact_email : ownForm.errors.contact_email" /></div>
                    <div><label class="form-label">Teléfono de contacto</label><input v-if="canManage" v-model="profileForm.contact_phone" class="form-input" maxlength="30"><input v-else v-model="ownForm.contact_phone" class="form-input" maxlength="30"><FormError :message="canManage ? profileForm.errors.contact_phone : ownForm.errors.contact_phone" /></div>
                    <template v-if="canManage">
                        <div><label class="form-label">Categoría o nivel</label><input v-model="profileForm.category_level" class="form-input" required maxlength="100"><FormError :message="profileForm.errors.category_level" /></div>
                        <div><label class="form-label">Estado</label><select v-model="profileForm.status" class="form-input"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
                        <div><label class="form-label">Fecha de ingreso (opcional)</label><input v-model="profileForm.joined_on" type="date" class="form-input"></div>
                        <div class="sm:col-span-2"><label class="form-label">Notas privadas (solo administradores)</label><textarea v-model="profileForm.notes" class="form-input" maxlength="3000"></textarea></div>
                    </template>
                    <div class="sm:col-span-2"><label class="form-label">Motivo del cambio</label><textarea v-if="canManage" v-model="profileForm.reason" class="form-input" required maxlength="500"></textarea><textarea v-else v-model="ownForm.reason" class="form-input" required maxlength="500"></textarea></div>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="closeProfile">Cancelar</button><button class="btn-primary" :disabled="canManage ? profileForm.processing : ownForm.processing">Guardar</button></div>
            </form>
        </div>

        <div v-if="availabilityReferee" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="availabilityReferee = null">
            <form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitAvailability"><h2 class="text-xl font-semibold">Agregar disponibilidad</h2><p class="mt-1 text-sm text-slate-500">{{ availabilityReferee.user.name }}</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Día</label><select v-model="availabilityForm.weekday" class="form-input"><option v-for="(label, value) in days" :key="value" :value="Number(value)">{{ label }}</option></select></div><div></div><div><label class="form-label">Desde</label><input v-model="availabilityForm.starts_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.starts_at" /></div><div><label class="form-label">Hasta</label><input v-model="availabilityForm.ends_at" type="time" class="form-input" required><FormError :message="availabilityForm.errors.ends_at" /></div><div><label class="form-label">Vigente desde (opcional)</label><input v-model="availabilityForm.valid_from" type="date" class="form-input"></div><div><label class="form-label">Vigente hasta (opcional)</label><input v-model="availabilityForm.valid_until" type="date" class="form-input"><FormError :message="availabilityForm.errors.valid_until" /></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="availabilityForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="availabilityReferee = null">Cancelar</button><button class="btn-primary" :disabled="availabilityForm.processing">Agregar</button></div></form>
        </div>

        <div v-if="observationReferee" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="observationReferee = null">
            <form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="submitObservation"><h2 class="text-xl font-semibold">Observación interna</h2><p class="mt-1 text-sm text-slate-500">{{ observationReferee.user.name }} · solo visible para administradores</p><div class="mt-5 space-y-4"><div><label class="form-label">Fecha</label><input v-model="observationForm.observed_on" type="date" class="form-input" required></div><div><label class="form-label">Evaluación u observación</label><textarea v-model="observationForm.observation" class="form-input min-h-32" required maxlength="3000"></textarea><FormError :message="observationForm.errors.observation" /></div><div><label class="form-label">Motivo del registro</label><textarea v-model="observationForm.reason" class="form-input" required maxlength="500"></textarea></div></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="observationReferee = null">Cancelar</button><button class="btn-primary" :disabled="observationForm.processing">Guardar observación</button></div></form>
        </div>
    </AppLayout>
</template>

<style scoped>
.referees-page { padding-bottom: 1rem; }
.referees-hero { position: relative; overflow: hidden; border-radius: 1.55rem; background-image: linear-gradient(105deg, rgba(7,32,78,.82), rgba(14,72,150,.65) 55%, rgba(15,88,158,.52)), url('/images/public/league-stadium.jpg'); background-size: cover; background-position: center 42%; box-shadow: 0 16px 35px rgba(17,45,87,.15); }
.referees-hero__inner { position: relative; display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between; gap: 1.5rem; padding: 1.8rem 2rem; }
.referees-hero__action { display: inline-flex; align-items: center; justify-content: center; gap: .6rem; flex: 0 0 auto; border-radius: .8rem; background: #fff; color: #144eaa; padding: .8rem 1.2rem; font-size: .9rem; font-weight: 750; box-shadow: 0 5px 15px rgba(4,18,45,.14); transition: background .2s; }
.referees-hero__action:hover { background: #f0f6ff; }
.referees-hero__action:disabled { opacity: .6; cursor: not-allowed; }
.referee-stat { display: flex; align-items: center; gap: 1rem; min-height: 6rem; border: 1px solid #e3eaf4; border-radius: 1.05rem; background: rgba(255,255,255,.98); padding: 1.1rem 1.2rem; box-shadow: 0 8px 22px rgba(16,41,83,.07); }
.referee-stat__icon { display: grid; flex: 0 0 3.6rem; height: 3.6rem; place-items: center; border-radius: .8rem; }
.referee-stat__icon--blue { background: #eaf2ff; }
.referee-stat__icon--green { background: #e5f8ef; }
.referee-stat__label { font-size: .84rem; font-weight: 700; color: #1c335b; }
.referee-stat__count { display: block; margin-top: .25rem; font-size: 1.85rem; line-height: 1; font-weight: 800; color: #102f71; }
.referee-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 1rem; border: 1px solid #e1e9f4; border-radius: 1rem; background: #fff; padding: 1rem; box-shadow: 0 7px 20px rgba(15,43,84,.06); }
.referee-card { overflow: hidden; border: 1px solid #dfe8f4; border-radius: 1.05rem; background: #fff; box-shadow: 0 9px 24px rgba(13,40,82,.09); }
.referee-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; padding: 1.1rem; }
.referee-avatar { display: grid; flex: 0 0 4.4rem; height: 4.4rem; overflow: hidden; place-items: center; border-radius: .9rem; background: #eaf2ff; color: #2464b8; font-size: 1.6rem; font-weight: 800; }
.referee-status { display: inline-flex; border-radius: 999px; padding: .24rem .55rem; font-size: .7rem; font-weight: 800; }
.referee-status--active { background: #dff8eb; color: #067647; }
.referee-status--inactive { background: #e9edf3; color: #5b6474; }
.referee-edit { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #cddcf0; border-radius: .6rem; padding: .5rem .7rem; color: #1b58ad; font-size: .8rem; font-weight: 700; white-space: nowrap; }
.referee-tabs { display: flex; overflow-x: auto; border-bottom: 1px solid #e4ebf5; padding: 0 .7rem; }
.referee-tabs button { flex: 1; min-width: max-content; border-bottom: 2px solid transparent; padding: .8rem .55rem; color: #61718a; font-size: .75rem; font-weight: 650; }
.referee-tabs button.is-active { border-color: #2474eb; color: #1162d7; }
.referee-card__body { min-height: 16rem; padding: .9rem; }
.referee-inner-panel { border: 1px solid #e6edf7; border-radius: .8rem; background: #fff; padding: .85rem; }
.referee-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: .7rem; }
.referee-panel-heading h3 { color: #1a3159; font-size: .84rem; font-weight: 800; }
.referee-add { display: inline-flex; align-items: center; gap: .25rem; border: 1px solid #c8daf7; border-radius: .6rem; padding: .45rem .6rem; color: #1260cb; font-size: .73rem; font-weight: 700; white-space: nowrap; }
.referee-add:hover,.referee-edit:hover { background: #f3f8ff; }
.referee-schedule-row { display: grid; grid-template-columns: minmax(60px, .75fr) minmax(95px, 1.25fr) auto; align-items: start; gap: .5rem; padding: .72rem .25rem; font-size: .78rem; }
.referee-muted-note { margin-top: .8rem; border: 1px dashed #dce5f2; border-radius: .55rem; background: #f8faff; padding: .8rem; color: #7182a2; font-size: .78rem; }
.referee-info-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; border-bottom: 1px solid #eef2f8; padding-bottom: .55rem; }
.referee-info-row dt { color: #64748b; }
.referee-info-row dd { color: #233f65; font-weight: 650; }
.referee-observation { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fafcff; padding: .8rem; }
.referee-empty { display: flex; flex-direction: column; align-items: center; border: 1px dashed #cad8ec; border-radius: 1rem; background: #fff; padding: 3rem 1.5rem; text-align: center; }
@media (min-width: 768px) { .referees-hero__inner { flex-direction: row; align-items: center; padding: 2rem 2.4rem; } }
@media (max-width: 540px) { .referee-card__header { flex-wrap: wrap; } .referee-edit { margin-left: auto; } .referee-panel-heading { align-items: flex-start; } }
</style>
