<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolTenant
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        |
        | Super Admin operates at platform level and does not necessarily
        | belong to a single school.
        |
        */

        $role = $this->resolveUserRole($user);

        if ($role === 'super_admin') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | School users must have school_id
        |--------------------------------------------------------------------------
        */

        if (!$user->school_id) {
            return response()->json([
                'message' => 'No school is associated with this account.',
            ], 403);
        }

        return $next($request);
    }

    private function resolveUserRole($user): ?string
    {
        if (!empty($user->role)) {
            return strtolower($user->role);
        }

        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->first()
                ? strtolower($user->getRoleNames()->first())
                : null;
        }

        return null;
    }
}