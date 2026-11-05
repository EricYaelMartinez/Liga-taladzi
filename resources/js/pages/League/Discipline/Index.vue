<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Policy = { automatic_sanctions_enabled: boolean; yellow_card_threshold: number; yellow_suspension_matches: number; second_yellow_suspension_matches: number; red_card_suspension_matches: number };
type Simple = { id: number; name: string };
type Competition = Simple & { tournament: { season: Simple } };
type Match = { id: number; scheduled_at: string | null; matchday: Simple; home_participation: { registered_name: string }; away_participation: { registered_name: string } };
type Appeal = { id: number; status: string; statement: string; submitted_at: string; resolution: string | null; has_evidence: boolean; appellant: { name: string }; reviewer: { name: string } | null };
type Sanction = { id: number; subject_type: string; subject_name: string; kind: string; source: string; reason: string; legal_basis: string | null; matches_total: number; matches_served: number; points_deducted: number; fine_amount: string | null; status: string; issued_at: string; appeal_deadline_at: string; completed_at: string | null; team: Simple | null; season: Simple | null; competition: Simple | null; match: { id: number; matchday: Simple | null } | null; appeal: Appeal | null; can_appeal: boolean; can_resolve: boolean };
type Pagination = { data: Sanction[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number };

const props = defineProps<{ policy: Policy; sanctions: Pagination; permissions: { manage: boolean; appeal: boolean }; players: { id: number; full_name: string }[]; teams: Simple[]; seasons: Simple[]; competitions: Competition[]; matches: Match[] }>();
const policyOpen = ref(false);
const sanctionOpen = ref(false);
const appealTarget = ref<Sanction | null>(null);
const resolveTarget = ref<Sanction | null>(null);
const syncing = ref(false);

const policyForm = useForm({ ...props.policy, reason: '' });
const sanctionForm = useForm({ subject_type: 'player', player_id: null as number | null, team_id: null as number | null, subject_name: '', kind: 'suspension', season_id: null as number | null, competition_id: null as number | null, match_id: null as number | null, matches_total: 1, points_deducted: 1, fine_amount: null as number | null, reason: '', legal_basis: '' });
const appealForm = useForm({ statement: '', evidence: null as File | null });
const resolveForm = useForm({ action: 'uphold', matches_total: 0, points_deducted: 0, fine_amount: 0, resolution: '' });

const subjectLabels: Record<string, string> = { player: 'Jugador', team: 'Equipo', staff: 'Cuerpo técnico' };
const kindLabels: Record<string, string> = { suspension: 'Suspensión', points_deduction: 'Descuento de puntos', fine: 'Multa', warning: 'Amonestación administrativa', disqualification: 'Descalificación' };
const statusLabels: Record<string, string> = { active: 'Vigente', completed: 'Cumplida', revoked: 'Revocada' };
const appealLabels: Record<string, string> = { submitted: 'En revisión', upheld: 'Confirmada', reduced: 'Reducida', revoked: 'Revocada' };
const activeCount = computed(() => props.sanctions.data.filter((item) => item.status === 'active').length);
const pendingAppeals = computed(() => props.sanctions.data.filter((item) => item.appeal?.status === 'submitted').length);

function dateTime(value: string | null): string { return value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function money(value: string | null): string { return value ? new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value)) : '—'; }
function policySubmit(): void { policyForm.put('/liga/disciplina/configuracion', { preserveScroll: true, onSuccess: () => { policyOpen.value = false; policyForm.reason = ''; } }); }
function sanctionSubmit(): void { sanctionForm.post('/liga/sanciones', { preserveScroll: true, onSuccess: () => { sanctionOpen.value = false; sanctionForm.reset(); sanctionForm.subject_type = 'player'; sanctionForm.kind = 'suspension'; sanctionForm.matches_total = 1; sanctionForm.points_deducted = 1; } }); }
function syncReports(): void { syncing.value = true; router.post('/liga/sanciones/sincronizar', {}, { preserveScroll: true, onFinish: () => { syncing.value = false; } }); }
function openAppeal(item: Sanction): void { appealTarget.value = item; appealForm.reset(); appealForm.clearErrors(); }
function appealSubmit(): void { if (!appealTarget.value) return; appealForm.post(`/liga/sanciones/${appealTarget.value.id}/apelaciones`, { forceFormData: true, preserveScroll: true, onSuccess: () => { appealTarget.value = null; appealForm.reset(); } }); }
function openResolve(item: Sanction): void { resolveTarget.value = item; resolveForm.reset(); resolveForm.clearErrors(); resolveForm.action = 'uphold'; resolveForm.matches_total = item.matches_total; resolveForm.points_deducted = item.points_deducted; resolveForm.fine_amount = Number(item.fine_amount ?? 0); }
function resolveSubmit(): void { if (!resolveTarget.value?.appeal) return; resolveForm.post(`/liga/apelaciones/${resolveTarget.value.appeal.id}/resolver`, { preserveScroll: true, onSuccess: () => { resolveTarget.value = null; resolveForm.reset(); } }); }
</script>

<template>
    <Head title="Sanciones y disciplina" />
    <AppLayout>
        <section>
            <div class="discipline-hero">
                <div class="relative z-10 flex flex-col gap-5 px-6 py-7 lg:flex-row lg:items-center lg:justify-between lg:px-9 lg:py-9">
                    <div class="flex min-w-0 items-center gap-5">
                        <div class="hidden h-20 w-1.5 shrink-0 rounded-full bg-sky-400 sm:block"></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-100">Módulo 14 · Control disciplinario</p>
                            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Sanciones y apelaciones</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-white/85 sm:text-base">Control de suspensiones, sanciones administrativas, cumplimiento por partidos oficiales y apelaciones.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-white/35 bg-slate-950/30 px-5 py-4 text-white shadow-lg backdrop-blur-sm">
                        <AppIcon name="badge" :size="32" />
                        <div><p class="text-xs font-semibold text-white/75">Partidos cumplidos en esta página</p><p class="mt-1 text-2xl font-bold">{{ sanctions.data.reduce((sum, item) => sum + (item.kind === 'suspension' ? item.matches_served : 0), 0) }} / {{ sanctions.data.reduce((sum, item) => sum + (item.kind === 'suspension' ? item.matches_total : 0), 0) }}</p></div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-4 xl:flex-row xl:items-stretch xl:justify-between">
                <div class="grid flex-1 gap-4 sm:grid-cols-3">
                    <div class="discipline-stat"><span class="discipline-stat__icon"><AppIcon name="gavel" variant="illustrated" :size="35" /></span><div><p>Sanciones mostradas</p><strong>{{ sanctions.total }}</strong></div></div>
                    <div class="discipline-stat"><span class="discipline-stat__icon"><AppIcon name="calendar" variant="illustrated" :size="35" /></span><div><p>Vigentes en esta página</p><strong>{{ activeCount }}</strong></div></div>
                    <div class="discipline-stat"><span class="discipline-stat__icon"><AppIcon name="badge" variant="illustrated" :size="35" /></span><div><p>Apelaciones pendientes</p><strong>{{ pendingAppeals }}</strong></div></div>
                </div>
                <div v-if="permissions.manage" class="flex flex-wrap items-center gap-2 rounded-2xl border border-white/80 bg-white/95 p-3 shadow-sm">
                    <button class="btn-secondary inline-flex items-center gap-2" type="button" :disabled="syncing" @click="syncReports"><AppIcon name="refresh" :size="16" />{{ syncing ? 'Sincronizando…' : 'Sincronizar cédulas' }}</button>
                    <button class="btn-secondary inline-flex items-center gap-2" type="button" @click="policyOpen = true"><AppIcon name="settings" :size="16" />Configuración</button>
                    <button class="btn-primary inline-flex items-center gap-2" type="button" @click="sanctionOpen = true"><AppIcon name="badge" :size="16" />Nueva sanción</button>
                </div>
            </div>

            <div v-if="!sanctions.data.length" class="mt-6 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-slate-600">No existen sanciones visibles para este acceso.</div>
            <div v-else class="mt-6 space-y-4">
                <article v-for="item in sanctions.data" :key="item.id" class="discipline-card">
                    <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(270px,350px)] lg:p-7">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="discipline-pill bg-slate-100 text-slate-700"><AppIcon name="user" :size="15" />{{ subjectLabels[item.subject_type] }}</span>
                                <span class="discipline-pill" :class="item.status === 'active' ? 'bg-rose-100 text-rose-800' : item.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'"><AppIcon :name="item.status === 'completed' ? 'check' : 'clock'" :size="15" />{{ statusLabels[item.status] }}</span>
                                <span class="discipline-pill bg-blue-50 text-blue-900"><AppIcon name="badge" :size="14" />{{ item.source === 'automatic' ? 'Automática' : 'Manual' }}</span>
                            </div>
                            <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">{{ item.subject_name }}</h2>
                            <p class="mt-1 flex items-center gap-2 text-sm text-slate-500"><AppIcon name="calendar" :size="16" />{{ kindLabels[item.kind] }} · Emitida {{ dateTime(item.issued_at) }}</p>
                            <div class="mt-5 border-y border-slate-200 py-4">
                                <p class="mb-1 flex items-center gap-2 text-sm font-bold text-slate-800"><AppIcon name="lineup" :size="17" />Motivo</p>
                                <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ item.reason }}</p>
                            </div>
                            <div v-if="item.legal_basis" class="mt-4 rounded-xl bg-slate-50 px-4 py-3"><p class="text-xs font-semibold uppercase text-slate-500">Fundamento reglamentario</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ item.legal_basis }}</p></div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div v-if="item.team" class="discipline-meta"><AppIcon name="users" :size="17" /><div><span>Equipo</span><strong>{{ item.team.name }}</strong></div></div>
                                <div v-if="item.season" class="discipline-meta"><AppIcon name="calendar" :size="17" /><div><span>Temporada</span><strong>{{ item.season.name }}</strong></div></div>
                                <div v-if="item.competition" class="discipline-meta"><AppIcon name="trophy" :size="17" /><div><span>Competencia</span><strong>{{ item.competition.name }}</strong></div></div>
                                <div v-if="item.match" class="discipline-meta"><AppIcon name="football" :size="17" /><div><span>Partido relacionado</span><strong>{{ item.match.matchday?.name ?? `#${item.match.id}` }}</strong></div></div>
                            </div>
                            <div v-if="item.can_appeal || item.can_resolve" class="mt-5 flex flex-wrap gap-2">
                                <button v-if="item.can_appeal" class="btn-secondary" type="button" @click="openAppeal(item)">Presentar apelación</button>
                                <button v-if="item.can_resolve" class="btn-primary" type="button" @click="openResolve(item)">Resolver apelación</button>
                            </div>
                        </div>
                        <aside class="discipline-progress">
                            <template v-if="item.kind === 'suspension'">
                                <div class="flex items-center justify-between gap-2"><p class="flex items-center gap-2 text-sm font-semibold text-slate-700"><AppIcon name="clipboard" :size="18" />Partidos cumplidos</p><AppIcon v-if="item.status === 'completed'" name="check" color="success" :size="20" /></div>
                                <p class="mt-3 text-3xl font-extrabold text-slate-900">{{ item.matches_served }} <span class="text-xl text-slate-500">/ {{ item.matches_total }}</span></p>
                                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: `${Math.min(100, item.matches_total ? item.matches_served / item.matches_total * 100 : 100)}%` }"></div></div>
                            </template>
                            <p v-else-if="item.kind === 'points_deduction'" class="text-sm font-semibold text-slate-700">Puntos descontados <strong class="mt-2 block text-3xl text-rose-700">-{{ item.points_deducted }}</strong></p>
                            <p v-else-if="item.kind === 'fine'" class="text-sm font-semibold text-slate-700">Importe <strong class="mt-2 block text-2xl text-slate-900">{{ money(item.fine_amount) }}</strong></p>
                            <p v-else class="text-sm font-semibold text-slate-700">{{ kindLabels[item.kind] }}</p>
                            <div class="mt-5 flex items-start gap-3 border-t border-slate-200 pt-4 text-sm"><AppIcon name="calendar" :size="18" /><div><span class="block text-slate-500">Plazo de apelación</span><strong class="mt-1 block text-slate-800">{{ dateTime(item.appeal_deadline_at) }}</strong></div></div>
                        </aside>
                    </div>
                    <div v-if="item.appeal" class="border-t border-amber-200 bg-amber-50 px-5 py-4 lg:px-7"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Apelación · {{ appealLabels[item.appeal.status] }}</h3><a v-if="item.appeal.has_evidence" :href="`/liga/apelaciones/${item.appeal.id}/evidencia`" class="text-sm font-semibold text-league-700 underline">Descargar evidencia</a></div><p class="mt-2 whitespace-pre-line text-sm">{{ item.appeal.statement }}</p><p class="mt-2 text-xs text-slate-500">Presentada por {{ item.appeal.appellant.name }} · {{ dateTime(item.appeal.submitted_at) }}</p><div v-if="item.appeal.resolution" class="mt-3 rounded-lg bg-white p-3 text-sm"><strong>Resolución:</strong> {{ item.appeal.resolution }}</div></div>
                </article>
            </div>
            <nav v-if="sanctions.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1"><Link v-for="link in sanctions.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg border px-3 py-2 text-sm" :class="[link.active ? 'border-league-600 bg-league-600 text-white' : 'border-slate-200 bg-white', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-scroll><span v-html="link.label"></span></Link></nav>
        </section>

        <div v-if="policyOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="policyOpen = false"><form class="discipline-modal max-h-[92vh] w-full max-w-2xl overflow-y-auto" @submit.prevent="policySubmit"><div class="discipline-modal__heading"><span class="discipline-modal__symbol"><AppIcon name="settings" :size="26" /></span><div><h2 class="text-2xl font-extrabold text-slate-900">Configuración disciplinaria</h2><p class="mt-1 text-sm text-slate-500">Los valores son propios de esta liga y solo afectan sanciones nuevas.</p></div><button type="button" class="discipline-modal__close" aria-label="Cerrar" @click="policyOpen = false">×</button></div><label class="mt-5 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4"><input v-model="policyForm.automatic_sanctions_enabled" type="checkbox"><span><strong>Generar sanciones automáticas</strong><small class="block text-slate-500">Desde tarjetas de cédulas aprobadas.</small></span></label><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Amarillas para suspensión</label><input v-model.number="policyForm.yellow_card_threshold" class="form-input" type="number" min="1" max="20"><FormError :message="policyForm.errors.yellow_card_threshold" /></div><div><label class="form-label">Partidos por acumulación</label><input v-model.number="policyForm.yellow_suspension_matches" class="form-input" type="number" min="1" max="20"></div><div><label class="form-label">Partidos por segunda amarilla</label><input v-model.number="policyForm.second_yellow_suspension_matches" class="form-input" type="number" min="1" max="20"></div><div><label class="form-label">Partidos por roja directa</label><input v-model.number="policyForm.red_card_suspension_matches" class="form-input" type="number" min="1" max="50"></div></div><div class="mt-4"><label class="form-label">Motivo del cambio</label><textarea v-model="policyForm.reason" class="form-input" required maxlength="500"></textarea><FormError :message="policyForm.errors.reason" /></div><div class="mt-6 flex justify-end gap-2"><button class="btn-secondary" type="button" @click="policyOpen = false">Cancelar</button><button class="btn-primary" :disabled="policyForm.processing">Guardar configuración</button></div></form></div>

        <div v-if="sanctionOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="sanctionOpen = false"><form class="discipline-modal max-h-[92vh] w-full max-w-3xl overflow-y-auto" @submit.prevent="sanctionSubmit"><div class="discipline-modal__heading"><span class="discipline-modal__symbol"><AppIcon name="gavel" :size="26" /></span><div><h2 class="text-2xl font-extrabold text-slate-900">Registrar sanción manual</h2><p class="mt-1 text-sm text-slate-500">Define la sanción, su alcance y el fundamento reglamentario.</p></div><button type="button" class="discipline-modal__close" aria-label="Cerrar" @click="sanctionOpen = false">×</button></div><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Sancionado</label><select v-model="sanctionForm.subject_type" class="form-input"><option value="player">Jugador</option><option value="team">Equipo</option><option value="staff">Cuerpo técnico</option></select></div><div><label class="form-label">Tipo de sanción</label><select v-model="sanctionForm.kind" class="form-input"><option value="suspension">Suspensión</option><option value="points_deduction">Descuento de puntos</option><option value="fine">Multa</option><option value="warning">Amonestación administrativa</option><option value="disqualification">Descalificación</option></select></div><div v-if="sanctionForm.subject_type === 'player'"><label class="form-label">Jugador</label><select v-model="sanctionForm.player_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="player in players" :key="player.id" :value="player.id">{{ player.full_name }}</option></select><FormError :message="sanctionForm.errors.player_id" /></div><div v-if="sanctionForm.subject_type !== 'player'"><label class="form-label">Equipo</label><select v-model="sanctionForm.team_id" class="form-input" required><option :value="null" disabled>Selecciona</option><option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option></select><FormError :message="sanctionForm.errors.team_id" /></div><div v-if="sanctionForm.subject_type === 'staff'"><label class="form-label">Nombre del integrante</label><input v-model="sanctionForm.subject_name" class="form-input" required maxlength="180"><FormError :message="sanctionForm.errors.subject_name" /></div><div><label class="form-label">Temporada</label><select v-model="sanctionForm.season_id" class="form-input"><option :value="null">Sin limitar</option><option v-for="season in seasons" :key="season.id" :value="season.id">{{ season.name }}</option></select></div><div><label class="form-label">Competencia</label><select v-model="sanctionForm.competition_id" class="form-input" :required="sanctionForm.kind === 'points_deduction'"><option :value="null">Sin limitar</option><option v-for="competition in competitions" :key="competition.id" :value="competition.id">{{ competition.tournament.season.name }} · {{ competition.name }}</option></select><FormError :message="sanctionForm.errors.competition_id" /></div><div class="sm:col-span-2"><label class="form-label">Partido relacionado (opcional)</label><select v-model="sanctionForm.match_id" class="form-input"><option :value="null">Sin partido</option><option v-for="match in matches" :key="match.id" :value="match.id">{{ match.matchday?.name }} · {{ match.home_participation.registered_name }} vs {{ match.away_participation.registered_name }}</option></select></div><div v-if="sanctionForm.kind === 'suspension'"><label class="form-label">Partidos de suspensión</label><input v-model.number="sanctionForm.matches_total" class="form-input" type="number" min="1" max="100" required></div><div v-if="sanctionForm.kind === 'points_deduction'"><label class="form-label">Puntos a descontar</label><input v-model.number="sanctionForm.points_deducted" class="form-input" type="number" min="1" max="100" required></div><div v-if="sanctionForm.kind === 'fine'"><label class="form-label">Importe de multa</label><input v-model.number="sanctionForm.fine_amount" class="form-input" type="number" min="0.01" step="0.01" required></div><div class="sm:col-span-2"><label class="form-label">Motivo</label><textarea v-model="sanctionForm.reason" class="form-input" required maxlength="3000"></textarea><FormError :message="sanctionForm.errors.reason" /></div><div class="sm:col-span-2"><label class="form-label">Fundamento reglamentario</label><textarea v-model="sanctionForm.legal_basis" class="form-input" maxlength="3000"></textarea></div></div><div class="mt-6 flex justify-end gap-2"><button class="btn-secondary" type="button" @click="sanctionOpen = false">Cancelar</button><button class="btn-primary" :disabled="sanctionForm.processing">Registrar sanción</button></div></form></div>

        <div v-if="appealTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="appealTarget = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="appealSubmit"><h2 class="text-xl font-semibold">Apelar sanción</h2><p class="mt-1 text-sm text-slate-500">{{ appealTarget.subject_name }} · vence {{ dateTime(appealTarget.appeal_deadline_at) }}</p><div class="mt-5"><label class="form-label">Argumentos de la apelación</label><textarea v-model="appealForm.statement" class="form-input min-h-36" required minlength="20" maxlength="5000"></textarea><FormError :message="appealForm.errors.statement" /></div><div class="mt-4"><label class="form-label">Evidencia opcional</label><input class="form-input" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="appealForm.evidence = ($event.target as HTMLInputElement).files?.[0] ?? null"><p class="mt-1 text-xs text-slate-500">PDF o imagen, máximo 5 MB.</p><FormError :message="appealForm.errors.evidence" /></div><div class="mt-6 flex justify-end gap-2"><button class="btn-secondary" type="button" @click="appealTarget = null">Cancelar</button><button class="btn-primary" :disabled="appealForm.processing">Enviar apelación</button></div></form></div>

        <div v-if="resolveTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="resolveTarget = null"><form class="w-full max-w-xl rounded-2xl bg-white p-6" @submit.prevent="resolveSubmit"><h2 class="text-xl font-semibold">Resolver apelación</h2><p class="mt-1 text-sm text-slate-500">{{ resolveTarget.subject_name }}</p><div class="mt-5"><label class="form-label">Resolución</label><select v-model="resolveForm.action" class="form-input"><option value="uphold">Confirmar sanción</option><option v-if="['suspension', 'points_deduction', 'fine'].includes(resolveTarget.kind)" value="reduce">Reducir sanción</option><option value="revoke">Revocar sanción</option></select></div><div v-if="resolveForm.action === 'reduce' && resolveTarget.kind === 'suspension'" class="mt-4"><label class="form-label">Nueva duración en partidos</label><input v-model.number="resolveForm.matches_total" class="form-input" type="number" min="0" :max="Math.max(0, resolveTarget.matches_total - 1)"><FormError :message="resolveForm.errors.matches_total" /></div><div v-if="resolveForm.action === 'reduce' && resolveTarget.kind === 'points_deduction'" class="mt-4"><label class="form-label">Nuevo descuento de puntos</label><input v-model.number="resolveForm.points_deducted" class="form-input" type="number" min="0" :max="Math.max(0, resolveTarget.points_deducted - 1)"><FormError :message="resolveForm.errors.points_deducted" /></div><div v-if="resolveForm.action === 'reduce' && resolveTarget.kind === 'fine'" class="mt-4"><label class="form-label">Nuevo importe</label><input v-model.number="resolveForm.fine_amount" class="form-input" type="number" min="0.01" :max="Math.max(0, Number(resolveTarget.fine_amount ?? 0) - 0.01)" step="0.01"><FormError :message="resolveForm.errors.fine_amount" /></div><div class="mt-4"><label class="form-label">Fundamento de la resolución</label><textarea v-model="resolveForm.resolution" class="form-input min-h-28" required minlength="10" maxlength="5000"></textarea><FormError :message="resolveForm.errors.resolution" /></div><div class="mt-6 flex justify-end gap-2"><button class="btn-secondary" type="button" @click="resolveTarget = null">Cancelar</button><button class="btn-primary" :disabled="resolveForm.processing">Guardar resolución</button></div></form></div>
    </AppLayout>
</template>

<style scoped>
.discipline-hero { position:relative; overflow:hidden; border-radius:1.75rem; background:linear-gradient(105deg,rgba(5,35,92,.93),rgba(12,86,189,.81) 55%,rgba(7,50,126,.83)),url('/images/public/league-stadium.jpg') center 55%/cover; box-shadow:0 14px 32px rgba(17,40,90,.17); }
.discipline-stat { display:flex; align-items:center; min-height:100px; gap:1rem; border:1px solid #e1e8f2; border-radius:1.25rem; padding:1rem 1.15rem; background:rgba(255,255,255,.97); box-shadow:0 10px 25px rgba(20,45,85,.075); }
.discipline-stat__icon { flex:none; display:grid; place-items:center; }
.discipline-stat p { font-size:.88rem; font-weight:600; color:#344765; }
.discipline-stat strong { display:block; margin-top:2px; color:#1359bf; font-size:1.9rem; line-height:1.2; font-weight:800; }
.discipline-card { overflow:hidden; border:1px solid #dce5f1; border-radius:1.55rem; background:rgba(255,255,255,.98); box-shadow:0 10px 26px rgba(17,39,78,.08); }
.discipline-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:999px; font-size:.76rem; font-weight:700; }
.discipline-meta { display:flex; align-items:flex-start; gap:.55rem; min-width:0; color:#295082; }
.discipline-meta span { display:block; font-size:.76rem; color:#64748b; }
.discipline-meta strong { display:block; font-size:.84rem; color:#162b4b; overflow-wrap:anywhere; }
.discipline-progress { align-self:stretch; border-radius:1.3rem; background:linear-gradient(140deg,#f2f6fc,#e7eef9); padding:1.35rem; }
.discipline-modal { border:1px solid #e4eaf3; border-radius:1.55rem; background:white; padding:1.5rem; box-shadow:0 26px 75px rgba(4,15,42,.26); }
.discipline-modal__heading { display:flex; align-items:center; gap:1rem; padding-bottom:1.1rem; border-bottom:1px solid #e6ecf5; }
.discipline-modal__symbol { flex:none; display:grid; height:3.25rem; width:3.25rem; place-items:center; border-radius:1rem; background:#eaf2ff; color:#1557be; }
.discipline-modal__close { margin-left:auto; flex:none; display:grid; height:2.25rem; width:2.25rem; place-items:center; border-radius:.75rem; background:#f2f5fa; color:#284165; }
.discipline-modal :deep(.form-label) { font-weight:700; color:#1c3053; }
.discipline-modal :deep(.form-input) { border-color:#cbd8ec; border-radius:.8rem; }
.discipline-modal :deep(textarea.form-input) { min-height:5.1rem; }
@media (max-width:640px) { .discipline-modal{padding:1rem;} .discipline-modal__heading h2{font-size:1.15rem;} }
</style>
