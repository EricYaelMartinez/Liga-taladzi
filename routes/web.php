<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvironmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'));
Route::get('/status/services', EnvironmentController::class)->name('status.services');

Route::middleware('guest')->group(function (): void {
    Route::get('/iniciar-sesion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/olvide-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/olvide-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/cerrar-sesion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/cambiar-contrasena', [PasswordChangeController::class, 'edit'])->name('password.change.edit');
    Route::put('/cambiar-contrasena', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware('force.password')->group(function (): void {
        Route::get('/panel', DashboardController::class)->name('dashboard');

        Route::prefix('administracion')->name('admin.')->group(function (): void {
            Route::get('/usuarios', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
            Route::get('/usuarios/crear', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
            Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
            Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
            Route::put('/usuarios/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
            Route::put('/usuarios/{user}/contrasena', [UserController::class, 'resetPassword'])->middleware('permission:users.reset-password')->name('users.password');
            Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
        });
    });
});
