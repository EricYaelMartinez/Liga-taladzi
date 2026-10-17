<?php

namespace App\Http\Middleware;

use App\Domain\Notification\Enums\NoticeStatus;
use App\Domain\Notification\Models\LeagueNoticeRecipient;
use App\Support\LeagueContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $activeContext = app(LeagueContext::class)->current($request);

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'brand' => [
                'name' => config('branding.name'),
                'tagline' => config('branding.tagline'),
                'logoUrl' => config('branding.logo_path'),
                'colors' => config('branding.colors'),
            ],
            'auth' => fn () => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'status' => $request->user()->status->value,
                    'forcePasswordChange' => $request->user()->force_password_change,
                    'roles' => $request->user()->roles()->pluck('slug')->all(),
                    'permissions' => $request->user()->roles()
                        ->with('permissions:id,slug')
                        ->get()
                        ->flatMap->permissions
                        ->concat($activeContext ? $activeContext['role']->permissions()->get(['permissions.id', 'slug']) : [])
                        ->pluck('slug')
                        ->unique()
                        ->values()
                        ->all(),
                ] : null,
            ],
            'activeContext' => fn () => $activeContext ? [
                'league' => [
                    'id' => $activeContext['league']->id,
                    'name' => $activeContext['league']->name,
                    'logoUrl' => $activeContext['league']->logo_path ? Storage::disk('public')->url($activeContext['league']->logo_path) : null,
                    'primaryColor' => $activeContext['league']->primary_color,
                    'secondaryColor' => $activeContext['league']->secondary_color,
                ],
                'role' => [
                    'id' => $activeContext['role']->id,
                    'name' => $activeContext['role']->name,
                    'slug' => $activeContext['role']->slug,
                ],
            ] : null,
            'notificationSummary' => fn () => [
                'unreadCount' => $request->user() && $activeContext
                    ? LeagueNoticeRecipient::query()
                        ->where('user_id', $request->user()->id)
                        ->whereNull('read_at')
                        ->whereHas('notice', fn ($query) => $query
                            ->where('league_id', $activeContext['league']->id)
                            ->where('status', NoticeStatus::Published)
                            ->where('published_at', '<=', now())
                            ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
                        ->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
