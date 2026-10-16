<?php

namespace App\Http\Controllers\League;

use App\Domain\League\Models\League;
use App\Domain\Reporting\Services\LeagueReportService;
use App\Domain\Reporting\Services\SimpleXlsxWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportLeagueReportRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly LeagueReportService $reports,
        private readonly SimpleXlsxWriter $xlsx,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $league = $this->league($request);

        return Inertia::render('League/Exports/Index', [
            'reportTypes' => $this->reports->definitions(),
            'options' => $this->reports->options($league),
        ]);
    }

    public function export(ExportLeagueReportRequest $request): Response
    {
        $league = $this->league($request);
        $validated = $request->validated();
        $type = $validated['type'];
        $format = $validated['format'];
        $filters = collect($validated)->except(['type', 'format'])->filter(fn ($value) => $value !== null && $value !== '')->all();
        $report = $this->reports->generate($league, $type, $filters);
        $folio = 'REP-'.now()->format('Ymd-His').'-'.$request->user()->id;
        $baseName = Str::slug($report['title']).'-'.strtolower($folio);

        if ($format === 'xlsx') {
            $path = $this->xlsx->create($report['columns'], $report['rows'], $report['title']);
            $response = response()->download($path, $baseName.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

            $this->auditExport($request, $league, $folio, $type, $format, $filters, $report['total']);

            return $response;
        }

        $logoPath = $league->logo_path ? storage_path('app/public/'.$league->logo_path) : null;
        if ($logoPath && ! is_file($logoPath)) $logoPath = null;

        $response = Pdf::loadView('exports.report', compact('league', 'report', 'folio', 'logoPath', 'filters'))
            ->setPaper('a4', 'landscape')
            ->download($baseName.'.pdf');

        $this->auditExport($request, $league, $folio, $type, $format, $filters, $report['total']);

        return $response;
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }

    private function auditExport(Request $request, League $league, string $folio, string $type, string $format, array $filters, int $rows): void
    {
        $this->audit->log($request, 'report.exported', $league, $league, [], [
            'folio' => $folio,
            'type' => $type,
            'format' => $format,
            'filters' => $filters,
            'rows' => $rows,
        ]);
    }
}
