<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\EnsureLeagueContext;
use App\Console\Commands\CreateInitialAdmin;
use App\Console\Commands\SyncMatchLifecycle;
use App\Console\Commands\SyncFinanceLifecycle;
use App\Console\Commands\CreateSystemBackup;
use App\Console\Commands\RestoreSystemBackup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'force.password' => RequirePasswordChange::class,
            'permission' => RequirePermission::class,
            'league.context' => EnsureLeagueContext::class,
        ]);
    })
    ->withCommands([CreateInitialAdmin::class, SyncMatchLifecycle::class, SyncFinanceLifecycle::class, CreateSystemBackup::class, RestoreSystemBackup::class])
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las excepciones específicas se configurarán por módulo.
    })->create();
