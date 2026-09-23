<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <main class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
        <section class="hidden bg-league-900 px-12 py-14 text-white lg:flex lg:flex-col lg:justify-between">
            <Link href="/" class="text-lg font-semibold tracking-wide">Liga Taladzi</Link>
            <div class="max-w-xl">
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.25em] text-gold-400">Administración deportiva</p>
                <h1 class="text-5xl font-semibold leading-tight">Toda la liga, organizada en un solo lugar.</h1>
                <p class="mt-6 text-lg leading-8 text-league-100">Usuarios, equipos, partidos, cédulas y estadísticas con acceso seguro para cada perfil.</p>
            </div>
            <p class="text-sm text-league-100">Sistema de la Liga Taladzi</p>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-8 block text-center text-xl font-semibold text-league-900 lg:hidden">Liga Taladzi</Link>
                <slot />
            </div>
        </section>
    </main>
</template>
