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
        $oldValues = $league->only(['name', 'logo_path', 'primary_color', 'secondary_color']);
        $data = $request->safe()->except(['logo', 'remove_logo', 'reason']);

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

        $league->update($data);
        $this->audit->log($request, 'league.settings.updated', $league, $league, $oldValues, $league->fresh()->only(array_keys($oldValues)), $request->string('reason')->toString());

        return back()->with('success', 'Identidad visual de la liga actualizada.');
    }
}
