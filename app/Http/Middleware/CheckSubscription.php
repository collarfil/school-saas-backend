<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{

    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        // Skip for super admin or specific routes
        if ($user->isSuperAdmin() || $this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if school has active subscription
        if ($user->school && $user->school->isSubscriptionExpired()) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew to continue using the platform.',
                'subscription_expired' => true,
                'redirect_to' => route('subscription.plans')
            ], 403);
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request)
    {
        $skipRoutes = [
            'subscription.*',
            'payment.*',
            'logout',
            'profile.*'
        ];

        foreach ($skipRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }

        return false;
    }

    // public function handle(Request $request, Closure $next): Response
    // {
    //     $user = auth()->user();
        
    //     // Super admin has unlimited access
    //     if ($user && $user->isSuperAdmin()) {
    //         return $next($request);
    //     }

    //     // Skip for subscription-related routes and auth routes
    //     $excludedRoutes = [
    //         'subscriptions',
    //         'subscriptions/initialize-payment',
    //         'subscriptions/payment/callback',
    //         'subscriptions/status/check',
    //         'subscriptions/pricing',
    //         'auth/login',
    //         'auth/register',
    //         'auth/logout'
    //     ];

    //     $currentRoute = $request->route()->getName() ?? $request->path();
        
    //     foreach ($excludedRoutes as $route) {
    //         if (str_contains($currentRoute, $route)) {
    //             return $next($request);
    //         }
    //     }

    //     // Check if user's school has active subscription
    //     if ($user && !$user->hasActiveSubscription()) {
    //         if ($request->expectsJson()) {
    //             return response()->json([
    //                 'message' => 'Your subscription has expired. Please renew to continue using the service.',
    //                 'subscription_expired' => true
    //             ], 403);
    //         }
            
    //         return redirect()->route('subscription.expired');
    //     }

    //     return $next($request);
    // }
}