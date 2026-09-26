<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { AppPageProps, RoleOption, StatusOption } from '../../../types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

type UserOption = { id: number; name: string; email: string; status?: string };
type Membership = { id: number; status: string; started_at: string; ended_at: string | null; user: UserOption; role: RoleOption };
const props = defineProps<{ memberships: Membership[]; roles: RoleOption[]; statuses: StatusOption[] }>();
const page = usePage<AppPageProps>();
const canCreateUser = page.props.auth.user?.permissions.includes('league.users.create') ?? false;
const form = useForm({ email: '', role_id: null as number | null, reason: '' });
const label = (value: string) => props.statuses.find((item) => item.value === value)?.label ?? value;
const changeStatus = (membership: Membership, status: string) => {
    if (membership.status === status) return;
    const reason = window.prompt(`Motivo para cambiar el acceso de ${membership.user.name} a “${label(status)}”:`);
    if (reason?.trim()) router.put(`/liga/miembros/${membership.id}`, { status, reason }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Miembros de la liga" />
    <AppLayout>
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Accesos</p><h1 class="mt-1 text-3xl font-semibold">Miembros y roles</h1><p class="mt-2 text-slate-600">Una persona puede tener varios roles dentro de la misma liga.</p></div><Link v-if="canCreateUser" href="/liga/miembros/crear-usuario" class="btn-primary">Crear usuario</Link></section>

        <form class="card mt-7 grid gap-4 p-5 lg:grid-cols-[1fr_1fr_1.2fr_auto]" @submit.prevent="form.post('/liga/miembros', { preserveScroll: true, onSuccess: () => form.reset() })">
            <div><label class="form-label">Correo exacto del usuario</label><input v-model="form.email" class="form-input" type="email" required placeholder="usuario@correo.com"><FormError :message="form.errors.email" /></div>
            <div><label class="form-label">Rol</label><select v-model="form.role_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select><FormError :message="form.errors.role_id" /></div>
            <div><label class="form-label">Motivo de asignación</label><input v-model="form.reason" class="form-input" required maxlength="500"><FormError :message="form.errors.reason" /></div>
            <div class="flex items-end"><button class="btn-primary w-full" type="submit" :disabled="form.processing">Asignar</button></div>
        </form>

        <div class="card mt-6 overflow-hidden">
            <div v-if="!memberships.length" class="p-12 text-center text-slate-600">No hay accesos asignados.</div>
            <div v-else class="divide-y divide-slate-200">
                <article v-for="membership in memberships" :key="membership.id" class="grid gap-4 p-5 md:grid-cols-[1fr_220px_180px] md:items-center">
                    <div><p class="font-semibold">{{ membership.user.name }}</p><p class="mt-1 text-sm text-slate-500">{{ membership.user.email }}</p></div>
                    <div><p class="text-sm font-medium">{{ membership.role.name }}</p><p class="mt-1 text-xs text-slate-500">{{ label(membership.status) }}</p></div>
                    <select :value="membership.status" class="form-input" @change="changeStatus(membership, ($event.target as HTMLSelectElement).value)"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
