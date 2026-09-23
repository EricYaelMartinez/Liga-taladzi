<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->force_password_change && ! $request->routeIs('password.change.*', 'logout')) {
            return redirect()->route('password.change.edit');
        }

        return $next($request);
    }
}
