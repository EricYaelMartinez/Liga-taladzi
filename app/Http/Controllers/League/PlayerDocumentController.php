<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Player\Models\Player;
use App\Domain\Player\Models\PlayerDocument;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerDocumentController extends Controller
{
    public function __construct(private readonly LeagueContext $context, private readonly AuditLogger $audit) {}

    public function storeGuardianConsent(Request $request, Player $player): RedirectResponse
    {
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $league = $this->league($request);
        $this->assertPlayer($player, $league);
        if ($player->birth_date->age >= 18) {
            throw ValidationException::withMessages(['document' => 'La carta responsiva solo corresponde a jugadores menores de edad.']);
        }

        $registration = $player->registrations()->with('season')->latest('requested_at')->firstOrFail();
        $file = $data['document'];
        $path = $file->store("players/{$player->id}/documents", 'local');
        if (! $path) throw ValidationException::withMessages(['document' => 'No fue posible guardar el documento privado.']);

        try {
            $document = DB::transaction(function () use ($request, $player, $registration, $file, $path): PlayerDocument {
                return $player->documents()->create([
                    'type' => 'guardian_consent',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'retain_until' => $registration->season->ends_on,
                    'uploaded_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $this->audit->log($request, 'player.document.uploaded', $document, $league, [], ['type' => $document->type, 'retain_until' => $document->retain_until], $data['reason']);
        return back()->with('success', 'Carta responsiva guardada de forma privada.');
    }

    public function download(Request $request, PlayerDocument $document): StreamedResponse
    {
        $document->load('player');
        $league = $this->league($request);
        $this->assertPlayer($document->player, $league);
        abort_unless(Storage::disk('local')->exists($document->getRawOriginal('path')), 404);
        $this->audit->log($request, 'player.document.downloaded', $document, $league, [], ['type' => $document->type]);
        return Storage::disk('local')->download($document->getRawOriginal('path'), $document->original_name ?: basename($document->getRawOriginal('path')));
    }

    public function destroy(Request $request, PlayerDocument $document): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $document->load('player');
        $league = $this->league($request);
        $this->assertPlayer($document->player, $league);
        if (! $document->retain_until || ! now()->startOfDay()->gt($document->retain_until)) {
            throw ValidationException::withMessages(['document' => 'El documento debe conservarse hasta que termine el periodo de retención.']);
        }

        Storage::disk('local')->delete($document->getRawOriginal('path'));
        $document->update(['deleted_by' => $request->user()->id, 'deletion_reason' => $data['reason']]);
        $this->audit->log($request, 'player.document.deleted', $document, $league, ['type' => $document->type], ['deleted_at' => now()->toIso8601String()], $data['reason']);
        $document->delete();
        return back()->with('success', 'Documento eliminado conforme a su periodo de retención.');
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertPlayer(Player $player, League $league): void { abort_unless($player->league_id === $league->id, 404); }
}
