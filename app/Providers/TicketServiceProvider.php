<?php

namespace App\Modules\Tickets\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TicketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Load Module Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('api')
            ->group(
                base_path('app/Modules/Tickets/Routes/api.php')
            );

        /*
        |--------------------------------------------------------------------------
        | Load Module Migrations
        |--------------------------------------------------------------------------
        */

        $this->loadMigrationsFrom(
            base_path('app/Modules/Tickets/Database/Migrations')
        );
    }
}