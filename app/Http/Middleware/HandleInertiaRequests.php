<?php

namespace App\Http\Middleware;

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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
