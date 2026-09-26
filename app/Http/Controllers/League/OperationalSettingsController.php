<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\LeagueSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOperationalSettingsRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationalSettingsController extends Controller
{
    private const FIELDS = [
        'match_periods',
        'period_duration_minutes',
        'halftime_minutes',
        'schedule_buffer_minutes',
        'appeal_deadline_hours',
        'payment_grace_days',
        'reactivation_window_days',
        'bond_enabled',
        'bond_amount',
        'currency',
        'revision',
    ];

    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function edit(Request $request): Response
    {
        $league = $this->context->current($request)['league'];
        $settings = LeagueSetting::firstOrNew(['league_id' => $league->id], $this->defaults());

        return Inertia::render('League/Settings/Operational', [
            'settings' => $settings->only(self::FIELDS),
            'isConfigured' => $settings->exists,
        ]);
    }

    public function update(UpdateOperationalSettingsRequest $request): RedirectResponse
    {
        $league = $this->context->current($request)['league'];

        DB::transaction(function () use ($request, $league): void {
            $settings = LeagueSetting::where('league_id', $league->id)->lockForUpdate()->first();
            $oldValues = $settings?->only(self::FIELDS) ?? [];
            $settings ??= new LeagueSetting(['league_id' => $league->id, ...$this->defaults()]);

            $data = $request->safe()->except(['reason']);
            if (! $request->boolean('bond_enabled')) {
                $data['bond_amount'] = null;
            }
            $data['updated_by'] = $request->user()->id;
            $data['revision'] = $settings->exists ? $settings->revision + 1 : 1;
            $settings->fill($data)->save();

            $this->audit->log(
                $request,
                'league.operational_settings.updated',
                $settings,
                $league,
                $oldValues,
                $settings->fresh()->only(self::FIELDS),
                $request->string('reason')->toString(),
            );
        });

        return back()->with('success', 'Parámetros generales actualizados correctamente.');
    }

    private function defaults(): array
    {
        return [
            'match_periods' => 2,
            'period_duration_minutes' => null,
            'halftime_minutes' => null,
            'schedule_buffer_minutes' => null,
            'appeal_deadline_hours' => 2,
            'payment_grace_days' => null,
            'reactivation_window_days' => 21,
            'bond_enabled' => false,
            'bond_amount' => null,
            'currency' => 'MXN',
            'revision' => 1,
        ];
    }
}
