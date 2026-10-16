<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
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
const editing = ref<Notice | null>(null);
const teamSearch = ref('');
const categoryLabels: Record<string, string> = { general: 'General', schedule: 'Calendario', finance: 'Pagos', discipline: 'Disciplina' };
const priorityLabels: Record<string, string> = { normal: 'Normal', important: 'Importante', urgent: 'Urgente' };
const statusLabels: Record<string, string> = { draft: 'Borrador', published: 'Publicado', withdrawn: 'Retirado' };
const preferenceOptions: { key: keyof Preferences; title: string; text: string }[] = [
    { key: 'general_enabled', title: 'Avisos generales', text: 'Comunicados y asuntos administrativos.' },
    { key: 'schedule_enabled', title: 'Calendario', text: 'Jornadas, partidos, campos y horarios.' },
    { key: 'finance_enabled', title: 'Pagos', text: 'Cobros, vencimientos y recibos.' },
    { key: 'discipline_enabled', title: 'Disciplina', text: 'Sanciones, apelaciones y resoluciones.' },
];
const roleLabels = computed(() => Object.fromEntries(props.roles.map((role) => [role.slug, role.name])));
const teamLabels = computed(() => Object.fromEntries(props.teams.map((team) => [team.id, team.name])));
const filteredTeams = computed(() => props.teams.filter((team) => team.name.toLowerCase().includes(teamSearch.value.toLowerCase())));
const unreadCount = computed(() => props.inbox.data.filter((recipient) => !recipient.read_at).length);

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
        <section>
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 16</p>
                    <h1 class="mt-1 text-3xl font-semibold">Avisos y notificaciones</h1>
                    <p class="mt-2 max-w-3xl text-slate-600">Comunicación interna de la liga con audiencias controladas, preferencias personales y constancia de lectura.</p>
                </div>
                <button v-if="permissions.manage" class="btn-primary" type="button" @click="openCreate">Crear aviso</button>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-b border-slate-200">
                <button class="border-b-2 px-4 py-3 text-sm font-semibold" :class="tab === 'inbox' ? 'border-league-600 text-league-700' : 'border-transparent text-slate-500'" type="button" @click="tab = 'inbox'">Mi bandeja <span v-if="unreadCount" class="ml-1 rounded-full bg-red-600 px-2 py-0.5 text-xs text-white">{{ unreadCount }}</span></button>
                <button v-if="permissions.manage" class="border-b-2 px-4 py-3 text-sm font-semibold" :class="tab === 'manage' ? 'border-league-600 text-league-700' : 'border-transparent text-slate-500'" type="button" @click="tab = 'manage'">Administrar avisos</button>
                <button class="border-b-2 px-4 py-3 text-sm font-semibold" :class="tab === 'preferences' ? 'border-league-600 text-league-700' : 'border-transparent text-slate-500'" type="button" @click="tab = 'preferences'">Preferencias</button>
            </div>

            <div v-if="tab === 'inbox'" class="mt-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">{{ inbox.total }} avisos disponibles.</p>
                    <button v-if="unreadCount" class="btn-secondary" type="button" @click="markAllRead">Marcar todos como leídos</button>
                </div>
                <div v-if="!inbox.data.length" class="card p-12 text-center text-slate-600">No tienes avisos vigentes en esta liga.</div>
                <div v-else class="space-y-4">
                    <article v-for="recipient in inbox.data" :key="recipient.id" class="card overflow-hidden" :class="!recipient.read_at ? 'border-l-4 border-l-league-600' : ''">
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="recipient.notice.priority === 'urgent' ? 'bg-red-100 text-red-800' : recipient.notice.priority === 'important' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'">{{ priorityLabels[recipient.notice.priority] }}</span>
                                        <span class="rounded-full bg-league-50 px-2.5 py-1 text-xs font-semibold text-league-700">{{ categoryLabels[recipient.notice.category] }}</span>
                                        <span v-if="!recipient.read_at" class="text-xs font-bold uppercase tracking-wide text-league-700">Nuevo</span>
                                    </div>
                                    <h2 class="mt-3 text-xl font-semibold">{{ recipient.notice.title }}</h2>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ recipient.notice.body }}</p>
                                    <p class="mt-4 text-xs text-slate-500">Publicado {{ dateTime(recipient.notice.published_at) }} por {{ recipient.notice.creator?.name ?? 'Administración de la liga' }}<span v-if="recipient.notice.expires_at"> · vence {{ dateTime(recipient.notice.expires_at) }}</span></p>
                                </div>
                                <button v-if="!recipient.read_at" class="btn-secondary shrink-0" type="button" @click="markRead(recipient)">Marcar como leído</button>
                            </div>
                        </div>
                    </article>
                </div>
                <nav v-if="inbox.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1"><Link v-for="link in inbox.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg border px-3 py-2 text-sm" :class="[link.active ? 'border-league-600 bg-league-600 text-white' : 'border-slate-200 bg-white', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-scroll><span v-html="link.label"></span></Link></nav>
            </div>

            <div v-if="tab === 'manage' && permissions.manage" class="mt-6">
                <div v-if="!managedNotices.length" class="card p-12 text-center text-slate-600">Todavía no hay avisos. Crea el primero como borrador.</div>
                <div v-else class="space-y-4">
                    <article v-for="notice in managedNotices" :key="notice.id" class="card p-5 sm:p-6">
                        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="notice.status === 'published' ? 'bg-emerald-100 text-emerald-800' : notice.status === 'withdrawn' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800'">{{ statusLabels[notice.status] }}</span>
                                    <span class="text-xs font-semibold text-slate-500">{{ categoryLabels[notice.category] }} · {{ priorityLabels[notice.priority] }}</span>
                                </div>
                                <h2 class="mt-2 text-lg font-semibold">{{ notice.title }}</h2>
                                <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm text-slate-600">{{ notice.body }}</p>
                                <dl class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <div><dt class="font-semibold text-slate-800">Audiencia</dt><dd>{{ audienceLabel(notice) }}</dd></div>
                                    <div><dt class="font-semibold text-slate-800">Vencimiento</dt><dd>{{ dateTime(notice.expires_at) }}</dd></div>
                                    <div v-if="notice.status !== 'draft'"><dt class="font-semibold text-slate-800">Entrega</dt><dd>{{ notice.recipients_count ?? 0 }} destinatarios · {{ notice.read_count ?? 0 }} lecturas</dd></div>
                                    <div><dt class="font-semibold text-slate-800">Creado por</dt><dd>{{ notice.creator?.name ?? 'Usuario eliminado' }} · {{ dateTime(notice.created_at) }}</dd></div>
                                </dl>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button v-if="notice.status === 'draft'" class="btn-secondary" type="button" @click="openEdit(notice)">Editar</button>
                                <button v-if="notice.status === 'draft'" class="btn-primary" type="button" @click="publish(notice)">Publicar</button>
                                <button v-if="notice.status === 'published'" class="btn-danger" type="button" @click="withdraw(notice)">Retirar</button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="tab === 'preferences'" class="mt-6">
                <form class="card max-w-3xl p-6" @submit.prevent="savePreferences">
                    <h2 class="text-xl font-semibold">Preferencias personales</h2>
                    <p class="mt-2 text-sm text-slate-600">Estas opciones afectan avisos futuros. Los avisos urgentes siempre se entregan y no pueden silenciarse.</p>
                    <div class="mt-5 divide-y divide-slate-200">
                        <label v-for="item in preferenceOptions" :key="item.key" class="flex items-center justify-between gap-5 py-4">
                            <span><strong class="block text-sm">{{ item.title }}</strong><small class="text-slate-500">{{ item.text }}</small></span>
                            <input v-model="preferencesForm[item.key]" class="h-5 w-5 accent-league-700" type="checkbox">
                        </label>
                    </div>
                    <button class="btn-primary mt-5" :disabled="preferencesForm.processing">Guardar preferencias</button>
                </form>
            </div>
        </section>

        <div v-if="editorOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="editorOpen = false">
            <form class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6" @submit.prevent="submitNotice">
                <h2 class="text-xl font-semibold">{{ editing ? 'Editar borrador' : 'Crear aviso' }}</h2>
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
