<?php

namespace App\Http\Controllers\League;

use App\Domain\Finance\Models\FinanceCharge;
use App\Domain\Finance\Models\FinanceConcept;
use App\Domain\Finance\Models\FinancePayment;
use App\Domain\Finance\Services\FinanceService;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueSetting;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Team\Models\TeamRepresentative;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkFinanceChargeRequest;
use App\Http\Requests\StoreFinanceChargeRequest;
use App\Http\Requests\StoreFinanceConceptRequest;
use App\Http\Requests\StoreFinancePaymentRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly FinanceService $finance, private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManage = $this->hasPermission($request, $league, 'finance.manage');
        $teamIds = $canManage
            ? Team::where('league_id', $league->id)->pluck('id')
            : TeamRepresentative::where('user_id', $request->user()->id)->where('status', 'active')->whereHas('team', fn ($query) => $query->where('league_id', $league->id))->pluck('team_id');

        $charges = FinanceCharge::query()->where('league_id', $league->id)->whereIn('team_id', $teamIds)
            ->with(['team:id,name', 'participation:id,registered_name,competition_id', 'participation.competition:id,name', 'season:id,name', 'concept:id,name'])
            ->latest('issued_on')->latest('id')->paginate(30, ['*'], 'charges_page')->withQueryString();
        $payments = FinancePayment::query()->where('league_id', $league->id)->whereIn('team_id', $teamIds)
            ->with(['team:id,name', 'receiver:id,name', 'voider:id,name', 'allocations.charge:id,description'])
            ->latest('paid_at')->limit(100)->get();
        $openCharges = FinanceCharge::query()->where('league_id', $league->id)->whereIn('team_id', $teamIds)
            ->whereIn('status', ['pending', 'partial'])->where('balance', '>', 0)->orderBy('due_on')->get(['id', 'team_id', 'description', 'balance', 'due_on', 'status']);

        return Inertia::render('League/Finance/Index', [
            'charges' => $charges,
            'payments' => $payments,
            'openCharges' => $openCharges,
            'permissions' => ['manage' => $canManage],
            'currency' => LeagueSetting::where('league_id', $league->id)->value('currency') ?? 'MXN',
            'summary' => [
                'outstanding' => FinanceCharge::where('league_id', $league->id)->whereIn('team_id', $teamIds)->whereIn('status', ['pending', 'partial'])->sum('balance'),
                'collected' => FinancePayment::where('league_id', $league->id)->whereIn('team_id', $teamIds)->whereNull('voided_at')->sum('amount'),
                'overdue' => FinanceCharge::where('league_id', $league->id)->whereIn('team_id', $teamIds)->whereIn('status', ['pending', 'partial'])->whereDate('due_on', '<', today())->sum('balance'),
            ],
            'concepts' => $canManage ? FinanceConcept::where('league_id', $league->id)->orderByDesc('active')->orderBy('name')->get() : [],
            'participations' => $canManage ? TeamParticipation::whereHas('team', fn ($query) => $query->where('league_id', $league->id))
                ->whereNotIn('status', ['rejected', 'deregistered'])->with(['team:id,name', 'season:id,name,status', 'competition:id,name,division_id,status', 'division:id,name'])->orderByDesc('id')->get() : [],
        ]);
    }

    public function storeConcept(StoreFinanceConceptRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league);
        if (FinanceConcept::where('league_id', $league->id)->whereRaw('LOWER(name) = ?', [strtolower($request->string('name')->toString())])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un concepto con este nombre.']);
        }
        $concept = FinanceConcept::create([...$request->validated(), 'league_id' => $league->id, 'active' => true, 'created_by' => $request->user()->id]);
        $this->audit->log($request, 'finance.concept.created', $concept, $league, [], $concept->toArray());
        return back()->with('success', 'Concepto de cobro creado.');
    }

    public function toggleConcept(Request $request, FinanceConcept $concept): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league); $this->assertConcept($concept, $league);
        $old = $concept->active; $concept->update(['active' => ! $concept->active]);
        $this->audit->log($request, 'finance.concept.status_changed', $concept, $league, ['active' => $old], ['active' => $concept->active]);
        return back()->with('success', $concept->active ? 'Concepto activado.' : 'Concepto desactivado.');
    }

    public function storeCharge(StoreFinanceChargeRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league); $data = $request->validated();
        $participation = TeamParticipation::with('team')->findOrFail($data['team_participation_id']);
        abort_unless($participation->team->league_id === $league->id && $participation->team_id === (int) $data['team_id'] && $participation->season_id === (int) $data['season_id'], 404);
        if (! empty($data['concept_id'])) $this->assertConcept(FinanceConcept::findOrFail($data['concept_id']), $league);
        $charge = $this->finance->createCharge($data, $league->id, $request->user()->id);
        $this->audit->log($request, 'finance.charge.created', $charge, $league, [], $charge->toArray(), $charge->description);
        return back()->with('success', 'Adeudo registrado.');
    }

    public function storeBulkCharges(StoreBulkFinanceChargeRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league); $data = $request->validated();
        if (! empty($data['concept_id'])) $this->assertConcept(FinanceConcept::findOrFail($data['concept_id']), $league);

        $participations = TeamParticipation::query()
            ->whereIn('id', $data['team_participation_ids'])
            ->where('status', 'active')
            ->whereHas('team', fn ($query) => $query->where('league_id', $league->id)->where('status', 'active'))
            ->whereHas('season', fn ($query) => $query->whereNotIn('status', ['finished', 'archived', 'cancelled']))
            ->whereHas('competition', fn ($query) => $query->whereNotIn('status', ['finished', 'archived', 'cancelled']))
            ->with('team:id,name,league_id,status')
            ->get();

        if ($participations->count() !== count($data['team_participation_ids'])) {
            throw ValidationException::withMessages([
                'team_participation_ids' => 'Uno o más equipos ya no están activos, pertenecen a una competencia o temporada cerrada, o no corresponden a la liga. Actualiza la pantalla y vuelve a seleccionarlos.',
            ]);
        }

        $charges = $this->finance->createBulkCharges($data, $participations, $league->id, $request->user()->id);
        foreach ($charges as $charge) {
            $this->audit->log(
                $request,
                'finance.charge.bulk_created',
                $charge,
                $league,
                [],
                $charge->toArray(),
                "Asignación masiva de {$charges->count()} adeudos.",
            );
        }

        return back()->with('success', "Se asignó el adeudo a {$charges->count()} equipos.");
    }

    public function cancelCharge(Request $request, FinanceCharge $charge): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->league($request); $this->assertManage($request, $league); $this->assertCharge($charge, $league); $old = $charge->toArray();
        $this->finance->cancelCharge($charge, $data['reason'], $request->user()->id);
        $this->audit->log($request, 'finance.charge.cancelled', $charge, $league, $old, $charge->fresh()->toArray(), $data['reason']);
        return back()->with('success', 'Adeudo cancelado sin eliminar su historial.');
    }

    public function storePayment(StoreFinancePaymentRequest $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league); $data = $request->validated();
        abort_unless(Team::where('league_id', $league->id)->whereKey($data['team_id'])->exists(), 404);
        $payment = $this->finance->registerPayment($data, $league->id, $request->user()->id);
        $this->audit->log($request, 'finance.payment.received', $payment, $league, [], $payment->toArray(), $payment->notes);
        return back()->with('success', "Pago registrado. Recibo {$payment->receipt_number}.");
    }

    public function voidPayment(Request $request, FinancePayment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->league($request); $this->assertManage($request, $league); $this->assertPayment($payment, $league); $old = $payment->toArray();
        $this->finance->voidPayment($payment, $data['reason'], $request->user()->id);
        $this->audit->log($request, 'finance.payment.voided', $payment, $league, $old, $payment->fresh()->toArray(), $data['reason']);
        return back()->with('success', 'Recibo anulado y saldos restaurados. El registro se conservó.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $league = $this->league($request); $this->assertManage($request, $league);
        $created = $this->finance->syncObligations($league->id, $request->user()->id);
        foreach ($created as $charge) $this->audit->log($request, 'finance.charge.synchronized', $charge, $league, [], $charge->toArray(), $charge->description);
        return back()->with('success', $created->isEmpty() ? 'Las fianzas y multas ya estaban sincronizadas; también se actualizaron los vencimientos.' : "Se generaron {$created->count()} adeudos y se actualizaron los vencimientos.");
    }

    public function receipt(Request $request, FinancePayment $payment): HttpResponse
    {
        $league = $this->league($request); $this->assertPayment($payment, $league);
        $canManage = $this->hasPermission($request, $league, 'finance.manage');
        $ownsTeam = TeamRepresentative::where('user_id', $request->user()->id)->where('team_id', $payment->team_id)->where('status', 'active')->exists();
        abort_unless($canManage || $ownsTeam, 403);
        $payment->load(['team', 'receiver', 'voider', 'allocations.charge']);
        $league->loadMissing('setting');
        $this->audit->log($request, 'finance.receipt.downloaded', $payment, $league, [], ['receipt_number' => $payment->receipt_number]);
        return Pdf::loadView('finance.receipt', ['payment' => $payment, 'league' => $league])->setPaper('letter', 'portrait')->download(strtolower($payment->receipt_number).'.pdf');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertManage(Request $request, League $league): void { abort_unless($this->hasPermission($request, $league, 'finance.manage'), 403); }
    private function assertConcept(FinanceConcept $concept, League $league): void { abort_unless($concept->league_id === $league->id, 404); }
    private function assertCharge(FinanceCharge $charge, League $league): void { abort_unless($charge->league_id === $league->id, 404); }
    private function assertPayment(FinancePayment $payment, League $league): void { abort_unless($payment->league_id === $league->id, 404); }
    private function hasPermission(Request $request, League $league, string $permission): bool { $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY); return $request->user()->hasPermission($permission) || $request->user()->hasLeaguePermission($permission, $league->id, $roleId); }
}
