<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
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
    reason: '',
});
const selectLogo = (event: Event) => {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
};
</script>

<template>
    <Head title="Configuración de liga" />
    <AppLayout>
        <section class="mx-auto max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Personalización</p>
            <h1 class="mt-1 text-3xl font-semibold">Identidad de la liga</h1>
            <form class="card mt-7 space-y-6 p-6 sm:p-8" @submit.prevent="form.post('/liga/configuracion', { forceFormData: true })">
                <div><label class="form-label">Nombre público</label><input v-model="form.name" class="form-input" required maxlength="150"><FormError :message="form.errors.name" /></div>
                <div>
                    <label class="form-label">Logotipo</label>
                    <img v-if="league.logo_path && !form.remove_logo" :src="`/storage/${league.logo_path}`" alt="Logotipo actual" class="mb-4 h-24 w-24 rounded-xl border border-slate-200 object-contain p-2">
                    <input class="form-input" type="file" accept="image/jpeg,image/png,image/webp" @change="selectLogo">
                    <FormError :message="form.errors.logo" />
                    <label v-if="league.logo_path" class="mt-3 flex items-center gap-2 text-sm text-slate-600"><input v-model="form.remove_logo" type="checkbox"> Eliminar logotipo actual</label>
                    <p class="mt-2 text-xs text-slate-500">JPG, PNG o WebP; máximo 2 MB.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label class="form-label">Color principal</label><div class="flex gap-2"><input v-model="form.primary_color" type="color" class="h-11 w-14 rounded-lg border"><input v-model="form.primary_color" class="form-input"></div><FormError :message="form.errors.primary_color" /></div>
                    <div><label class="form-label">Color secundario</label><div class="flex gap-2"><input v-model="form.secondary_color" type="color" class="h-11 w-14 rounded-lg border"><input v-model="form.secondary_color" class="form-input"></div><FormError :message="form.errors.secondary_color" /></div>
                </div>
                <div class="rounded-2xl p-6 text-white" :style="{ background: `linear-gradient(120deg, ${form.primary_color}, ${form.secondary_color})` }"><p class="text-sm opacity-80">Vista previa</p><p class="mt-2 text-2xl font-semibold">{{ form.name || 'Nombre de la liga' }}</p></div>
                <div><label class="form-label">Motivo del cambio</label><textarea v-model="form.reason" class="form-input min-h-24" required maxlength="500"></textarea><FormError :message="form.errors.reason" /></div>
                <div class="flex justify-end"><button class="btn-primary" type="submit" :disabled="form.processing">Guardar personalización</button></div>
            </form>
        </section>
    </AppLayout>
</template>
