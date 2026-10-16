<?php

namespace App\Http\Controllers;

use App\Domain\Notification\Enums\NoticeStatus;
use App\Domain\Notification\Models\LeagueNoticeRecipient;
use App\Support\LeagueContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $activeContext = app(LeagueContext::class)->current($request);
        $unread = $activeContext ? LeagueNoticeRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('notice', fn ($query) => $query
                ->where('league_id', $activeContext['league']->id)
                ->where('status', NoticeStatus::Published)
                ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->count() : 0;

        return Inertia::render('Dashboard', [
            'summary' => [
                'pendingTasks' => 0,
                'upcomingMatches' => 0,
                'notifications' => $unread,
            ],
        ]);
    }
}
