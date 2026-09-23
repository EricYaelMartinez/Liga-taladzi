<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', ['forced' => $request->user()->force_password_change]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'force_password_change' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }
}
