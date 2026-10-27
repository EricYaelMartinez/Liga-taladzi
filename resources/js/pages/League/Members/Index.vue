<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import MemberRoleIcon from '../../../components/MemberRoleIcon.vue';
import type { AppPageProps, RoleOption, StatusOption } from '../../../types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type UserOption = { id: number; name: string; email: string; status?: string };
type Membership = { id: number; status: string; started_at: string; ended_at: string | null; user: UserOption; role: RoleOption };
const props = defineProps<{ memberships: Membership[]; roles: RoleOption[]; statuses: StatusOption[] }>();
const page = usePage<AppPageProps>();
const canCreateUser = computed(() => page.props.auth.user?.permissions.includes('league.users.create') ?? false);
const form = useForm({ email: '', role_id: null as number | null, reason: '' });
const search = ref('');
const statusFilter = ref('all');
const roleFilter = ref('all');
const editingMembership = ref<Membership | null>(null);
const proposedStatus = ref('');
const statusReason = ref('');
const statusError = ref('');
const statusSaving = ref(false);
const statusChoices = computed(() => [
    { value: 'active', title: 'Activo', detail: 'Puede acceder con los permisos de este rol.', icon: '✓' },
    { value: 'inactive', title: 'Inactivo', detail: 'Su acceso queda deshabilitado hasta reactivarlo.', icon: '−' },
    { value: 'suspended', title: 'Suspendido', detail: 'Acceso bloqueado temporalmente por decisión administrativa.', icon: '!' },
].filter((choice) => props.statuses.some((status) => status.value === choice.value)));
const openStatusEditor = (membership: Membership) => {
    editingMembership.value = membership;
    proposedStatus.value = membership.status;
    statusReason.value = '';
    statusError.value = '';
};
const closeStatusEditor = () => {
    if (statusSaving.value) return;
    editingMembership.value = null;
};
const saveStatus = () => {
    const membership = editingMembership.value;
    if (!membership || statusSaving.value) return;
    if (proposedStatus.value === membership.status) {
        statusError.value = 'Selecciona un estado diferente al actual.';
        return;
    }
    if (!statusReason.value.trim()) {
        statusError.value = 'Escribe el motivo del cambio de acceso.';
        return;
    }
    statusError.value = '';
    statusSaving.value = true;
    router.put(`/liga/miembros/${membership.id}`, {
        status: proposedStatus.value,
        reason: statusReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => { editingMembership.value = null; },
        onError: (errors) => { statusError.value = errors.status ?? errors.reason ?? 'No fue posible actualizar el acceso.'; },
        onFinish: () => { statusSaving.value = false; },
    });
};
const context = computed(() => page.props.activeContext);
const label = (value: string) => props.statuses.find((item) => item.value === value)?.label ?? value;
const normalize = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
const filteredMemberships = computed(() => props.memberships.filter((membership) => {
    const query = normalize(search.value.trim());
    const matchesSearch = !query || normalize(`${membership.user.name} ${membership.user.email} ${membership.role.name}`).includes(query);
    return matchesSearch && (statusFilter.value === 'all' || membership.status === statusFilter.value)
        && (roleFilter.value === 'all' || String(membership.role.id) === roleFilter.value);
}));
const initials = (name: string) => name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
const isActive = (status: string) => ['active', 'activo', 'approved', 'aprobado'].includes(normalize(status));

</script>

<template>
    <Head title="Miembros y roles" />
    <AppLayout>
        <div class="members-page">
            <section class="members-heading flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="members-eyebrow">Accesos · {{ context?.league.name ?? 'Liga' }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">Miembros y roles</h1>
                    <p class="mt-2 text-sm text-slate-600">Gestiona los permisos y la participación de cada integrante de tu liga.</p>
                </div>
                <Link v-if="canCreateUser" href="/liga/miembros/crear-usuario" class="btn-primary inline-flex min-h-11 items-center justify-center gap-2 self-start">
                    <span aria-hidden="true" class="text-lg">+</span> Crear usuario
                </Link>
            </section>

            <section class="member-form-panel mt-6" aria-labelledby="assign-title">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div><p class="members-eyebrow">Administración de acceso</p><h2 id="assign-title" class="text-lg font-extrabold text-slate-900">Asignación de roles</h2></div>
                    <span class="members-count">{{ memberships.length }} asignaciones</span>
                </div>
                <form class="grid gap-4 lg:grid-cols-[1fr_1fr_1.2fr_auto]" @submit.prevent="form.post('/liga/miembros', { preserveScroll: true, onSuccess: () => form.reset() })">
                    <div><label class="form-label" for="member-email">Correo exacto del usuario</label><input id="member-email" v-model="form.email" class="form-input" type="email" required placeholder="usuario@correo.com" autocomplete="email"><FormError :message="form.errors.email" /></div>
                    <div><label class="form-label" for="member-role">Rol a asignar</label><select id="member-role" v-model="form.role_id" class="form-input" required><option :value="null" disabled>Selecciona un rol</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select><FormError :message="form.errors.role_id" /></div>
                    <div><label class="form-label" for="member-reason">Motivo de asignación</label><input id="member-reason" v-model="form.reason" class="form-input" required maxlength="500" placeholder="Describe el motivo"><FormError :message="form.errors.reason" /></div>
                    <div class="flex items-end"><button class="btn-primary min-h-11 w-full" type="submit" :disabled="form.processing">{{ form.processing ? 'Asignando...' : 'Asignar rol' }}</button></div>
                </form>
            </section>

            <section class="member-list-panel mt-6" aria-labelledby="members-title">
                <div class="members-toolbar">
                    <div><p class="members-eyebrow">Directorio</p><h2 id="members-title" class="text-lg font-extrabold text-slate-900">Roles asignados</h2></div>
                    <div class="members-filters">
                        <label class="sr-only" for="member-search">Buscar miembro</label>
                        <input id="member-search" v-model="search" type="search" class="member-filter-input" placeholder="Buscar nombre, correo o rol..." />
                        <label class="sr-only" for="member-role-filter">Filtrar por rol</label>
                        <select id="member-role-filter" v-model="roleFilter" class="member-filter-input"><option value="all">Todos los roles</option><option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option></select>
                        <label class="sr-only" for="member-status-filter">Filtrar por estado</label>
                        <select id="member-status-filter" v-model="statusFilter" class="member-filter-input"><option value="all">Todos los estados</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                    </div>
                </div>
                <div v-if="!memberships.length" class="p-12 text-center text-slate-600">No hay accesos asignados.</div>
                <div v-else-if="!filteredMemberships.length" class="p-12 text-center text-slate-600">No se encontraron miembros con los filtros seleccionados.</div>
                <template v-else>
                    <div class="hidden member-table-head md:grid"><span>Usuario</span><span>Rol actual</span><span>Estado</span><span>Gestionar acceso</span></div>
                    <div class="divide-y divide-slate-200/80">
                        <article v-for="membership in filteredMemberships" :key="membership.id" class="member-row">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="member-avatar" :title="membership.user.name">{{ initials(membership.user.name) }}</span>
                                <div class="min-w-0"><p class="truncate font-bold text-slate-900">{{ membership.user.name }}</p><p class="mt-0.5 truncate text-sm text-slate-500">{{ membership.user.email }}</p></div>
                            </div>
                            <div class="flex min-w-0 items-center gap-3"><span class="member-role-icon"><MemberRoleIcon :role="membership.role.name" :size="32" /></span><span class="text-sm font-semibold text-slate-800">{{ membership.role.name }}</span></div>
                            <div><span class="member-status" :class="membership.status === 'suspended' ? 'member-status-suspended' : isActive(membership.status) ? 'member-status-active' : 'member-status-inactive'">{{ label(membership.status) }}</span></div>
                            <div><button type="button" class="member-manage-button" :aria-label="`Gestionar acceso de ${membership.user.name}`" @click="openStatusEditor(membership)"><span>Gestionar acceso</span><span aria-hidden="true">→</span></button></div>
                        </article>
                    </div>
                </template>
                <footer class="members-list-footer">Mostrando {{ filteredMemberships.length }} de {{ memberships.length }} asignaciones</footer>
            </section>
        </div>

        <div v-if="editingMembership" class="member-modal-backdrop" @click.self="closeStatusEditor" @keydown.esc="closeStatusEditor">
            <section class="member-modal" role="dialog" aria-modal="true" aria-labelledby="change-access-title" tabindex="-1">
                <div class="member-modal-header">
                    <div>
                        <p class="members-eyebrow">Administración de acceso</p>
                        <h2 id="change-access-title" class="mt-1 text-xl font-extrabold text-slate-900">Modificar estado del miembro</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ editingMembership.user.name }} · {{ editingMembership.role.name }}</p>
                    </div>
                    <button type="button" class="member-close-button" aria-label="Cerrar" :disabled="statusSaving" @click="closeStatusEditor">×</button>
                </div>
                <div class="member-modal-body">
                    <p class="mb-3 text-sm font-bold text-slate-800">Selecciona el nuevo estado</p>
                    <div class="member-status-options">
                        <label v-for="choice in statusChoices" :key="choice.value" class="member-status-option" :class="[`choice-${choice.value}`, { 'member-status-option-selected': proposedStatus === choice.value }]">
                            <input v-model="proposedStatus" type="radio" name="membership-new-status" :value="choice.value" :disabled="statusSaving" />
                            <span class="member-choice-icon" aria-hidden="true">{{ choice.icon }}</span>
                            <span class="flex-1"><strong class="block text-sm">{{ choice.title }}</strong><small class="mt-0.5 block text-xs leading-relaxed text-slate-500">{{ choice.detail }}</small></span>
                        </label>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Estado actual: <strong>{{ label(editingMembership.status) }}</strong></p>
                    <label for="membership-change-reason" class="form-label mt-4 block">Motivo del cambio <span class="text-red-600">*</span></label>
                    <textarea id="membership-change-reason" v-model="statusReason" class="form-input member-reason-textarea" rows="3" maxlength="500" required :disabled="statusSaving" placeholder="Explica por qué se modifica el acceso; quedará registrado en la bitácora."></textarea>
                    <p v-if="statusError" role="alert" class="mt-2 text-sm font-semibold text-red-700">{{ statusError }}</p>
                </div>
                <div class="member-modal-footer">
                    <button type="button" class="member-cancel-button" :disabled="statusSaving" @click="closeStatusEditor">Cancelar</button>
                    <button type="button" class="btn-primary min-h-11" :disabled="statusSaving || proposedStatus === editingMembership.status || !statusReason.trim()" @click="saveStatus">{{ statusSaving ? 'Guardando…' : 'Guardar cambio' }}</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.members-page { position:relative; padding:clamp(14px,2.3vw,30px); border-radius:24px; background:linear-gradient(105deg,rgba(242,247,251,.93),rgba(246,250,251,.81)),url('/images/public/league-stadium.jpg') center/cover; box-shadow:inset 0 0 0 1px rgba(255,255,255,.55); }
.members-eyebrow { color:var(--color-league-600,#2e7d32); font-size:11px; text-transform:uppercase; letter-spacing:.18em; font-weight:800; }
.member-form-panel,.member-list-panel { background:rgba(255,255,255,.96); border-radius:18px; border:1px solid rgba(226,232,240,.95); box-shadow:0 12px 30px rgba(15,23,42,.08); }
.member-form-panel { padding:clamp(16px,2vw,26px);  }
.member-list-panel { overflow:hidden; }
.members-count { font-size:12px; font-weight:800; color:var(--color-league-600,#2e7d32); background:var(--color-league-50,#ecfdf5); padding:7px 11px; border-radius:999px; }
.members-toolbar { display:flex; align-items:end; justify-content:space-between; flex-wrap:wrap; gap:16px; padding:20px 22px; }
.members-filters { display:flex; gap:9px; flex-wrap:wrap; }
.member-filter-input { min-height:42px; border:1px solid #cbd5e1; background:#fff; border-radius:10px; padding:9px 12px; color:#334155; font-size:13px; max-width:100%; }
.member-filter-input:focus,.member-status-select:focus { outline:2px solid var(--color-league-600,#2e7d32); outline-offset:1px; }
.member-table-head,.member-row { grid-template-columns:minmax(190px,1.5fr) minmax(160px,1.15fr) minmax(100px,.6fr) minmax(155px,.8fr); align-items:center; gap:16px; }
.member-table-head { padding:13px 22px; background:color-mix(in srgb,var(--color-league-600,#2e7d32) 6%,white); font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#475569; }
.member-row { display:grid; padding:14px 22px; background:#fff; transition:background .2s; }
.member-row:hover { background:color-mix(in srgb,var(--color-league-600,#2e7d32) 4%,white); }
.member-avatar { display:grid; place-items:center; flex-shrink:0; width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--color-league-600,#2e7d32),var(--color-league-900,#1b2a4a)); color:#fff; font-size:12px; font-weight:800; box-shadow:0 2px 8px rgba(0,0,0,.12); }
.member-role-icon { display:grid; place-items:center; width:44px; height:44px; flex-shrink:0; border-radius:12px; background:var(--color-league-50,#eff6f0); color:var(--color-league-900,#1b2a4a); }
.member-status { display:inline-flex; padding:6px 11px; border-radius:9px; font-weight:800; font-size:12px; }
.member-status-active { color:#166534; background:#dcfce7; }.member-status-inactive { color:#475569; background:#e2e8f0; }.member-status-suspended { color:#991b1b; background:#fee2e2; }
.member-status-select { max-width:205px; font-size:13px; }
.members-list-footer { padding:14px 22px; border-top:1px solid #e2e8f0; font-size:12px; font-weight:600; color:#64748b; }

.member-manage-button { display:inline-flex; align-items:center; justify-content:space-between; gap:14px; width:100%; max-width:205px; min-height:43px; padding:9px 14px; border:1px solid color-mix(in srgb,var(--color-league-600,#2563eb) 28%,#e2e8f0); border-radius:11px; font-size:13px; font-weight:800; color:var(--color-league-600,#2563eb); background:#fff; cursor:pointer; transition:background .2s, box-shadow .2s; }
.member-manage-button:hover { background:var(--color-league-50,#eff6ff); box-shadow:0 4px 12px rgba(15,23,42,.08); }
.member-manage-button:focus-visible,.member-close-button:focus-visible,.member-status-option:focus-within { outline:2px solid var(--color-league-600,#2563eb); outline-offset:2px; }
.member-modal-backdrop { position:fixed; z-index:100; inset:0; display:grid; place-items:center; background:rgba(15,23,42,.6); padding:18px; overflow-y:auto; }
.member-modal { width:min(100%,540px); max-height:calc(100vh - 36px); overflow-y:auto; border-radius:20px; background:#fff; box-shadow:0 28px 75px rgba(0,0,0,.3); }
.member-modal-header { display:flex; justify-content:space-between; gap:16px; padding:24px 24px 16px; border-bottom:1px solid #e2e8f0; }
.member-close-button { flex-shrink:0; display:grid; place-items:center; width:32px; height:32px; border-radius:9px; background:#f1f5f9; color:#334155; font-size:24px; cursor:pointer; }
.member-modal-body { padding:20px 24px; }
.member-status-options { display:grid; gap:9px; }
.member-status-option { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1.5px solid #dbe3ed; border-radius:12px; cursor:pointer; transition:border-color .15s, background .15s; }
.member-status-option input { accent-color:var(--color-league-600,#2563eb); }
.member-status-option-selected { border-color:var(--color-league-600,#2563eb); background:var(--color-league-50,#eff6ff); }
.member-choice-icon { display:grid; flex:0 0 34px; place-items:center; height:34px; border-radius:10px; background:#dcfce7; color:#166534; font-size:18px; font-weight:900; }
.choice-inactive .member-choice-icon { background:#e2e8f0; color:#475569; }
.choice-suspended .member-choice-icon { background:#fee2e2; color:#991b1b; }
.member-reason-textarea { width:100%; min-height:92px; resize:vertical; }
.member-modal-footer { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:10px; padding:17px 24px 22px; border-top:1px solid #e2e8f0; }
.member-cancel-button { min-height:44px; padding:9px 18px; background:#f1f5f9; color:#334155; font-weight:750; border-radius:10px; cursor:pointer; }
.member-modal-footer button:disabled { opacity:.5; cursor:not-allowed; }

@media(max-width:767px) { .member-row { grid-template-columns:1fr 1fr; gap:16px; }.member-table-head { display:none; }.members-filters,.member-filter-input { width:100%; }.members-toolbar { align-items:stretch; }.member-status-select { max-width:100%; } }
@media(max-width:430px) { .member-row { grid-template-columns:1fr; }.members-page { padding:10px; }.members-toolbar { padding:16px; } }
@media(prefers-reduced-motion:reduce) { .member-row { transition:none; } }
</style>
