<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadModuleRoutes();
    }

    protected function loadModuleRoutes()
    {
        $modules = [
            'Core',
            'HR', 
            'Academics',
            'Finance',
            'Communication',
            'Onlinelearning'
        ];

        foreach ($modules as $module) {
            $routePath = base_path("Modules/{$module}/Routes/{$module}.php");
            if (file_exists($routePath)) {
                Route::group([
                    'namespace' => "App\\Modules\\{$module}\\Controllers\\Api",
                    'middleware' => ['api']
                ], function () use ($routePath) {
                    require $routePath;
                });
            }
        }
    }
}