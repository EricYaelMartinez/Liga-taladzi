<script setup lang="ts">
import FormError from '../../components/FormError.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ email: string; token: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Restablecer contraseña" />
    <GuestLayout>
        <div class="card p-6 sm:p-8">
            <h2 class="text-3xl font-semibold">Nueva contraseña</h2>
            <form class="mt-7 space-y-5" @submit.prevent="form.post('/restablecer-contrasena')">
                <div>
                    <label class="form-label">Correo</label>
                    <input v-model="form.email" class="form-input" type="email" readonly>
                    <FormError :message="form.errors.email" />
                </div>
                <div>
                    <label class="form-label">Nueva contraseña</label>
                    <input v-model="form.password" class="form-input" type="password" autocomplete="new-password" required>
                    <FormError :message="form.errors.password" />
                </div>
                <div>
                    <label class="form-label">Confirmar contraseña</label>
                    <input v-model="form.password_confirmation" class="form-input" type="password" autocomplete="new-password" required>
                </div>
                <button class="btn-primary w-full" type="submit" :disabled="form.processing">Guardar contraseña</button>
            </form>
        </div>
    </GuestLayout>
</template>
