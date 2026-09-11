<?php

use App\Modules\Tickets\Controllers\Api\AdminSupportTicketController;
use App\Modules\Tickets\Controllers\Api\SupportTicketController;
use App\Modules\Tickets\Controllers\Api\TicketLookupController;
use App\Modules\Tickets\Controllers\Api\TicketMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tickets Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('support')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Lookup Data
    |--------------------------------------------------------------------------
    |
    | These are used by the ticket creation form.
    |
    */

    Route::middleware('auth:api')->group(function () {

        Route::get(
            '/lookups/categories',
            [TicketLookupController::class, 'categories']
        );

        Route::get(
            '/lookups/priorities',
            [TicketLookupController::class, 'priorities']
        );

        /*
        |--------------------------------------------------------------------------
        | School Admin Tickets
        |--------------------------------------------------------------------------
        */

        Route::middleware([
            'school.tenant',
            'ticket.role:school_admin',
        ])->prefix('tickets')->group(function () {

            Route::get(
                '/',
                [SupportTicketController::class, 'index']
            );

            Route::post(
                '/',
                [SupportTicketController::class, 'store']
            );

            Route::get(
                '/{uuid}',
                [SupportTicketController::class, 'show']
            );

            Route::post(
                '/{uuid}/messages',
                [TicketMessageController::class, 'store']
            );

            Route::post(
                '/{uuid}/close',
                [SupportTicketController::class, 'close']
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Super Admin / Support Staff
        |--------------------------------------------------------------------------
        |
        | Super Admin gets platform-wide access.
        | Support Staff will be restricted by policies/services.
        |
        */

        Route::middleware([
            'ticket.role:super_admin,support_staff',
        ])->prefix('admin/support/tickets')->group(function () {

            Route::get(
                '/',
                [AdminSupportTicketController::class, 'index']
            );

            Route::get(
                '/{uuid}',
                [AdminSupportTicketController::class, 'show']
            );

            Route::post(
                '/{uuid}/messages',
                [TicketMessageController::class, 'store']
            );

            Route::post(
                '/{uuid}/assign',
                [AdminSupportTicketController::class, 'assign']
            );

            Route::post(
                '/{uuid}/status',
                [AdminSupportTicketController::class, 'changeStatus']
            );

            Route::post(
                '/{uuid}/resolve',
                [AdminSupportTicketController::class, 'resolve']
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Super Admin Status Lookup
        |--------------------------------------------------------------------------
        */

        Route::middleware([
            'ticket.role:super_admin',
        ])->group(function () {

            Route::get(
                '/lookups/statuses',
                [TicketLookupController::class, 'statuses']
            );
        });
    });
});