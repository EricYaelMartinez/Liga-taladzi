<script setup lang="ts">
import FormError from '../../components/FormError.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();
const form = useForm({ email: '' });
</script>

<template>
    <Head title="Recuperar contraseña" />
    <GuestLayout>
        <div class="card p-6 sm:p-8">
            <h2 class="text-3xl font-semibold">Recuperar contraseña</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">Escribe tu correo. Si existe una cuenta activa, recibirás instrucciones para recuperar el acceso.</p>
            <div v-if="status" class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ status }}</div>
            <form class="mt-7 space-y-5" @submit.prevent="form.post('/olvide-contrasena')">
                <div>
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input id="email" v-model="form.email" class="form-input" type="email" required autofocus>
                    <FormError :message="form.errors.email" />
                </div>
                <button class="btn-primary w-full" type="submit" :disabled="form.processing">Enviar instrucciones</button>
                <Link href="/iniciar-sesion" class="block text-center text-sm font-medium text-league-700 hover:underline">Volver al inicio de sesión</Link>
            </form>
        </div>
    </GuestLayout>
</template>
