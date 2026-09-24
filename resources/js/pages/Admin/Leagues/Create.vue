<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { StatusOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type UserOption = { id: number; name: string; email: string };
defineProps<{ users: UserOption[]; statuses: StatusOption[] }>();
const form = useForm({ name: '', slug: '', primary_color: '#125444', secondary_color: '#d9a928', status: 'active', initial_admin_user_id: null as number | null });
</script>

<template>
    <Head title="Crear liga" />
    <AppLayout>
        <section class="mx-auto max-w-3xl">
            <div class="mb-6 flex items-center justify-between"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Multiliga</p><h1 class="mt-1 text-3xl font-semibold">Nueva liga</h1></div><Link href="/administracion/ligas" class="btn-secondary">Volver</Link></div>
            <form class="card space-y-6 p-6 sm:p-8" @submit.prevent="form.post('/administracion/ligas')">
                <div><label class="form-label">Nombre</label><input v-model="form.name" class="form-input" required maxlength="150"><FormError :message="form.errors.name" /></div>
                <div><label class="form-label">Identificador URL (opcional)</label><input v-model="form.slug" class="form-input" placeholder="Se genera automáticamente"><FormError :message="form.errors.slug" /></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label class="form-label">Color principal</label><div class="flex gap-2"><input v-model="form.primary_color" type="color" class="h-11 w-14 rounded-lg border border-slate-300"><input v-model="form.primary_color" class="form-input" required></div><FormError :message="form.errors.primary_color" /></div>
                    <div><label class="form-label">Color secundario</label><div class="flex gap-2"><input v-model="form.secondary_color" type="color" class="h-11 w-14 rounded-lg border border-slate-300"><input v-model="form.secondary_color" class="form-input" required></div><FormError :message="form.errors.secondary_color" /></div>
                </div>
                <div><label class="form-label">Estado</label><select v-model="form.status" class="form-input"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select><FormError :message="form.errors.status" /></div>
                <div><label class="form-label">Administrador inicial</label><select v-model="form.initial_admin_user_id" class="form-input" required><option :value="null" disabled>Selecciona un usuario</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option></select><FormError :message="form.errors.initial_admin_user_id" /><p class="mt-2 text-xs text-slate-500">Si no aparece, crea primero su cuenta desde Usuarios.</p></div>
                <div class="flex justify-end gap-3"><Link href="/administracion/ligas" class="btn-secondary">Cancelar</Link><button class="btn-primary" type="submit" :disabled="form.processing">Crear liga</button></div>
            </form>
        </section>
    </AppLayout>
</template>
