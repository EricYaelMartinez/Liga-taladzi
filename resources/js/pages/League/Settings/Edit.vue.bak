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
const dragTarget = ref<LogoField | null>(null);
const fileMeta = reactive<Record<LogoField, string>>({ logo: '', credential_logo_1: '', credential_logo_2: '', credential_logo_3: '', credential_logo_4: '' });
const contrastWarnings = computed(() => ({
    primary: contrastRatio(mainColor.value, '#FFFFFF'),
    secondary: contrastRatio(accentColor.value, '#FFFFFF'),
}));
function luminance(hex: string): number {
    const comps = [1,3,5].map((i) => parseInt(hex.slice(i, i+2),16) / 255).map(v => v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
    return comps[0] * 0.2126 + comps[1] * 0.7152 + comps[2] * 0.0722;
}
function contrastRatio(a: string,b: string): number {
    const x = luminance(a), y = luminance(b);
    return (Math.max(x,y) + 0.05) / (Math.min(x,y) + 0.05);
}
const accessibleText = (hex: string): string => contrastRatio(hex, '#FFFFFF') >= contrastRatio(hex, '#111827') ? '#FFFFFF' : '#111827';
const primaryText = computed(() => accessibleText(mainColor.value));
const secondaryText = computed(() => accessibleText(accentColor.value));
const colorValid = computed(() => /^#[0-9A-Fa-f]{6}$/.test(form.primary_color) && /^#[0-9A-Fa-f]{6}$/.test(form.secondary_color));
const hasChanges = computed(() => form.isDirty);
const filesAccepted = 'image/jpeg,image/png,image/webp';
function acceptDrop(key: LogoField, event: DragEvent) {
    event.preventDefault(); dragTarget.value = null;
    const file = event.dataTransfer?.files?.[0];
    if (file) setImage(key,file);
}
function setImage(key: LogoField, file: File) {
    fileError.value = '';
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 2*1024*1024) {
        fileError.value = 'Archivo rechazado: usa JPG, PNG o WebP de máximo 2 MB.'; return;
    }
    const objectUrl = URL.createObjectURL(file);
    const probe = new Image();
    probe.onload = () => {
        if (previews[key]) URL.revokeObjectURL(previews[key]!);
        previews[key] = objectUrl;
        selectedNames[key] = file.name;
        fileMeta[key] = `${probe.naturalWidth} × ${probe.naturalHeight} px · ${(file.size / 1024).toFixed(0)} KB`;
        form[key] = file;
        form[`remove_${key}` as Removable] = false;
    };
    probe.onerror = () => { URL.revokeObjectURL(objectUrl); fileError.value = 'No se pudo leer esta imagen.'; };
    probe.src = objectUrl;
}
function discardChanges() {
    Object.values(previews).forEach(u => { if (u) URL.revokeObjectURL(u); });
    for (const key of ['logo','credential_logo_1','credential_logo_2','credential_logo_3','credential_logo_4'] as LogoField[]) {
        previews[key] = null; selectedNames[key] = ''; fileMeta[key] = ''; fileInputKeys[key]++;
    }
    form.reset(); form.clearErrors(); fileError.value = '';
}

const imageUrl = (path: string | null | undefined) => path ? `/storage/${path.replace(/^\/+/, '')}` : null;
const oldLogo = (key: LogoField): string | null => key === 'logo' ? imageUrl(props.league.logo_path) : imageUrl(props.league[`${key}_path` as keyof LeagueSummary] as string | null);
const currentImage = (key: LogoField): string | null => {
    const removing = form[`remove_${key}` as Removable];
    return previews[key] ?? (removing ? null : oldLogo(key));
};
const chooseFile = (key: LogoField, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) setImage(key,file);
};
const removeImage = (key: LogoField) => {
    if (previews[key]) URL.revokeObjectURL(previews[key]!);
    previews[key] = null;
    selectedNames[key] = '';
    fileMeta[key] = '';
    form[key] = null;
    form[`remove_${key}` as Removable] = Boolean(oldLogo(key));
    fileInputKeys[key]++;
};
const formColor = (value: string) => /^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#1B2A4A';
const mainColor = computed(() => formColor(form.primary_color));
const accentColor = computed(() => formColor(form.secondary_color));
const previewBackground = computed(() => ({ background: `linear-gradient(110deg, ${mainColor.value} 0%, ${mainColor.value} 38%, ${accentColor.value} 100%)` }));
const save = () => { if (colorValid.value) form.post('/liga/configuracion', { forceFormData: true, preserveScroll: true }); };
onBeforeUnmount(() => Object.values(previews).forEach((url) => { if (url) URL.revokeObjectURL(url); }));
</script>

<template>
    <Head title="Identidad de la liga" />
    <AppLayout>
        <section class="identity-page pb-24">
            <header class="identity-hero">
                <div class="relative z-10 flex flex-wrap items-center justify-between gap-5">
                    <div class="flex items-center gap-4">
                        <span class="hero-symbol"><AppIcon name="palette" :size="33" /></span>
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-100">Configuración · Personalización de liga</p>
                            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Identidad de la liga</h1>
                            <p class="mt-1 text-sm text-white/85">Personaliza el nombre, logotipos y colores de tu liga.</p>
                        </div>
                    </div>
                    <div class="hero-label"><AppIcon name="settings" :size="19" /> Configuración de marca</div>
                </div>
            </header>

            <form id="league-identity-form" class="identity-grid mt-5" @submit.prevent="save">
                <div class="space-y-4">
                    <section class="identity-panel">
                        <div class="panel-heading"><span class="section-chip">Datos institucionales</span><h2>Información básica y logotipo principal</h2></div>
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label class="form-label" for="league-name">Nombre público <span class="form-required">*</span></label>
                                <input id="league-name" v-model="form.name" class="form-input" maxlength="150" required placeholder="Nombre oficial de la liga" />
                                <FormError :message="form.errors.name" />
                                <p class="help-block mt-3"><AppIcon name="badge" :size="18" /> Este nombre identificará a la liga en la plataforma y sus documentos.</p>
                            </div>
                            <div>
                                <p class="form-label">Logotipo principal</p>
                                <div class="drop-zone flex items-center gap-3" :class="dragTarget === 'logo' ? 'drop-zone--active' : ''" @dragover.prevent="dragTarget = 'logo'" @dragleave.prevent="dragTarget = null" @drop="acceptDrop('logo', $event)">
                                    <div class="logo-box"><img v-if="currentImage('logo')" :src="currentImage('logo')!" alt="Logotipo principal" class="h-full w-full object-contain" /><AppIcon v-else name="image" :size="34" class="text-slate-400" /></div>
                                    <div class="min-w-0 flex-1">
                                        <label class="file-button" for="logo-principal"><AppIcon name="image" :size="16" /> {{ currentImage('logo') ? 'Cambiar logotipo' : 'Subir logotipo' }}</label>
                                        <input :key="fileInputKeys.logo" id="logo-principal" class="sr-only" type="file" :accept="filesAccepted" @change="chooseFile('logo', $event)" />
                                        <p class="mt-2 break-words text-xs text-slate-500">{{ selectedNames.logo || 'Arrastra una imagen aquí' }}</p>
                                        <p v-if="fileMeta.logo" class="mt-1 text-xs text-slate-500">{{ fileMeta.logo }}</p>
                                        <button v-if="currentImage('logo')" type="button" class="danger-link mt-2" @click="removeImage('logo')">Quitar logotipo</button>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">PNG, JPG o WebP · Máximo 2 MB · Sugerido 512 × 512 px</p>
                                <FormError :message="form.errors.logo" />
                            </div>
                        </div>
                    </section>

                    <section class="identity-panel">
                        <div class="panel-heading"><span class="section-chip">Identificación oficial</span><h2>Logotipos para credenciales</h2><p>Hasta cuatro logotipos, en orden de izquierda a derecha. Los espacios 2–4 son opcionales.</p></div>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <div v-for="n in credentialPositions" :key="n" class="credential-card">
                                <div class="flex flex-wrap items-center justify-between gap-1"><span class="slot-number">{{ n }}</span><span class="slot-status" :class="currentImage(credentialKey(n)) ? 'slot-status--ok' : ''">{{ currentImage(credentialKey(n)) ? 'Configurado' : n === 1 ? 'Sin imagen' : 'Opcional' }}</span></div>
                                <div class="credential-drop mt-3" :class="dragTarget === credentialKey(n) ? 'drop-zone--active' : ''" @dragover.prevent="dragTarget = credentialKey(n)" @dragleave.prevent="dragTarget = null" @drop="acceptDrop(credentialKey(n), $event)">
                                    <img v-if="currentImage(credentialKey(n))" :src="currentImage(credentialKey(n))!" :alt="`Logotipo ${n} de credenciales`" class="h-24 w-full object-contain" />
                                    <div v-else class="grid h-24 place-items-center text-center text-slate-400"><div><AppIcon name="image" :size="28" /><p class="mt-1 text-[11px]">Arrastra tu imagen aquí</p></div></div>
                                </div>
                                <label class="file-button mt-3 w-full" :for="`credential-${n}`"><AppIcon name="image" :size="15" /> {{ currentImage(credentialKey(n)) ? 'Cambiar imagen' : 'Subir imagen' }}</label>
                                <input :id="`credential-${n}`" :key="fileInputKeys[credentialKey(n)]" class="sr-only" type="file" :accept="filesAccepted" @change="chooseFile(credentialKey(n), $event)" />
                                <p v-if="fileMeta[credentialKey(n)]" class="mt-2 truncate text-center text-[11px] text-slate-500" :title="fileMeta[credentialKey(n)]">{{ fileMeta[credentialKey(n)] }}</p>
                                <button v-if="currentImage(credentialKey(n))" type="button" class="danger-link mx-auto mt-2" @click="removeImage(credentialKey(n))">Eliminar imagen</button>
                                <FormError :message="credentialError(n)" />
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Admite JPG, PNG y WebP de hasta 2 MB por archivo. Se recomienda formato cuadrado y fondo transparente.</p>
                    </section>

                    <section class="identity-panel">
                        <div class="panel-heading"><span class="section-chip">Identidad visual</span><h2>Colores institucionales</h2><p>Los cambios se reflejan inmediatamente en la vista previa.</p></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label class="form-label">Color principal</label><div class="color-row"><input v-model="form.primary_color" type="color" class="color-swatch" aria-label="Seleccionar color principal" /><input v-model="form.primary_color" class="form-input font-mono uppercase" maxlength="7" placeholder="#1B2A4A" /></div><FormError :message="form.errors.primary_color" /></div>
                            <div><label class="form-label">Color secundario</label><div class="color-row"><input v-model="form.secondary_color" type="color" class="color-swatch" aria-label="Seleccionar color secundario" /><input v-model="form.secondary_color" class="form-input font-mono uppercase" maxlength="7" placeholder="#C5A059" /></div><FormError :message="form.errors.secondary_color" /></div>
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <p class="contrast-chip" :class="contrastWarnings.primary >= 4.5 ? 'contrast-chip--ok' : 'contrast-chip--warn'">{{ contrastWarnings.primary >= 4.5 ? '✓' : '!' }} Texto blanco sobre principal: {{ contrastWarnings.primary.toFixed(2) }}:1 · {{ contrastWarnings.primary >= 4.5 ? 'AA' : 'Revisar' }}</p>
                            <p class="contrast-chip" :class="contrastWarnings.secondary >= 4.5 ? 'contrast-chip--ok' : 'contrast-chip--warn'">{{ contrastWarnings.secondary >= 4.5 ? '✓' : '!' }} Texto blanco sobre secundario: {{ contrastWarnings.secondary.toFixed(2) }}:1 · {{ contrastWarnings.secondary >= 4.5 ? 'AA' : 'Revisar' }}</p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">WCAG 2.1 AA exige 4.5:1 para texto normal. La vista previa adapta el texto al color con mejor contraste; un degradado debe revisarse también visualmente.</p>
                        <p v-if="!colorValid" role="alert" class="mt-2 text-sm font-medium text-rose-700">Introduce ambos colores en formato HEX, por ejemplo #1B2A4A.</p>
                    </section>

                    <section class="identity-panel">
                        <div class="panel-heading"><span class="section-chip">Confirmar cambios</span><h2>Motivo de la actualización</h2><p>El motivo queda registrado en el historial administrativo.</p></div>
                        <label class="form-label" for="identity-reason">Motivo del cambio <span class="form-required">*</span></label>
                        <textarea id="identity-reason" v-model="form.reason" class="form-input min-h-24" required maxlength="500" placeholder="Describe brevemente por qué se realiza esta actualización..."></textarea>
                        <div class="mt-1 flex justify-between text-xs text-slate-500"><span>Obligatorio para guardar</span><span>{{ form.reason.length }}/500</span></div>
                        <FormError :message="form.errors.reason" />
                        <p v-if="fileError" role="alert" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ fileError }}</p>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <section class="identity-panel preview-panel">
                        <div class="mb-4 flex items-start gap-3"><span class="preview-icon"><AppIcon name="image" :size="24" /></span><div><h2 class="text-xl font-bold text-slate-900">Vista previa en vivo</h2><p class="text-xs text-slate-500">Ejemplo visual de cómo se aplicará tu identidad en ProLiga.</p></div></div>
                        <div class="mini-app">
                            <div class="mini-app-hero" :style="previewBackground">
                                <div class="flex min-w-0 items-center gap-3"><span class="mini-logo"><img v-if="currentImage('logo')" :src="currentImage('logo')!" class="h-full w-full object-contain" alt="Escudo de liga" /><AppIcon v-else name="shield" :size="25" class="text-league-700" /></span><strong class="min-w-0 break-words text-sm sm:text-base" :style="{ color: primaryText }">{{ form.name || 'Nombre de la liga' }}</strong></div>
                                <AppIcon name="bell" :size="20" :style="{ color: primaryText }" />
                            </div>
                            <div class="mini-nav"><span class="mini-nav-active" :style="{ color: mainColor, borderColor: mainColor }">Inicio</span><span>Partidos</span><span>Equipos</span><span>Clasificación</span></div>
                            <div class="p-3">
                                <div class="grid grid-cols-3 gap-2"><div v-for="(item,i) in [{name:'Equipos',number:'18',icon:'users'},{name:'Partidos',number:'42',icon:'calendar'},{name:'Jugadores',number:'312',icon:'user'}]" :key="i" class="mini-stat"><AppIcon :name="item.icon" :size="20" :style="{ color: i === 1 ? accentColor : mainColor }" /><strong>{{ item.number }}</strong><small>{{ item.name }}</small></div></div>
                                <div class="mini-match mt-3"><div class="flex items-center justify-between"><span class="text-xs font-bold">Próximo partido</span><span class="rounded-full px-2 py-1 text-[10px] font-bold" :style="{ backgroundColor: accentColor, color: secondaryText }">Jornada 5</span></div><div class="mt-3 flex items-center justify-between text-xs"><strong>Local</strong><span class="rounded-lg bg-slate-100 px-3 py-1 font-bold">VS</span><strong>Visitante</strong></div><p class="mt-2 text-center text-[11px] text-slate-500">Sábado · 16:00 · Estadio Municipal</p></div>
                                <div class="mini-match mt-3"><p class="mb-2 text-xs font-bold">Componentes de marca</p><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg px-3 py-2 text-xs font-semibold" :style="{ backgroundColor: mainColor, color: primaryText }">Botón principal</span><span class="rounded-lg px-3 py-2 text-xs font-semibold" :style="{ backgroundColor: accentColor, color: secondaryText }">Badge</span><span class="rounded-lg border px-3 py-2 text-xs font-semibold" :style="{ borderColor: mainColor, color: mainColor }">Acción secundaria</span></div></div>
                                <div class="mini-match mt-3"><div class="mb-2 flex items-center justify-between text-xs font-bold"><span>Progreso</span><span>68%</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full" :style="{ width: '68%', backgroundColor: mainColor }"></div></div></div>
                            </div>
                        </div>
                    </section>
                    <section class="identity-panel"><h3 class="font-bold text-slate-900">Accesibilidad y colores</h3><p class="mt-1 text-xs text-slate-500">Contraste WCAG con texto blanco.</p><div class="mt-4 space-y-3"><div class="flex items-center justify-between text-sm"><span>Color principal</span><strong :class="contrastWarnings.primary >= 4.5 ? 'text-emerald-700' : 'text-amber-700'">{{ contrastWarnings.primary >= 4.5 ? 'AA aprobado' : 'Revisar' }}</strong></div><div class="flex items-center justify-between text-sm"><span>Color secundario</span><strong :class="contrastWarnings.secondary >= 4.5 ? 'text-emerald-700' : 'text-amber-700'">{{ contrastWarnings.secondary >= 4.5 ? 'AA aprobado' : 'Revisar' }}</strong></div><div class="border-t border-slate-200 pt-3 text-xs text-slate-500">Los componentes de ejemplo ajustan automáticamente el texto para mejorar su legibilidad.</div></div></section>
                </aside>
            </form>

            <div class="sticky-actions mt-4"><div class="flex items-center gap-2 text-sm text-slate-600"><AppIcon name="badge" :size="18" class="text-league-700" /><span>{{ hasChanges ? 'Tienes cambios sin guardar.' : 'Los cambios se aplicarán al guardar.' }}</span></div><div class="flex flex-wrap gap-2"><button type="button" class="btn-discard" :disabled="!hasChanges || form.processing" @click="discardChanges">Descartar cambios</button><button type="submit" form="league-identity-form" class="btn-save" :disabled="form.processing || !colorValid"><AppIcon name="check" :size="17" /> {{ form.processing ? 'Guardando…' : 'Guardar personalización' }}</button></div></div>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.identity-page { max-width: 1500px; margin: 0 auto; }
.identity-hero { position:relative; overflow:hidden; border-radius:24px; padding:25px 30px; background: linear-gradient(100deg,rgba(4,35,86,.91),rgba(18,94,201,.78),rgba(16,88,170,.84)),url('/images/public/league-stadium.jpg') center 55%/cover; box-shadow:0 12px 30px rgba(15,55,110,.15); }
.hero-symbol { display:grid; place-items:center; background:rgba(255,255,255,.19); color:white; width:66px; height:66px; border-radius:17px; flex:none; }
.hero-label { display:flex; align-items:center; gap:9px; border-radius:12px; padding:11px 14px; background:white; font-size:12px; font-weight:800; color:#164c91; }
.identity-grid { display:grid; gap:16px; grid-template-columns:minmax(0,1.75fr) minmax(320px,1fr); }
.identity-panel { background:rgba(255,255,255,.97); border:1px solid #dbe4ef; border-radius:20px; padding:19px 21px; box-shadow:0 7px 18px rgba(27,42,74,.055); }
.panel-heading { margin-bottom:17px; }
.panel-heading h2 { margin-top:5px; font-size:19px; line-height:1.3; font-weight:800; color:#10213e; }
.panel-heading p { margin-top:3px; font-size:12px; color:#64748b; }
.section-chip { display:inline-flex; border-radius:999px; background:#eaf2ff; color:#1559b0; padding:4px 10px; text-transform:uppercase; letter-spacing:.08em; font-size:10px; font-weight:850; }
.help-block { display:flex; gap:10px; align-items:center; padding:12px; border:1px solid #dfebf9; border-radius:12px; background:#f5f9ff; color:#58708e; font-size:12px; }
.drop-zone,.credential-drop { border:1.5px dashed #b8d1ed; border-radius:13px; padding:12px; background:#fbfdff; transition:background .15s,border-color .15s; }
.drop-zone--active { background:#e8f2ff!important; border-color:#2d7aff!important; }
.logo-box { display:grid; place-items:center; width:90px; height:90px; padding:8px; border-radius:12px; border:1px solid #e1e9f5; background:white; flex:none; }
.file-button { display:inline-flex; min-height:33px; align-items:center; justify-content:center; gap:5px; padding:7px 10px; border-radius:9px; border:1px solid #c5d9f5; background:white; color:#135bbb; font-size:11px; font-weight:800; cursor:pointer; text-align:center; box-shadow:0 2px 5px rgba(15,35,70,.05); }
.file-button:hover { background:#eef5ff; }
.danger-link { display:block; color:#e21c3a; font-size:11px; font-weight:700; cursor:pointer; }
.credential-card { min-width:0; padding:10px; background:#fafcff; border:1px solid #dce6f2; border-radius:13px; }
.slot-number { background:#e8f1ff; color:#1762ca; font-weight:800; border-radius:7px; font-size:11px; padding:4px 7px; }
.slot-status { background:#f0f3f7; color:#60728b; font-size:10px; font-weight:700; border-radius:999px; padding:4px 7px; }
.slot-status--ok { background:#dff8e9; color:#138047; }
.credential-drop { padding:9px; display:grid; place-items:center; }
.color-row { display:flex; align-items:center; gap:7px; }
.color-swatch { width:46px; height:42px; border-radius:9px; padding:4px; border:1px solid #d4deea; cursor:pointer; background:white; flex:none; }
.contrast-chip { border-radius:10px; padding:9px; font-size:11px; font-weight:650; }
.contrast-chip--ok { background:#e8f8ef; color:#176c3c; }
.contrast-chip--warn { background:#fff5de; color:#875318; }
.preview-panel { padding:16px; }
.preview-icon { display:grid; place-items:center; background:#e7f1ff; color:#1261c5; border-radius:12px; width:43px; height:43px; flex:none; }
.mini-app { border:1px solid #d6e1f0; border-radius:18px; overflow:hidden; background:#f6f9fd; box-shadow:0 7px 18px rgba(15,40,80,.09); }
.mini-app-hero { min-height:89px; padding:17px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.mini-logo { display:grid; place-items:center; background:white; border-radius:10px; padding:6px; width:53px; height:53px; flex:none; }
.mini-nav { display:flex; flex-wrap:wrap; gap:5px; align-items:center; justify-content:space-around; background:white; border-bottom:1px solid #e0e7f1; padding:9px; font-size:11px; color:#41516e; }
.mini-nav span { padding:6px 9px; }
.mini-nav-active { font-weight:800; border-bottom:2px solid; }
.mini-stat { border:1px solid #edf0f6; border-radius:12px; background:white; min-width:0; text-align:center; padding:10px 5px; display:grid; justify-items:center; gap:2px; }
.mini-stat strong { font-size:18px; color:#13284a; }
.mini-stat small { color:#657892; font-size:10px; }
.mini-match { border:1px solid #e7edf6; border-radius:12px; background:white; padding:12px; color:#142b4f; }
.sticky-actions { position:sticky; bottom:12px; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap; background:rgba(255,255,255,.98); border:1px solid #dae5f3; padding:10px 14px; border-radius:15px; box-shadow:0 10px 35px rgba(11,33,72,.14); }
.btn-discard { border:1px solid #bfd0e7; border-radius:10px; padding:10px 18px; font-size:12px; font-weight:750; color:#143566; background:white; }
.btn-discard:disabled { opacity:.45; cursor:not-allowed; }
.btn-save { display:flex; align-items:center; justify-content:center; gap:9px; border-radius:10px; padding:10px 19px; background:#1367db; color:white; font-size:12px; font-weight:800; }
.btn-save:disabled { opacity:.6; cursor:not-allowed; }
@media (max-width:1050px) { .identity-grid { grid-template-columns:1fr; } aside { position:static!important; } }
@media (max-width:640px) { .identity-hero { padding:21px; } .identity-panel { padding:15px; } .sticky-actions { bottom:0; } }
</style>
