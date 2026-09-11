<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\SupportTicket;
use App\Policies\SupportTicketPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(
            SupportTicket::class,
            SupportTicketPolicy::class
        );
    }
}
