<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTicketRole
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Adjust this section to your existing role implementation
        |--------------------------------------------------------------------------
        */

        $userRole = $this->resolveUserRole($user);

        if (!$userRole || !in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        return $next($request);
    }

    private function resolveUserRole($user): ?string
    {
        /*
        |--------------------------------------------------------------------------
        | If your User model already has a role property
        |--------------------------------------------------------------------------
        */

        if (!empty($user->role)) {
            return strtolower($user->role);
        }

        /*
        |--------------------------------------------------------------------------
        | If your application uses Spatie Permission
        |--------------------------------------------------------------------------
        */

        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->first()
                ? strtolower($user->getRoleNames()->first())
                : null;
        }

        return null;
    }
}