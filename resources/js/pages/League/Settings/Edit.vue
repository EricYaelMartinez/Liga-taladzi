<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { LeagueSummary } from '../../../types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ league: LeagueSummary }>();
const form = useForm({
    name: props.league.name,
    primary_color: props.league.primary_color,
    secondary_color: props.league.secondary_color,
    logo: null as File | null,
    remove_logo: false,
    credential_logo_1: null as File | null,
    credential_logo_2: null as File | null,
    credential_logo_3: null as File | null,
    credential_logo_4: null as File | null,
    remove_credential_logo_1: false,
    remove_credential_logo_2: false,
    remove_credential_logo_3: false,
    remove_credential_logo_4: false,
    reason: '',
});

type LogoField = 'logo' | 'credential_logo_1' | 'credential_logo_2' | 'credential_logo_3' | 'credential_logo_4';
type Removable = 'remove_logo' | 'remove_credential_logo_1' | 'remove_credential_logo_2' | 'remove_credential_logo_3' | 'remove_credential_logo_4';
const credentialPositions = [1, 2, 3, 4] as const;
const credentialKey = (n: number): LogoField => `credential_logo_${n}` as LogoField;
const credentialError = (n: number): string | undefined => form.errors[credentialKey(n)];
const previews = reactive<Record<LogoField, string | null>>({ logo: null, credential_logo_1: null, credential_logo_2: null, credential_logo_3: null, credential_logo_4: null });
const fileInputKeys = reactive<Record<LogoField, number>>({ logo: 0, credential_logo_1: 0, credential_logo_2: 0, credential_logo_3: 0, credential_logo_4: 0 });
const selectedNames = reactive<Record<LogoField, string>>({ logo: '', credential_logo_1: '', credential_logo_2: '', credential_logo_3: '', credential_logo_4: '' });
const fileError = ref('');
const imageUrl = (path: string | null | undefined) => path ? `/storage/${path.replace(/^\/+/, '')}` : null;
const oldLogo = (key: LogoField): string | null => key === 'logo' ? imageUrl(props.league.logo_path) : imageUrl(props.league[`${key}_path` as keyof LeagueSummary] as string | null);
const currentImage = (key: LogoField): string | null => {
    const removing = form[`remove_${key}` as Removable];
    return previews[key] ?? (removing ? null : oldLogo(key));
};
const chooseFile = (key: LogoField, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    fileError.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
        fileError.value = 'Selecciona una imagen JPG, PNG o WebP de máximo 2 MB.';
        (event.target as HTMLInputElement).value = '';
        return;
    }
    if (previews[key]) URL.revokeObjectURL(previews[key]!);
    previews[key] = URL.createObjectURL(file);
    selectedNames[key] = file.name;
    form[key] = file;
    form[`remove_${key}` as Removable] = false;
};
const removeImage = (key: LogoField) => {
    if (previews[key]) URL.revokeObjectURL(previews[key]!);
    previews[key] = null;
    selectedNames[key] = '';
    form[key] = null;
    form[`remove_${key}` as Removable] = Boolean(oldLogo(key));
    fileInputKeys[key]++;
};
const formColor = (value: string) => /^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#1B2A4A';
const mainColor = computed(() => formColor(form.primary_color));
const accentColor = computed(() => formColor(form.secondary_color));
const previewBackground = computed(() => ({ background: `linear-gradient(110deg, ${mainColor.value} 0%, ${mainColor.value} 38%, ${accentColor.value} 100%)` }));
const save = () => form.post('/liga/configuracion', { forceFormData: true, preserveScroll: true });
onBeforeUnmount(() => Object.values(previews).forEach((url) => { if (url) URL.revokeObjectURL(url); }));
</script>

<template>
    <Head title="Identidad de la liga" />
    <AppLayout>
        <section class="mx-auto max-w-5xl pb-12">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-league-700">Personalización de liga</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Identidad de la liga</h1>
                    <p class="mt-2 text-sm text-slate-600">Personaliza la identidad visual que verán los integrantes de tu liga.</p>
                </div>
                <span class="rounded-full border border-league-100 bg-league-50 px-4 py-2 text-xs font-bold text-league-700">Configuración de marca</span>
            </div>

            <form class="space-y-5" @submit.prevent="save">
                <section class="identity-panel">
                    <div class="panel-heading"><div><p class="panel-eyebrow">Datos institucionales</p><h2 class="panel-title">Información básica y logotipo principal</h2></div><AppIcon name="building" variant="illustrated" :size="43" /></div>
                    <div class="grid gap-6 lg:grid-cols-[1fr_1.08fr]">
                        <div class="space-y-5">
                            <div><label class="form-label" for="league-name">Nombre público <span class="form-required">*</span></label><input id="league-name" v-model="form.name" class="form-input" maxlength="150" required placeholder="Nombre oficial de la liga" /><FormError :message="form.errors.name" /></div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">Este nombre se utilizará para identificar la liga en las pantallas y documentos del sistema.</div>
                        </div>
                        <div>
                            <label class="form-label" for="logo-principal">Logotipo principal</label>
                            <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                <div class="logo-stage">
                                    <img v-if="currentImage('logo')" :src="currentImage('logo')!" alt="Vista previa del logotipo principal" class="h-full w-full object-contain" />
                                    <AppIcon v-else name="shield" :size="44" class="text-slate-400" />
                                </div>
                                <div class="min-w-0 flex-1 space-y-2">
                                    <label for="logo-principal" class="file-button">Seleccionar logotipo</label>
                                    <input :key="fileInputKeys.logo" id="logo-principal" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseFile('logo', $event)" />
                                    <p class="break-all text-xs text-slate-500">{{ selectedNames.logo || 'JPG, PNG o WebP · Máximo 2 MB' }}</p>
                                    <button v-if="currentImage('logo')" type="button" class="remove-link" @click="removeImage('logo')">Quitar logotipo</button>
                                </div>
                            </div>
                            <FormError :message="form.errors.logo" />
                        </div>
                    </div>
                </section>

                <section class="identity-panel">
                    <div class="panel-heading"><div><p class="panel-eyebrow">Identificación oficial</p><h2 class="panel-title">Logotipos para credenciales</h2><p class="mt-1 text-sm text-slate-500">Configura hasta cuatro imágenes utilizadas en las credenciales de los jugadores.</p></div><AppIcon name="id" variant="illustrated" :size="43" /></div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="n in credentialPositions" :key="n" class="credential-tile">
                            <span class="credential-number">{{ n }}</span>
                            <div class="my-3 grid h-24 place-items-center">
                                <img v-if="currentImage(credentialKey(n))" :src="currentImage(credentialKey(n))!" :alt="`Logotipo ${n}`" class="h-full max-w-full object-contain" />
                                <AppIcon v-else name="shield" variant="illustrated" :size="65" class="opacity-40" />
                            </div>
                            <label :for="`credential-${n}`" class="file-button w-full">{{ currentImage(credentialKey(n)) ? 'Cambiar imagen' : 'Subir imagen' }}</label>
                            <input :id="`credential-${n}`" :key="fileInputKeys[credentialKey(n)]" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseFile(credentialKey(n), $event)" />
                            <p v-if="selectedNames[credentialKey(n)]" class="mt-2 truncate text-center text-xs text-slate-500" :title="selectedNames[credentialKey(n)]">{{ selectedNames[credentialKey(n)] }}</p>
                            <button v-if="currentImage(credentialKey(n))" type="button" class="remove-link mt-2" @click="removeImage(credentialKey(n))">Eliminar imagen</button>
                            <FormError :message="credentialError(n)" />
                        </div>
                    </div>
                </section>

                <section class="identity-panel">
                    <div class="panel-heading"><div><p class="panel-eyebrow">Identidad visual</p><h2 class="panel-title">Paleta de colores</h2><p class="mt-1 text-sm text-slate-500">Los colores seleccionados se aplicarán al diseño de tu liga.</p></div><AppIcon name="palette" variant="illustrated" :size="43" /></div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div><label class="form-label" for="primary-color">Color principal</label><div class="color-field"><input id="primary-color" v-model="form.primary_color" type="color" class="color-picker" aria-label="Elegir color principal" /><input v-model="form.primary_color" class="form-input font-mono uppercase" maxlength="7" placeholder="#1B2A4A" /></div><FormError :message="form.errors.primary_color" /></div>
                        <div><label class="form-label" for="secondary-color">Color secundario</label><div class="color-field"><input id="secondary-color" v-model="form.secondary_color" type="color" class="color-picker" aria-label="Elegir color secundario" /><input v-model="form.secondary_color" class="form-input font-mono uppercase" maxlength="7" placeholder="#C5A059" /></div><FormError :message="form.errors.secondary_color" /></div>
                    </div>
                    <div class="mt-7"><h3 class="text-sm font-bold text-slate-900">Vista previa de marca</h3><p class="mb-3 mt-1 text-xs text-slate-500">Previsualización inmediata antes de guardar los cambios.</p>
                        <div class="brand-preview" :style="previewBackground"><div class="relative z-10 flex min-w-0 items-center gap-4"><div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl border border-white/30 bg-white/95 p-2 shadow-lg"><img v-if="currentImage('logo')" :src="currentImage('logo')!" alt="Logotipo de la liga" class="h-full w-full object-contain" /><AppIcon v-else name="shield" :size="33" class="text-slate-500" /></div><div class="min-w-0"><p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/75">Vista previa</p><strong class="mt-1 block break-words text-lg font-bold text-white sm:text-2xl">{{ form.name || 'Nombre de la liga' }}</strong><p class="mt-1 text-xs text-white/80">Identidad institucional</p></div></div><AppIcon name="trophy" :size="48" class="relative z-10 hidden text-white/70 sm:block" /></div>
                    </div>
                    <div class="mt-7"><label class="form-label" for="identity-reason">Motivo del cambio <span class="form-required">*</span></label><textarea id="identity-reason" v-model="form.reason" class="form-input min-h-28" required maxlength="500" placeholder="Describe brevemente por qué se realiza esta actualización de identidad visual."></textarea><div class="mt-1 flex justify-between gap-4 text-xs text-slate-500"><span>Este motivo quedará registrado para seguimiento.</span><span>{{ form.reason.length }}/500</span></div><FormError :message="form.errors.reason" /></div>
                    <p v-if="fileError" role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ fileError }}</p>
                    <div class="mt-7 flex justify-end border-t border-slate-100 pt-5"><button type="submit" class="btn-primary inline-flex min-h-11 items-center justify-center gap-2 px-6" :disabled="form.processing"><AppIcon name="check" :size="18" />{{ form.processing ? 'Guardando…' : 'Guardar personalización' }}</button></div>
                </section>
            </form>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.identity-panel { @apply rounded-[1.5rem] border border-white/80 bg-white/95 p-5 shadow-xl shadow-slate-900/5 backdrop-blur sm:p-7; }
.panel-heading { @apply mb-6 flex items-start justify-between gap-4 border-b border-slate-100 pb-5; }
.panel-eyebrow { @apply text-[11px] font-extrabold uppercase tracking-[0.2em] text-league-600; }
.panel-title { @apply mt-1 text-xl font-bold text-slate-900; }
.logo-stage { @apply grid h-28 w-28 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-white p-3 shadow-sm; }
.file-button { @apply inline-flex min-h-10 cursor-pointer items-center justify-center rounded-xl border border-league-100 bg-white px-3 py-2 text-center text-xs font-bold text-league-700 shadow-sm transition hover:bg-league-50; }
.remove-link { @apply block cursor-pointer text-xs font-semibold text-rose-600 hover:underline; }
.credential-tile { @apply relative flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-slate-50/70 p-4; }
.credential-number { @apply absolute left-3 top-3 rounded-lg bg-league-100 px-2 py-1 text-xs font-extrabold text-league-700; }
.color-field { @apply flex items-center gap-3; }
.color-picker { @apply h-12 w-16 shrink-0 cursor-pointer rounded-xl border border-slate-200 bg-white p-1; }
.brand-preview { @apply relative flex min-h-36 items-center justify-between gap-4 overflow-hidden rounded-2xl px-5 py-6 shadow-lg sm:px-7; }
.brand-preview::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 85% 30%, rgba(255,255,255,.20), transparent 50%); }
</style>
