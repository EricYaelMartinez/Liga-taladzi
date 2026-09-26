<?php

namespace App\Http\Controllers\League;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\LeagueContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function __invoke(Request $request): Response
    {
        $league = $this->context->current($request)['league'];

        return Inertia::render('League/Audit/Index', [
            'entries' => AuditLog::where('league_id', $league->id)
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->paginate(20),
        ]);
    }
}
