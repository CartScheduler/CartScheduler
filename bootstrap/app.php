<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsAdminMiddleware;
use App\Http\Middleware\IsUserEnabledMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Jetstream\Http\Middleware\ShareInertiaData;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            append: [
                RedirectIfAuthenticated::class,
                HandleInertiaRequests::class,
            ],
        );

        $middleware->alias([
            'is-admin'     => IsAdminMiddleware::class,
            'user-enabled' => IsUserEnabledMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
