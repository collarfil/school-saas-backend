<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AccountStaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'employee' || $user->employee_type !== 'non_teaching') {
            return response()->json([
                'message' => 'Unauthorized. Account staff access required.',
                'user_role' => $user->role,
                'employee_type' => $user->employee_type
            ], 403);
        }

        if (!$user->canManageFinancials()) {
            return response()->json([
                'message' => 'Unauthorized. Financial permissions required.'
            ], 403);
        }

        return $next($request);
    }
}
