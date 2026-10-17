<script setup lang="ts">
import FormError from '../../components/FormError.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ canResetPassword: boolean; status?: string }>();

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const submit = () => form.post('/iniciar-sesion', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Iniciar sesión" />
    <GuestLayout>
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-league-700">Acceso seguro</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-league-900 sm:text-4xl">Bienvenido de nuevo</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Ingresa tus credenciales para acceder a la plataforma.</p>

            <div v-if="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ status }}</div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.08em] text-slate-700" for="email">Correo electrónico</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <input id="email" v-model="form.email" class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-league-600 focus:ring-4 focus:ring-league-100" type="email" autocomplete="username" placeholder="nombre@correo.com" required autofocus>
                    </div>
                    <FormError :message="form.errors.email" />
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-4">
                        <label class="block text-xs font-bold uppercase tracking-[0.08em] text-slate-700" for="password">Contraseña</label>
                        <Link v-if="canResetPassword" href="/olvide-contrasena" class="text-xs font-semibold text-league-700 transition hover:text-league-900 hover:underline">¿Olvidaste tu contraseña?</Link>
                    </div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round"/></svg>
                        </span>
                        <input id="password" v-model="form.password" class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-12 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-league-600 focus:ring-4 focus:ring-league-100" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="absolute inset-y-0 right-0 flex min-w-12 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-league-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-league-600" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.1 2.7M6.5 6.5C4 8.2 2.5 12 2.5 12s3.5 6 9.5 6a9 9 0 0 0 3.1-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    <FormError :message="form.errors.password" />
                </div>

                <label class="flex w-fit cursor-pointer items-center gap-3 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-league-600 focus:ring-league-600">
                    Recordar mi sesión
                </label>

                <button class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-league-900 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-league-900/15 transition hover:-translate-y-0.5 hover:bg-league-700 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-league-100 disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}
                    <svg v-if="!form.processing" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </form>

            <div class="my-7 flex items-center gap-4 text-[11px] font-bold uppercase tracking-wider text-slate-400"><span class="h-px flex-1 bg-slate-200"></span><span>Accesos futuros</span><span class="h-px flex-1 bg-slate-200"></span></div>

            <div class="grid grid-cols-2 gap-3">
                <button type="button" disabled title="Inicio de sesión con Google: próximamente" class="flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-500 opacity-75 shadow-sm">
                    <span class="text-base font-bold text-[#4285f4]">G</span> Google
                </button>
                <button type="button" disabled title="Inicio de sesión con Apple: próximamente" class="flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-500 opacity-75 shadow-sm">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current" aria-hidden="true"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.79 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.1ZM12.03 7.25C11.88 5.02 13.69 3.18 15.77 3c.29 2.58-2.34 4.5-3.74 4.25Z"/></svg>
                    Apple
                </button>
            </div>
            <p class="mt-3 text-center text-[11px] font-semibold text-slate-400">Google y Apple estarán disponibles en una versión posterior.</p>

            <div class="mt-7 text-center">
                <Link href="/" class="text-sm font-semibold text-league-700 transition hover:text-league-900 hover:underline">← Consultar el portal público</Link>
            </div>
        </div>
    </GuestLayout>
</template>
