<script setup lang="ts">
import FormError from '../../../components/FormError.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import MemberRoleIcon from '../../../components/MemberRoleIcon.vue';
import type { AppPageProps, RoleOption, StatusOption } from '../../../types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../../../components/AppIcon.vue';

type UserOption = { id: number; name: string; email: string; status?: string; last_login_at?: string | null };
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
const roleKind = (name: string) => { const value = normalize(name); return value.includes('administr') ? 'admin' : value.includes('arbit') ? 'referee' : value.includes('represent') ? 'representative' : value.includes('jugador') ? 'player' : 'other'; };
const lastAccess = (value: string | null | undefined) => {
    if (!value) return 'Sin acceso registrado';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Sin acceso registrado';
    return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
};
const leagueInitials = computed(() => initials(context.value?.league.name ?? 'Liga'));


</script>

<template>
    <Head title="Miembros y roles" />
    <AppLayout>
        <div class="members-page">
            <header class="members-hero">
                <div class="members-hero-content">
                    <div class="members-hero-title">
                        <span class="members-hero-icon"><AppIcon name="users" :size="30" /></span>
                        <div class="min-w-0">
                            <p class="members-hero-eyebrow">Configuración · Control de accesos</p>
                            <h1>Miembros y roles</h1>
                            <p>Administra los usuarios, permisos y roles asignados a los integrantes de tu liga.</p>
                        </div>
                    </div>
                    <div class="members-hero-league">
                        <div class="members-hero-league-logo">
                            <img v-if="context?.league.logoUrl" :src="context.league.logoUrl" :alt="`Logotipo de ${context.league.name}`" />
                            <span v-else>{{ leagueInitials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="members-hero-active"><span class="members-live-dot"></span> Liga activa</p>
                            <p class="members-hero-league-name">{{ context?.league.name ?? 'Liga administrada' }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="member-form-panel mt-4" aria-labelledby="assign-title">
                <div class="member-section-header">
                    <div class="member-section-heading">
                        <span class="member-section-icon"><AppIcon name="users" :size="21" /></span>
                        <div><h2 id="assign-title">Asignación rápida de roles</h2><p>Asigna permisos a un usuario existente mediante su correo.</p></div>
                    </div>
                    <div class="member-section-actions">
                        <span class="members-count">{{ memberships.length }} asignaciones</span>
                        <Link v-if="canCreateUser" href="/liga/miembros/crear-usuario" class="member-create-button"><AppIcon name="users" :size="16"/> Crear usuario</Link>
                    </div>
                </div>
                <form class="member-assign-grid" @submit.prevent="form.post('/liga/miembros', { preserveScroll: true, onSuccess: () => form.reset() })">
                    <div><label class="form-label" for="member-email">Correo del usuario <span class="text-red-500">*</span></label><div class="member-input-wrap"><AppIcon name="mail" :size="17" /><input id="member-email" v-model="form.email" class="member-input" type="email" required placeholder="usuario@correo.com" autocomplete="email" /></div><FormError :message="form.errors.email" /></div>
                    <div><label class="form-label" for="member-role">Rol a asignar <span class="text-red-500">*</span></label><div class="member-input-wrap"><AppIcon name="users" :size="17"/><select id="member-role" v-model="form.role_id" class="member-input" required><option :value="null" disabled>Selecciona un rol</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div><FormError :message="form.errors.role_id" /></div>
                    <div><label class="form-label" for="member-reason">Motivo de asignación <span class="text-red-500">*</span></label><div class="member-input-wrap"><AppIcon name="file" :size="17"/><input id="member-reason" v-model="form.reason" class="member-input" required maxlength="500" placeholder="Describe brevemente el motivo" /></div><FormError :message="form.errors.reason" /></div>
                    <div class="flex items-end"><button class="member-assign-button" type="submit" :disabled="form.processing"><AppIcon name="users" :size="17"/> {{ form.processing ? 'Asignando...' : 'Asignar rol' }}</button></div>
                </form>
            </section>

            <section class="member-list-panel mt-4" aria-labelledby="members-title">
                <div class="members-toolbar">
                    <div class="member-section-heading">
                        <span class="member-section-icon"><AppIcon name="users" :size="21" /></span>
                        <div><h2 id="members-title">Directorio de miembros</h2><p>Usuarios con acceso a la liga y sus permisos.</p></div>
                    </div>
                    <p class="members-showing">Mostrando {{ filteredMemberships.length }} de {{ memberships.length }} registros</p>
                </div>
                <div class="members-filters">
                    <label class="member-filter-search"><AppIcon name="badge" :size="19"/><span class="sr-only">Buscar miembro</span><input id="member-search" v-model="search" type="search" placeholder="Buscar por nombre o correo..."/></label>
                    <label class="sr-only" for="member-role-filter">Filtrar por rol</label>
                    <select id="member-role-filter" v-model="roleFilter" class="member-filter-input"><option value="all">Todos los roles</option><option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option></select>
                    <label class="sr-only" for="member-status-filter">Filtrar por estado</label>
                    <select id="member-status-filter" v-model="statusFilter" class="member-filter-input"><option value="all">Todos los estados</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                </div>
                <div v-if="!memberships.length" class="p-12 text-center text-slate-600">No hay accesos asignados.</div>
                <div v-else-if="!filteredMemberships.length" class="p-12 text-center text-slate-600">No se encontraron miembros con los filtros seleccionados.</div>
                <template v-else>
                    <div class="hidden member-table-head lg:grid"><span>Usuario</span><span>Rol actual</span><span>Estado</span><span>Último acceso</span><span class="text-center">Acciones</span></div>
                    <div class="member-table-body">
                        <article v-for="membership in filteredMemberships" :key="membership.id" class="member-row">
                            <div class="member-user-cell"><span class="member-avatar" :class="`member-avatar-${roleKind(membership.role.name)}`">{{ initials(membership.user.name) }}</span><div class="min-w-0"><p class="truncate font-bold text-slate-900">{{ membership.user.name }}</p><p class="truncate text-xs text-slate-500">{{ membership.user.email }}</p></div></div>
                            <div class="member-table-cell"><span class="member-role-pill" :class="`member-role-${roleKind(membership.role.name)}`"><MemberRoleIcon :role="membership.role.name" :size="19"/><span>{{ membership.role.name }}</span></span></div>
                            <div class="member-table-cell"><span class="member-status" :class="membership.status === 'suspended' ? 'member-status-suspended' : isActive(membership.status) ? 'member-status-active' : 'member-status-inactive'"><span class="status-dot"></span>{{ label(membership.status) }}</span></div>
                            <div class="member-table-cell member-last-access"><AppIcon name="calendar" :size="15"/><span>{{ lastAccess(membership.user.last_login_at) }}</span></div>
                            <div class="member-table-actions"><button type="button" class="member-menu-button" :aria-label="`Gestionar acceso de ${membership.user.name}`" :title="`Gestionar acceso de ${membership.user.name}`" @click="openStatusEditor(membership)"><AppIcon name="menu" :size="20"/></button></div>
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
.members-page { padding: clamp(12px,2vw,28px); border-radius:26px; background:linear-gradient(rgba(236,244,251,.81),rgba(237,246,250,.79)),url('/images/public/league-stadium.jpg') center / cover; }
.members-hero { position:relative; overflow:hidden; border-radius:23px; color:white; background:linear-gradient(100deg, color-mix(in srgb,var(--color-league-900,#0d2651) 96%,#08112a) 0%,color-mix(in srgb,var(--color-league-600,#235abb) 83%,#05234c) 57%,color-mix(in srgb,var(--color-league-600,#235abb) 75%,#008b94) 100%),url('/images/public/league-stadium.jpg') center 42% / cover; box-shadow:0 13px 28px rgba(15,35,70,.17); }
.members-hero::before { content:''; position:absolute; inset:0; background:url('/images/public/league-stadium.jpg') center 52% / cover; opacity:.23; mix-blend-mode:screen; pointer-events:none; }
.members-hero-content { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; gap:30px; padding:24px 30px; }
.members-hero-title { display:flex; align-items:center; gap:22px; min-width:0; }
.members-hero-icon { display:grid; flex:0 0 auto; place-items:center; width:66px; height:66px; border-radius:17px; color:#fff; background:rgba(255,255,255,.16); }
.members-hero-eyebrow { font-size:11px; letter-spacing:.18em; text-transform:uppercase; font-weight:800; color:rgba(255,255,255,.84); }
.members-hero h1 { margin:6px 0 4px; font-size:clamp(24px,2.2vw,34px); line-height:1.12; font-weight:800; letter-spacing:-.025em; }
.members-hero-title p:last-child { color:rgba(255,255,255,.88); font-size:13px; }
.members-hero-league { display:flex; flex:0 0 29%; align-items:center; gap:14px; min-width:0; border-left:1px solid rgba(255,255,255,.35); padding-left:24px; }
.members-hero-league-logo { display:grid; place-items:center; height:50px; width:50px; flex-shrink:0; border-radius:13px; background:#fff; color:var(--color-league-900,#142650); font-size:13px; font-weight:800; overflow:hidden; }
.members-hero-league-logo img { max-height:100%; max-width:100%; object-fit:contain; padding:5px; }
.members-hero-active { display:flex; align-items:center; gap:7px; text-transform:uppercase; letter-spacing:.07em; color:#9ff5b4; font-size:11px; font-weight:800; }
.members-live-dot { width:7px; height:7px; border-radius:50%; background:#25d366; }
.members-hero-league-name { margin-top:5px; font-size:13px; font-weight:800; line-height:1.35; }
.member-form-panel,.member-list-panel { background:rgba(255,255,255,.97); border:1px solid #e1e8f3; border-radius:22px; box-shadow:0 13px 26px rgba(15,23,42,.09); }
.member-form-panel { padding:19px 23px 22px; }
.member-section-header,.members-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.member-section-heading { display:flex; align-items:center; gap:13px; }
.member-section-heading h2 { font-size:17px; line-height:1.3; font-weight:800; color:#111d3d; }
.member-section-heading p { margin-top:2px; font-size:12px; color:#64748b; }
.member-section-icon { display:grid; width:43px; height:43px; flex-shrink:0; place-items:center; background:color-mix(in srgb,var(--color-league-600,#2563eb) 11%,white); border-radius:12px; color:var(--color-league-600,#2563eb); }
.member-section-actions { display:flex; align-items:center; gap:13px; }
.members-count { color:var(--color-league-600,#2563eb); font-size:11px; font-weight:800; background:color-mix(in srgb,var(--color-league-600,#2563eb) 10%,white); border-radius:20px; padding:7px 12px; }
.member-create-button { display:inline-flex; align-items:center; gap:8px; border:1px solid #ccd8eb; border-radius:11px; color:#172b50; padding:9px 14px; font-size:12px; font-weight:800; transition:background .2s; }
.member-create-button:hover { background:#f1f5fb; }
.member-assign-grid { display:grid; grid-template-columns:1fr 1fr 1.16fr auto; align-items:start; gap:16px; margin-top:18px; }
.member-assign-grid .form-label { display:block; margin-bottom:8px; font-size:12px; font-weight:750; color:#203455; }
.member-input-wrap { display:flex; align-items:center; gap:10px; min-height:42px; padding:0 12px; color:#34567e; border:1px solid #bfcee2; border-radius:11px; background:white; }
.member-input { background:transparent; border:none; min-width:0; width:100%; min-height:40px; color:#1b2a4a; font-size:13px; outline:none; }
.member-input-wrap:focus-within { outline:2px solid color-mix(in srgb,var(--color-league-600,#2563eb) 30%,transparent); border-color:var(--color-league-600,#2563eb); }
.member-assign-button { min-height:42px; display:flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; padding:10px 16px; color:white; background:linear-gradient(135deg,var(--color-league-600,#2563eb),var(--color-league-900,#1b2a4a)); border-radius:11px; font-size:13px; font-weight:800; }
.member-assign-button:disabled { opacity:.6; cursor:wait; }
.members-toolbar { padding:20px 23px 13px; }
.members-showing { font-size:12px; font-weight:600; color:#64748b; }
.members-filters { display:grid; grid-template-columns:minmax(200px,2fr) minmax(150px,.8fr) minmax(150px,.8fr); gap:16px; padding:7px 22px 16px; }
.member-filter-search { display:flex; align-items:center; gap:11px; border:1px solid #cbd8e9; border-radius:10px; padding:0 12px; color:#33577e; }
.member-filter-search input { min-height:42px; width:100%; border:none; background:transparent; font-size:13px; outline:none; }
.member-filter-input { min-height:44px; border:1px solid #cbd8e9; border-radius:10px; background:white; padding:0 12px; color:#203455; font-size:13px; }
.member-table-head,.member-row { display:grid; grid-template-columns:minmax(215px,2fr) minmax(175px,1.55fr) minmax(105px,.78fr) minmax(155px,1fr) 67px; align-items:center; gap:12px; }
.member-table-head { padding:11px 22px; background:#f5f7fb; font-size:11px; text-transform:uppercase; font-weight:800; color:#5d6d85; }
.member-table-body { padding:0 22px 15px; }
.member-row { padding:10px 0; border-bottom:1px solid #e8edf5; }
.member-row:last-child { border-bottom:0; }
.member-row:hover { background:color-mix(in srgb,var(--color-league-600,#2563eb) 3%,white); }
.member-user-cell { display:flex; align-items:center; gap:12px; min-width:0; }
.member-avatar { width:36px; height:36px; border-radius:12px; flex:0 0 auto; display:grid; place-items:center; color:white; font-size:11px; font-weight:800; background:linear-gradient(135deg,var(--color-league-600,#2563eb),var(--color-league-900,#1b2a4a)); }
.member-avatar-admin { background:linear-gradient(135deg,#9464dc,#7847c9); }
.member-avatar-representative { background:linear-gradient(135deg,#63b883,#2e9c59); }
.member-avatar-referee { background:linear-gradient(135deg,#ffbb24,#f28219); }
.member-avatar-player { background:linear-gradient(135deg,#468edc,#2359b8); }
.member-table-cell { min-width:0; }
.member-role-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:5px 10px; font-size:11px; line-height:1.35; font-weight:800; }
.member-role-admin { color:#214cca; background:#e7edff; }.member-role-representative { color:#176a39; background:#e1f7e7; }.member-role-referee { color:#a65e00; background:#fff1d0; }.member-role-player { color:#3755b5; background:#edf1ff; }.member-role-other { color:var(--color-league-600,#2563eb); background:#edf2f8; }
.member-status { display:inline-flex; align-items:center; gap:7px; padding:5px 10px; border-radius:30px; font-weight:800; font-size:11px; }
.status-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
.member-status-active { color:#15772c; background:#dafbe3; }.member-status-inactive { color:#526174; background:#e7edf5; }.member-status-suspended { color:#b52222; background:#ffe4e6; }
.member-last-access { display:flex; align-items:center; gap:8px; font-size:12px; color:#536680; }
.member-table-actions { text-align:center; }
.member-menu-button { width:37px; height:31px; display:inline-grid; place-items:center; border:1px solid #d9e2ef; border-radius:10px; color:#435878; }
.member-menu-button:hover { background:var(--color-league-50,#eff6ff); color:var(--color-league-600,#2563eb); }
.members-list-footer { padding:11px 23px; border-top:1px solid #eef2f7; font-size:11px; color:#64748b; }
.members-eyebrow { color:var(--color-league-600,#2563eb); font-size:11px; text-transform:uppercase; letter-spacing:.18em; font-weight:800; }
.member-modal-backdrop { position:fixed; z-index:100; inset:0; display:grid; place-items:center; background:rgba(15,23,42,.6); padding:18px; overflow-y:auto; }
.member-modal { width:min(100%,540px); max-height:calc(100vh - 36px); overflow-y:auto; border-radius:20px; background:#fff; box-shadow:0 28px 75px rgba(0,0,0,.3); }
.member-modal-header { display:flex; justify-content:space-between; gap:16px; padding:24px 24px 16px; border-bottom:1px solid #e2e8f0; }
.member-close-button { flex-shrink:0; display:grid; place-items:center; width:32px; height:32px; border-radius:9px; background:#f1f5f9; color:#334155; font-size:24px; cursor:pointer; }
.member-modal-body { padding:20px 24px; }
.member-status-options { display:grid; gap:9px; }
.member-status-option { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1.5px solid #dbe3ed; border-radius:12px; cursor:pointer; transition:border-color .15s, background .15s; }
.member-status-option input { accent-color:var(--color-league-600,#2563eb); }
.member-status-option-selected { border-color:var(--color-league-600,#2563eb); background:color-mix(in srgb,var(--color-league-600,#2563eb) 7%,white); }
.member-choice-icon { display:grid; flex:0 0 34px; place-items:center; height:34px; border-radius:10px; background:#dcfce7; color:#166534; font-size:18px; font-weight:900; }
.choice-inactive .member-choice-icon { background:#e2e8f0; color:#475569; }.choice-suspended .member-choice-icon { background:#fee2e2; color:#991b1b; }
.member-reason-textarea { width:100%; min-height:92px; resize:vertical; }
.member-modal-footer { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:10px; padding:17px 24px 22px; border-top:1px solid #e2e8f0; }
.member-cancel-button { min-height:44px; padding:9px 18px; background:#f1f5f9; color:#334155; font-weight:750; border-radius:10px; cursor:pointer; }
.member-modal-footer button:disabled { opacity:.5; cursor:not-allowed; }
@media(max-width:1050px) { .member-assign-grid { grid-template-columns:1fr 1fr; }.member-table-head,.member-row { grid-template-columns:minmax(180px,1.7fr) minmax(140px,1.2fr) minmax(85px,.7fr) 65px; } .member-last-access,.member-table-head span:nth-child(4) { display:none; } }
@media(max-width:767px) { .members-hero-content { padding:24px 19px; flex-direction:column; align-items:flex-start; gap:18px; }.members-hero-league { border-left:0; padding-left:0; flex-basis:auto; }.members-hero-title { align-items:flex-start; gap:12px; }.members-hero-icon { width:48px; height:48px; }.member-assign-grid,.members-filters { grid-template-columns:1fr; }.member-form-panel { padding:17px; }.members-toolbar { padding:17px; }.members-filters { padding:8px 17px 15px; gap:9px; }.member-table-head { display:none; }.member-table-body { padding:0 17px 14px; }.member-row { grid-template-columns:1fr 1fr; gap:13px; padding:16px 0; }.member-user-cell { grid-column:span 2; }.member-table-actions { text-align:right; } }
@media(max-width:430px) { .member-row { grid-template-columns:1fr; }.member-user-cell { grid-column:auto; }.member-table-actions { text-align:left; }.members-page { padding:8px; }.member-section-actions { width:100%; flex-wrap:wrap; } }
@media(prefers-reduced-motion:reduce) { .member-row,.member-status-option { transition:none; } }
</style>