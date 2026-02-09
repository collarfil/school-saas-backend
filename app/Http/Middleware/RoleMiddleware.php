<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Unauthorized for this role',
                'user_role' => $user->role,
                'required_roles' => $roles
            ], 403);
        }

        return $next($request);
    }
}
