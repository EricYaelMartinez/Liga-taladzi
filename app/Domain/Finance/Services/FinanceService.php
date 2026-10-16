<?php

namespace App\Domain\Finance\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Discipline\Models\Sanction;
use App\Domain\Finance\Models\FinanceCharge;
use App\Domain\Finance\Models\FinancePayment;
use App\Domain\Finance\Models\FinanceReceiptSequence;
use App\Domain\League\Models\LeagueSetting;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Team\Enums\ParticipationStatus;
use App\Domain\Team\Enums\TeamStatus;
use App\Domain\Team\Models\TeamParticipation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function createCharge(array $data, int $leagueId, int $actorId): FinanceCharge
    {
        $settings = LeagueSetting::where('league_id', $leagueId)->first();
        $charge = $this->createChargeRecord($data, $leagueId, $actorId, $settings);

        $this->evaluateLeague($leagueId, $actorId);
        return $charge;
    }

    public function createBulkCharges(array $data, Collection $participations, int $leagueId, int $actorId): Collection
    {
        return DB::transaction(function () use ($data, $participations, $leagueId, $actorId): Collection {
            $participations = $participations->unique('id')->values();
            if ($participations->isEmpty()) {
                throw ValidationException::withMessages(['team_participation_ids' => 'Selecciona al menos un equipo.']);
            }

            $duplicateQuery = FinanceCharge::query()
                ->where('league_id', $leagueId)
                ->whereIn('team_participation_id', $participations->pluck('id'))
                ->whereDate('due_on', Carbon::parse($data['due_on'])->toDateString())
                ->where('status', '!=', 'cancelled');

            if (! empty($data['concept_id'])) {
                $duplicateQuery->where('concept_id', $data['concept_id']);
            } else {
                $duplicateQuery->whereNull('concept_id')
                    ->where('description', $data['description'])
                    ->where('type', $data['type'])
                    ->where('original_amount', $data['amount']);
            }

            $duplicateParticipationIds = $duplicateQuery->lockForUpdate()->pluck('team_participation_id');
            if ($duplicateParticipationIds->isNotEmpty()) {
                $teamNames = $participations->whereIn('id', $duplicateParticipationIds)->pluck('team.name')->filter()->unique()->sort()->values();
                throw ValidationException::withMessages([
                    'team_participation_ids' => 'El cobro ya existe con el mismo vencimiento para: '.$teamNames->join(', ').'. No se creó ningún adeudo.',
                ]);
            }

            $settings = LeagueSetting::where('league_id', $leagueId)->first();
            $batchKey = 'bulk:'.Str::uuid();
            $charges = collect();

            foreach ($participations as $participation) {
                $charges->push($this->createChargeRecord([
                    ...$data,
                    'team_id' => $participation->team_id,
                    'team_participation_id' => $participation->id,
                    'season_id' => $participation->season_id,
                    'source_key' => "{$batchKey}:{$participation->id}",
                ], $leagueId, $actorId, $settings));
            }

            $this->evaluateLeague($leagueId, $actorId);
            return $charges;
        });
    }

    public function registerPayment(array $data, int $leagueId, int $actorId): FinancePayment
    {
        return DB::transaction(function () use ($data, $leagueId, $actorId): FinancePayment {
            $allocations = collect($data['allocations'])->filter(fn (array $allocation) => $this->cents($allocation['amount']) > 0);
            if ($allocations->isEmpty()) throw ValidationException::withMessages(['allocations' => 'Debes aplicar el pago al menos a un adeudo.']);
            if ($allocations->pluck('charge_id')->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['allocations' => 'Un adeudo no puede aparecer dos veces en el mismo pago.']);

            $charges = FinanceCharge::query()->where('league_id', $leagueId)->where('team_id', $data['team_id'])
                ->whereIn('id', $allocations->pluck('charge_id'))->lockForUpdate()->get()->keyBy('id');
            if ($charges->count() !== $allocations->count()) throw ValidationException::withMessages(['allocations' => 'Uno de los adeudos no pertenece al equipo o a la liga seleccionada.']);

            $totalCents = 0;
            foreach ($allocations as $allocation) {
                $charge = $charges->get((int) $allocation['charge_id']);
                if (in_array($charge->status, ['paid', 'cancelled'], true)) throw ValidationException::withMessages(['allocations' => "El adeudo {$charge->description} ya no admite pagos."]);
                $amountCents = $this->cents($allocation['amount']);
                if ($amountCents > $this->cents($charge->balance)) throw ValidationException::withMessages(['allocations' => "El importe aplicado a {$charge->description} supera su saldo."]);
                $totalCents += $amountCents;
            }
            if ($totalCents <= 0) throw ValidationException::withMessages(['allocations' => 'El total del pago debe ser mayor que cero.']);

            $payment = FinancePayment::create([
                'league_id' => $leagueId,
                'team_id' => $data['team_id'],
                'receipt_number' => $this->nextReceiptNumber($leagueId),
                'amount' => $this->money($totalCents),
                'method' => 'cash',
                'paid_at' => Carbon::parse($data['paid_at']),
                'notes' => $data['notes'] ?? null,
                'received_by' => $actorId,
            ]);

            foreach ($allocations as $allocation) {
                $charge = $charges->get((int) $allocation['charge_id']);
                $amountCents = $this->cents($allocation['amount']);
                $payment->allocations()->create(['charge_id' => $charge->id, 'amount' => $this->money($amountCents)]);
                $remaining = max(0, $this->cents($charge->balance) - $amountCents);
                $charge->update([
                    'balance' => $this->money($remaining),
                    'status' => $remaining === 0 ? 'paid' : 'partial',
                    'paid_at' => $remaining === 0 ? $payment->paid_at : null,
                ]);
            }

            $this->evaluateLeague($leagueId, $actorId);
            return $payment->load('allocations.charge');
        });
    }

    public function cancelCharge(FinanceCharge $charge, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($charge, $reason, $actorId): void {
            $locked = FinanceCharge::lockForUpdate()->findOrFail($charge->id);
            if ($locked->status === 'cancelled') throw ValidationException::withMessages(['reason' => 'El adeudo ya fue cancelado.']);
            if ($locked->allocations()->whereHas('payment', fn ($query) => $query->whereNull('voided_at'))->exists()) {
                throw ValidationException::withMessages(['reason' => 'No puede cancelarse un adeudo con pagos vigentes; primero anula el recibo relacionado.']);
            }
            $locked->update(['status' => 'cancelled', 'balance' => 0, 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancellation_reason' => $reason]);
            $this->evaluateLeague($locked->league_id, $actorId);
        });
    }

    public function voidPayment(FinancePayment $payment, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($payment, $reason, $actorId): void {
            $locked = FinancePayment::lockForUpdate()->with('allocations.charge')->findOrFail($payment->id);
            if ($locked->voided_at) throw ValidationException::withMessages(['reason' => 'El recibo ya fue anulado.']);
            foreach ($locked->allocations as $allocation) {
                $charge = FinanceCharge::lockForUpdate()->findOrFail($allocation->charge_id);
                if ($charge->status === 'cancelled') throw ValidationException::withMessages(['reason' => 'Uno de los adeudos relacionados está cancelado y requiere revisión administrativa.']);
                $balance = $this->cents($charge->balance) + $this->cents($allocation->amount);
                $charge->update([
                    'balance' => $this->money($balance),
                    'status' => $balance >= $this->cents($charge->original_amount) ? 'pending' : 'partial',
                    'paid_at' => null,
                ]);
            }
            $locked->update(['voided_at' => now(), 'voided_by' => $actorId, 'void_reason' => $reason]);
            $this->evaluateLeague($locked->league_id, $actorId);
        });
    }

    public function syncObligations(int $leagueId, int $actorId): Collection
    {
        $settings = LeagueSetting::where('league_id', $leagueId)->first();
        $created = collect();

        if ($settings?->bond_enabled && $this->cents($settings->bond_amount) > 0) {
            TeamParticipation::query()->whereHas('team', fn ($query) => $query->where('league_id', $leagueId))
                ->whereIn('status', ['active', 'suspended'])->with(['team', 'season'])->each(function (TeamParticipation $participation) use ($leagueId, $actorId, $settings, $created): void {
                    $charge = $this->firstOrCreateSystemCharge(
                        $leagueId, $participation, 'bond', "Fianza — {$participation->season->name}",
                        $settings->bond_amount, "bond:participation:{$participation->id}", $actorId,
                    );
                    if ($charge->wasRecentlyCreated) $created->push($charge);
                });
        }

        Sanction::query()->where('league_id', $leagueId)->where('kind', 'fine')->where('status', 'active')
            ->whereNotNull('team_id')->where('fine_amount', '>', 0)->with(['team', 'season'])->each(function (Sanction $sanction) use ($leagueId, $actorId, $created): void {
                $participation = TeamParticipation::query()->where('team_id', $sanction->team_id)
                    ->when($sanction->season_id, fn ($query) => $query->where('season_id', $sanction->season_id))->latest('id')->first();
                $charge = $this->firstOrCreateSystemCharge(
                    $leagueId, $participation, 'fine', "Multa disciplinaria: {$sanction->reason}",
                    $sanction->fine_amount, "sanction:{$sanction->id}", $actorId, $sanction->team_id, $sanction->season_id,
                );
                if ($charge->wasRecentlyCreated) $created->push($charge);
            });

        $this->evaluateLeague($leagueId, $actorId);
        return $created;
    }

    public function evaluateAll(): int
    {
        return (int) DB::table('leagues')->whereNull('deleted_at')->pluck('id')->sum(fn ($leagueId) => $this->evaluateLeague((int) $leagueId));
    }

    public function evaluateLeague(int $leagueId, ?int $actorId = null): int
    {
        $settings = LeagueSetting::where('league_id', $leagueId)->first();
        $reactivationDays = (int) ($settings?->reactivation_window_days ?? 21);
        $today = today();

        $changed = 0;
        TeamParticipation::query()->whereHas('team', fn ($query) => $query->where('league_id', $leagueId))
            ->with('team')->get()->each(function (TeamParticipation $participation) use ($leagueId, $reactivationDays, $today, $actorId, &$changed): void {
                if ($participation->financial_status === 'excluded') return;
                $overdue = FinanceCharge::query()->where('league_id', $leagueId)->where('team_id', $participation->team_id)
                    ->where(fn ($query) => $query->where('team_participation_id', $participation->id)->orWhere(function ($team) use ($participation): void {
                        $team->whereNull('team_participation_id')->where(fn ($season) => $season->whereNull('season_id')->orWhere('season_id', $participation->season_id));
                    }))
                    ->whereIn('status', ['pending', 'partial'])->where('balance', '>', 0)->whereDate('due_on', '<', $today)
                    ->orderBy('due_on')->get();

                $target = 'clear';
                foreach ($overdue as $charge) {
                    $graceEnd = $charge->grace_until ?: $charge->due_on;
                    if ($today->lessThanOrEqualTo($graceEnd)) {
                        if ($target === 'clear') $target = 'grace';
                        continue;
                    }
                    $reactivationEnd = $graceEnd->copy()->addDays($reactivationDays);
                    $target = $today->greaterThan($reactivationEnd) ? 'excluded' : 'suspended';
                    if ($target === 'excluded') break;
                }
                if ($this->applyFinancialStatus($participation, $target, $actorId)) $changed++;
            });
        return $changed;
    }

    private function firstOrCreateSystemCharge(int $leagueId, ?TeamParticipation $participation, string $type, string $description, mixed $amount, string $sourceKey, int $actorId, ?int $teamId = null, ?int $seasonId = null): FinanceCharge
    {
        $settings = LeagueSetting::where('league_id', $leagueId)->first();
        $due = today();
        return FinanceCharge::firstOrCreate(
            ['league_id' => $leagueId, 'source_key' => $sourceKey],
            [
                'team_id' => $participation?->team_id ?? $teamId,
                'team_participation_id' => $participation?->id,
                'season_id' => $participation?->season_id ?? $seasonId,
                'description' => $description,
                'type' => $type,
                'original_amount' => $amount,
                'balance' => $amount,
                'issued_on' => today(),
                'due_on' => $due,
                'grace_until' => $settings?->payment_grace_days === null ? null : $due->copy()->addDays((int) $settings->payment_grace_days),
                'status' => 'pending',
                'created_by' => $actorId,
            ],
        );
    }

    private function createChargeRecord(array $data, int $leagueId, int $actorId, ?LeagueSetting $settings): FinanceCharge
    {
        $issuedOn = Carbon::parse($data['issued_on'])->startOfDay();
        $dueOn = Carbon::parse($data['due_on'])->startOfDay();
        $graceDays = $settings?->payment_grace_days;

        return FinanceCharge::create([
            'league_id' => $leagueId,
            'team_id' => $data['team_id'],
            'team_participation_id' => $data['team_participation_id'] ?? null,
            'season_id' => $data['season_id'] ?? null,
            'concept_id' => $data['concept_id'] ?? null,
            'source_key' => $data['source_key'] ?? null,
            'description' => $data['description'],
            'type' => $data['type'],
            'original_amount' => $data['amount'],
            'balance' => $data['amount'],
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'grace_until' => $graceDays === null ? null : $dueOn->copy()->addDays((int) $graceDays),
            'status' => 'pending',
            'created_by' => $actorId,
        ]);
    }

    private function applyFinancialStatus(TeamParticipation $participation, string $target, ?int $actorId): bool
    {
        $current = $participation->financial_status ?: 'clear';
        if ($current === $target) return false;
        $changes = ['financial_status' => $target, 'financial_status_changed_at' => now()];

        if ($target === 'suspended') {
            $changes['financial_suspended_at'] = $participation->financial_suspended_at ?? now();
            if ($participation->status === ParticipationStatus::Active) {
                $changes['status'] = ParticipationStatus::Suspended;
                $changes['suspension_source'] = 'financial';
                $changes['suspended_at'] = now();
                $participation->team->update(['status' => TeamStatus::Suspended]);
                $this->cancelFutureMatches($participation, $actorId, 'Suspensión automática por adeudo vencido.');
            }
        } elseif ($target === 'excluded') {
            $changes['financial_excluded_at'] = now();
            $changes['status'] = ParticipationStatus::Deregistered;
            $changes['suspension_source'] = 'financial';
            $participation->team->update(['status' => TeamStatus::Deregistered]);
            $this->cancelFutureMatches($participation, $actorId, 'Exclusión automática por adeudo fuera del periodo de reactivación.');
        } elseif ($target === 'clear' && $current === 'suspended' && $participation->status === ParticipationStatus::Suspended && $participation->suspension_source === 'financial') {
            $changes['status'] = ParticipationStatus::Active;
            $changes['suspension_source'] = null;
            $changes['reactivated_at'] = now();
            $participation->team->update(['status' => TeamStatus::Active]);
        }

        $participation->update($changes);
        AuditLog::create([
            'actor_user_id' => $actorId,
            'league_id' => $participation->team->league_id,
            'action' => 'finance.participation_status_changed',
            'auditable_type' => $participation->getMorphClass(),
            'auditable_id' => $participation->id,
            'old_values' => ['financial_status' => $current],
            'new_values' => ['financial_status' => $target, 'participation_status' => $participation->fresh()->status->value],
            'reason' => 'Evaluación automática de vencimientos financieros.',
            'created_at' => now(),
        ]);
        return true;
    }

    private function cancelFutureMatches(TeamParticipation $participation, ?int $actorId, string $reason): void
    {
        GameMatch::query()->where(fn ($query) => $query->where('home_team_participation_id', $participation->id)->orWhere('away_team_participation_id', $participation->id))
            ->whereIn('status', ['draft', 'scheduled', 'postponed'])->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '>=', now()))
            ->get()->each(function (GameMatch $match) use ($actorId, $reason): void {
                $old = $match->status->value;
                $match->update(['status' => 'cancelled']);
                $match->scheduleChanges()->create(['type' => 'cancelled', 'old_values' => ['status' => $old], 'new_values' => ['status' => 'cancelled', 'administrative_result_pending' => true], 'reason' => $reason, 'performed_by' => $actorId, 'occurred_at' => now()]);
            });
    }

    private function nextReceiptNumber(int $leagueId): string
    {
        FinanceReceiptSequence::firstOrCreate(['league_id' => $leagueId], ['next_number' => 1]);
        $sequence = FinanceReceiptSequence::where('league_id', $leagueId)->lockForUpdate()->firstOrFail();
        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);
        return 'REC-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function cents(mixed $amount): int { return (int) round(((float) ($amount ?? 0)) * 100); }
    private function money(int $cents): string { return number_format($cents / 100, 2, '.', ''); }
}
