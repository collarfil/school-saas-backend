<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\PaystackServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Modules\Tickets\Providers\TicketServiceProvider::class,
];