<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
                'user_role' => $user->role,
                'required_roles' => ['super_admin', 'admin']
            ], 403);
        }

        return $next($request);
    }
}
