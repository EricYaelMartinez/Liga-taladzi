<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { RoleOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ roles: RoleOption[] }>();
const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', role_id: null as number | null, reason: '' });
</script>

<template>
    <Head title="Crear usuario de liga" />
    <AppLayout>
        <section class="mx-auto max-w-3xl">
            <div class="mb-6 flex items-center justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Miembros</p><h1 class="mt-1 text-3xl font-semibold">Crear usuario</h1></div><Link href="/liga/miembros" class="btn-secondary">Volver</Link></div>
            <form class="card space-y-5 p-6 sm:p-8" @submit.prevent="form.post('/liga/miembros/crear-usuario')">
                <div><label class="form-label">Nombre completo</label><input v-model="form.name" class="form-input" required maxlength="150"><FormError :message="form.errors.name" /></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Correo</label><input v-model="form.email" class="form-input" type="email" required><FormError :message="form.errors.email" /></div><div><label class="form-label">Teléfono</label><input v-model="form.phone" class="form-input" maxlength="30"><FormError :message="form.errors.phone" /></div></div>
                <div><label class="form-label">Rol inicial</label><select v-model="form.role_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select><FormError :message="form.errors.role_id" /></div>
                <div class="grid gap-5 sm:grid-cols-2"><div><label class="form-label">Contraseña temporal</label><input v-model="form.password" class="form-input" type="password" required autocomplete="new-password"><FormError :message="form.errors.password" /></div><div><label class="form-label">Confirmar contraseña</label><input v-model="form.password_confirmation" class="form-input" type="password" required autocomplete="new-password"></div></div>
                <div><label class="form-label">Motivo del alta</label><textarea v-model="form.reason" class="form-input min-h-24" required maxlength="500"></textarea><FormError :message="form.errors.reason" /></div>
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">La contraseña es temporal y deberá cambiarse en el primer acceso.</div>
                <div class="flex justify-end gap-3"><Link href="/liga/miembros" class="btn-secondary">Cancelar</Link><button class="btn-primary" type="submit" :disabled="form.processing">Crear y asignar</button></div>
            </form>
        </section>
    </AppLayout>
</template>
