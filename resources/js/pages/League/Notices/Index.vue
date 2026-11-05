<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Simple = { id: number; name: string };
type Role = { slug: string; name: string };
type Creator = { id: number; name: string } | null;
type Notice = {
    id: number;
    title: string;
    body: string;
    category: string;
    priority: string;
    status: string;
    audience_type: string;
    audience_role_slugs: string[] | null;
    audience_team_ids: number[] | null;
    published_at: string | null;
    expires_at: string | null;
    withdrawn_at: string | null;
    created_at: string;
    creator: Creator;
    recipients_count?: number;
    read_count?: number;
};
type Recipient = { id: number; read_at: string | null; delivered_at: string; notice: Notice };
type Pagination = { data: Recipient[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
type Preferences = { general_enabled: boolean; schedule_enabled: boolean; finance_enabled: boolean; discipline_enabled: boolean };

const props = defineProps<{
    inbox: Pagination;
    managedNotices: Notice[];
    permissions: { manage: boolean };
    roles: Role[];
    teams: Simple[];
    preferences: Preferences;
}>();

const tab = ref<'inbox' | 'manage' | 'preferences'>('inbox');
const editorOpen = ref(false);
const selectedRecipient = ref<Recipient | null>(null);
const editing = ref<Notice | null>(null);
const teamSearch = ref('');
const categoryLabels: Record<string, string> = { general: 'General', schedule: 'Calendario', finance: 'Pagos', discipline: 'Disciplina' };
const priorityLabels: Record<string, string> = { normal: 'Normal', important: 'Importante', urgent: 'Urgente' };
const statusLabels: Record<string, string> = { draft: 'Borrador', published: 'Publicado', withdrawn: 'Retirado' };
const preferenceOptions: { key: keyof Preferences; title: string; text: string; icon: string }[] = [
    { key: 'general_enabled', title: 'Avisos generales', text: 'Comunicados y asuntos administrativos.', icon: 'bell' },
    { key: 'schedule_enabled', title: 'Calendario', text: 'Jornadas, partidos, campos y horarios.', icon: 'calendar' },
    { key: 'finance_enabled', title: 'Pagos', text: 'Cobros, vencimientos y recibos.', icon: 'wallet' },
    { key: 'discipline_enabled', title: 'Disciplina', text: 'Sanciones, apelaciones y resoluciones.', icon: 'gavel' },
];
const roleLabels = computed(() => Object.fromEntries(props.roles.map((role) => [role.slug, role.name])));
const teamLabels = computed(() => Object.fromEntries(props.teams.map((team) => [team.id, team.name])));
const filteredTeams = computed(() => props.teams.filter((team) => team.name.toLowerCase().includes(teamSearch.value.toLowerCase())));
const unreadCount = computed(() => props.inbox.data.filter((recipient) => !recipient.read_at).length);
function readPercent(notice: Notice): number {
    const total = notice.recipients_count ?? 0;
    return total > 0 ? Math.min(100, Math.round(((notice.read_count ?? 0) / total) * 100)) : 0;
}
function iconForCategory(category: string): string {
    return ({ general: 'bell', schedule: 'calendar', finance: 'wallet', discipline: 'gavel' } as Record<string,string>)[category] ?? 'bell';
}
function iconForPriority(priority: string): string {
    return priority === 'urgent' ? 'bell' : priority === 'important' ? 'badge' : 'clock';
}
function openDetail(recipient: Recipient): void { selectedRecipient.value = recipient; }


const noticeForm = useForm({
    title: '',
    body: '',
    category: 'general',
    priority: 'normal',
    audience_type: 'all',
    audience_role_slugs: [] as string[],
    audience_team_ids: [] as number[],
    expires_at: '',
});
const preferencesForm = useForm<Preferences>({ ...props.preferences });

function dateTime(value: string | null): string {
    return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin vencimiento';
}

function localDateTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}

function audienceLabel(notice: Notice): string {
    if (notice.audience_type === 'all') return 'Toda la liga';
    if (notice.audience_type === 'roles') return (notice.audience_role_slugs ?? []).map((slug) => roleLabels.value[slug] ?? slug).join(', ');
    return (notice.audience_team_ids ?? []).map((id) => teamLabels.value[id] ?? `Equipo #${id}`).join(', ');
}

function openCreate(): void {
    editing.value = null;
    noticeForm.reset();
    noticeForm.clearErrors();
    noticeForm.category = 'general';
    noticeForm.priority = 'normal';
    noticeForm.audience_type = 'all';
    teamSearch.value = '';
    editorOpen.value = true;
}

function openEdit(notice: Notice): void {
    editing.value = notice;
    noticeForm.clearErrors();
    noticeForm.title = notice.title;
    noticeForm.body = notice.body;
    noticeForm.category = notice.category;
    noticeForm.priority = notice.priority;
    noticeForm.audience_type = notice.audience_type;
    noticeForm.audience_role_slugs = [...(notice.audience_role_slugs ?? [])];
    noticeForm.audience_team_ids = [...(notice.audience_team_ids ?? [])];
    noticeForm.expires_at = localDateTime(notice.expires_at);
    teamSearch.value = '';
    editorOpen.value = true;
}

function submitNotice(): void {
    const options = { preserveScroll: true, onSuccess: () => { editorOpen.value = false; editing.value = null; noticeForm.reset(); } };
    if (editing.value) noticeForm.put(`/liga/avisos/${editing.value.id}`, options);
    else noticeForm.post('/liga/avisos', options);
}

function publish(notice: Notice): void {
    if (!window.confirm(`¿Publicar “${notice.title}”? Los destinatarios recibirán el aviso inmediatamente.`)) return;
    router.post(`/liga/avisos/${notice.id}/publicar`, {}, {
        preserveScroll: true,
        onError: (errors) => window.alert(Object.values(errors)[0] ?? 'No fue posible publicar el aviso.'),
    });
}

function withdraw(notice: Notice): void {
    if (!window.confirm(`¿Retirar “${notice.title}”? Dejará de mostrarse a los destinatarios.`)) return;
    router.post(`/liga/avisos/${notice.id}/retirar`, {}, {
        preserveScroll: true,
        onError: (errors) => window.alert(Object.values(errors)[0] ?? 'No fue posible retirar el aviso.'),
    });
}

function markRead(recipient: Recipient): void {
    router.post(`/liga/avisos/${recipient.id}/leer`, {}, { preserveScroll: true });
}

function markAllRead(): void { router.post('/liga/avisos/leer-todos', {}, { preserveScroll: true }); }
function savePreferences(): void { preferencesForm.put('/liga/avisos/preferencias', { preserveScroll: true }); }
</script>

<template>
    <Head title="Avisos y notificaciones" />
    <AppLayout>
        <section class="notices-page mx-auto max-w-7xl pb-12">
            <header class="notices-heading flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-league-600">Módulo 16</p>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Avisos y notificaciones</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">Comunicación interna de la liga con audiencias controladas, preferencias personales y constancia de lectura.</p>
                </div>
                <button v-if="permissions.manage" class="btn-primary inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-xl px-6" type="button" @click="openCreate"><span class="text-xl leading-none">＋</span> Crear aviso</button>
            </header>

            <nav class="notice-tabs mt-7 flex flex-wrap gap-2 border-b border-slate-200 pb-0" aria-label="Secciones de avisos">
                <button type="button" class="notice-tab" :class="{ 'notice-tab--active': tab === 'inbox' }" :aria-current="tab === 'inbox' ? 'page' : undefined" @click="tab = 'inbox'"><AppIcon name="mail" :size="20" /> Mi bandeja <span v-if="unreadCount" class="rounded-full bg-league-100 px-2 text-xs font-bold text-league-700">{{ unreadCount }}</span></button>
                <button v-if="permissions.manage" type="button" class="notice-tab" :class="{ 'notice-tab--active': tab === 'manage' }" :aria-current="tab === 'manage' ? 'page' : undefined" @click="tab = 'manage'"><AppIcon name="database" :size="20" /> Administrar avisos</button>
                <button type="button" class="notice-tab" :class="{ 'notice-tab--active': tab === 'preferences' }" :aria-current="tab === 'preferences' ? 'page' : undefined" @click="tab = 'preferences'"><AppIcon name="sliders" :size="20" /> Preferencias</button>
            </nav>

            <div v-if="tab === 'inbox'" class="mt-7">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium text-slate-600">{{ inbox.total }} avisos disponibles.</p>
                    <button v-if="unreadCount" class="btn-secondary inline-flex items-center gap-2 text-sm" type="button" @click="markAllRead"><AppIcon name="check" :size="16" /> Marcar todos como leídos</button>
                </div>
                <div v-if="!inbox.data.length" class="notice-card p-12 text-center text-slate-500">No tienes avisos vigentes en esta liga.</div>
                <div v-else class="space-y-5">
                    <article v-for="recipient in inbox.data" :key="recipient.id" class="notice-card inbox-card" :class="!recipient.read_at ? 'inbox-card--unread' : ''">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="notice-pill" :class="`priority-${recipient.notice.priority}`"><AppIcon :name="iconForPriority(recipient.notice.priority)" :size="15" />{{ priorityLabels[recipient.notice.priority] }}</span>
                                <span class="notice-pill notice-pill--category"><AppIcon :name="iconForCategory(recipient.notice.category)" :size="15" />{{ categoryLabels[recipient.notice.category] }}</span>
                            </div>
                            <span class="notice-read-state" :class="recipient.read_at ? 'notice-read-state--read' : 'notice-read-state--unread'"> <span v-if="!recipient.read_at" class="h-2 w-2 rounded-full bg-blue-600"></span><AppIcon v-else name="check" :size="15" />{{ recipient.read_at ? 'Leído' : 'No leído' }}</span>
                        </div>
                        <h2 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900">{{ recipient.notice.title }}</h2>
                        <p class="mt-2 line-clamp-3 whitespace-pre-line text-base leading-7 text-slate-600">{{ recipient.notice.body }}</p>
                        <div class="mt-6 flex flex-col gap-4 border-t border-slate-100 pt-4 lg:flex-row lg:items-center lg:justify-between">
                            <p class="inline-flex flex-wrap items-center gap-2 text-sm text-slate-500"><AppIcon name="calendar" :size="17" /> Publicado {{ dateTime(recipient.notice.published_at) }} · por {{ recipient.notice.creator?.name ?? 'Administración de la liga' }}<span v-if="recipient.notice.expires_at"> · vence {{ dateTime(recipient.notice.expires_at) }}</span></p>
                            <div class="flex flex-wrap items-center gap-3">
                                <button v-if="!recipient.read_at" type="button" class="notice-text-action" @click="markRead(recipient)"><AppIcon name="mail" :size="18" /> Marcar como leído</button>
                                <span class="hidden h-7 w-px bg-slate-200 sm:block"></span>
                                <button type="button" class="notice-text-action notice-text-action--blue" @click="openDetail(recipient)"><AppIcon name="file" :size="18" /> Ver detalle</button>
                            </div>
                        </div>
                    </article>
                </div>
                <nav v-if="inbox.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1"><Link v-for="link in inbox.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg border px-3 py-2 text-sm" :class="[link.active ? 'border-league-600 bg-league-600 text-white' : 'border-slate-200 bg-white', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-scroll><span v-html="link.label"></span></Link></nav>
            </div>

            <div v-if="tab === 'manage' && permissions.manage" class="mt-7">
                <div v-if="!managedNotices.length" class="notice-card p-12 text-center text-slate-500">Todavía no hay avisos. Crea el primero como borrador.</div>
                <div v-else class="space-y-4">
                    <article v-for="notice in managedNotices" :key="notice.id" class="notice-card admin-card">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="notice-pill" :class="notice.status === 'published' ? 'notice-pill--published' : notice.status === 'withdrawn' ? 'notice-pill--withdrawn' : 'notice-pill--draft'"><AppIcon :name="notice.status === 'published' ? 'check' : 'file'" :size="15" />{{ statusLabels[notice.status] }}</span>
                                    <span class="notice-pill notice-pill--category"><AppIcon :name="iconForCategory(notice.category)" :size="15" />{{ categoryLabels[notice.category] }}</span>
                                    <span class="notice-pill" :class="`priority-${notice.priority}`"><AppIcon :name="iconForPriority(notice.priority)" :size="15" />{{ priorityLabels[notice.priority] }}</span>
                                </div>
                                <h2 class="mt-3 text-xl font-bold text-slate-950">{{ notice.title }}</h2>
                                <p class="mt-1 line-clamp-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ notice.body }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <button v-if="notice.status === 'draft'" type="button" class="btn-secondary inline-flex gap-2" @click="openEdit(notice)"><AppIcon name="edit" :size="17" /> Editar</button>
                                <button v-if="notice.status === 'draft'" type="button" class="btn-primary" @click="publish(notice)">Publicar</button>
                                <button v-if="notice.status === 'published'" type="button" class="notice-retire" @click="withdraw(notice)"><AppIcon name="logout" :size="17" /> Retirar</button>
                            </div>
                        </div>
                        <div class="admin-meta mt-5">
                            <div class="admin-meta-cell"><AppIcon name="users" :size="21" /><div><strong>Audiencia</strong><span>{{ audienceLabel(notice) }}</span></div></div>
                            <div class="admin-meta-cell"><AppIcon name="calendar" :size="21" /><div><strong>Vencimiento</strong><span>{{ dateTime(notice.expires_at) }}</span></div></div>
                            <div class="admin-meta-cell"><AppIcon name="user" :size="21" /><div><strong>Creado por</strong><span>{{ notice.creator?.name ?? 'Usuario eliminado' }}<small>{{ dateTime(notice.created_at) }}</small></span></div></div>
                            <div class="admin-meta-cell admin-meta-cell--delivery"><AppIcon name="mail" :size="21" /><div class="min-w-0 flex-1"><strong>Entrega</strong><span v-if="notice.status !== 'draft'">{{ notice.recipients_count ?? 0 }} destinatarios · {{ notice.read_count ?? 0 }} lecturas</span><span v-else>Aún no se ha publicado</span><div v-if="notice.status !== 'draft'" class="progress-track mt-2"><div class="progress-fill" :style="{ width: `${readPercent(notice)}%` }"></div></div></div><span v-if="notice.status !== 'draft'" class="font-bold text-emerald-700">{{ readPercent(notice) }}%</span></div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="tab === 'preferences'" class="mt-7">
                <form class="notice-card preferences-panel" @submit.prevent="savePreferences">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><h2 class="text-2xl font-bold tracking-tight text-slate-950">Preferencias personales</h2><p class="mt-2 text-sm leading-6 text-slate-600">Estas opciones afectan avisos futuros. Los avisos urgentes siempre se entregan y no pueden silenciarse.</p></div>
                        <span class="notice-pill priority-urgent"><AppIcon name="bell" :size="17" /> Urgentes siempre activos</span>
                    </div>
                    <div class="mt-6 space-y-3">
                        <label v-for="item in preferenceOptions" :key="item.key" class="preference-row">
                            <span class="preference-icon"><AppIcon :name="item.icon" :size="26" /></span>
                            <span class="min-w-0 flex-1"><strong class="block text-base font-bold text-slate-900">{{ item.title }}</strong><small class="mt-1 block text-sm text-slate-600">{{ item.text }}</small></span>
                            <span class="toggle-shell"><input v-model="preferencesForm[item.key]" type="checkbox" class="toggle-input" :aria-label="`Activar ${item.title}`"><span class="toggle-slider" aria-hidden="true"></span></span>
                        </label>
                    </div>
                    <button class="btn-primary mt-6 inline-flex min-h-12 items-center gap-2 rounded-xl px-6" :disabled="preferencesForm.processing"><AppIcon name="check" :size="20" /> Guardar preferencias</button>
                </form>
            </div>
        </section>

        <div v-if="selectedRecipient" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="selectedRecipient = null">
            <section class="w-full max-w-2xl rounded-[26px] border border-white bg-white p-6 shadow-2xl sm:p-8" role="dialog" aria-modal="true" aria-label="Detalle del aviso">
                <div class="flex items-start justify-between gap-4"><div class="flex flex-wrap gap-2"><span class="notice-pill" :class="`priority-${selectedRecipient.notice.priority}`">{{ priorityLabels[selectedRecipient.notice.priority] }}</span><span class="notice-pill notice-pill--category">{{ categoryLabels[selectedRecipient.notice.category] }}</span></div><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Cerrar detalle" @click="selectedRecipient = null">✕</button></div>
                <h2 class="mt-5 text-2xl font-bold text-slate-900">{{ selectedRecipient.notice.title }}</h2>
                <p class="mt-4 max-h-[45vh] overflow-y-auto whitespace-pre-line text-base leading-7 text-slate-700">{{ selectedRecipient.notice.body }}</p>
                <p class="mt-6 border-t border-slate-200 pt-4 text-sm text-slate-500">Publicado {{ dateTime(selectedRecipient.notice.published_at) }} por {{ selectedRecipient.notice.creator?.name ?? 'Administración de la liga' }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-2"><button class="btn-secondary" type="button" @click="selectedRecipient = null">Cerrar</button><button v-if="!selectedRecipient.read_at" class="btn-primary" type="button" @click="markRead(selectedRecipient); selectedRecipient = null">Marcar como leído</button></div>
            </section>
        </div>
        <div v-if="editorOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="editorOpen = false">
            <form class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-[26px] border border-white/80 bg-white p-6 shadow-2xl sm:p-8" @submit.prevent="submitNotice">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ editing ? 'Editar borrador' : 'Crear aviso' }}</h2>
                <p class="mt-1 text-sm text-slate-500">El aviso se guardará como borrador; la entrega solo ocurre al publicarlo.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="form-label">Título</label><input v-model="noticeForm.title" class="form-input" required minlength="5" maxlength="150"><FormError :message="noticeForm.errors.title" /></div>
                    <div class="sm:col-span-2"><label class="form-label">Mensaje</label><textarea v-model="noticeForm.body" class="form-input min-h-36" required minlength="10" maxlength="5000"></textarea><FormError :message="noticeForm.errors.body" /></div>
                    <div><label class="form-label">Categoría</label><select v-model="noticeForm.category" class="form-input"><option value="general">General</option><option value="schedule">Calendario</option><option value="finance">Pagos</option><option value="discipline">Disciplina</option></select></div>
                    <div><label class="form-label">Prioridad</label><select v-model="noticeForm.priority" class="form-input"><option value="normal">Normal</option><option value="important">Importante</option><option value="urgent">Urgente (obligatorio)</option></select></div>
                    <div><label class="form-label">Audiencia</label><select v-model="noticeForm.audience_type" class="form-input"><option value="all">Toda la liga</option><option value="roles">Roles seleccionados</option><option value="teams">Equipos seleccionados</option></select></div>
                    <div><label class="form-label">Vence (opcional)</label><input v-model="noticeForm.expires_at" class="form-input" type="datetime-local"><FormError :message="noticeForm.errors.expires_at" /></div>
                    <div v-if="noticeForm.audience_type === 'roles'" class="sm:col-span-2">
                        <p class="form-label">Roles destinatarios</p>
                        <div class="grid gap-2 sm:grid-cols-2"><label v-for="role in roles" :key="role.slug" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input v-model="noticeForm.audience_role_slugs" type="checkbox" :value="role.slug"><span class="text-sm font-medium">{{ role.name }}</span></label></div>
                        <FormError :message="noticeForm.errors.audience_role_slugs" />
                    </div>
                    <div v-if="noticeForm.audience_type === 'teams'" class="sm:col-span-2">
                        <label class="form-label">Equipos destinatarios</label>
                        <input v-model="teamSearch" class="form-input" placeholder="Buscar equipo">
                        <div class="mt-3 grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2"><label v-for="team in filteredTeams" :key="team.id" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"><input v-model="noticeForm.audience_team_ids" type="checkbox" :value="team.id"><span class="text-sm font-medium">{{ team.name }}</span></label></div>
                        <FormError :message="noticeForm.errors.audience_team_ids" />
                    </div>
                    <FormError class="sm:col-span-2" :message="noticeForm.errors.audience_type" />
                </div>
                <div class="mt-6 flex justify-end gap-2"><button class="btn-secondary" type="button" @click="editorOpen = false">Cancelar</button><button class="btn-primary" :disabled="noticeForm.processing">Guardar borrador</button></div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.notices-page { position: relative; }
.notice-tabs { gap: .35rem; }
.notice-tab { display: inline-flex; align-items: center; gap: .65rem; border-bottom: 3px solid transparent; border-radius: 14px 14px 0 0; padding: .9rem 1.1rem; font-size: .95rem; font-weight: 650; color: #52637c; transition: .18s ease; }
.notice-tab:hover { background: rgba(221,234,251,.45); color: #164c91; }
.notice-tab--active { background: #e8f0ff; border-bottom-color: #1767de; color: #0759cd; }
.notice-card { border: 1px solid #dce5f0; border-radius: 23px; background: rgba(255,255,255,.97); box-shadow: 0 13px 30px rgba(22, 49, 88, .075); }
.inbox-card { position: relative; padding: 1.5rem 1.7rem; }
.inbox-card--unread { border-left: 6px solid #1767ee; }
.notice-pill { display: inline-flex; align-items: center; gap: .45rem; border-radius: 999px; padding: .44rem .8rem; font-size: .8rem; font-weight: 700; white-space: nowrap; background: #f0f4fa; color: #3e526e; }
.priority-urgent { background: #fce9e9; color: #c92323; }
.priority-important { background: #fff0cf; color: #9c5800; }
.priority-normal { background: #eef2f6; color: #46556b; }
.notice-pill--category { background: #eaf1ff; color: #075bd1; }
.notice-pill--published { background: #dcfae8; color: #116c37; }
.notice-pill--withdrawn { background: #e9edf2; color: #59677b; }
.notice-pill--draft { background: #fff2d8; color: #9f6500; }
.notice-read-state { display: inline-flex; align-items: center; gap: .5rem; border-radius: 999px; padding: .4rem .75rem; font-size: .85rem; font-weight: 700; }
.notice-read-state--unread { background: #edf4ff; color: #075bd1; }
.notice-read-state--read { background: #f0f3f7; color: #4d5e76; }
.notice-text-action { display: inline-flex; align-items: center; gap: .55rem; color: #50617b; font-size: .87rem; font-weight: 650; white-space: nowrap; }
.notice-text-action--blue { color: #0962da; }
.notice-text-action:hover { text-decoration: underline; }
.admin-card { padding: 1.5rem 1.6rem; }
.notice-retire { display: inline-flex; align-items: center; gap: .45rem; border: 1px solid #f4a4a4; border-radius: 11px; padding: .65rem .95rem; color: #c81f29; font-size: .85rem; font-weight: 700; transition: .2s ease; }
.notice-retire:hover { background: #fff1f1; }
.admin-meta { display: grid; gap: 1rem; grid-template-columns: repeat(1, minmax(0,1fr)); }
.admin-meta-cell { display: flex; min-width: 0; align-items: flex-start; gap: .7rem; color: #5b6e88; }
.admin-meta-cell > div { min-width: 0; }
.admin-meta-cell strong { display: block; color: #182b4d; font-size: .79rem; }
.admin-meta-cell span { display: block; overflow-wrap: anywhere; font-size: .84rem; }
.admin-meta-cell small { display: block; color: #71829b; font-size: .76rem; }
.admin-meta-cell--delivery { align-items: center; }
.progress-track { height: 9px; min-width: 70px; overflow: hidden; border-radius: 999px; background: #dce4ee; }
.progress-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #08a95b, #13b96c); }
.preferences-panel { padding: 1.6rem; }
.preference-row { display: flex; align-items: center; gap: 1.1rem; border: 1px solid #dae4ef; border-radius: 17px; padding: 1rem 1.2rem; cursor: pointer; }
.preference-icon { display: grid; height: 51px; width: 51px; flex: 0 0 auto; place-items: center; border-radius: 15px; background: #eaf1ff; color: #1167df; }
.toggle-shell { position: relative; display: inline-flex; height: 31px; width: 54px; flex: 0 0 auto; }
.toggle-input { position: absolute; inset: 0; z-index: 2; height: 100%; width: 100%; cursor: pointer; opacity: 0; }
.toggle-slider { display: block; width: 100%; border-radius: 999px; background: #c7d2e2; transition: background .18s ease; }
.toggle-slider::after { content: ''; position: absolute; top: 4px; left: 4px; width: 23px; height: 23px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.19); transition: transform .18s ease; }
.toggle-input:checked + .toggle-slider { background: #075de1; }
.toggle-input:checked + .toggle-slider::after { transform: translateX(23px); }
.toggle-input:focus-visible + .toggle-slider { outline: 3px solid #93c5fd; outline-offset: 3px; }
@media(min-width: 770px) { .admin-meta { grid-template-columns: repeat(2, minmax(0,1fr)); } .preferences-panel { padding: 2rem; } }
@media(min-width: 1160px) { .admin-meta { grid-template-columns: 1fr .9fr 1.15fr 1.3fr; } .admin-meta-cell:not(:first-child) { border-left: 1px solid #e1e7f0; padding-left: 1rem; } }
</style>
