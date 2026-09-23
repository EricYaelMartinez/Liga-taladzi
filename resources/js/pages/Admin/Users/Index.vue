<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import type { AppPageProps, ManagedUser, StatusOption } from '../../../types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedUsers = {
    data: ManagedUser[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    users: PaginatedUsers;
    filters: { search: string; status: string };
    statuses: StatusOption[];
}>();

const page = usePage<AppPageProps>();
const filters = reactive({ search: props.filters.search, status: props.filters.status });
const canCreate = page.props.auth.user?.permissions.includes('users.create') ?? false;
const canUpdate = page.props.auth.user?.permissions.includes('users.update') ?? false;
const canDelete = page.props.auth.user?.permissions.includes('users.delete') ?? false;

const applyFilters = () => router.get('/administracion/usuarios', filters, { preserveState: true, replace: true });
const clearFilters = () => {
    filters.search = '';
    filters.status = '';
    applyFilters();
};
const removeUser = (user: ManagedUser) => {
    if (window.confirm(`¿Eliminar la cuenta de ${user.name}? El historial se conservará.`)) {
        router.delete(`/administracion/usuarios/${user.id}`, { preserveScroll: true });
    }
};
const statusLabel = (value: string) => props.statuses.find((status) => status.value === value)?.label ?? value;
</script>

<template>
    <Head title="Usuarios" />
    <AppLayout>
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Administración</p>
                <h1 class="mt-1 text-3xl font-semibold">Usuarios</h1>
                <p class="mt-2 text-sm text-slate-600">Cuentas, estados y acceso global a la plataforma.</p>
            </div>
            <Link v-if="canCreate" href="/administracion/usuarios/crear" class="btn-primary">Crear usuario</Link>
        </section>

        <form class="card mt-7 grid gap-4 p-4 sm:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFilters">
            <div>
                <label class="form-label" for="search">Buscar</label>
                <input id="search" v-model="filters.search" class="form-input" placeholder="Nombre o correo">
            </div>
            <div>
                <label class="form-label" for="status">Estado</label>
                <select id="status" v-model="filters.status" class="form-input">
                    <option value="">Todos</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="btn-primary" type="submit">Filtrar</button>
                <button class="btn-secondary" type="button" @click="clearFilters">Limpiar</button>
            </div>
        </form>

        <div class="card mt-6 overflow-hidden">
            <div v-if="users.data.length === 0" class="px-6 py-14 text-center">
                <p class="font-medium text-slate-700">No se encontraron usuarios.</p>
                <p class="mt-1 text-sm text-slate-500">Prueba con otros filtros o crea una cuenta.</p>
            </div>

            <div v-else>
                <div class="divide-y divide-slate-200 md:hidden">
                    <article v-for="user in users.data" :key="user.id" class="space-y-4 p-5">
                        <div>
                            <p class="font-semibold">{{ user.name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-league-50 px-2.5 py-1 font-medium text-league-700">{{ statusLabel(user.status) }}</span>
                            <span v-for="role in user.roles" :key="role.id" class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ role.name }}</span>
                        </div>
                        <div class="flex gap-2">
                            <Link v-if="canUpdate" :href="`/administracion/usuarios/${user.id}/editar`" class="btn-secondary">Editar</Link>
                            <button v-if="canDelete && page.props.auth.user?.id !== user.id" class="btn-danger" type="button" @click="removeUser(user)">Eliminar</button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-6 py-4">Usuario</th><th class="px-6 py-4">Estado</th><th class="px-6 py-4">Rol global</th><th class="px-6 py-4 text-right">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ user.name }}</p><p class="mt-1 text-slate-500">{{ user.email }}</p></td>
                                <td class="px-6 py-4"><span class="rounded-full bg-league-50 px-2.5 py-1 text-xs font-medium text-league-700">{{ statusLabel(user.status) }}</span></td>
                                <td class="px-6 py-4 text-slate-600">{{ user.roles.map((role) => role.name).join(', ') || 'Sin rol global' }}</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><Link v-if="canUpdate" :href="`/administracion/usuarios/${user.id}/editar`" class="btn-secondary">Editar</Link><button v-if="canDelete && page.props.auth.user?.id !== user.id" class="btn-danger" type="button" @click="removeUser(user)">Eliminar</button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="users.total > 0" class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">Mostrando {{ users.from }}–{{ users.to }} de {{ users.total }}</p>
                <nav class="flex flex-wrap gap-1" aria-label="Paginación">
                    <Link v-for="link in users.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg px-3 py-2 text-sm" :class="[link.active ? 'bg-league-700 text-white' : 'text-slate-600 hover:bg-slate-100', { 'pointer-events-none opacity-40': !link.url }]" preserve-state v-html="link.label" />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
