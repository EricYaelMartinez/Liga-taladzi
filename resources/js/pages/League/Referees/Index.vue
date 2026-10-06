<script setup lang="ts">
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
        <section>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 9</p>
                    <h1 class="mt-1 text-3xl font-semibold">Árbitros y disponibilidad</h1>
                    <p class="mt-2 text-slate-600">Expedientes, horarios disponibles y seguimiento interno del cuerpo arbitral.</p>
                </div>
                <button v-if="canManage" class="btn-primary" :disabled="!eligibleMembers.length" @click="newReferee">Registrar árbitro</button>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-3">
                <div class="stat-card"><span class="stat-label">Árbitros registrados</span><span class="stat-value">{{ referees.length }}</span></div>
                <div class="stat-card"><span class="stat-label">Árbitros activos</span><span class="stat-value">{{ activeCount }}</span></div>
                <div class="stat-card"><span class="stat-label">Horarios registrados</span><span class="stat-value">{{ totalAvailability }}</span></div>
            </div>

            <div v-if="canManage && !eligibleMembers.length && !referees.length" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Primero crea o asigna en <strong>Miembros</strong> un usuario con el rol Árbitro. Después podrás abrir su expediente aquí.
            </div>
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Las asignaciones e historial de partidos se habilitarán al crear jornadas y partidos en el Módulo 10. La disponibilidad registrada aquí será usada para impedir cruces de horario.
            </div>

            <div v-if="!referees.length" class="card mt-7 p-12 text-center text-slate-600">
                {{ canManage ? 'No hay árbitros registrados en esta liga.' : 'Tu usuario todavía no tiene un expediente arbitral. Solicita al administrador que lo registre.' }}
            </div>
            <div v-else class="mt-7 grid gap-6 lg:grid-cols-2">
                <article v-for="referee in referees" :key="referee.id" class="card overflow-hidden">
                    <header class="flex items-start gap-4 border-b border-slate-200 p-6">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-league-100 text-2xl font-bold text-league-900">
                            <img v-if="referee.photo_url" :src="referee.photo_url" :alt="referee.user.name" class="h-full w-full object-cover">
                            <span v-else>{{ referee.user.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-xl font-semibold">{{ referee.user.name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="referee.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">{{ referee.status === 'active' ? 'Activo' : 'Inactivo' }}</span>
                            </div>
                            <p class="mt-1 font-medium text-league-700">{{ referee.category_level }}</p>
                            <p class="mt-2 break-all text-sm text-slate-600">{{ referee.contact_email || referee.user.email }}</p>
                            <p class="text-sm text-slate-600">{{ referee.contact_phone || referee.user.phone || 'Sin teléfono' }}</p>
                        </div>
                        <button class="text-sm font-semibold text-league-700 underline" @click="canManage ? editReferee(referee) : editOwn(referee)">Editar</button>
                    </header>

                    <div class="p-6">
                        <div class="flex items-center justify-between"><h3 class="font-semibold">Disponibilidad semanal</h3><button class="text-sm font-semibold text-league-700" @click="newAvailability(referee)">+ Horario</button></div>
                        <div v-if="!referee.availabilities.length" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Sin disponibilidad: no podrá asignarse a partidos.</div>
                        <div v-else class="mt-3 space-y-2">
                            <div v-for="item in referee.availabilities" :key="item.id" class="flex justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                                <div><span class="font-medium">{{ days[item.weekday] }}</span> · {{ time(item.starts_at) }}–{{ time(item.ends_at) }}<p v-if="item.valid_from || item.valid_until" class="text-xs text-slate-500">Vigencia: {{ item.valid_from ?? 'sin inicio' }} a {{ item.valid_until ?? 'sin fin' }}</p></div>
                                <button class="text-red-700" @click="deleteAvailability(item)">Quitar</button>
                            </div>
                        </div>

                        <template v-if="canManage">
                            <div class="mt-5 border-t border-slate-200 pt-4">
                                <div class="flex items-center justify-between"><h3 class="font-semibold">Observaciones internas</h3><button class="text-sm font-semibold text-league-700" @click="newObservation(referee)">+ Observación</button></div>
                                <p v-if="!referee.observations.length" class="mt-2 text-sm text-slate-500">Sin observaciones administrativas.</p>
                                <div v-else class="mt-3 space-y-2"><div v-for="item in referee.observations" :key="item.id" class="rounded-lg border border-slate-200 p-3 text-sm"><div class="flex justify-between gap-3 text-xs text-slate-500"><span>{{ item.observed_on }}</span><span>{{ item.creator?.name ?? 'Administrador' }}</span></div><p class="mt-2 whitespace-pre-line text-slate-700">{{ item.observation }}</p></div></div>
                            </div>
                            <div v-if="referee.notes" class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas privadas del expediente</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ referee.notes }}</p></div>
                        </template>
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
