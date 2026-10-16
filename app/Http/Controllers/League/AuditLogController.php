<?php

namespace App\Http\Controllers\League;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\LeagueContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function index(Request $request): Response
    {
        $league = $this->context->current($request)['league'];
        $filters = $this->filters($request);

        return Inertia::render('League/Audit/Index', [
            'entries' => $this->query($league->id, $filters)
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->paginate(20)->withQueryString(),
            'filters' => $filters,
            'options' => [
                'actions' => AuditLog::where('league_id', $league->id)->distinct()->orderBy('action')->pluck('action'),
                'actors' => AuditLog::where('league_id', $league->id)->whereNotNull('actor_user_id')->with('actor:id,name,email')->get()->pluck('actor')->filter()->unique('id')->values(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $league = $this->context->current($request)['league'];
        $filters = $this->filters($request);
        $filename = 'bitacora-'.str($league->slug)->slug().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($league, $filters): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Fecha', 'Acción', 'Usuario', 'Correo', 'IP', 'Motivo', 'Tipo', 'ID', 'Valores anteriores', 'Valores nuevos']);
            $this->query($league->id, $filters)->with('actor:id,name,email')->oldest('id')->chunkById(500, function ($entries) use ($stream): void {
                foreach ($entries as $entry) {
                    fputcsv($stream, [$entry->created_at?->format('d/m/Y H:i:s'), $entry->action, $entry->actor?->name ?? 'Sistema', $entry->actor?->email, $entry->ip_address, $entry->reason, class_basename($entry->auditable_type), $entry->auditable_id, json_encode($entry->old_values, JSON_UNESCAPED_UNICODE), json_encode($entry->new_values, JSON_UNESCAPED_UNICODE)]);
                }
            });
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);
    }

    private function query(int $leagueId, array $filters): Builder
    {
        return AuditLog::query()->where('league_id', $leagueId)
            ->when($filters['action'] ?? null, fn (Builder $query, $value) => $query->where('action', $value))
            ->when($filters['actor_id'] ?? null, fn (Builder $query, $value) => $query->where('actor_user_id', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when($filters['ip_address'] ?? null, fn (Builder $query, $value) => $query->where('ip_address', 'like', $value.'%'));
    }
}
