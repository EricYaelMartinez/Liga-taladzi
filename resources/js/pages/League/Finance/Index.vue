<script setup lang="ts">
import AppLayout from '../../../layouts/AppLayout.vue';
import FormError from '../../../components/FormError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Concept = { id: number; name: string; description: string | null; type: string; default_amount: string; active: boolean };
type Participation = { id: number; team_id: number; registered_name: string; status: string; financial_status: string; team: { id: number; name: string }; season: { id: number; name: string; status: string }; competition: { id: number; name: string; division_id: number; status: string }; division: { id: number; name: string } | null };
type Charge = { id: number; team_id: number; description: string; type: string; original_amount: string; balance: string; issued_on: string; due_on: string; grace_until: string | null; status: string; team: { name: string }; participation: { registered_name: string; competition: { name: string } } | null; season: { name: string } | null; concept: { name: string } | null };
type Allocation = { id: number; amount: string; charge: { id: number; description: string } };
type Payment = { id: number; receipt_number: string; amount: string; method: string; paid_at: string; notes: string | null; voided_at: string | null; void_reason: string | null; team: { name: string }; receiver: { name: string } | null; allocations: Allocation[] };
type OpenCharge = { id: number; team_id: number; description: string; balance: string; due_on: string; status: string };
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };

const props = defineProps<{
    charges: Paginated<Charge>;
    payments: Payment[];
    openCharges: OpenCharge[];
    permissions: { manage: boolean };
    currency: string;
    summary: { outstanding: string | number; collected: string | number; overdue: string | number };
    concepts: Concept[];
    participations: Participation[];
}>();

const today = new Date().toISOString().slice(0, 10);
const now = new Date().toISOString().slice(0, 16);
const conceptForm = useForm({ name: '', description: '', type: 'fee', default_amount: '' });
const chargeForm = useForm({ team_id: 0, team_participation_id: 0, season_id: 0, concept_id: null as number | null, description: '', type: 'fee', amount: '', issued_on: today, due_on: today });
const bulkChargeForm = useForm<{ team_participation_ids: number[]; concept_id: number | null; description: string; type: string; amount: string; issued_on: string; due_on: string }>({
    team_participation_ids: [], concept_id: null, description: '', type: 'fee', amount: '', issued_on: today, due_on: today,
});
const paymentForm = useForm<{ team_id: number; paid_at: string; notes: string; allocations: { charge_id: number; amount: string }[] }>({ team_id: 0, paid_at: now, notes: '', allocations: [] });
const chargeMode = ref<'single' | 'bulk'>('single');
const seasonFilter = ref(0);
const competitionFilter = ref(0);
const divisionFilter = ref(0);

const selectedParticipation = computed(() => props.participations.find((item) => item.id === chargeForm.team_participation_id));
const teamOptions = computed(() => {
    const unique = new Map<number, { id: number; name: string }>();
    for (const participation of props.participations) unique.set(participation.team.id, participation.team);
    return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const teamOpenCharges = computed(() => props.openCharges.filter((charge) => charge.team_id === paymentForm.team_id));
const paymentTotal = computed(() => paymentForm.allocations.reduce((sum, allocation) => sum + Number(allocation.amount || 0), 0));
const closedCompetitionStatuses = ['finished', 'archived', 'cancelled'];
const activeParticipations = computed(() => props.participations.filter((item) => item.status === 'active'
    && !closedCompetitionStatuses.includes(item.season.status)
    && !closedCompetitionStatuses.includes(item.competition.status)));
const seasonOptions = computed(() => uniqueOptions(activeParticipations.value.map((item) => item.season)));
const competitionOptions = computed(() => uniqueOptions(activeParticipations.value
    .filter((item) => !seasonFilter.value || item.season.id === seasonFilter.value)
    .map((item) => item.competition)));
const divisionOptions = computed(() => uniqueOptions(activeParticipations.value
    .filter((item) => !seasonFilter.value || item.season.id === seasonFilter.value)
    .filter((item) => !competitionFilter.value || item.competition.id === competitionFilter.value)
    .map((item) => item.division)
    .filter((item): item is { id: number; name: string } => item !== null)));
const filteredParticipations = computed(() => activeParticipations.value
    .filter((item) => !seasonFilter.value || item.season.id === seasonFilter.value)
    .filter((item) => !competitionFilter.value || item.competition.id === competitionFilter.value)
    .filter((item) => !divisionFilter.value || item.division?.id === divisionFilter.value)
    .sort((a, b) => a.team.name.localeCompare(b.team.name)));
const allVisibleSelected = computed(() => filteredParticipations.value.length > 0
    && filteredParticipations.value.every((item) => bulkChargeForm.team_participation_ids.includes(item.id)));
const bulkTotal = computed(() => bulkChargeForm.team_participation_ids.length * Number(bulkChargeForm.amount || 0));

const typeLabels: Record<string, string> = { fee: 'Cuota', bond: 'Fianza', fine: 'Multa', other: 'Otro' };
const statusLabels: Record<string, string> = { pending: 'Pendiente', partial: 'Pago parcial', paid: 'Pagado', cancelled: 'Cancelado' };
const statusClasses: Record<string, string> = { pending: 'bg-amber-100 text-amber-800', partial: 'bg-blue-100 text-blue-800', paid: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-slate-200 text-slate-700' };

function uniqueOptions<T extends { id: number; name: string }>(items: T[]): T[] {
    return [...new Map(items.map((item) => [item.id, item])).values()].sort((a, b) => a.name.localeCompare(b.name));
}

function money(value: string | number): string {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: props.currency }).format(Number(value));
}
function date(value: string): string {
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(new Date(`${value.slice(0, 10)}T12:00:00`));
}
function dateTime(value: string): string {
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
function submitConcept(): void {
    conceptForm.post('/liga/finanzas/conceptos', { preserveScroll: true, onSuccess: () => conceptForm.reset() });
}
function selectParticipation(): void {
    if (!selectedParticipation.value) return;
    chargeForm.team_id = selectedParticipation.value.team_id;
    chargeForm.season_id = selectedParticipation.value.season.id;
}
function selectConcept(): void {
    const concept = props.concepts.find((item) => item.id === chargeForm.concept_id);
    if (!concept) return;
    chargeForm.description = concept.name;
    chargeForm.type = concept.type;
    chargeForm.amount = concept.default_amount;
}
function selectBulkConcept(): void {
    const concept = props.concepts.find((item) => item.id === bulkChargeForm.concept_id);
    if (!concept) return;
    bulkChargeForm.description = concept.name;
    bulkChargeForm.type = concept.type;
    bulkChargeForm.amount = concept.default_amount;
}
function submitCharge(): void {
    chargeForm.post('/liga/finanzas/adeudos', { preserveScroll: true, onSuccess: () => chargeForm.reset('description', 'amount', 'concept_id') });
}
function toggleBulkParticipation(participationId: number): void {
    const index = bulkChargeForm.team_participation_ids.indexOf(participationId);
    if (index >= 0) bulkChargeForm.team_participation_ids.splice(index, 1);
    else bulkChargeForm.team_participation_ids.push(participationId);
}
function toggleAllVisible(): void {
    const visibleIds = filteredParticipations.value.map((item) => item.id);
    if (allVisibleSelected.value) {
        bulkChargeForm.team_participation_ids = bulkChargeForm.team_participation_ids.filter((id) => !visibleIds.includes(id));
        return;
    }
    bulkChargeForm.team_participation_ids = [...new Set([...bulkChargeForm.team_participation_ids, ...visibleIds])];
}
function clearBulkFilters(): void {
    seasonFilter.value = 0;
    competitionFilter.value = 0;
    divisionFilter.value = 0;
}
function submitBulkCharge(): void {
    const count = bulkChargeForm.team_participation_ids.length;
    if (!count) return;
    const confirmed = window.confirm(`Se crearán ${count} adeudos de ${money(bulkChargeForm.amount)}. Total proyectado: ${money(bulkTotal.value)}. ¿Deseas continuar?`);
    if (!confirmed) return;
    bulkChargeForm.post('/liga/finanzas/adeudos/masivos', {
        preserveScroll: true,
        onSuccess: () => {
            bulkChargeForm.reset('team_participation_ids', 'concept_id', 'description', 'amount');
            clearBulkFilters();
        },
    });
}
function changePaymentTeam(): void {
    paymentForm.allocations = [];
}
function isSelected(chargeId: number): boolean {
    return paymentForm.allocations.some((allocation) => allocation.charge_id === chargeId);
}
function toggleAllocation(charge: OpenCharge): void {
    const index = paymentForm.allocations.findIndex((allocation) => allocation.charge_id === charge.id);
    if (index >= 0) paymentForm.allocations.splice(index, 1);
    else paymentForm.allocations.push({ charge_id: charge.id, amount: charge.balance });
}
function allocationIndex(chargeId: number): number {
    return paymentForm.allocations.findIndex((item) => item.charge_id === chargeId);
}
function submitPayment(): void {
    paymentForm.post('/liga/finanzas/pagos', { preserveScroll: true, onSuccess: () => paymentForm.reset() });
}
function cancelCharge(charge: Charge): void {
    const reason = window.prompt('Motivo obligatorio para cancelar el adeudo:');
    if (reason?.trim()) router.put(`/liga/finanzas/adeudos/${charge.id}/cancelar`, { reason }, { preserveScroll: true });
}
function voidPayment(payment: Payment): void {
    const reason = window.prompt(`Motivo obligatorio para anular ${payment.receipt_number}:`);
    if (reason?.trim()) router.put(`/liga/finanzas/pagos/${payment.id}/anular`, { reason }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pagos y recibos" />
    <AppLayout>
        <section>
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-league-600">Módulo 15</p><h1 class="mt-1 text-3xl font-semibold">Pagos, fianzas y recibos</h1><p class="mt-2 max-w-3xl text-slate-600">Estado de cuenta por equipo, cobros en efectivo, pagos parciales, recibos y control de morosidad.</p></div>
                <button v-if="permissions.manage" type="button" class="btn-primary" @click="router.post('/liga/finanzas/sincronizar', {}, { preserveScroll: true })">Sincronizar fianzas y vencimientos</button>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="stat-card"><span class="stat-label">Saldo pendiente</span><span class="stat-value">{{ money(summary.outstanding) }}</span></div>
                <div class="stat-card"><span class="stat-label">Cobrado</span><span class="stat-value">{{ money(summary.collected) }}</span></div>
                <div class="stat-card"><span class="stat-label">Saldo vencido</span><span class="stat-value text-red-700">{{ money(summary.overdue) }}</span></div>
            </div>

            <div v-if="permissions.manage" class="mt-6 grid gap-6 xl:grid-cols-3">
                <form class="card p-5" @submit.prevent="submitConcept">
                    <h2 class="text-xl font-semibold">Nuevo concepto</h2><p class="mt-1 text-sm text-slate-500">Crea conceptos reutilizables para cuotas, fianzas o multas.</p>
                    <div class="mt-4"><label class="form-label">Nombre</label><input v-model="conceptForm.name" class="form-input" required><FormError :message="conceptForm.errors.name" /></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-1"><div><label class="form-label">Tipo</label><select v-model="conceptForm.type" class="form-input"><option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Importe predeterminado</label><input v-model="conceptForm.default_amount" class="form-input" type="number" min="0.01" step="0.01" required><FormError :message="conceptForm.errors.default_amount" /></div></div>
                    <div class="mt-4"><label class="form-label">Descripción</label><textarea v-model="conceptForm.description" class="form-input" rows="2" /></div>
                    <button class="btn-primary mt-4 w-full" :disabled="conceptForm.processing">Guardar concepto</button>
                    <div v-if="concepts.length" class="mt-5 border-t border-slate-200 pt-4"><div v-for="concept in concepts" :key="concept.id" class="flex items-center justify-between gap-3 py-2 text-sm"><div><strong>{{ concept.name }}</strong><p class="text-xs text-slate-500">{{ typeLabels[concept.type] }} · {{ money(concept.default_amount) }}</p></div><button type="button" class="text-xs font-semibold" :class="concept.active ? 'text-red-700' : 'text-emerald-700'" @click="router.put(`/liga/finanzas/conceptos/${concept.id}/estado`, {}, { preserveScroll: true })">{{ concept.active ? 'Desactivar' : 'Activar' }}</button></div></div>
                </form>

                <div class="card p-5">
                    <h2 class="text-xl font-semibold">Asignar adeudo</h2><p class="mt-1 text-sm text-slate-500">Registra un cobro individual o asígnalo simultáneamente a varios equipos.</p>
                    <div class="mt-4 grid grid-cols-2 rounded-xl bg-slate-100 p-1">
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold transition" :class="chargeMode === 'single' ? 'bg-white text-league-700 shadow-sm' : 'text-slate-600'" @click="chargeMode = 'single'">Un equipo</button>
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold transition" :class="chargeMode === 'bulk' ? 'bg-white text-league-700 shadow-sm' : 'text-slate-600'" @click="chargeMode = 'bulk'">Varios equipos</button>
                    </div>

                    <form v-if="chargeMode === 'single'" @submit.prevent="submitCharge">
                        <div class="mt-4"><label class="form-label">Participación</label><select v-model.number="chargeForm.team_participation_id" class="form-input" required @change="selectParticipation"><option :value="0" disabled>Selecciona...</option><option v-for="item in participations" :key="item.id" :value="item.id">{{ item.team.name }} · {{ item.season.name }} · {{ item.competition.name }}</option></select><FormError :message="chargeForm.errors.team_participation_id" /></div>
                        <div class="mt-4"><label class="form-label">Concepto (opcional)</label><select v-model="chargeForm.concept_id" class="form-input" @change="selectConcept"><option :value="null">Captura libre</option><option v-for="concept in concepts.filter((item) => item.active)" :key="concept.id" :value="concept.id">{{ concept.name }}</option></select></div>
                        <div class="mt-4"><label class="form-label">Descripción</label><input v-model="chargeForm.description" class="form-input" required><FormError :message="chargeForm.errors.description" /></div>
                        <div class="mt-4 grid grid-cols-2 gap-3"><div><label class="form-label">Tipo</label><select v-model="chargeForm.type" class="form-input"><option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Importe</label><input v-model="chargeForm.amount" class="form-input" type="number" min="0.01" step="0.01" required></div></div>
                        <div class="mt-4 grid grid-cols-2 gap-3"><div><label class="form-label">Emisión</label><input v-model="chargeForm.issued_on" class="form-input" type="date" required></div><div><label class="form-label">Vencimiento</label><input v-model="chargeForm.due_on" class="form-input" type="date" required></div></div>
                        <FormError :message="chargeForm.errors.due_on" /><button class="btn-primary mt-4 w-full" :disabled="chargeForm.processing">Registrar adeudo</button>
                    </form>

                    <form v-else @submit.prevent="submitBulkCharge">
                        <div class="mt-4"><label class="form-label">Concepto (opcional)</label><select v-model="bulkChargeForm.concept_id" class="form-input" @change="selectBulkConcept"><option :value="null">Captura libre</option><option v-for="concept in concepts.filter((item) => item.active)" :key="concept.id" :value="concept.id">{{ concept.name }}</option></select></div>
                        <div class="mt-4"><label class="form-label">Descripción</label><input v-model="bulkChargeForm.description" class="form-input" required><FormError :message="bulkChargeForm.errors.description" /></div>
                        <div class="mt-4 grid grid-cols-2 gap-3"><div><label class="form-label">Tipo</label><select v-model="bulkChargeForm.type" class="form-input"><option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option></select></div><div><label class="form-label">Importe por equipo</label><input v-model="bulkChargeForm.amount" class="form-input" type="number" min="0.01" step="0.01" required></div></div>
                        <div class="mt-4 grid grid-cols-2 gap-3"><div><label class="form-label">Emisión</label><input v-model="bulkChargeForm.issued_on" class="form-input" type="date" required></div><div><label class="form-label">Vencimiento</label><input v-model="bulkChargeForm.due_on" class="form-input" type="date" required></div></div>
                        <FormError :message="bulkChargeForm.errors.due_on" />

                        <div class="mt-5 border-t border-slate-200 pt-4">
                            <div class="flex items-center justify-between gap-3"><div><p class="form-label mb-0">Equipos participantes</p><p class="text-xs text-slate-500">Incluye competencias en planeación, registro o activas; excluye las cerradas.</p></div><button type="button" class="text-xs font-semibold text-league-700" @click="clearBulkFilters">Limpiar filtros</button></div>
                            <div class="mt-3 grid gap-2">
                                <select v-model.number="seasonFilter" class="form-input" @change="competitionFilter = 0; divisionFilter = 0"><option :value="0">Todas las temporadas</option><option v-for="season in seasonOptions" :key="season.id" :value="season.id">{{ season.name }}</option></select>
                                <select v-model.number="competitionFilter" class="form-input" @change="divisionFilter = 0"><option :value="0">Todas las competencias</option><option v-for="competition in competitionOptions" :key="competition.id" :value="competition.id">{{ competition.name }}</option></select>
                                <select v-model.number="divisionFilter" class="form-input"><option :value="0">Todas las divisiones</option><option v-for="division in divisionOptions" :key="division.id" :value="division.id">{{ division.name }}</option></select>
                            </div>

                            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                                <label v-if="filteredParticipations.length" class="flex cursor-pointer items-center gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible"> Seleccionar los {{ filteredParticipations.length }} equipos visibles</label>
                                <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                                    <label v-for="item in filteredParticipations" :key="item.id" class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-slate-50"><input type="checkbox" class="mt-1" :checked="bulkChargeForm.team_participation_ids.includes(item.id)" @change="toggleBulkParticipation(item.id)"><span class="min-w-0 text-sm"><strong class="block">{{ item.team.name }}</strong><span class="block text-xs text-slate-500">{{ item.season.name }} · {{ item.competition.name }}<template v-if="item.division"> · {{ item.division.name }}</template></span></span></label>
                                    <p v-if="!filteredParticipations.length" class="p-4 text-center text-sm text-slate-500">No hay equipos activos con estos filtros.</p>
                                </div>
                            </div>
                            <FormError :message="bulkChargeForm.errors.team_participation_ids" />
                        </div>

                        <div class="mt-4 rounded-xl bg-league-50 p-3 text-sm"><div class="flex justify-between gap-3"><span>Equipos seleccionados</span><strong>{{ bulkChargeForm.team_participation_ids.length }}</strong></div><div class="mt-1 flex justify-between gap-3"><span>Total proyectado</span><strong class="text-league-900">{{ money(bulkTotal) }}</strong></div></div>
                        <button class="btn-primary mt-4 w-full" :disabled="bulkChargeForm.processing || !bulkChargeForm.team_participation_ids.length">Asignar adeudo a los equipos</button>
                    </form>
                </div>

                <form class="card p-5" @submit.prevent="submitPayment">
                    <h2 class="text-xl font-semibold">Registrar pago en efectivo</h2><p class="mt-1 text-sm text-slate-500">Puede aplicarse parcialmente o a varios adeudos.</p>
                    <div class="mt-4"><label class="form-label">Equipo</label><select v-model.number="paymentForm.team_id" class="form-input" required @change="changePaymentTeam"><option :value="0" disabled>Selecciona...</option><option v-for="team in teamOptions" :key="team.id" :value="team.id">{{ team.name }}</option></select></div>
                    <div v-if="paymentForm.team_id" class="mt-4 space-y-2"><p class="form-label">Adeudos a cubrir</p><div v-if="!teamOpenCharges.length" class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">El equipo no tiene adeudos abiertos.</div><div v-for="charge in teamOpenCharges" :key="charge.id" class="rounded-xl border border-slate-200 p-3"><label class="flex items-start gap-3"><input type="checkbox" class="mt-1" :checked="isSelected(charge.id)" @change="toggleAllocation(charge)"><span class="min-w-0 flex-1 text-sm"><strong>{{ charge.description }}</strong><span class="block text-xs text-slate-500">Saldo {{ money(charge.balance) }} · vence {{ date(charge.due_on) }}</span></span></label><input v-if="allocationIndex(charge.id) >= 0" v-model="paymentForm.allocations[allocationIndex(charge.id)].amount" class="form-input mt-2" type="number" min="0.01" :max="charge.balance" step="0.01"></div></div>
                    <FormError :message="paymentForm.errors.allocations" /><div class="mt-4"><label class="form-label">Fecha y hora</label><input v-model="paymentForm.paid_at" class="form-input" type="datetime-local" required></div><div class="mt-4"><label class="form-label">Observaciones</label><textarea v-model="paymentForm.notes" class="form-input" rows="2" /></div>
                    <div class="mt-4 flex items-center justify-between rounded-xl bg-league-50 p-3"><span class="text-sm font-semibold">Total</span><strong class="text-lg text-league-900">{{ money(paymentTotal) }}</strong></div>
                    <button class="btn-primary mt-4 w-full" :disabled="paymentForm.processing || !paymentForm.allocations.length">Cobrar y generar recibo</button>
                </form>
            </div>

            <section class="card mt-6 overflow-hidden">
                <header class="border-b border-slate-200 p-5"><h2 class="text-xl font-semibold">Estado de cuenta</h2><p class="mt-1 text-sm text-slate-500">Los adeudos cancelados permanecen visibles como historial.</p></header>
                <div v-if="!charges.data.length" class="p-10 text-center text-slate-500">No existen adeudos registrados.</div>
                <div v-else class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-left">Equipo / concepto</th><th class="px-4 py-3 text-left">Fechas</th><th class="px-4 py-3 text-right">Importe</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3 text-center">Estado</th><th v-if="permissions.manage" class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="charge in charges.data" :key="charge.id"><td class="px-4 py-4"><strong>{{ charge.team.name }}</strong><p>{{ charge.description }}</p><p class="text-xs text-slate-500">{{ typeLabels[charge.type] }}<span v-if="charge.season"> · {{ charge.season.name }}</span></p></td><td class="px-4 py-4 text-xs"><p>Vence: {{ date(charge.due_on) }}</p><p v-if="charge.grace_until" class="text-slate-500">Prórroga: {{ date(charge.grace_until) }}</p></td><td class="px-4 py-4 text-right">{{ money(charge.original_amount) }}</td><td class="px-4 py-4 text-right font-semibold">{{ money(charge.balance) }}</td><td class="px-4 py-4 text-center"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClasses[charge.status]">{{ statusLabels[charge.status] }}</span></td><td v-if="permissions.manage" class="px-4 py-4 text-right"><button v-if="!['paid', 'cancelled'].includes(charge.status)" type="button" class="text-xs font-semibold text-red-700" @click="cancelCharge(charge)">Cancelar</button></td></tr></tbody></table></div>
                <nav v-if="charges.links.length > 3" class="flex flex-wrap gap-2 border-t border-slate-200 p-4"><Link v-for="link in charges.links" :key="link.label" :href="link.url ?? '#'" class="rounded-lg border px-3 py-1.5 text-sm" :class="link.active ? 'border-league-600 bg-league-600 text-white' : link.url ? 'border-slate-200 bg-white' : 'pointer-events-none border-slate-100 text-slate-400'" preserve-scroll><span v-html="link.label" /></Link></nav>
            </section>

            <section class="card mt-6 overflow-hidden">
                <header class="border-b border-slate-200 p-5"><h2 class="text-xl font-semibold">Recibos</h2><p class="mt-1 text-sm text-slate-500">Un recibo puede anularse, pero nunca se elimina ni reutiliza su folio.</p></header>
                <div v-if="!payments.length" class="p-10 text-center text-slate-500">Todavía no existen pagos.</div>
                <div v-else class="divide-y divide-slate-200"><article v-for="payment in payments" :key="payment.id" class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"><div><div class="flex flex-wrap items-center gap-2"><strong>{{ payment.receipt_number }}</strong><span v-if="payment.voided_at" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Anulado</span></div><p class="mt-1">{{ payment.team.name }} · {{ money(payment.amount) }}</p><p class="text-xs text-slate-500">{{ dateTime(payment.paid_at) }} · Efectivo · {{ payment.receiver?.name }}</p><p class="mt-1 text-xs text-slate-500">{{ payment.allocations.map((item) => item.charge.description).join(' · ') }}</p><p v-if="payment.void_reason" class="mt-1 text-xs text-red-700">{{ payment.void_reason }}</p></div><div class="flex gap-2"><a :href="`/liga/pagos/${payment.id}/recibo.pdf`" class="btn-secondary">Descargar PDF</a><button v-if="permissions.manage && !payment.voided_at" type="button" class="btn-danger" @click="voidPayment(payment)">Anular</button></div></article></div>
            </section>
        </section>
    </AppLayout>
</template>
