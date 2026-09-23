<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
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
                        ->pluck('slug')
                        ->unique()
                        ->values()
                        ->all(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
