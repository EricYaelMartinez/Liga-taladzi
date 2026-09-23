<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\AuthenticationEvent;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = Str::lower($credentials['email']);
        $throttleKey = Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intenta nuevamente en ".RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        $user = User::where('email', $email)->first();
        $authenticated = Auth::attempt(
            ['email' => $email, 'password' => $credentials['password'], 'status' => 'active'],
            (bool) ($credentials['remember'] ?? false),
        );

        AuthenticationEvent::create([
            'user_id' => $user?->id,
            'email' => $email,
            'successful' => $authenticated,
            'failure_reason' => $authenticated ? null : ($user && ! $user->isActive() ? 'inactive_account' : 'invalid_credentials'),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'occurred_at' => now(),
        ]);

        if (! $authenticated) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => 'Las credenciales proporcionadas no son válidas.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
