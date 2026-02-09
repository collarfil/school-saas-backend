<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PaystackService;

class PaystackServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PaystackService::class, function ($app) {
            return new PaystackService();
        });
    }

    public function boot()
    {
        //
    }
}