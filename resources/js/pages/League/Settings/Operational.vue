<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
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

const durationSummary = computed(() => {
    if (!estimatedDuration.value) return 'Completa los datos para estimar la duración total del partido.';
    return `${form.match_periods} ${form.match_periods === 1 ? 'tiempo' : 'tiempos'} de ${form.period_duration_minutes} min${(form.halftime_minutes ?? 0) > 0 ? ` + ${form.halftime_minutes} min de descanso` : ''}.`;
});
</script>

<template>
    <Head title="Parámetros generales" />
    <AppLayout>
        <section class="mx-auto max-w-6xl">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="header-icon-shell">
                        <AppIcon name="sliders" variant="illustrated" :size="54" />
                    </div>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.24em] text-league-600">Configuración • Reglas base</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-[2.35rem]">Parámetros generales</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                            Define los valores predeterminados que utilizará esta liga en la programación, apelaciones y condiciones administrativas.
                        </p>
                    </div>
                </div>

                <button class="btn-primary inline-flex min-h-12 items-center justify-center gap-2 px-5 py-3 lg:mt-2" type="submit" form="operational-settings-form" :disabled="form.processing">
                    <AppIcon name="check" :size="16" />
                    {{ form.processing ? 'Guardando…' : 'Guardar parámetros' }}
                </button>
            </div>

            <div class="mt-6 space-y-3">
                <div v-if="!isConfigured" class="notice-card notice-card--warning">
                    <AppIcon name="clock" :size="18" />
                    <span>La duración del partido todavía no ha sido definida. Guarda este formulario antes de crear competencias.</span>
                </div>
                <div class="notice-card notice-card--info">
                    <AppIcon name="file" :size="18" />
                    <span>Cuando se cree un torneo, sus reglas copiarán estos valores y quedarán versionadas. Cambiar estos parámetros no modificará torneos históricos.</span>
                </div>
            </div>

            <form id="operational-settings-form" class="mt-7 space-y-6" @submit.prevent="form.put('/liga/parametros', { preserveScroll: true })">
                <section class="premium-card">
                    <div class="premium-card__sidebar">
                        <div class="step-badge">1</div>
                        <div class="sidebar-icon"><AppIcon name="clock" variant="illustrated" :size="42" /></div>
                    </div>
                    <div class="premium-card__content">
                        <div class="section-header">
                            <div>
                                <h2 class="section-title">Duración de los partidos</h2>
                                <p class="section-description">Define la estructura base del encuentro y la duración total estimada para la agenda deportiva.</p>
                            </div>
                            <div class="section-pill">
                                <strong>{{ estimatedDuration ?? '—' }}</strong>
                                <span>minutos estimados</span>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-5 xl:grid-cols-3">
                            <div class="field-card">
                                <label class="form-label">Número de tiempos</label>
                                <input v-model.number="form.match_periods" class="form-input" type="number" min="1" max="4" required>
                                <FormError :message="form.errors.match_periods" />
                            </div>
                            <div class="field-card">
                                <label class="form-label">Minutos por tiempo</label>
                                <input v-model.number="form.period_duration_minutes" class="form-input" type="number" min="5" max="120" required>
                                <FormError :message="form.errors.period_duration_minutes" />
                            </div>
                            <div class="field-card">
                                <label class="form-label">Descanso en minutos</label>
                                <input v-model.number="form.halftime_minutes" class="form-input" type="number" min="0" max="60" placeholder="Opcional">
                                <FormError :message="form.errors.halftime_minutes" />
                            </div>
                        </div>

                        <div class="summary-band mt-5">
                            <div class="summary-band__icon"><AppIcon name="clock" :size="18" /></div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Resumen de la configuración</p>
                                <p class="mt-1 text-sm text-slate-600">{{ durationSummary }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="premium-card">
                    <div class="premium-card__sidebar">
                        <div class="step-badge">2</div>
                        <div class="sidebar-icon"><AppIcon name="calendar" variant="illustrated" :size="42" /></div>
                    </div>
                    <div class="premium-card__content">
                        <div class="section-header">
                            <div>
                                <h2 class="section-title">Programación y apelaciones</h2>
                                <p class="section-description">Ajusta el margen operativo entre encuentros y el tiempo disponible para presentar apelaciones.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                            <div class="feature-panel">
                                <label class="toggle-row">
                                    <input v-model="bufferEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-league-600 focus:ring-league-400">
                                    <div>
                                        <span class="toggle-title">Usar margen entre partidos</span>
                                        <span class="toggle-description">Añade tiempo de separación para logística, descanso y transición entre encuentros.</span>
                                    </div>
                                </label>

                                <div v-if="bufferEnabled" class="mt-5 field-card">
                                    <label class="form-label">Margen en minutos</label>
                                    <input v-model.number="form.schedule_buffer_minutes" class="form-input" type="number" min="0" max="240">
                                    <FormError :message="form.errors.schedule_buffer_minutes" />
                                </div>
                                <div v-else class="empty-note mt-5">Sin margen adicional; únicamente se considerará la duración del partido.</div>
                            </div>

                            <div class="feature-panel">
                                <div class="field-card">
                                    <label class="form-label">Plazo de apelación en horas</label>
                                    <input v-model.number="form.appeal_deadline_hours" class="form-input" type="number" min="1" max="168" required>
                                    <FormError :message="form.errors.appeal_deadline_hours" />
                                    <p class="mt-2 text-xs text-slate-500">Valor inicial aprobado: 2 horas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="premium-card">
                    <div class="premium-card__sidebar">
                        <div class="step-badge">3</div>
                        <div class="sidebar-icon"><AppIcon name="wallet" variant="illustrated" :size="42" /></div>
                    </div>
                    <div class="premium-card__content">
                        <div class="section-header">
                            <div>
                                <h2 class="section-title">Pagos, reactivación y fianza</h2>
                                <p class="section-description">Establece criterios administrativos para prórrogas de pago, suspensión de acceso y garantías económicas.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                            <div class="feature-panel">
                                <label class="toggle-row">
                                    <input v-model="paymentGraceEnabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-league-600 focus:ring-league-400">
                                    <div>
                                        <span class="toggle-title">Aplicar prórroga de pago</span>
                                        <span class="toggle-description">Habilita días adicionales antes de considerar incumplimiento administrativo.</span>
                                    </div>
                                </label>

                                <div v-if="paymentGraceEnabled" class="mt-5 field-card">
                                    <label class="form-label">Días de prórroga</label>
                                    <input v-model.number="form.payment_grace_days" class="form-input" type="number" min="0" max="365">
                                    <FormError :message="form.errors.payment_grace_days" />
                                </div>

                                <div class="mt-5 border-t border-slate-200 pt-5">
                                    <label class="toggle-row">
                                        <input v-model="form.bond_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-league-600 focus:ring-league-400">
                                        <div>
                                            <span class="toggle-title">La liga requiere fianza</span>
                                            <span class="toggle-description">Activa una garantía económica para respaldar registros, sanciones o compromisos administrativos.</span>
                                        </div>
                                    </label>
                                </div>

                                <div v-if="form.bond_enabled" class="mt-5 grid gap-5 md:grid-cols-2">
                                    <div class="field-card">
                                        <label class="form-label">Monto de la fianza</label>
                                        <input v-model="form.bond_amount" class="form-input" type="number" min="0" max="9999999.99" step="0.01" required>
                                        <FormError :message="form.errors.bond_amount" />
                                    </div>
                                    <div class="field-card">
                                        <label class="form-label">Moneda</label>
                                        <select v-model="form.currency" class="form-input">
                                            <option value="MXN">MXN — Peso mexicano</option>
                                        </select>
                                        <FormError :message="form.errors.currency" />
                                    </div>
                                </div>
                            </div>

                            <div class="feature-panel">
                                <div class="field-card">
                                    <label class="form-label">Días para reincorporarse después de la suspensión</label>
                                    <input v-model.number="form.reactivation_window_days" class="form-input" type="number" min="1" max="365" required>
                                    <FormError :message="form.errors.reactivation_window_days" />
                                    <p class="mt-2 text-xs text-slate-500">Valor inicial: 21 días, equivalentes a 3 semanas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="premium-card premium-card--compact">
                    <div class="premium-card__sidebar">
                        <div class="step-badge">4</div>
                        <div class="sidebar-icon"><AppIcon name="file" variant="illustrated" :size="42" /></div>
                    </div>
                    <div class="premium-card__content">
                        <div class="section-header">
                            <div>
                                <h2 class="section-title">Motivo de la configuración o modificación</h2>
                                <p class="section-description">Este texto se utilizará como respaldo administrativo de la actualización.</p>
                            </div>
                        </div>

                        <div class="mt-5 field-card">
                            <textarea v-model="form.reason" class="form-input min-h-28" maxlength="500" required placeholder="Ejemplo: Parámetros aprobados para la temporada 2027"></textarea>
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
                                <span>Describe el motivo de forma breve y clara.</span>
                                <span>{{ form.reason.length }}/500</span>
                            </div>
                            <FormError :message="form.errors.reason" />
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button class="btn-primary inline-flex min-h-12 items-center justify-center gap-2 px-5 py-3" type="submit" :disabled="form.processing">
                                <AppIcon name="check" :size="16" />
                                {{ form.processing ? 'Guardando…' : 'Guardar parámetros' }}
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.header-icon-shell {
    @apply grid h-16 w-16 shrink-0 place-items-center rounded-2xl border border-white/80 bg-white/90 shadow-sm shadow-slate-900/5;
}

.notice-card {
    @apply flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-sm;
}

.notice-card--warning {
    @apply border-amber-200 bg-amber-50 text-amber-900;
}

.notice-card--info {
    @apply border-sky-200 bg-sky-50 text-sky-900;
}

.premium-card {
    display: grid;
    gap: 0;
    border-radius: 1.75rem;
    border: 1px solid rgba(255, 255, 255, 0.85);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.premium-card--compact {
    align-items: start;
}

.premium-card__sidebar {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem 1.25rem 0;
}

.premium-card__content {
    padding: 0 1.25rem 1.25rem;
}

.step-badge {
    @apply grid h-9 w-9 shrink-0 place-items-center rounded-full bg-league-50 text-sm font-extrabold text-league-700;
}

.sidebar-icon {
    @apply grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-white shadow-sm;
}

.section-header {
    @apply flex flex-col gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-start md:justify-between;
}

.section-title {
    @apply text-2xl font-bold tracking-tight text-slate-900;
}

.section-description {
    @apply mt-2 max-w-3xl text-sm leading-6 text-slate-600;
}

.section-pill {
    @apply inline-flex w-fit flex-col rounded-2xl border border-league-100 bg-league-50/60 px-4 py-3 text-right;
}

.section-pill strong {
    @apply text-xl font-bold text-league-900;
}

.section-pill span {
    @apply text-xs font-semibold uppercase tracking-[0.14em] text-league-700/75;
}

.field-card {
    @apply rounded-2xl border border-slate-200 bg-white p-4 shadow-sm;
}

.feature-panel {
    @apply rounded-[1.35rem] border border-slate-200 bg-slate-50/70 p-4 sm:p-5;
}

.toggle-row {
    @apply flex items-start gap-3 text-sm;
}

.toggle-title {
    @apply block font-semibold text-slate-900;
}

.toggle-description {
    @apply mt-1 block text-sm leading-6 text-slate-500;
}

.empty-note {
    @apply rounded-2xl border border-dashed border-slate-300 bg-white/70 px-4 py-4 text-sm text-slate-500;
}

.summary-band {
    @apply flex items-start gap-3 rounded-2xl border border-league-100 bg-league-50/60 px-4 py-4;
}

.summary-band__icon {
    @apply mt-0.5 shrink-0 text-league-700;
}

@media (min-width: 900px) {
    .premium-card {
        grid-template-columns: 6.25rem minmax(0, 1fr);
    }

    .premium-card__sidebar {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 1.4rem 0 1.4rem 1.4rem;
    }

    .premium-card__content {
        padding: 1.4rem 1.4rem 1.4rem 0;
    }
}
</style>
