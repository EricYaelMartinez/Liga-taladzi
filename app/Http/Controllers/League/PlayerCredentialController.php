<?php

namespace App\Http\Controllers\League;

use App\Domain\Team\Models\TeamParticipation;
use App\Http\Controllers\Controller;
use App\Support\LeagueContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PlayerCredentialController extends Controller
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function preview(Request $request, TeamParticipation $participation): View
    {
        return view('credentials.team', [
            ...$this->data($request, $participation),
            'pdfMode' => false,
        ]);
    }

    public function download(Request $request, TeamParticipation $participation): Response
    {
        $data = $this->data($request, $participation);
        $filename = 'credenciales-'.Str::slug($participation->team->name).'-'.Str::slug($participation->season->name).'.pdf';

        return Pdf::loadView('credentials.team', [...$data, 'pdfMode' => true])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function data(Request $request, TeamParticipation $participation): array
    {
        $league = $this->context->current($request)['league'];
        $participation->load(['team', 'season', 'competition.category', 'competition.division']);
        abort_unless($participation->team->league_id === $league->id && $participation->status->value === 'active', 404);

        $registrations = $participation->playerRegistrations()
            ->where('status', 'active')
            ->whereHas('player', fn ($query) => $query->where('status', 'active'))
            ->with('player')
            ->orderBy('jersey_number')
            ->get()
            ->map(function ($registration) {
                $registration->photo_data = $this->dataUri('local', $registration->player->getRawOriginal('photo_path'));
                return $registration;
            });

        abort_if($registrations->isEmpty(), 422, 'Este equipo no tiene jugadores activos y aprobados para generar credenciales.');

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
