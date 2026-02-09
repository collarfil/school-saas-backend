<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
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

        // 7. Get user permissions (this was causing the error)
        // The getPermissions() method is now public
        $permissions = $user->getPermissions();
        
        // Optional: Log permissions for debugging
        if (config('app.debug')) {
            \Log::debug('User permissions', [
                'user_id' => $user->id,
                'role' => $user->role,
                'permissions' => $permissions
            ]);
        }

        return $next($request);
    }

    protected function isSubscriptionRoute(Request $request): bool
{
    $subscriptionRoutes = [
        'api/v1/subscriptions*',
        'api/v1/subscription*',
        'api/v1/payments*',
        'api/v1/payment*',
        'api/v1/auth*',
        'api/v1/register*',
        'api/v1/logout'
    ];

    foreach ($subscriptionRoutes as $route) {
        if ($request->is($route) || $request->routeIs($route)) {
            return true;
        }
    }

    return false;
}

}