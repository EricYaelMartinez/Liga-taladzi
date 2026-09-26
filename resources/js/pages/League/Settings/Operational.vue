<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type OperationalSettings = {
    match_periods: number;
    period_duration_minutes: number | null;
    halftime_minutes: number | null;
    schedule_buffer_minutes: number | null;
    appeal_deadline_hours: number;
    payment_grace_days: number | null;
    reactivation_window_days: number;
    bond_enabled: boolean;
    bond_amount: string | null;
    currency: string;
    revision: number;
};

const props = defineProps<{ settings: OperationalSettings; isConfigured: boolean }>();
const form = useForm({
    match_periods: props.settings.match_periods,
    period_duration_minutes: props.settings.period_duration_minutes,
    halftime_minutes: props.settings.halftime_minutes,
    schedule_buffer_minutes: props.settings.schedule_buffer_minutes,
    appeal_deadline_hours: props.settings.appeal_deadline_hours,
    payment_grace_days: props.settings.payment_grace_days,
    reactivation_window_days: props.settings.reactivation_window_days,
    bond_enabled: props.settings.bond_enabled,
    bond_amount: props.settings.bond_amount,
    currency: props.settings.currency,
    reason: '',
});

const bufferEnabled = ref(props.settings.schedule_buffer_minutes !== null);
const paymentGraceEnabled = ref(props.settings.payment_grace_days !== null);

watch(bufferEnabled, (enabled) => {
    form.schedule_buffer_minutes = enabled ? (form.schedule_buffer_minutes ?? 0) : null;
});
watch(paymentGraceEnabled, (enabled) => {
    form.payment_grace_days = enabled ? (form.payment_grace_days ?? 0) : null;
});
watch(() => form.bond_enabled, (enabled) => {
    if (!enabled) form.bond_amount = null;
});

const estimatedDuration = computed(() => {
    if (!form.period_duration_minutes) return null;
    return (form.match_periods * form.period_duration_minutes) + (Math.max(form.match_periods - 1, 0) * (form.halftime_minutes ?? 0));
});
</script>

<template>
    <Head title="Parámetros generales" />
    <AppLayout>
        <section class="mx-auto max-w-4xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Configuración por liga</p>
            <h1 class="mt-1 text-3xl font-semibold">Parámetros generales</h1>
            <p class="mt-2 text-slate-600">Define los valores predeterminados que utilizará esta liga.</p>

            <div v-if="!isConfigured" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                La duración del partido todavía no ha sido definida. Guarda este formulario antes de crear competencias.
            </div>
            <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                Cuando se cree un torneo, sus reglas copiarán estos valores y quedarán versionadas. Cambiar estos parámetros no modificará torneos históricos.
            </div>

            <form class="mt-7 space-y-6" @submit.prevent="form.put('/liga/parametros', { preserveScroll: true })">
                <section class="card p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Duración de los partidos</h2>
                    <p class="mt-2 text-sm text-slate-600">La configuración aprobada parte de dos tiempos, pero la liga puede ajustarla.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-3">
                        <div><label class="form-label">Número de tiempos</label><input v-model.number="form.match_periods" class="form-input" type="number" min="1" max="4" required><FormError :message="form.errors.match_periods" /></div>
                        <div><label class="form-label">Minutos por tiempo</label><input v-model.number="form.period_duration_minutes" class="form-input" type="number" min="5" max="120" required><FormError :message="form.errors.period_duration_minutes" /></div>
                        <div><label class="form-label">Descanso en minutos</label><input v-model.number="form.halftime_minutes" class="form-input" type="number" min="0" max="60" placeholder="Opcional"><FormError :message="form.errors.halftime_minutes" /></div>
                    </div>
                    <p v-if="estimatedDuration" class="mt-5 rounded-xl bg-league-50 px-4 py-3 text-sm text-league-900">Duración estimada total: <strong>{{ estimatedDuration }} minutos</strong>.</p>
                </section>

                <section class="card p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Programación y apelaciones</h2>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="flex items-center gap-3 text-sm font-medium"><input v-model="bufferEnabled" type="checkbox" class="h-4 w-4"> Usar margen entre partidos</label>
                            <div v-if="bufferEnabled" class="mt-3"><label class="form-label">Margen en minutos</label><input v-model.number="form.schedule_buffer_minutes" class="form-input" type="number" min="0" max="240"><FormError :message="form.errors.schedule_buffer_minutes" /></div>
                            <p v-else class="mt-2 text-xs text-slate-500">Sin margen adicional; únicamente se considerará la duración del partido.</p>
                        </div>
                        <div><label class="form-label">Plazo de apelación en horas</label><input v-model.number="form.appeal_deadline_hours" class="form-input" type="number" min="1" max="168" required><FormError :message="form.errors.appeal_deadline_hours" /><p class="mt-2 text-xs text-slate-500">Valor inicial aprobado: 2 horas.</p></div>
                    </div>
                </section>

                <section class="card p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Pagos, reactivación y fianza</h2>
                    <p class="mt-2 text-sm text-slate-600">Estos parámetros preparan las reglas; el registro de pagos se incorporará en su módulo correspondiente.</p>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="flex items-center gap-3 text-sm font-medium"><input v-model="paymentGraceEnabled" type="checkbox" class="h-4 w-4"> Aplicar prórroga de pago</label>
                            <div v-if="paymentGraceEnabled" class="mt-3"><label class="form-label">Días de prórroga</label><input v-model.number="form.payment_grace_days" class="form-input" type="number" min="0" max="365"><FormError :message="form.errors.payment_grace_days" /></div>
                        </div>
                        <div><label class="form-label">Días para reincorporarse después de la suspensión</label><input v-model.number="form.reactivation_window_days" class="form-input" type="number" min="1" max="365" required><FormError :message="form.errors.reactivation_window_days" /><p class="mt-2 text-xs text-slate-500">Valor inicial: 21 días, equivalentes a 3 semanas.</p></div>
                    </div>
                    <div class="mt-6 border-t border-slate-200 pt-6">
                        <label class="flex items-center gap-3 text-sm font-medium"><input v-model="form.bond_enabled" type="checkbox" class="h-4 w-4"> La liga requiere fianza</label>
                        <div v-if="form.bond_enabled" class="mt-4 grid gap-5 sm:grid-cols-2">
                            <div><label class="form-label">Monto de la fianza</label><input v-model="form.bond_amount" class="form-input" type="number" min="0" max="9999999.99" step="0.01" required><FormError :message="form.errors.bond_amount" /></div>
                            <div><label class="form-label">Moneda</label><select v-model="form.currency" class="form-input"><option value="MXN">MXN — Peso mexicano</option></select><FormError :message="form.errors.currency" /></div>
                        </div>
                    </div>
                </section>

                <section class="card p-6 sm:p-8">
                    <label class="form-label">Motivo de la configuración o modificación</label>
                    <textarea v-model="form.reason" class="form-input min-h-24" maxlength="500" required placeholder="Ejemplo: Parámetros aprobados para la temporada 2027"></textarea>
                    <FormError :message="form.errors.reason" />
                    <div class="mt-6 flex justify-end"><button class="btn-primary" type="submit" :disabled="form.processing">Guardar parámetros</button></div>
                </section>
            </form>
        </section>
    </AppLayout>
</template>
