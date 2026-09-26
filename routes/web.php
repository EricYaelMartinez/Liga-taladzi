<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LeagueController;
use App\Http\Controllers\AccessContextController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\League\AuditLogController;
use App\Http\Controllers\League\CompetitionSetupController;
use App\Http\Controllers\League\MembershipController;
use App\Http\Controllers\League\OperationalSettingsController;
use App\Http\Controllers\League\SettingsController;
use App\Http\Controllers\League\TeamController;
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
        Route::get('/seleccionar-acceso', [AccessContextController::class, 'index'])->name('access.index');
        Route::post('/seleccionar-acceso', [AccessContextController::class, 'store'])->name('access.store');

        Route::prefix('administracion')->name('admin.')->group(function (): void {
            Route::get('/usuarios', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
            Route::get('/usuarios/crear', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
            Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
            Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
            Route::put('/usuarios/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
            Route::put('/usuarios/{user}/contrasena', [UserController::class, 'resetPassword'])->middleware('permission:users.reset-password')->name('users.password');
            Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

            Route::get('/ligas', [LeagueController::class, 'index'])->middleware('permission:leagues.view')->name('leagues.index');
            Route::get('/ligas/crear', [LeagueController::class, 'create'])->middleware('permission:leagues.create')->name('leagues.create');
            Route::post('/ligas', [LeagueController::class, 'store'])->middleware('permission:leagues.create')->name('leagues.store');
            Route::get('/ligas/{league}/editar', [LeagueController::class, 'edit'])->middleware('permission:leagues.update')->name('leagues.edit');
            Route::put('/ligas/{league}', [LeagueController::class, 'update'])->middleware('permission:leagues.update')->name('leagues.update');
            Route::delete('/ligas/{league}', [LeagueController::class, 'destroy'])->middleware('permission:leagues.delete')->name('leagues.destroy');
        });

        Route::prefix('liga')->name('league.')->middleware('league.context')->group(function (): void {
            Route::get('/configuracion', [SettingsController::class, 'edit'])->middleware('permission:league.settings.view')->name('settings.edit');
            Route::post('/configuracion', [SettingsController::class, 'update'])->middleware('permission:league.settings.update')->name('settings.update');
            Route::get('/parametros', [OperationalSettingsController::class, 'edit'])->middleware('permission:league.settings.view')->name('settings.operational.edit');
            Route::put('/parametros', [OperationalSettingsController::class, 'update'])->middleware('permission:league.settings.update')->name('settings.operational.update');
            Route::get('/miembros', [MembershipController::class, 'index'])->middleware('permission:league.members.view')->name('members.index');
            Route::get('/miembros/crear-usuario', [MembershipController::class, 'createUser'])->middleware('permission:league.users.create')->name('members.users.create');
            Route::post('/miembros/crear-usuario', [MembershipController::class, 'storeUser'])->middleware('permission:league.users.create')->name('members.users.store');
            Route::post('/miembros', [MembershipController::class, 'store'])->middleware('permission:league.members.create')->name('members.store');
            Route::put('/miembros/{membership}', [MembershipController::class, 'update'])->middleware('permission:league.members.update')->name('members.update');
            Route::get('/bitacora', AuditLogController::class)->middleware('permission:audit.view')->name('audit.index');
            Route::controller(CompetitionSetupController::class)->middleware('permission:competitions.view')->group(function (): void {
                Route::get('/competencias', 'index')->name('competitions.index');
                Route::middleware('permission:competitions.manage')->group(function (): void {
                    Route::post('/temporadas', 'storeSeason')->name('seasons.store');
                    Route::put('/temporadas/{season}/estado', 'transitionSeason')->name('seasons.status');
                    Route::post('/divisiones', 'storeDivision')->name('divisions.store');
                    Route::post('/categorias', 'storeCategory')->name('categories.store');
                    Route::post('/torneos', 'storeTournament')->name('tournaments.store');
                    Route::post('/reglamentos', 'storeRegulation')->name('regulations.store');
                    Route::put('/reglamentos/{regulation}', 'updateRegulation')->name('regulations.update');
                    Route::post('/reglamentos/{regulation}/publicar', 'publishRegulation')->name('regulations.publish');
                    Route::post('/competencias', 'storeCompetition')->name('competitions.store');
                });
            });
            Route::controller(TeamController::class)->middleware('permission:teams.view')->group(function (): void {
                Route::get('/equipos', 'index')->name('teams.index');
                Route::post('/equipos/{team}/solicitudes-cambio', 'proposeChange')->middleware('permission:teams.propose-update')->name('teams.changes.store');
                Route::middleware('permission:teams.manage')->group(function (): void {
                    Route::post('/equipos', 'store')->name('teams.store');
                    Route::post('/equipos/{team}/actualizar', 'update')->name('teams.update');
                    Route::post('/equipos/{team}/representante', 'assignRepresentative')->name('teams.representative.update');
                    Route::post('/equipos/{team}/participaciones', 'requestParticipation')->name('teams.participations.store');
                    Route::put('/participaciones/{participation}/estado', 'transitionParticipation')->name('teams.participations.status');
                    Route::put('/solicitudes-cambio/{change}', 'reviewChange')->name('teams.changes.review');
                    Route::get('/equipos/{team}/representante/{document}', 'representativeDocument')->name('teams.representative.document');
                });
            });
        });
    });
});
