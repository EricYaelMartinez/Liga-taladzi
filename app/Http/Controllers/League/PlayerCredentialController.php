<?php

namespace App\Http\Controllers\League;

use App\Domain\Team\Models\TeamParticipation;
use App\Domain\Player\Models\PlayerCredential;
use App\Domain\Player\Services\PlayerCredentialService;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PlayerCredentialController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly PlayerCredentialService $credentials,
        private readonly AuditLogger $audit,
    )
    {
    }

    public function preview(Request $request, TeamParticipation $participation): View
    {
        $data = $this->data($request, $participation);
        $this->audit->log($request, 'player.credentials.previewed', $participation, $data['league'], [], ['credentials' => $data['pages']->sum(fn ($page) => $page->count())]);
        return view('credentials.team', [
            ...$data,
            'pdfMode' => false,
        ]);
    }

    public function download(Request $request, TeamParticipation $participation): Response
    {
        $data = $this->data($request, $participation);
        $filename = 'credenciales-'.Str::slug($participation->team->name).'-'.Str::slug($participation->season->name).'.pdf';
        $this->audit->log($request, 'player.credentials.downloaded', $participation, $data['league'], [], ['credentials' => $data['pages']->sum(fn ($page) => $page->count()), 'filename' => $filename]);

        return Pdf::loadView('credentials.team', [...$data, 'pdfMode' => true])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function issueMissing(Request $request, TeamParticipation $participation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->context->current($request)['league'];
        $participation->load('team');
        abort_unless($participation->team->league_id === $league->id && $participation->status->value === 'active', 404);

        $registrations = $participation->playerRegistrations()
            ->where('status', 'active')
            ->whereHas('player', fn ($query) => $query->where('status', 'active'))
            ->whereDoesntHave('activeCredential')
            ->get();
        foreach ($registrations as $registration) {
            $credential = $this->credentials->issue($registration, $request->user());
            $this->audit->log($request, 'player.credential.issued', $credential, $league, [], ['folio' => $credential->folio], $data['reason']);
        }

        return back()->with('success', $registrations->isEmpty() ? 'Todas las credenciales ya estaban emitidas.' : "Se emitieron {$registrations->count()} credenciales.");
    }

    public function revoke(Request $request, PlayerCredential $credential): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $league = $this->context->current($request)['league'];
        abort_unless($credential->league_id === $league->id, 404);
        abort_if($credential->status->value !== 'active', 422, 'La credencial ya fue revocada.');
        $credential = $this->credentials->revoke($credential->registration, $request->user(), $data['reason']);
        if ($credential) $this->audit->log($request, 'player.credential.revoked', $credential, $league, ['status' => 'active'], ['status' => 'revoked'], $data['reason']);
        return back()->with('success', 'Credencial revocada.');
    }

    private function data(Request $request, TeamParticipation $participation): array
    {
        $league = $this->context->current($request)['league'];
        $participation->load(['team', 'season', 'competition.category', 'competition.division']);
        abort_unless($participation->team->league_id === $league->id && $participation->status->value === 'active', 404);

        $registrations = $participation->playerRegistrations()
            ->where('status', 'active')
            ->whereHas('player', fn ($query) => $query->where('status', 'active'))
            ->whereHas('activeCredential')
            ->with(['player', 'activeCredential'])
            ->orderBy('jersey_number')
            ->get()
            ->map(function ($registration) {
                $registration->photo_data = $this->dataUri('local', $registration->player->getRawOriginal('photo_path'));
                return $registration;
            });

        abort_if($registrations->isEmpty(), 422, 'Este equipo no tiene credenciales activas emitidas.');

        $logos = collect(range(1, 4))->map(fn (int $position) => $this->dataUri(
            'public', $league->{"credential_logo_{$position}_path"}
        ));

        return [
            'league' => $league,
            'participation' => $participation,
            'pages' => $registrations->chunk(8),
            'logos' => $logos,
            'primary' => $league->primary_color,
            'secondary' => $league->secondary_color,
        ];
    }

    private function dataUri(string $disk, ?string $path): ?string
    {
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }
        $contents = Storage::disk($disk)->get($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/png';
        return "data:{$mime};base64,".base64_encode($contents);
    }
}
