<script setup lang="ts">
import FormError from '../../components/FormError.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ forced: boolean }>();
const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const submit = () => form.put('/cambiar-contrasena', { onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Cambiar contraseña" />
    <AppLayout>
        <section class="mx-auto max-w-xl">
            <div v-if="forced" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Por seguridad debes reemplazar la contraseña temporal antes de continuar.</div>
            <div class="card p-6 sm:p-8">
                <h1 class="text-2xl font-semibold">Cambiar contraseña</h1>
                <p class="mt-2 text-sm text-slate-600">Utiliza al menos 12 caracteres, mayúsculas, minúsculas y números.</p>
                <form class="mt-7 space-y-5" @submit.prevent="submit">
                    <div><label class="form-label">Contraseña actual</label><input v-model="form.current_password" class="form-input" type="password" required><FormError :message="form.errors.current_password" /></div>
                    <div><label class="form-label">Nueva contraseña</label><input v-model="form.password" class="form-input" type="password" required><FormError :message="form.errors.password" /></div>
                    <div><label class="form-label">Confirmación</label><input v-model="form.password_confirmation" class="form-input" type="password" required></div>
                    <button class="btn-primary" type="submit" :disabled="form.processing">Actualizar contraseña</button>
                </form>
            </div>
        </section>
    </AppLayout>
</template>
