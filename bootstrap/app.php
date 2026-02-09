<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // API group with JWT auth applied by default
        $middleware->group('api', [
    \Illuminate\Http\Middleware\HandleCors::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
]);

        $middleware->alias([
            'jwt.auth' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\RefreshToken::class,
            'subscription' => \App\Http\Middleware\CheckSchoolSubscription::class,
            'subscription.access' => \App\Http\Middleware\CheckSubscriptionAccess::class,
            'subscription.active' => \App\Http\Middleware\SubscriptionActive::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'teaching_staff' => \App\Http\Middleware\TeachingStaffMiddleware::class,
            'account_staff' => \App\Http\Middleware\AccountStaffMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
