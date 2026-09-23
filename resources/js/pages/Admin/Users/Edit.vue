<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { ManagedUser, RoleOption, StatusOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ managedUser: ManagedUser; statuses: StatusOption[]; roles: RoleOption[] }>();

const form = useForm({
    name: props.managedUser.name,
    email: props.managedUser.email,
    phone: props.managedUser.phone ?? '',
    status: props.managedUser.status,
    role_id: (props.managedUser.roles[0]?.id ?? null) as number | null,
});

const passwordForm = useForm({ password: '', password_confirmation: '' });
const resetPassword = () => passwordForm.put(`/administracion/usuarios/${props.managedUser.id}/contrasena`, {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
});
</script>

<template>
    <Head :title="`Editar ${managedUser.name}`" />
    <AppLayout>
        <section class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Administración</p>
                    <h1 class="mt-1 text-3xl font-semibold">Editar usuario</h1>
                </div>
                <Link href="/administracion/usuarios" class="btn-secondary">Volver</Link>
            </div>

            <form class="card space-y-6 p-6 sm:p-8" @submit.prevent="form.put(`/administracion/usuarios/${managedUser.id}`)">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="form-label" for="name">Nombre completo</label>
                        <input id="name" v-model="form.name" class="form-input" maxlength="150" required>
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
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href="/administracion/usuarios" class="btn-secondary">Cancelar</Link>
                    <button class="btn-primary" type="submit" :disabled="form.processing">Guardar cambios</button>
                </div>
            </form>

            <form class="card p-6 sm:p-8" @submit.prevent="resetPassword">
                <h2 class="text-xl font-semibold">Restablecer contraseña</h2>
                <p class="mt-2 text-sm text-slate-600">Asigna una contraseña temporal. Se solicitará cambiarla en el siguiente acceso.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="new-password">Nueva contraseña</label>
                        <input id="new-password" v-model="passwordForm.password" class="form-input" type="password" autocomplete="new-password" required>
                        <FormError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <label class="form-label" for="new-confirmation">Confirmar contraseña</label>
                        <input id="new-confirmation" v-model="passwordForm.password_confirmation" class="form-input" type="password" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button class="btn-secondary" type="submit" :disabled="passwordForm.processing">Restablecer contraseña</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
