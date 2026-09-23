<script setup lang="ts">
import FormError from '../../components/FormError.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ canResetPassword: boolean; status?: string }>();

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/iniciar-sesion', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Iniciar sesión" />
    <GuestLayout>
        <div class="card p-6 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Acceso seguro</p>
            <h2 class="mt-2 text-3xl font-semibold text-slate-900">Iniciar sesión</h2>
            <p class="mt-2 text-sm text-slate-600">Ingresa con la cuenta proporcionada por la administración.</p>

            <div v-if="status" class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ status }}</div>

            <form class="mt-7 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input id="email" v-model="form.email" class="form-input" type="email" autocomplete="username" required autofocus>
                    <FormError :message="form.errors.email" />
                </div>
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="form-label" for="password">Contraseña</label>
                        <Link v-if="canResetPassword" href="/olvide-contrasena" class="text-sm font-medium text-league-700 hover:underline">¿La olvidaste?</Link>
                    </div>
                    <input id="password" v-model="form.password" class="form-input" type="password" autocomplete="current-password" required>
                    <FormError :message="form.errors.password" />
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-league-600">
                    Mantener mi sesión iniciada
                </label>
                <button class="btn-primary w-full" type="submit" :disabled="form.processing">{{ form.processing ? 'Ingresando…' : 'Ingresar' }}</button>
            </form>
        </div>
    </GuestLayout>
</template>
