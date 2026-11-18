<script setup lang="ts">
import AppIcon from '../../../components/AppIcon.vue';
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
const activeSection = ref('duracion');
const sectionLinks = [
    { id: 'duracion', label: 'Duración del partido', icon: 'clock' },
    { id: 'programacion', label: 'Programación y apelaciones', icon: 'calendar' },
    { id: 'finanzas', label: 'Pagos y reactivación', icon: 'wallet' },
    { id: 'registro', label: 'Registro del cambio', icon: 'file' },
];

watch(bufferEnabled, (enabled) => {
    form.schedule_buffer_minutes = enabled ? (form.schedule_buffer_minutes ?? 15) : null;
});
watch(paymentGraceEnabled, (enabled) => {
    form.payment_grace_days = enabled ? (form.payment_grace_days ?? 0) : null;
});
watch(() => form.bond_enabled, (enabled) => {
    if (!enabled) form.bond_amount = null;
    else if (form.bond_amount === null) form.bond_amount = '0';
});

const periods = computed(() => Math.max(1, Math.min(4, Number(form.match_periods) || 1)));
const periodMinutes = computed(() => Math.max(0, Number(form.period_duration_minutes) || 0));
const breakMinutes = computed(() => Math.max(0, Number(form.halftime_minutes) || 0));
const estimatedDuration = computed(() => (periods.value * periodMinutes.value) + ((periods.value - 1) * breakMinutes.value));
const timeline = computed(() => {
    const result: { label: string; minutes: number; rest: boolean; key: string }[] = [];
    for (let i = 1; i <= periods.value; i++) {
        result.push({ label: `${i}.º tiempo · ${periodMinutes.value} min`, minutes: periodMinutes.value, rest: false, key: `time-${i}` });
        if (i < periods.value && breakMinutes.value > 0) result.push({ label: `Descanso · ${breakMinutes.value} min`, minutes: breakMinutes.value, rest: true, key: `break-${i}` });
    }
    return result;
});

function navigateTo(id: string) {
    activeSection.value = id;
    document.getElementById(`op-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function discard() {
    form.reset();
    form.clearErrors();
    bufferEnabled.value = props.settings.schedule_buffer_minutes !== null;
    paymentGraceEnabled.value = props.settings.payment_grace_days !== null;
}
function save() {
    form.put('/liga/parametros', {
        preserveScroll: true,
        onSuccess: () => {
            // Inertia updates props from the backend; keep current inputs as the new baseline.
            form.defaults();
            form.reason = '';
            form.defaults('reason', '');
        },
    });
}
</script>

<template>
    <Head title="Parámetros generales" />
    <AppLayout>
        <div class="operational-page mx-auto max-w-[1440px] pb-8">
            <header class="operational-hero">
                <div class="operational-hero-content">
                    <div class="op-hero-icon"><AppIcon name="sliders" :size="29" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="op-eyebrow">Configuración · Reglas base</p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Parámetros generales</h1>
                        <p class="mt-2 max-w-3xl text-sm text-white/85">Define los valores predeterminados para la programación, apelaciones y condiciones administrativas de esta liga.</p>
                    </div>
                    <div class="op-hero-context hidden items-center gap-3 lg:flex">
                        <AppIcon name="settings" :size="20" />
                        <span>Configuración de la liga</span>
                        <span class="op-hero-status"><span class="op-dot"></span>{{ isConfigured ? 'Activa' : 'Por configurar' }}</span>
                    </div>
                </div>
            </header>

            <div v-if="!isConfigured" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                La duración del partido todavía no ha sido definida. Guarda este formulario antes de crear competencias.
            </div>
            <div class="op-notice mt-4" role="note">
                <AppIcon name="badge" :size="19" />
                <p>Cuando se cree un torneo, sus reglas copiarán estos valores y quedarán versionadas. Los cambios no modificarán torneos históricos.</p>
            </div>

            <form class="mt-4" @submit.prevent="save">
                <div class="op-main-shell">
                    <aside class="op-sidebar" aria-label="Categorías de parámetros">
                        <p class="mb-4 px-3 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Secciones</p>
                        <nav class="flex gap-2 overflow-x-auto lg:flex-col" aria-label="Navegación de secciones">
                            <button v-for="item in sectionLinks" :key="item.id" type="button" class="op-nav-item" :class="{ 'op-nav-item--active': activeSection === item.id }" @click="navigateTo(item.id)">
                                <AppIcon :name="item.icon" :size="19" />
                                <span>{{ item.label }}</span>
                            </button>
                        </nav>
                        <div class="op-sidebar-note hidden lg:block">
                            <AppIcon name="shield" :size="19" />
                            <p>Los cambios se aplican a nuevas competencias; el historial permanece protegido.</p>
                        </div>
                    </aside>

                    <div class="op-content">
                        <section id="op-duracion" class="op-section" @mouseenter="activeSection = 'duracion'">
                            <div class="op-section-head">
                                <span class="op-section-icon"><AppIcon name="clock" :size="21" /></span>
                                <div class="flex-1">
                                    <h2>Duración del partido</h2>
                                    <p>Define la estructura del encuentro y su duración estimada para la agenda deportiva.</p>
                                </div>
                                <div class="op-total" aria-live="polite">{{ estimatedDuration }} minutos estimados</div>
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="match_periods" class="form-label">Número de tiempos</label>
                                    <select id="match_periods" v-model.number="form.match_periods" class="form-input" required>
                                        <option v-for="n in 4" :key="n" :value="n">{{ n }}</option>
                                    </select>
                                    <FormError :message="form.errors.match_periods" />
                                </div>
                                <div>
                                    <label for="period_duration_minutes" class="form-label">Minutos por tiempo</label>
                                    <input id="period_duration_minutes" v-model.number="form.period_duration_minutes" class="form-input" type="number" min="5" max="120" required />
                                    <FormError :message="form.errors.period_duration_minutes" />
                                </div>
                                <div>
                                    <label for="halftime_minutes" class="form-label">Descanso (min)</label>
                                    <input id="halftime_minutes" v-model.number="form.halftime_minutes" class="form-input" type="number" min="0" max="60" placeholder="0" />
                                    <FormError :message="form.errors.halftime_minutes" />
                                </div>
                            </div>
                            <div class="op-timeline mt-4" role="img" :aria-label="`${periods} tiempos de ${periodMinutes} minutos y ${breakMinutes} minutos de descanso entre tiempos. Total ${estimatedDuration} minutos`">
                                <div v-for="segment in timeline" :key="segment.key" class="op-timeline-part" :class="segment.rest ? 'op-timeline-part--rest' : ''" :style="{ flexGrow: Math.max(segment.minutes, 1) }">
                                    <span>{{ segment.label }}</span>
                                </div>
                            </div>
                            <p class="op-helper mt-3"><AppIcon name="badge" :size="16" /> {{ periods }} {{ periods === 1 ? 'tiempo' : 'tiempos' }} de {{ periodMinutes }} min<span v-if="periods > 1"> + {{ periods - 1 }} {{ periods === 2 ? 'descanso' : 'descansos' }} de {{ breakMinutes }} min</span>.</p>
                        </section>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <section id="op-programacion" class="op-section" @mouseenter="activeSection = 'programacion'">
                                <div class="op-section-head">
                                    <span class="op-section-icon"><AppIcon name="calendar" :size="21" /></span>
                                    <div>
                                        <h2>Programación y apelaciones</h2>
                                        <p>Ajusta los tiempos operativos entre partidos y el plazo para presentar apelaciones.</p>
                                    </div>
                                </div>
                                <div class="op-field-row mt-5">
                                    <label class="op-check-row" for="buffer-toggle">
                                        <input id="buffer-toggle" v-model="bufferEnabled" type="checkbox" class="op-check" />
                                        <span><strong>Usar margen entre partidos</strong><small>Añade tiempo de separación para logística y descanso.</small></span>
                                    </label>
                                    <div class="min-w-[125px]">
                                        <label for="buffer-minutes" class="form-label">Margen (min)</label>
                                        <input id="buffer-minutes" v-model.number="form.schedule_buffer_minutes" class="form-input" type="number" min="0" max="240" :disabled="!bufferEnabled" placeholder="Sin margen" />
                                        <FormError :message="form.errors.schedule_buffer_minutes" />
                                    </div>
                                </div>
                                <div class="op-field-row op-field-row--bottom">
                                    <div class="flex-1">
                                        <label for="appeal" class="form-label">Plazo de apelación (horas)</label>
                                        <input id="appeal" v-model.number="form.appeal_deadline_hours" class="form-input" type="number" min="1" max="168" required />
                                        <FormError :message="form.errors.appeal_deadline_hours" />
                                    </div>
                                    <p class="op-muted-help"><AppIcon name="clock" :size="17" /> Tiempo máximo para presentar apelaciones.</p>
                                </div>
                            </section>

                            <section id="op-finanzas" class="op-section" @mouseenter="activeSection = 'finanzas'">
                                <div class="op-section-head">
                                    <span class="op-section-icon"><AppIcon name="wallet" :size="21" /></span>
                                    <div>
                                        <h2>Pagos, reactivación y fianza</h2>
                                        <p>Configura las prórrogas de pago, la reactivación y las garantías económicas.</p>
                                    </div>
                                </div>
                                <div class="op-field-row mt-5">
                                    <label class="op-check-row" for="grace-toggle">
                                        <input id="grace-toggle" v-model="paymentGraceEnabled" type="checkbox" class="op-check" />
                                        <span><strong>Aplicar prórroga de pago</strong><small>Otorga días adicionales antes del vencimiento administrativo.</small></span>
                                    </label>
                                    <div class="min-w-[125px]">
                                        <label for="grace" class="form-label">Prórroga (días)</label>
                                        <input id="grace" v-model.number="form.payment_grace_days" class="form-input" type="number" min="0" max="365" :disabled="!paymentGraceEnabled" placeholder="No aplica" />
                                        <FormError :message="form.errors.payment_grace_days" />
                                    </div>
                                </div>
                                <div class="op-field-row">
                                    <label class="op-check-row" for="bond-toggle">
                                        <input id="bond-toggle" v-model="form.bond_enabled" type="checkbox" class="op-check" />
                                        <span><strong>La liga requiere fianza</strong><small>Solicita una garantía para respaldar compromisos administrativos.</small></span>
                                    </label>
                                    <div class="min-w-[125px]">
                                        <label for="bond-amount" class="form-label">Monto ({{ form.currency }})</label>
                                        <input id="bond-amount" v-model="form.bond_amount" class="form-input" type="number" min="0" max="9999999.99" step="0.01" :disabled="!form.bond_enabled" :required="form.bond_enabled" placeholder="0.00" />
                                        <FormError :message="form.errors.bond_amount" />
                                        <FormError :message="form.errors.currency" />
                                    </div>
                                </div>
                                <div class="op-field-row op-field-row--bottom">
                                    <div class="flex-1">
                                        <label for="reactivation" class="form-label">Días para reincorporarse después de la suspensión</label>
                                        <input id="reactivation" v-model.number="form.reactivation_window_days" class="form-input" type="number" min="1" max="365" required />
                                        <FormError :message="form.errors.reactivation_window_days" />
                                    </div>
                                    <p class="op-muted-help">Plazo administrativo de reincorporación.</p>
                                </div>
                            </section>
                        </div>

                        <section id="op-registro" class="op-section" @mouseenter="activeSection = 'registro'">
                            <div class="op-section-head">
                                <span class="op-section-icon"><AppIcon name="file" :size="21" /></span>
                                <div>
                                    <h2>Motivo de la configuración o modificación</h2>
                                    <p>Este texto quedará registrado como respaldo administrativo de la actualización.</p>
                                </div>
                            </div>
                            <label for="reason" class="sr-only">Motivo de la configuración o modificación</label>
                            <textarea id="reason" v-model="form.reason" class="form-input mt-4 min-h-24" maxlength="500" required placeholder="Describe brevemente el motivo de esta actualización..."></textarea>
                            <div class="mt-1 flex items-start justify-between gap-3">
                                <FormError :message="form.errors.reason" />
                                <span class="ml-auto text-xs text-slate-500">{{ form.reason.length }} / 500</span>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="op-save-bar" aria-live="polite">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <AppIcon :name="form.isDirty ? 'badge' : 'check'" :size="21" />
                        <div>
                            <p class="font-semibold">{{ form.isDirty ? 'Tienes cambios sin guardar' : 'Configuración sin cambios pendientes' }}</p>
                            <p class="hidden text-xs text-slate-600 md:block">Los nuevos parámetros se aplicarán al guardar la personalización de esta liga.</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="op-discard" :disabled="!form.isDirty || form.processing" @click="discard">Descartar</button>
                        <button type="submit" class="op-submit" :disabled="!form.isDirty || form.processing">
                            <AppIcon name="check" :size="18" />
                            {{ form.processing ? 'Guardando…' : 'Guardar parámetros' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.operational-page { --op-primary: var(--color-league-600, #2e7d32); --op-deep: var(--color-league-900, #1b2a4a); --op-light: var(--color-league-50, #eff8f0); }
.operational-hero { overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,.36); border-radius: 25px; background: linear-gradient(101deg, color-mix(in srgb,var(--op-deep) 92%, #092951) 0%, color-mix(in srgb,var(--op-primary) 57%, #163359) 68%, color-mix(in srgb,var(--op-primary) 47%, #0c2648) 100%),url('/images/public/league-stadium.jpg') center 52%/cover; box-shadow: 0 17px 32px rgba(13,31,62,.14); }
.operational-hero::after { content:''; position:absolute; inset:0; pointer-events:none; background: linear-gradient(120deg,transparent 42%,rgba(255,255,255,.07) 61%,transparent 61%); }
.operational-hero-content { position:relative; z-index:1; display:flex; align-items:center; gap:20px; padding:25px 28px; }
.op-hero-icon { color:white; width:62px; height:62px; border-radius:16px; border:1px solid rgba(255,255,255,.27); background:rgba(255,255,255,.14); box-shadow:inset 0 1px 1px rgba(255,255,255,.15); display:grid; place-items:center; flex-shrink:0; }
.op-eyebrow { text-transform:uppercase; font-size:11px; letter-spacing:.17em; font-weight:800; color:#ffffffd9; }
.op-hero-context { padding:12px 14px; background:rgba(0,0,0,.14); border:1px solid rgba(255,255,255,.31); border-radius:14px; color:white; font-weight:650; font-size:12px; }
.op-hero-status { display:flex; align-items:center; gap:7px; border-radius:999px; padding:4px 10px; background:rgba(255,255,255,.2); }
.op-dot { background:#b9f6c6; height:7px; width:7px; border-radius:50%; }
.op-notice { display:flex; align-items:flex-start; gap:11px; border:1px solid #a6cef9; border-radius:13px; padding:12px 17px; background:#edf6ff; color:#164679; font-size:13px; }
.op-notice svg { flex-shrink:0; }
.op-main-shell { display:grid; grid-template-columns:240px minmax(0,1fr); border:1px solid #d9e2ee; border-radius:23px; background:#ffffffee; box-shadow:0 12px 30px rgba(20,43,78,.08); overflow:hidden; }
.op-sidebar { border-right:1px solid #dce5ef; background:linear-gradient(180deg,#fcfdff,#f6f9fd); padding:22px 13px; }
.op-sidebar nav { position:sticky; top:18px; }
.op-nav-item { display:flex; align-items:center; gap:12px; text-align:left; width:100%; flex-shrink:0; padding:12px; border-radius:12px; color:#304663; font-weight:650; font-size:13px; transition:.18s ease; }
.op-nav-item:hover { background:#eaf1fa; }
.op-nav-item--active { background:color-mix(in srgb,var(--op-primary) 10%,white); color:var(--op-primary); box-shadow:inset 3px 0 var(--op-primary); }
.op-sidebar-note { margin:40px 10px 0; border-top:1px solid #d9e2ee; padding-top:16px; color:#64748b; font-size:12px; line-height:1.5; }
.op-sidebar-note svg { color:var(--op-primary); margin-bottom:6px; }
.op-content { padding:14px; display:flex; flex-direction:column; gap:14px; min-width:0; }
.op-section { min-width:0; border:1px solid #dbe4f0; border-radius:18px; background:#fff; padding:19px; scroll-margin-top:110px; }
.op-section-head { display:flex; align-items:center; gap:13px; padding-bottom:13px; border-bottom:1px solid #e5eaf1; }
.op-section-head h2 { font-size:16px; font-weight:800; color:#14243e; line-height:1.25; }
.op-section-head p { font-size:12px; color:#62748d; margin-top:3px; line-height:1.5; }
.op-section-icon { height:39px; width:39px; border-radius:11px; background:color-mix(in srgb,var(--op-primary) 13%,white); color:var(--op-primary); display:grid; place-items:center; flex-shrink:0; }
.op-total { margin-left:auto; flex-shrink:0; border-radius:11px; padding:8px 12px; background:color-mix(in srgb,var(--op-primary) 10%,white); border:1px solid color-mix(in srgb,var(--op-primary) 27%,white); color:var(--op-primary); font-weight:800; font-size:14px; }
.op-timeline { display:flex; gap:3px; width:100%; min-height:34px; overflow:hidden; border-radius:9px; }
.op-timeline-part { flex-basis:0; min-width:0; background:color-mix(in srgb,var(--op-primary) 14%,#e8f2ff); color:var(--op-deep); display:flex; align-items:center; justify-content:center; text-align:center; padding:5px 4px; font-size:11px; font-weight:700; }
.op-timeline-part--rest { background:#fff0d8; color:#9b5709; }
.op-helper { display:flex; align-items:center; gap:8px; font-size:12px; color:#64748b; }
.op-field-row { display:flex; align-items:center; gap:15px; padding:11px 0; border-bottom:1px solid #e6ecf3; }
.op-field-row--bottom { border-bottom:0; padding-bottom:0; }
.op-check-row { display:flex; flex:1; align-items:flex-start; gap:12px; cursor:pointer; min-width:0; }
.op-check-row strong { display:block; color:#192c48; font-size:13px; line-height:1.4; }
.op-check-row small { display:block; color:#667791; font-size:11px; line-height:1.5; margin-top:3px; }
.op-check { accent-color:var(--op-primary); flex-shrink:0; margin-top:3px; width:18px; height:18px; cursor:pointer; }
.op-muted-help { width:38%; display:flex; align-items:center; gap:7px; color:#63758d; font-size:11px; }
.operational-page :deep(.form-label) { font-size:12px; font-weight:700; color:#304462; margin-bottom:6px; }
.operational-page :deep(.form-input) { width:100%; background:white; font-size:13px; border-radius:10px; border-color:#cad6e6; }
.operational-page :deep(.form-input:disabled) { background:#f3f6fa; color:#94a3b8; cursor:not-allowed; }
.op-save-bar { position:sticky; bottom:12px; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:15px; margin-top:-1px; padding:13px 18px; border:1px solid color-mix(in srgb,var(--op-primary) 21%,#dce5f0); background:rgba(255,255,255,.98); border-radius:0 0 18px 18px; box-shadow:0 10px 25px rgba(15,32,58,.15); color:#193450; font-size:13px; backdrop-filter:blur(10px); }
.op-save-bar > div > svg { color:var(--op-primary); flex-shrink:0; }
.op-discard,.op-submit { display:inline-flex; gap:8px; justify-content:center; align-items:center; min-height:40px; border-radius:10px; padding:9px 17px; font-size:13px; font-weight:700; transition:.18s ease; }
.op-discard { color:#253a56; background:#fff; border:1px solid #c7d3e4; }
.op-discard:hover:not(:disabled) { background:#f1f5fa; }
.op-submit { color:white; background:var(--op-primary); border:1px solid color-mix(in srgb,var(--op-primary) 80%,#000); box-shadow:0 3px 8px color-mix(in srgb,var(--op-primary) 20%,transparent); }
.op-submit:hover:not(:disabled) { filter:brightness(1.08); }
.op-discard:disabled,.op-submit:disabled { opacity:.52; cursor:not-allowed; }
@media(max-width:1023px) { .op-main-shell { grid-template-columns:1fr; } .op-sidebar { border-right:0; border-bottom:1px solid #e0e7f0; padding:15px; } .op-sidebar nav { position:static; } .op-nav-item { width:auto; } }
@media(max-width:720px) { .operational-hero-content { padding:20px; gap:12px; } .op-hero-icon { width:46px; height:46px; } .op-content { padding:10px; } .op-section { padding:14px; } .op-section-head { flex-wrap:wrap; } .op-total { margin-left:0; } .op-field-row { flex-wrap:wrap; } .op-muted-help { width:100%; } .op-save-bar { flex-wrap:wrap; bottom:0; border-radius:12px; } .op-save-bar>div:last-child { width:100%; } .op-save-bar button { flex:1; } }
</style>
