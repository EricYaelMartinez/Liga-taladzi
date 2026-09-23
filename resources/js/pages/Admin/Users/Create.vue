<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { RoleOption, StatusOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ statuses: StatusOption[]; roles: RoleOption[] }>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    status: 'active',
    role_id: null as number | null,
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Crear usuario" />
    <AppLayout>
        <section class="mx-auto max-w-3xl">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Administración</p>
                    <h1 class="mt-1 text-3xl font-semibold">Nuevo usuario</h1>
                </div>
                <Link href="/administracion/usuarios" class="btn-secondary">Volver</Link>
            </div>

            <form class="card space-y-6 p-6 sm:p-8" @submit.prevent="form.post('/administracion/usuarios')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="form-label" for="name">Nombre completo</label>
                        <input id="name" v-model="form.name" class="form-input" maxlength="150" required autofocus>
                        <FormError :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input id="email" v-model="form.email" class="form-input" type="email" maxlength="190" required>
                        <FormError :message="form.errors.email" />
                    </div>
                    <div>
                        <label class="form-label" for="phone">Teléfono</label>
                        <input id="phone" v-model="form.phone" class="form-input" maxlength="30">
                        <FormError :message="form.errors.phone" />
                    </div>
                    <div>
                        <label class="form-label" for="status">Estado</label>
                        <select id="status" v-model="form.status" class="form-input" required>
                            <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                        <FormError :message="form.errors.status" />
                    </div>
                    <div>
                        <label class="form-label" for="role">Rol global</label>
                        <select id="role" v-model="form.role_id" class="form-input">
                            <option :value="null">Sin rol global</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <FormError :message="form.errors.role_id" />
                        <p class="mt-2 text-xs text-slate-500">Los roles de liga se asignarán dentro de cada liga.</p>
                    </div>
                    <div>
                        <label class="form-label" for="password">Contraseña temporal</label>
                        <input id="password" v-model="form.password" class="form-input" type="password" autocomplete="new-password" required>
                        <FormError :message="form.errors.password" />
                    </div>
                    <div>
                        <label class="form-label" for="confirmation">Confirmar contraseña</label>
                        <input id="confirmation" v-model="form.password_confirmation" class="form-input" type="password" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">El usuario deberá cambiar la contraseña temporal al iniciar sesión.</div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href="/administracion/usuarios" class="btn-secondary">Cancelar</Link>
                    <button class="btn-primary" type="submit" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Crear usuario' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
