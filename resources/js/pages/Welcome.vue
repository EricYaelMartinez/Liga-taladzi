<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type Service = { status: 'ok' | 'error'; driver?: string; name?: string };
type HealthResponse = {
    application: Service;
    database: Service;
    redis: Service;
};

const health = ref<HealthResponse | null>(null);
const loading = ref(true);

const services = computed(() => [
    { name: 'Laravel', value: health.value?.application },
    { name: 'PostgreSQL', value: health.value?.database },
    { name: 'Redis', value: health.value?.redis },
]);

onMounted(async () => {
    try {
        const response = await fetch('/status/services', { headers: { Accept: 'application/json' } });
        health.value = await response.json();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <main class="min-h-screen px-5 py-10 sm:px-8">
        <section class="mx-auto max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl shadow-slate-200/70">
            <header class="bg-league-900 px-6 py-10 text-white sm:px-10">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="mb-2 text-sm font-semibold uppercase tracking-[0.2em] text-gold-400">Módulo 1</p>
                        <h1 class="text-3xl font-semibold sm:text-4xl">Sistema de la Liga Taladzi</h1>
                        <p class="mt-3 max-w-2xl text-league-100">Entorno base y acceso seguro por roles y permisos.</p>
                    </div>
                    <Link href="/iniciar-sesion" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-league-900 transition hover:bg-league-50">Iniciar sesión</Link>
                </div>
            </header>

            <div class="grid gap-5 p-6 sm:grid-cols-3 sm:p-10">
                <article v-for="service in services" :key="service.name" class="rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold">{{ service.name }}</h2>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="service.value?.status === 'ok' ? 'bg-league-100 text-league-700' : 'bg-amber-100 text-amber-800'"
                        >
                            {{ loading ? 'Comprobando' : service.value?.status === 'ok' ? 'Disponible' : 'Revisar' }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ service.value?.driver ? `Controlador: ${service.value.driver}` : 'Servicio requerido por la plataforma.' }}
                    </p>
                </article>
            </div>

            <footer class="border-t border-slate-200 bg-slate-50 px-6 py-5 text-sm text-slate-600 sm:px-10">
                Cuando los tres servicios indiquen “Disponible”, puedes iniciar sesión con una cuenta creada por la administración.
            </footer>
        </section>
    </main>
</template>
