<?php

namespace App\Http\Middleware;

use App\Support\LeagueContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLeagueContext
{
    public function __construct(private readonly LeagueContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->current($request)) {
            return redirect()->route('access.index')
                ->with('error', 'Selecciona una liga y un rol activo para continuar.');
        }

        return $next($request);
    }
}
