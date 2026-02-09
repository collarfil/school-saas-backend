<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 1. Super admin bypass - full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // 2. Skip for subscription-related routes
        if ($this->isSubscriptionRoute($request)) {
            return $next($request);
        }

        // 3. Check if user has a school
        $school = $user->school;
        if (!$school) {
            return response()->json([
                'status' => 'error',
                'message' => 'No school associated with your account',
                'requires_school' => true
            ], 403);
        }

        // 4. Check if school is unlocked
        if (!$school->is_unlocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'School is locked. Please complete your subscription setup.',
                'school_unlocked' => false,
                'requires_subscription' => true
            ], 402);
        }

        // 5. Check for active subscription
        $activeSubscription = $school->activeSubscription();
        
        if (!$activeSubscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active subscription found. Please subscribe to access this feature.',
                'subscription_active' => false,
                'requires_subscription' => true
            ], 402);
        }

        // 6. Check if subscription is expired
        if ($activeSubscription->valid_until && now()->greaterThan($activeSubscription->valid_until)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your subscription has expired. Please renew to continue.',
                'subscription_expired' => true,
                'requires_subscription' => true
            ], 402);
        }

        return $next($request);
    }

    protected function isSubscriptionRoute(Request $request): bool
    {
        $subscriptionRoutes = [
            'subscriptions*',
            'subscription*',
            'payments*',
            'payment*',
            'auth*'
        ];

        foreach ($subscriptionRoutes as $route) {
            if ($request->is($route) || $request->routeIs($route)) {
                return true;
            }
        }

        return false;
    }
}