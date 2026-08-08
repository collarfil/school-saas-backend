<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'custom.auth' => \App\Http\Middleware\Authenticate::class,
            'jwt.auth' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\RefreshToken::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'teaching_staff' => \App\Http\Middleware\TeachingStaffMiddleware::class,
            'account_staff' => \App\Http\Middleware\AccountStaffMiddleware::class,
        ]);

        $middleware->group('api', [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->group('jwt', [
            'jwt.auth',
        ]);

        $middleware->group('jwt.super_admin', [
            'jwt.auth',
            'super_admin',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();