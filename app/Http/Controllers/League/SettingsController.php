<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeagueSettingsRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly AuditLogger $audit,
    ) {
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('League/Settings/Edit', [
            'league' => $this->context->current($request)['league'],
        ]);
    }

    public function update(UpdateLeagueSettingsRequest $request): RedirectResponse
    {
        $league = $this->context->current($request)['league'];
        $logoColumns = ['credential_logo_1_path', 'credential_logo_2_path', 'credential_logo_3_path', 'credential_logo_4_path'];
        $oldValues = $league->only(['name', 'logo_path', ...$logoColumns, 'primary_color', 'secondary_color']);
        $data = $request->safe()->except([
            'logo', 'remove_logo', 'reason',
            'credential_logo_1', 'credential_logo_2', 'credential_logo_3', 'credential_logo_4',
            'remove_credential_logo_1', 'remove_credential_logo_2', 'remove_credential_logo_3', 'remove_credential_logo_4',
        ]);

        if ($request->boolean('remove_logo') && $league->logo_path) {
            Storage::disk('public')->delete($league->logo_path);
            $data['logo_path'] = null;
        }
        if ($request->hasFile('logo')) {
            if ($league->logo_path) {
                Storage::disk('public')->delete($league->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store("leagues/{$league->id}", 'public');
        }

        foreach (range(1, 4) as $position) {
            $input = "credential_logo_{$position}";
            $remove = "remove_credential_logo_{$position}";
            $column = "credential_logo_{$position}_path";
            if ($request->boolean($remove) && $league->{$column}) {
                Storage::disk('public')->delete($league->{$column});
                $data[$column] = null;
            }
            if ($request->hasFile($input)) {
                if ($league->{$column}) {
                    Storage::disk('public')->delete($league->{$column});
                }
                $data[$column] = $request->file($input)->store("leagues/{$league->id}/credential-logos", 'public');
            }
        }

        $league->update($data);
        $this->audit->log($request, 'league.settings.updated', $league, $league, $oldValues, $league->fresh()->only(array_keys($oldValues)), $request->string('reason')->toString());

        return back()->with('success', 'Identidad visual de la liga actualizada.');
    }
}
