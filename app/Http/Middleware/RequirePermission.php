<?php

namespace App\Http\Middleware;

use App\Support\LeagueContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $allowed = $user?->hasPermission($permission) === true;

        if (! $allowed && ($active = $this->context->current($request))) {
            $allowed = $user->hasLeaguePermission($permission, $active['league']->id, $active['role']->id);
        }

        abort_unless($allowed, 403);

        return $next($request);
    }
}
