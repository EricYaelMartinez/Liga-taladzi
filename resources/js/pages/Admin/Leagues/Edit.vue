<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { LeagueSummary, StatusOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ league: LeagueSummary; statuses: StatusOption[] }>();
const form = useForm({ name: props.league.name, slug: props.league.slug, primary_color: props.league.primary_color, secondary_color: props.league.secondary_color, status: props.league.status, reason: '' });
</script>

<template>
    <Head :title="`Editar ${league.name}`" />
    <AppLayout>
        <section class="mx-auto max-w-3xl">
            <div class="mb-6 flex items-center justify-between"><h1 class="text-3xl font-semibold">Editar liga</h1><Link href="/administracion/ligas" class="btn-secondary">Volver</Link></div>
            <form class="card space-y-5 p-6 sm:p-8" @submit.prevent="form.put(`/administracion/ligas/${league.id}`)">
                <div><label class="form-label">Nombre</label><input v-model="form.name" class="form-input" required><FormError :message="form.errors.name" /></div>
                <div><label class="form-label">Identificador URL</label><input v-model="form.slug" class="form-input" required><FormError :message="form.errors.slug" /></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Color principal</label><input v-model="form.primary_color" type="color" class="h-11 w-full rounded-xl border border-slate-300"><FormError :message="form.errors.primary_color" /></div><div><label class="form-label">Color secundario</label><input v-model="form.secondary_color" type="color" class="h-11 w-full rounded-xl border border-slate-300"><FormError :message="form.errors.secondary_color" /></div></div>
                <div><label class="form-label">Estado</label><select v-model="form.status" class="form-input"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select><FormError :message="form.errors.status" /></div>
                <div><label class="form-label">Motivo del cambio</label><textarea v-model="form.reason" class="form-input min-h-24" required maxlength="500"></textarea><FormError :message="form.errors.reason" /></div>
                <div class="flex justify-end gap-3"><Link href="/administracion/ligas" class="btn-secondary">Cancelar</Link><button class="btn-primary" type="submit" :disabled="form.processing">Guardar cambios</button></div>
            </form>
        </section>
    </AppLayout>
</template>
